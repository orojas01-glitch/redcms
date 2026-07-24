<?php
/**
 * Read-only presentation helpers for the administrator FTP upload workspace.
 */

require_once __DIR__ . '/upload_helpers.php';

if (!function_exists('red_admin_ftp_allowed_extensions')) {
    function red_admin_ftp_allowed_extensions()
    {
        return red_upload_ftp_allowed_extensions();
    }
}

if (!function_exists('red_admin_ftp_file_kind')) {
    function red_admin_ftp_file_kind($extension)
    {
        $extension = strtolower((string) $extension);

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            return ['key' => 'image', 'label' => 'Image'];
        }
        if (in_array($extension, ['doc', 'docx', 'pdf'], true)) {
            return ['key' => 'document', 'label' => 'Document'];
        }
        if (in_array($extension, ['xls', 'xlsx'], true)) {
            return ['key' => 'spreadsheet', 'label' => 'Spreadsheet'];
        }
        if (in_array($extension, ['ppt', 'pptx', 'pps'], true)) {
            return ['key' => 'presentation', 'label' => 'Presentation'];
        }
        if ($extension === 'zip') {
            return ['key' => 'archive', 'label' => 'Archive'];
        }

        return ['key' => 'text', 'label' => 'Text file'];
    }
}

if (!function_exists('red_admin_ftp_format_bytes')) {
    function red_admin_ftp_format_bytes($bytes)
    {
        $bytes = max(0, (int) $bytes);
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        if ($unitIndex === 0) {
            return (string) $bytes . ' ' . $units[$unitIndex];
        }

        return number_format($value, $value >= 10 ? 0 : 1) . ' ' . $units[$unitIndex];
    }
}

if (!function_exists('red_admin_ftp_file_library')) {
    function red_admin_ftp_file_library($documentRoot)
    {
        $documentRoot = realpath((string) $documentRoot);
        if ($documentRoot === false) {
            return [];
        }

        $directory = realpath($documentRoot . '/images/articles');
        if ($directory === false || !is_dir($directory)) {
            return [];
        }

        $insideDocumentRoot = $directory === $documentRoot
            || strpos($directory, $documentRoot . DIRECTORY_SEPARATOR) === 0;
        if (!$insideDocumentRoot) {
            return [];
        }

        $allowedExtensions = red_admin_ftp_allowed_extensions();
        $files = [];

        try {
            $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isLink() || !$fileInfo->isFile()) {
                    continue;
                }

                $name = $fileInfo->getFilename();
                if ($name === '' || substr($name, 0, 1) === '.') {
                    continue;
                }

                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions, true)) {
                    continue;
                }

                $kind = red_admin_ftp_file_kind($extension);
                $modified = (int) $fileInfo->getMTime();
                $size = (int) $fileInfo->getSize();
                $files[] = [
                    'name' => $name,
                    'extension' => $extension,
                    'kind' => $kind['key'],
                    'typeLabel' => $kind['label'],
                    'size' => $size,
                    'sizeLabel' => red_admin_ftp_format_bytes($size),
                    'modified' => $modified,
                    'modifiedLabel' => date('M j, Y \a\t g:i a', $modified),
                    'publicPath' => '/images/articles/' . rawurlencode($name),
                ];
            }
        } catch (UnexpectedValueException $exception) {
            return [];
        }

        usort($files, function ($left, $right) {
            if ($left['modified'] !== $right['modified']) {
                return $left['modified'] > $right['modified'] ? -1 : 1;
            }

            return strnatcasecmp($left['name'], $right['name']);
        });

        return $files;
    }
}
