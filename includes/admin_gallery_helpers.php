<?php
/**
 * Helpers for admin RED_C_Gallery write endpoints.
 */

require_once __DIR__ . '/admin_article_helpers.php';
require_once __DIR__ . '/video_url_helpers.php';

if (!function_exists('red_admin_gallery_scalar')) {
    function red_admin_gallery_scalar($value)
    {
        return is_array($value) ? '' : (string) $value;
    }
}

if (!function_exists('red_admin_gallery_article_post')) {
    function red_admin_gallery_article_post($post, $artRecordId, $mode)
    {
        $fields = [
            'Title',
            'Alias',
            'Tags',
            'Active',
            'Sections',
            'Categories',
            'SubCategories',
            'Article',
            'HomePosition',
            'SectionPosition',
            'CategoryPosition',
            'SubCategoryPosition',
            'PagePosition',
            'HomePositionOrder',
            'SectionPositionOrder',
            'CategoryPositionOrder',
            'SubCategoryPositionOrder',
            'PagePositionOrder',
            'HomeFeature',
            'StartDate',
            'ExpDate',
            'BigPict',
            'SmallPict',
            'SmallPictAlign',
            'EditedBy',
        ];

        if ($mode === 'insert') {
            $fields[] = 'Language';
            $fields[] = 'Component';
            $fields[] = 'Layout';
        }

        $articlePost = ['RecordID' => $artRecordId];
        foreach ($fields as $fieldName) {
            if (array_key_exists($fieldName, $post)) {
                $articlePost[$fieldName] = $post[$fieldName];
            }
        }

        foreach (['Delete_BigPict', 'Delete_SmallPict'] as $deleteField) {
            if (array_key_exists($deleteField, $post)) {
                $articlePost[$deleteField] = $post[$deleteField];
            }
        }

        if ($mode === 'insert' && !isset($articlePost['Component'])) {
            $articlePost['Component'] = 'Gallery';
        }

        return $articlePost;
    }
}

if (!function_exists('red_admin_gallery_photo_list')) {
    function red_admin_gallery_photo_list($post)
    {
        $photos = [];
        foreach ($post as $name => $value) {
            if (!is_string($name) || !preg_match('/^Photo(\d+)$/', $name, $matches)) {
                continue;
            }

            $index = (int) $matches[1];
            if (isset($post['Delete' . $index]) && red_admin_gallery_scalar($post['Delete' . $index]) === 'Y') {
                continue;
            }

            $photo = red_admin_text(red_admin_gallery_scalar($value));
            if ($photo !== '') {
                $photos[$index] = $photo;
            }
        }

        if (empty($photos)) {
            return null;
        }

        ksort($photos);
        return implode(',', $photos);
    }
}

if (!function_exists('red_admin_gallery_has_payload')) {
    function red_admin_gallery_has_payload($post)
    {
        $fields = [
            'Title' => true,
            'Alias' => true,
            'GalleryType' => true,
            'GalleryPresentation' => true,
            'ShortDesc' => true,
            'Link' => true,
            'LongDesc' => true,
            'NewWindow' => true,
        ];

        foreach ($post as $name => $value) {
            if (!is_string($name)) {
                continue;
            }
            if (isset($fields[$name])) {
                return true;
            }
            if (preg_match('/^Photo\d+$/', $name)) {
                return true;
            }
            if (preg_match('/^Delete\d+$/', $name) && red_admin_gallery_scalar($value) === 'Y') {
                return true;
            }
        }

        return red_admin_article_has_payload(red_admin_gallery_article_post($post, (int) ($post['ArtRecordID'] ?? 0), 'update'));
    }
}

if (!function_exists('red_admin_gallery_clean_presentation')) {
    function red_admin_gallery_clean_presentation($value)
    {
        return red_admin_gallery_scalar($value) === 'carousel' ? 'carousel' : 'stack';
    }
}

if (!function_exists('red_admin_gallery_clean_type')) {
    function red_admin_gallery_clean_type($value)
    {
        $value = red_admin_text(red_admin_gallery_scalar($value));
        return in_array($value, ['Gallery', 'Video', 'Banner'], true) ? $value : '';
    }
}

if (!function_exists('red_admin_gallery_insert_reuse_allowed')) {
    function red_admin_gallery_insert_reuse_allowed($existingType, $postedType)
    {
        $existingType = red_admin_text(red_admin_gallery_scalar($existingType));
        $postedType = red_admin_gallery_clean_type($postedType);

        return $postedType !== '' && ($existingType === '' || $existingType === $postedType);
    }
}

