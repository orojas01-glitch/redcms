<?php
/**
 * Shared nullable SEO metadata storage and validation.
 */

if (!function_exists('red_seo_owner_types')) {
    function red_seo_owner_types()
    {
        return [
            'article' => true,
            'section' => true,
            'category' => true,
            'subcategory' => true,
        ];
    }

    function red_seo_field_definitions()
    {
        return [
            'SEO_Title' => ['kind' => 'text', 'limit' => 255],
            'MetaDescription' => ['kind' => 'text', 'limit' => 1000],
            'CanonicalURL' => ['kind' => 'absolute_url', 'limit' => 2048],
            'RobotsIndex' => ['kind' => 'choice', 'choices' => ['', 'Y', 'N']],
            'RobotsFollow' => ['kind' => 'choice', 'choices' => ['', 'Y', 'N']],
            'OGTitle' => ['kind' => 'text', 'limit' => 255],
            'OGDescription' => ['kind' => 'text', 'limit' => 1000],
            'OGImage' => ['kind' => 'url_reference', 'limit' => 2048],
            'OGImageAlt' => ['kind' => 'text', 'limit' => 255],
            'OGType' => [
                'kind' => 'choice',
                'choices' => ['', 'website', 'article', 'profile', 'book'],
            ],
            'OGLocale' => ['kind' => 'locale', 'limit' => 20],
            'XCard' => [
                'kind' => 'choice',
                'choices' => ['', 'summary', 'summary_large_image'],
            ],
            'XTitle' => ['kind' => 'text', 'limit' => 255],
            'XDescription' => ['kind' => 'text', 'limit' => 1000],
            'XImage' => ['kind' => 'url_reference', 'limit' => 2048],
            'SchemaType' => [
                'kind' => 'choice',
                'choices' => ['', 'WebPage', 'Course', 'Service'],
            ],
            'SchemaIdentityType' => [
                'kind' => 'choice',
                'choices' => ['', 'Person', 'Organization'],
            ],
            'SchemaIdentityName' => ['kind' => 'text', 'limit' => 255],
            'SchemaIdentityURL' => ['kind' => 'absolute_url', 'limit' => 2048],
            'SchemaMainEntityName' => ['kind' => 'text', 'limit' => 255],
            'SchemaEducationalLevel' => ['kind' => 'text', 'limit' => 255],
            'SchemaCourseMode' => [
                'kind' => 'choice',
                'choices' => ['', 'online', 'onsite', 'blended'],
            ],
            'SchemaCourseWorkload' => ['kind' => 'duration', 'limit' => 50],
            'SchemaInstructorName' => ['kind' => 'text', 'limit' => 255],
            'SchemaTeaches' => ['kind' => 'text', 'limit' => 2000],
            'SchemaServiceType' => ['kind' => 'text', 'limit' => 255],
        ];
    }

    function red_seo_empty_values()
    {
        return array_fill_keys(array_keys(red_seo_field_definitions()), '');
    }

    function red_seo_scalar($value)
    {
        return is_scalar($value) ? (string) $value : '';
    }

    function red_seo_trim_to_bytes($value, $limit)
    {
        $value = trim(red_seo_scalar($value));
        $limit = max(0, (int) $limit);
        if ($limit === 0 || strlen($value) <= $limit) {
            return $value;
        }

        while ($limit > 0 && (ord($value[$limit]) & 0xC0) === 0x80) {
            $limit--;
        }
        return substr($value, 0, $limit);
    }

    function red_seo_valid_absolute_url($value)
    {
        $value = trim(red_seo_scalar($value));
        if ($value === '' || preg_match('/[\x00-\x20\x7F]/', $value)) {
            return false;
        }
        $parts = parse_url($value);
        if (!is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    function red_seo_valid_root_path($value)
    {
        $value = trim(red_seo_scalar($value));
        if ($value === ''
            || $value[0] !== '/'
            || substr($value, 0, 2) === '//'
            || strpos($value, '\\') !== false
            || preg_match('/[\x00-\x20\x7F]/', $value)
        ) {
            return false;
        }
        $path = parse_url($value, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }
        foreach (explode('/', rawurldecode($path)) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }

    function red_seo_normalize_field($field, $value, &$valid)
    {
        $definitions = red_seo_field_definitions();
        $valid = false;
        if (!isset($definitions[$field])) {
            return '';
        }

        $definition = $definitions[$field];
        $kind = $definition['kind'];
        $normalized = red_seo_trim_to_bytes($value, $definition['limit'] ?? 4096);
        if ($normalized === '') {
            $valid = true;
            return '';
        }

        if ($kind === 'text') {
            $valid = true;
            return $normalized;
        }
        if ($kind === 'choice') {
            $valid = in_array($normalized, $definition['choices'], true);
            return $valid ? $normalized : '';
        }
        if ($kind === 'absolute_url') {
            $valid = red_seo_valid_absolute_url($normalized);
            return $valid ? $normalized : '';
        }
        if ($kind === 'url_reference') {
            $valid = red_seo_valid_absolute_url($normalized) || red_seo_valid_root_path($normalized);
            return $valid ? $normalized : '';
        }
        if ($kind === 'locale') {
            $valid = preg_match('/\A[a-z]{2}(?:_[A-Z]{2})?\z/', $normalized) === 1;
            return $valid ? $normalized : '';
        }
        if ($kind === 'duration') {
            $valid = preg_match(
                '/\AP(?=\d|T\d)(?:\d+Y)?(?:\d+M)?(?:\d+W)?(?:\d+D)?' .
                '(?:T(?=\d)(?:\d+H)?(?:\d+M)?(?:\d+(?:\.\d+)?S)?)?\z/',
                $normalized
            ) === 1;
            return $valid ? $normalized : '';
        }

        return '';
    }

    function red_seo_collect_input(array $input)
    {
        $values = red_seo_empty_values();
        $present = false;
        $errors = [];

        foreach (red_seo_field_definitions() as $field => $definition) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $present = true;
            $valid = false;
            $values[$field] = red_seo_normalize_field($field, $input[$field], $valid);
            if (!$valid) {
                $errors[] = $field;
            }
        }

        $identityPresent = trim($values['SchemaIdentityType']) !== ''
            || trim($values['SchemaIdentityName']) !== ''
            || trim($values['SchemaIdentityURL']) !== '';
        if ($identityPresent && trim($values['SchemaIdentityType']) === '') {
            $errors[] = 'SchemaIdentityType';
        }
        if ($identityPresent && trim($values['SchemaIdentityName']) === '') {
            $errors[] = 'SchemaIdentityName';
        }

        $schemaType = trim($values['SchemaType']);
        if ($schemaType !== 'WebPage' && trim($values['SchemaMainEntityName']) !== '') {
            $errors[] = 'SchemaMainEntityName';
        }
        foreach ([
            'SchemaEducationalLevel',
            'SchemaCourseMode',
            'SchemaCourseWorkload',
            'SchemaInstructorName',
            'SchemaTeaches',
        ] as $courseField) {
            if ($schemaType !== 'Course' && trim($values[$courseField]) !== '') {
                $errors[] = $courseField;
            }
        }
        if ($schemaType !== 'Service' && trim($values['SchemaServiceType']) !== '') {
            $errors[] = 'SchemaServiceType';
        }

        $errors = array_values(array_unique($errors));

        return [
            'present' => $present,
            'valid' => $errors === [],
            'values' => $values,
            'errors' => $errors,
        ];
    }

    function red_seo_has_overrides(array $values)
    {
        foreach (array_keys(red_seo_field_definitions()) as $field) {
            if (trim(red_seo_scalar($values[$field] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    function red_seo_normalize_owner_type($ownerType)
    {
        $ownerType = strtolower(trim(red_seo_scalar($ownerType)));
        return isset(red_seo_owner_types()[$ownerType]) ? $ownerType : '';
    }

    function red_seo_table_available($connection)
    {
        if (!($connection instanceof mysqli)) {
            return false;
        }
        static $availability = [];
        $key = spl_object_id($connection);
        if (array_key_exists($key, $availability)) {
            return $availability[$key];
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                "SELECT COUNT(*) FROM information_schema.TABLES " .
                "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Page_SEO'"
            );
            if (!$stmt || !mysqli_stmt_execute($stmt)) {
                if ($stmt) {
                    mysqli_stmt_close($stmt);
                }
                return $availability[$key] = false;
            }
            $count = 0;
            mysqli_stmt_bind_result($stmt, $count);
            $available = mysqli_stmt_fetch($stmt) === true && (int) $count === 1;
            mysqli_stmt_close($stmt);
            return $availability[$key] = $available;
        } catch (Throwable $exception) {
            error_log('SEO metadata table lookup failed: ' . $exception->getMessage());
            return $availability[$key] = false;
        }
    }

    function red_seo_metadata_row($connection, $ownerType, $ownerRecordId)
    {
        $ownerType = red_seo_normalize_owner_type($ownerType);
        $ownerRecordId = (int) $ownerRecordId;
        if ($ownerType === '' || $ownerRecordId <= 0 || !red_seo_table_available($connection)) {
            return null;
        }

        $columns = implode(', ', array_map(
            static function ($field) {
                return '`' . $field . '`';
            },
            array_keys(red_seo_field_definitions())
        ));
        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT ' . $columns . ' FROM RED_Page_SEO ' .
                'WHERE OwnerType=? AND OwnerRecordID=? LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }
            mysqli_stmt_bind_param($stmt, 'si', $ownerType, $ownerRecordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return $row ?: null;
        } catch (Throwable $exception) {
            error_log('SEO metadata lookup failed: ' . $exception->getMessage());
            return null;
        }
    }

    function red_seo_save_metadata(
        $connection,
        $ownerType,
        $ownerRecordId,
        array $values,
        $updatedByAdminRecordId = 0
    ) {
        $ownerType = red_seo_normalize_owner_type($ownerType);
        $ownerRecordId = (int) $ownerRecordId;
        $updatedByAdminRecordId = (int) $updatedByAdminRecordId;
        if ($ownerType === '' || $ownerRecordId <= 0 || !red_seo_table_available($connection)) {
            return false;
        }

        $input = red_seo_collect_input(array_merge(red_seo_empty_values(), $values));
        if (!$input['valid']) {
            return false;
        }
        $normalized = $input['values'];

        try {
            if (!red_seo_has_overrides($normalized)) {
                $stmt = mysqli_prepare(
                    $connection,
                    'DELETE FROM RED_Page_SEO WHERE OwnerType=? AND OwnerRecordID=?'
                );
                if (!$stmt) {
                    return false;
                }
                mysqli_stmt_bind_param($stmt, 'si', $ownerType, $ownerRecordId);
                $deleted = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return $deleted;
            }

            $fields = array_keys(red_seo_field_definitions());
            $columns = array_merge(['OwnerType', 'OwnerRecordID'], $fields, ['UpdatedByAdminRecordID']);
            $quotedColumns = array_map(
                static function ($field) {
                    return '`' . $field . '`';
                },
                $columns
            );
            $updates = array_map(
                static function ($field) {
                    return '`' . $field . '`=VALUES(`' . $field . '`)';
                },
                array_merge($fields, ['UpdatedByAdminRecordID'])
            );
            $sql = 'INSERT INTO RED_Page_SEO (' . implode(', ', $quotedColumns) . ') VALUES (' .
                implode(', ', array_fill(0, count($columns), '?')) . ') ' .
                'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                return false;
            }

            $boundValues = [$ownerType, $ownerRecordId];
            foreach ($fields as $field) {
                $boundValues[] = $normalized[$field] === '' ? null : $normalized[$field];
            }
            $boundValues[] = $updatedByAdminRecordId > 0 ? $updatedByAdminRecordId : null;
            $types = 'si' . str_repeat('s', count($fields)) . 'i';
            $references = [$types];
            foreach ($boundValues as $key => &$boundValue) {
                $references[] = &$boundValue;
            }
            call_user_func_array([$stmt, 'bind_param'], $references);
            $saved = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $saved;
        } catch (Throwable $exception) {
            error_log('SEO metadata save failed: ' . $exception->getMessage());
            return false;
        }
    }

    function red_seo_delete_metadata($connection, $ownerType, $ownerRecordId)
    {
        $ownerType = red_seo_normalize_owner_type($ownerType);
        $ownerRecordId = (int) $ownerRecordId;
        if ($ownerType === '' || $ownerRecordId <= 0) {
            return false;
        }
        if (!red_seo_table_available($connection)) {
            return true;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'DELETE FROM RED_Page_SEO WHERE OwnerType=? AND OwnerRecordID=?'
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'si', $ownerType, $ownerRecordId);
            $deleted = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $deleted;
        } catch (Throwable $exception) {
            error_log('SEO metadata delete failed: ' . $exception->getMessage());
            return false;
        }
    }

    function red_seo_absolute_reference($value, $origin)
    {
        $value = trim(red_seo_scalar($value));
        $origin = rtrim(trim(red_seo_scalar($origin)), '/');
        if (red_seo_valid_absolute_url($value)) {
            return $value;
        }
        if ($origin !== '' && red_seo_valid_root_path($value)) {
            return $origin . $value;
        }
        return '';
    }
}
?>
