<?php
/**
 * Shared upload validation helpers for admin upload endpoints.
 */

if (!function_exists('red_upload_status')) {
    function red_upload_status($message, $httpStatus = 200, $extra = [])
    {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array_merge(['status' => $message], $extra));
        exit;
    }
}

if (!function_exists('red_upload_extension')) {
    function red_upload_extension($fileName)
    {
        return strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
    }
}

if (!function_exists('red_upload_clean_filename')) {
    function red_upload_clean_filename($fileName, $prefix = '')
    {
        $extension = red_upload_extension($fileName);
        $baseName = pathinfo((string) $fileName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $baseName);
        $baseName = trim($baseName, '._-');

        if ($baseName === '') {
            $baseName = 'upload';
        }

        $prefix = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $prefix);
        return $prefix . $baseName . ($extension !== '' ? '.' . $extension : '');
    }
}

if (!function_exists('red_upload_allowed_mimes')) {
    function red_upload_allowed_mimes($extension)
    {
        $map = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'],
            'doc' => ['application/msword', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'pps' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage'],
            'mp3' => ['audio/mpeg', 'audio/mp3'],
        ];

        return $map[$extension] ?? [];
    }
}

if (!function_exists('red_upload_detect_mime')) {
    function red_upload_detect_mime($tmpName)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                return is_string($mime) ? $mime : '';
            }
        }

        return '';
    }
}

if (!function_exists('red_upload_validate_file')) {
    function red_upload_validate_file($file, $allowedExtensions, $maxBytes, $requireImage = false)
    {
        if (!is_array($file) || empty($file['name']) || empty($file['tmp_name'])) {
            red_upload_status('No file was uploaded.', 400);
        }

        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            red_upload_status('Upload failed.', 400);
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0 || $size > $maxBytes) {
            red_upload_status('File size is not allowed.', 400);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            red_upload_status('Invalid upload source.', 400);
        }

        $extension = red_upload_extension($file['name']);
        if (!in_array($extension, $allowedExtensions, true)) {
            red_upload_status('Only ' . implode(',', $allowedExtensions) . ' files are allowed.', 400);
        }

        if ($requireImage && getimagesize($file['tmp_name']) === false) {
            red_upload_status('Only valid image files are allowed.', 400);
        }

        $detectedMime = red_upload_detect_mime($file['tmp_name']);
        $allowedMimes = red_upload_allowed_mimes($extension);
        if ($detectedMime !== '' && !in_array($detectedMime, $allowedMimes, true)) {
            red_upload_status('File type is not allowed.', 400);
        }

        return [
            'extension' => $extension,
            'mime' => $detectedMime,
            'safe_name' => red_upload_clean_filename($file['name']),
        ];
    }
}

if (!function_exists('red_upload_resolve_directory')) {
    function red_upload_resolve_directory($relativeDirectory)
    {
        $documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        $targetDirectory = realpath($_SERVER['DOCUMENT_ROOT'] . '/' . trim($relativeDirectory, '/'));

        if (!$documentRoot || !$targetDirectory) {
            red_upload_status('Upload destination is not allowed.', 400);
        }

        $insideDocumentRoot = $targetDirectory === $documentRoot || strpos($targetDirectory, $documentRoot . DIRECTORY_SEPARATOR) === 0;
        if (!$insideDocumentRoot || !is_dir($targetDirectory)) {
            red_upload_status('Upload destination is not allowed.', 400);
        }

        return $targetDirectory;
    }
}

if (!function_exists('red_upload_unique_path')) {
    function red_upload_unique_path($directory, $fileName)
    {
        $extension = red_upload_extension($fileName);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $candidate = $fileName;
        $counter = 1;

        while (file_exists($directory . '/' . $candidate)) {
            $candidate = $baseName . '-' . $counter . ($extension !== '' ? '.' . $extension : '');
            $counter++;
        }

        return [$directory . '/' . $candidate, $candidate];
    }
}

if (!function_exists('red_upload_move')) {
    function red_upload_move($file, $relativeDirectory, $safeName)
    {
        $targetDirectory = red_upload_resolve_directory($relativeDirectory);
        [$targetPath, $storedName] = red_upload_unique_path($targetDirectory, $safeName);

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            red_upload_status('Something went wrong with your upload.', 400);
        }

        return $storedName;
    }
}

?>