if (!function_exists('red_admin_gallery_insert_target_allowed')) {
    function red_admin_gallery_insert_target_allowed($existingArticle, $existingGallery, $postedType)
    {
        $postedType = red_admin_gallery_clean_type($postedType);
        if ($postedType === '') {
            return false;
        }

        $hasArticle = is_array($existingArticle);
        $hasGallery = is_array($existingGallery);
        $existingGalleryType = $hasGallery
            ? red_admin_text($existingGallery['GalleryType'] ?? '')
            : '';

        // A file upload may reserve a blank child row before the paired article
        // exists. No populated orphan child is safe to promote implicitly.
        if (!$hasArticle) {
            return !$hasGallery || $existingGalleryType === '';
        }

        // RED_C_Gallery subtypes are paired only with the generic Gallery
        // article component. Never let insert/upsert mutate another component.
        if (red_admin_article_clean_value('Component', $existingArticle['Component'] ?? '') !== 'Gallery') {
            return false;
        }

        // Article-picture uploads can create a tightly constrained placeholder.
        // It may be promoted with no child or the blank child reserved by a
        // gallery upload, but never over a populated child record.
        if (red_admin_article_is_upload_placeholder($existingArticle)) {
            return !$hasGallery || $existingGalleryType === '';
        }

        // Every other existing article is a retry, not a create. It must have
        // the exact paired child and subtype; the endpoint separately enforces
        // the current administrator's access to that article.
        return $hasGallery && $existingGalleryType === $postedType;
    }
}

if (!function_exists('red_admin_gallery_collect_values')) {
    function red_admin_gallery_collect_values($post, $mode, $recordId, $artRecordId)
    {
        $data = [];
        $postedGalleryType = '';
        if ($mode === 'insert') {
            $data['RecordID'] = $recordId;
            $data['RefID'] = (string) $artRecordId;
        }

        if (array_key_exists('Title', $post)) {
            $data['Title'] = red_admin_gallery_scalar($post['Title']);
            if (!array_key_exists('Alias', $post)) {
                $data['Alias'] = red_admin_slug($data['Title'], true);
            }
        }

        if (array_key_exists('Alias', $post)) {
            $data['Alias'] = red_admin_slug($post['Alias'], true);
        }

        if (array_key_exists('GalleryType', $post)) {
            $postedGalleryType = red_admin_gallery_clean_type($post['GalleryType']);
            $data['GalleryType'] = $postedGalleryType;
        }

        // RED_C_Gallery.NewWindow is unused by the public Gallery subtype. Keep
        // the legacy one-character column as a compatibility-safe binary
        // presentation flag: empty means photo stack and Y means carousel.
        // Video and Banner continue to use their existing NewWindow behavior.
        if ($postedGalleryType === 'Gallery' && array_key_exists('GalleryPresentation', $post)) {
            $data['NewWindow'] = red_admin_gallery_clean_presentation($post['GalleryPresentation']) === 'carousel'
                ? 'Y'
                : '';
        }

        foreach (['ShortDesc', 'Link'] as $fieldName) {
            if (array_key_exists($fieldName, $post)) {
                $data[$fieldName] = red_admin_gallery_scalar($post[$fieldName]);
            }
        }

        if (array_key_exists('LongDesc', $post)) {
            $data['LongDesc'] = red_admin_gallery_scalar($post['LongDesc']);
        }

        $photoList = red_admin_gallery_photo_list($post);
        if ($photoList !== null) {
            $data['LongDesc'] = $photoList;
        } elseif (red_admin_gallery_has_photo_fields($post)) {
            $data['LongDesc'] = '';
        }

        $preserveGalleryPresentation = $mode === 'update'
            && $postedGalleryType === 'Gallery'
            && !array_key_exists('GalleryPresentation', $post);
        if (red_admin_gallery_has_payload($post)
            && !array_key_exists('NewWindow', $data)
            && !$preserveGalleryPresentation
        ) {
            $data['NewWindow'] = isset($post['NewWindow']) && red_admin_gallery_scalar($post['NewWindow']) === 'Y' ? 'Y' : '';
        }

        return $data;
    }
}

if (!function_exists('red_admin_gallery_has_photo_fields')) {
    function red_admin_gallery_has_photo_fields($post)
    {
        foreach ($post as $name => $value) {
            if (is_string($name) && preg_match('/^Photo\d+$/', $name)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('red_admin_gallery_default_insert_data')) {
    function red_admin_gallery_default_insert_data($recordId, $artRecordId)
    {
        return [
            'RecordID' => $recordId,
            'RefID' => (string) $artRecordId,
            'Title' => '',
            'Alias' => '',
            'GalleryType' => '',
            'ShortDesc' => '',
            'Link' => '',
            'LongDesc' => '',
            'NewWindow' => '',
        ];
    }
}

if (!function_exists('red_admin_gallery_param_type')) {
    function red_admin_gallery_param_type($fieldName)
    {
        return $fieldName === 'RecordID' ? 'i' : 's';
    }
}

if (!function_exists('red_admin_gallery_record_exists')) {
    function red_admin_gallery_record_exists($connection, $recordId)
    {
        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID FROM RED_C_Gallery WHERE RecordID=? LIMIT 1');
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
            return $exists;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_C_Gallery lookup failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_gallery_render_record')) {
    function red_admin_gallery_render_record($connection, $recordId, $artRecordId)
    {
        $recordId = (int) red_admin_scalar($recordId);
        $artRecordId = (int) red_admin_scalar($artRecordId);
        if ($recordId <= 0 || $artRecordId <= 0) {
            return null;
        }

        return red_admin_area_fetch_one(
            $connection,
            'SELECT * FROM RED_C_Gallery WHERE RecordID=? AND RefID=? LIMIT 1',
            'is',
            [$recordId, (string) $artRecordId],
            'RED_C_Gallery render lookup failed'
        );
    }
}

if (!function_exists('red_admin_gallery_record_matches')) {
    function red_admin_gallery_record_matches($connection, $recordId, $artRecordId)
    {
        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID FROM RED_C_Gallery WHERE RecordID=? AND RefID=? LIMIT 1');
            if (!$stmt) {
                return false;
            }

            $refId = (string) $artRecordId;
            mysqli_stmt_bind_param($stmt, 'is', $recordId, $refId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            mysqli_stmt_store_result($stmt);
            $matches = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
            return $matches;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_C_Gallery ref lookup failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_gallery_update')) {
    function red_admin_gallery_update($connection, $recordId, $data)
    {
        unset($data['RecordID'], $data['RefID']);
        if ($recordId <= 0 || empty($data)) {
            return false;
        }

        $sets = [];
        $types = '';
        $values = [];
        foreach ($data as $fieldName => $value) {
            if (!array_key_exists($fieldName, red_admin_gallery_default_insert_data($recordId, 0)) || $fieldName === 'RecordID') {
                continue;
            }

            $sets[] = "`$fieldName`=?";
            $types .= red_admin_gallery_param_type($fieldName);
            $values[] = $value;
        }

        if (empty($sets)) {
            return false;
        }

        $types .= 'i';
        $values[] = $recordId;

        try {
            $stmt = mysqli_prepare($connection, 'UPDATE RED_C_Gallery SET ' . implode(', ', $sets) . ' WHERE RecordID=?');
            if (!$stmt) {
                return false;
            }

            if (!red_admin_article_bind_values($stmt, $types, $values)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_C_Gallery update failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_gallery_insert')) {
    function red_admin_gallery_insert($connection, $recordId, $artRecordId, $data)
    {
        if ($recordId <= 0 || $artRecordId <= 0) {
            return false;
        }

        $data = array_merge(red_admin_gallery_default_insert_data($recordId, $artRecordId), $data);
        $data['RecordID'] = $recordId;
        $data['RefID'] = (string) $artRecordId;

        $columns = [];
        $placeholders = [];
        $types = '';
        $values = [];
        foreach ($data as $fieldName => $value) {
            if (!array_key_exists($fieldName, red_admin_gallery_default_insert_data($recordId, $artRecordId))) {
                continue;
            }

            $columns[] = "`$fieldName`";
            $placeholders[] = '?';
            $types .= red_admin_gallery_param_type($fieldName);
            $values[] = $value;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_C_Gallery (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            if (!$stmt) {
                return false;
            }

            if (!red_admin_article_bind_values($stmt, $types, $values)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_C_Gallery insert failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_gallery_save')) {
    function red_admin_gallery_save($connection, $recordId, $artRecordId, $data)
    {
        if (red_admin_gallery_record_exists($connection, $recordId)) {
            if (!red_admin_gallery_record_matches($connection, $recordId, $artRecordId)) {
                return false;
            }

            return red_admin_gallery_update($connection, $recordId, $data);
        }

        return red_admin_gallery_insert($connection, $recordId, $artRecordId, $data);
    }
}

?>
