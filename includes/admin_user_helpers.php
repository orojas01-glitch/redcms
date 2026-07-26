<?php

require_once __DIR__ . '/admin_addon_authorization_helpers.php';

if (!function_exists('red_admin_user_scalar')) {
    function red_admin_user_scalar($value)
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

if (!function_exists('red_admin_user_text')) {
    function red_admin_user_text($value)
    {
        return trim(red_admin_user_scalar($value));
    }
}

if (!function_exists('red_admin_user_html')) {
    function red_admin_user_html($value)
    {
        return htmlspecialchars(red_admin_user_scalar($value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('red_admin_user_fetch_all')) {
    function red_admin_user_fetch_all($connection, $sql, $errorContext)
    {
        try {
            $result = mysqli_query($connection, $sql);
            if (!$result) {
                return [];
            }

            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);

            return $rows;
        } catch (mysqli_sql_exception $e) {
            error_log($errorContext . ': ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('red_admin_user_list')) {
    function red_admin_user_list($connection)
    {
        return red_admin_user_fetch_all(
            $connection,
            "SELECT RecordID, Username, Alias, AdminType, AdminComponents, AdminTools, Email FROM RED_Admin ORDER BY CASE WHEN Email='' THEN 1 ELSE 0 END, Email ASC, Username ASC",
            'RED administrator user list lookup failed'
        );
    }
}

if (!function_exists('red_admin_user_email_exists')) {
    function red_admin_user_email_exists($connection, $email, $excludeRecordId = 0)
    {
        $excludeRecordId = (int) $excludeRecordId;
        try {
            if ($excludeRecordId > 0) {
                $stmt = mysqli_prepare($connection, 'SELECT RecordID FROM RED_Admin WHERE Email=? AND RecordID<>? LIMIT 1');
                if (!$stmt) {
                    return true;
                }
                mysqli_stmt_bind_param($stmt, 'si', $email, $excludeRecordId);
            } else {
                $stmt = mysqli_prepare($connection, 'SELECT RecordID FROM RED_Admin WHERE Email=? LIMIT 1');
                if (!$stmt) {
                    return true;
                }
                mysqli_stmt_bind_param($stmt, 's', $email);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            return $exists;
        } catch (mysqli_sql_exception $e) {
            error_log('RED administrator email duplicate lookup failed: ' . $e->getMessage());
            return true;
        }
    }
}

if (!function_exists('red_admin_user_components')) {
    function red_admin_user_components($connection)
    {
        return red_admin_user_fetch_all(
            $connection,
            'SELECT RecordID, UniqueName, Layout, ButtonTag FROM RED_Components ORDER BY RecordID ASC',
            'RED administrator component list lookup failed'
        );
    }
}

if (!function_exists('red_admin_user_component_permissions')) {
    function red_admin_user_component_permissions($rows)
    {
        $permissions = [];
        $formBuilderRows = [];
        $adminLoginRows = [];

        foreach ((array) $rows as $row) {
            $recordId = (int) ($row['RecordID'] ?? 0);
            if ($recordId <= 0) {
                continue;
            }

            $uniqueName = strtolower(red_admin_user_text($row['UniqueName'] ?? ''));
            $layout = strtolower(red_admin_user_text($row['Layout'] ?? ''));
            if ($uniqueName === 'form') {
                if ($layout === 'login') {
                    $adminLoginRows[] = $row;
                } else {
                    $formBuilderRows[] = $row;
                }
                continue;
            }

            $row['PermissionIDs'] = [$recordId];
            $row['PermissionKey'] = 'component-' . $recordId;
            $row['PermissionDescription'] = '';
            $permissions[] = $row;
        }

        $groupPermission = function ($groupRows, $key, $label, $description) {
            if (empty($groupRows)) {
                return null;
            }

            usort($groupRows, function ($left, $right) {
                return (int) ($left['RecordID'] ?? 0) <=> (int) ($right['RecordID'] ?? 0);
            });
            $permissionIds = [];
            foreach ($groupRows as $groupRow) {
                $permissionId = (int) ($groupRow['RecordID'] ?? 0);
                if ($permissionId > 0) {
                    $permissionIds[$permissionId] = $permissionId;
                }
            }
            if (empty($permissionIds)) {
                return null;
            }

            $permission = reset($groupRows);
            $permission['RecordID'] = reset($permissionIds);
            $permission['PermissionIDs'] = array_values($permissionIds);
            $permission['PermissionKey'] = $key;
            $permission['PermissionDescription'] = $description;
            $permission['ButtonTag'] = $label;

            return $permission;
        };

        $formBuilder = $groupPermission(
            $formBuilderRows,
            'form-builder',
            'Form Builder',
            'Contact, response, registration, and display-only forms.'
        );
        if ($formBuilder !== null) {
            $permissions[] = $formBuilder;
        }

        $adminLogin = $groupPermission(
            $adminLoginRows,
            'admin-login',
            'Admin Login',
            'The protected administrator sign-in form.'
        );
        if ($adminLogin !== null) {
            $permissions[] = $adminLogin;
        }

        usort($permissions, function ($left, $right) {
            $leftLabel = red_admin_user_text($left['ButtonTag'] ?? $left['UniqueName'] ?? '');
            $rightLabel = red_admin_user_text($right['ButtonTag'] ?? $right['UniqueName'] ?? '');
            $comparison = strnatcasecmp($leftLabel, $rightLabel);
            if ($comparison !== 0) {
                return $comparison;
            }

            return (int) ($left['RecordID'] ?? 0) <=> (int) ($right['RecordID'] ?? 0);
        });

        return $permissions;
    }
}

if (!function_exists('red_admin_user_tools')) {
    function red_admin_user_tools($connection)
    {
        return red_admin_user_fetch_all(
            $connection,
            'SELECT RecordID, UniqueName, CompGroup, ButtonTag, AltContent FROM RED_Tools ORDER BY RecordID ASC',
            'RED administrator tool list lookup failed'
        );
    }
}

if (!function_exists('red_admin_user_ids')) {
    function red_admin_user_ids($value)
    {
        $ids = [];
        $values = is_array($value) ? $value : explode(',', red_admin_user_scalar($value));
        foreach ($values as $id) {
            $id = (int) red_admin_user_scalar($id);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('red_admin_user_allowed_ids')) {
    function red_admin_user_allowed_ids($connection, $table, $value)
    {
        if (!in_array($table, ['RED_Components', 'RED_Tools'], true)) {
            return [];
        }

        $requested = red_admin_user_ids($value);
        if (empty($requested)) {
            return [];
        }

        $available = [];
        foreach (red_admin_user_fetch_all(
            $connection,
            'SELECT RecordID FROM `' . $table . '` ORDER BY RecordID ASC',
            'RED administrator permission lookup failed'
        ) as $row) {
            $id = (int) ($row['RecordID'] ?? 0);
            if ($id > 0) {
                $available[$id] = true;
            }
        }

        return array_values(array_filter($requested, function ($id) use ($available) {
            return isset($available[$id]);
        }));
    }
}

if (!function_exists('red_admin_user_lookup')) {
    function red_admin_user_lookup($connection, $recordId)
    {
        $recordId = (int) $recordId;
        if ($recordId <= 0) {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RecordID, Username, Alias, AdminType, AdminComponents, AdminTools, Email FROM RED_Admin WHERE RecordID=? LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }
            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            return $row ?: null;
        } catch (mysqli_sql_exception $e) {
            error_log('RED administrator lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_user_username_exists')) {
    function red_admin_user_username_exists($connection, $username)
    {
        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID FROM RED_Admin WHERE Username=? LIMIT 1');
            if (!$stmt) {
                return true;
            }
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            return $exists;
        } catch (mysqli_sql_exception $e) {
            error_log('RED administrator duplicate lookup failed: ' . $e->getMessage());
            return true;
        }
    }
}

if (!function_exists('red_admin_user_validate_profile')) {
    function red_admin_user_validate_profile($post)
    {
        $alias = red_admin_user_text($post['Alias'] ?? '');
        $email = red_admin_user_text($post['Email'] ?? '');
        if ($alias === '' || strlen($alias) > 14) {
            return null;
        }
        if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return ['alias' => $alias, 'email' => $email];
    }
}

if (!function_exists('red_admin_user_password_valid')) {
    function red_admin_user_password_valid($password)
    {
        $length = strlen(red_admin_user_scalar($password));
        return $length >= 12 && $length <= 255;
    }
}

if (!function_exists('red_admin_user_type')) {
    function red_admin_user_type($value, $default = '')
    {
        $value = strtolower(red_admin_user_text($value));
        return in_array($value, ['guest', 'webmaster', 'superadmin'], true) ? $value : $default;
    }
}

if (!function_exists('red_admin_user_is_manager_type')) {
    function red_admin_user_is_manager_type($value)
    {
        return in_array(red_admin_user_type($value), ['webmaster', 'superadmin'], true);
    }
}

if (!function_exists('red_admin_user_assignable_type')) {
    function red_admin_user_assignable_type($value, $default = '')
    {
        $value = strtolower(red_admin_user_text($value));
        return in_array($value, ['guest', 'webmaster'], true) ? $value : $default;
    }
}

if (!function_exists('red_admin_user_manager_count')) {
    function red_admin_user_manager_count($connection)
    {
        return count(red_admin_user_fetch_all(
            $connection,
            "SELECT RecordID FROM RED_Admin WHERE LOWER(AdminType) IN ('webmaster','superadmin')",
            'RED administrator manager count failed'
        ));
    }
}

if (!function_exists('red_admin_user_create')) {
    function red_admin_user_create($connection, $post)
    {
        $username = red_admin_user_text($post['Username'] ?? '');
        $password = red_admin_user_scalar($post['Password'] ?? '');
        $email = red_admin_user_text($post['Email'] ?? '');
        if ($email === '') {
            return 'email_required';
        }
        $profile = red_admin_user_validate_profile($post);
        $adminType = red_admin_user_assignable_type($post['AdminType'] ?? 'guest');
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) || !$profile || $adminType === '') {
            return 'invalid';
        }
        if (!red_admin_user_password_valid($password)) {
            return 'weak_password';
        }
        if (red_admin_user_username_exists($connection, $username)) {
            return 'duplicate';
        }
        if (red_admin_user_email_exists($connection, $profile['email'])) {
            return 'duplicate_email';
        }

        $componentIds = red_admin_user_allowed_ids($connection, 'RED_Components', $post['components'] ?? []);
        $toolIds = red_admin_user_allowed_ids($connection, 'RED_Tools', $post['tools'] ?? []);
        $components = implode(',', $componentIds);
        $tools = implode(',', $toolIds);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            return 'no';
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                "INSERT INTO RED_Admin (Username, Password, Administrator, Alias, AdminType, AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref, Donation_Form, Donation_Form_Pref) VALUES (?, ?, 'Admin', ?, ?, ?, ?, ?, 'N', 'to', 'N', 'to')"
            );
            if (!$stmt) {
                return 'no';
            }
            mysqli_stmt_bind_param(
                $stmt,
                'sssssss',
                $username,
                $passwordHash,
                $profile['alias'],
                $adminType,
                $components,
                $tools,
                $profile['email']
            );
            $created = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $created ? 'yes' : 'no';
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1062) {
                return 'duplicate';
            }
            error_log('RED administrator insert failed: ' . $e->getMessage());
            return 'no';
        }
    }
}

if (!function_exists('red_admin_user_update')) {
    function red_admin_user_update($connection, $post)
    {
        $recordId = (int) red_admin_user_scalar($post['RecordID'] ?? 0);
        $password = red_admin_user_scalar($post['Password'] ?? '');
        $email = red_admin_user_text($post['Email'] ?? '');
        if ($email === '') {
            return 'email_required';
        }
        $profile = red_admin_user_validate_profile($post);
        $target = red_admin_user_lookup($connection, $recordId);
        if ($recordId <= 0 || !$profile || !$target) {
            return 'invalid';
        }
        $currentRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
        $currentType = red_admin_user_type($target['AdminType'] ?? 'guest', 'guest');
        $requestedType = strtolower(red_admin_user_text($post['AdminType'] ?? ''));
        $adminType = red_admin_user_assignable_type($requestedType);
        if ($adminType === '' && !($currentType === 'superadmin' && $requestedType === 'superadmin')) {
            return 'invalid';
        }
        if ($recordId === $currentRecordId && $adminType !== $currentType) {
            return 'self_role';
        }
        if (
            red_admin_addon_is_owner($connection, $recordId)
            && !red_admin_user_is_manager_type($adminType)
        ) {
            return 'owner_protected';
        }
        if (
            red_admin_user_is_manager_type($currentType)
            && !red_admin_user_is_manager_type($adminType)
            && red_admin_user_manager_count($connection) <= 1
        ) {
            return 'last_manager';
        }
        if ($password !== '' && !red_admin_user_password_valid($password)) {
            return 'weak_password';
        }
        if (red_admin_user_email_exists($connection, $profile['email'], $recordId)) {
            return 'duplicate_email';
        }

        $components = implode(',', red_admin_user_allowed_ids($connection, 'RED_Components', $post['components'] ?? []));
        $tools = implode(',', red_admin_user_allowed_ids($connection, 'RED_Tools', $post['tools'] ?? []));

        try {
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if ($passwordHash === false) {
                    return 'no';
                }
                $stmt = mysqli_prepare(
                    $connection,
                    'UPDATE RED_Admin SET Alias=?, Email=?, AdminType=?, AdminComponents=?, AdminTools=?, Password=? WHERE RecordID=?'
                );
                if (!$stmt) {
                    return 'no';
                }
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssssi',
                    $profile['alias'],
                    $profile['email'],
                    $adminType,
                    $components,
                    $tools,
                    $passwordHash,
                    $recordId
                );
            } else {
                $stmt = mysqli_prepare(
                    $connection,
                    'UPDATE RED_Admin SET Alias=?, Email=?, AdminType=?, AdminComponents=?, AdminTools=? WHERE RecordID=?'
                );
                if (!$stmt) {
                    return 'no';
                }
                mysqli_stmt_bind_param(
                    $stmt,
                    'sssssi',
                    $profile['alias'],
                    $profile['email'],
                    $adminType,
                    $components,
                    $tools,
                    $recordId
                );
            }

            $updated = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $updated ? 'yes' : 'no';
        } catch (mysqli_sql_exception $e) {
            error_log('RED administrator update failed: ' . $e->getMessage());
            return 'no';
        }
    }
}

if (!function_exists('red_admin_user_delete')) {
    function red_admin_user_delete($connection, $post)
    {
        $recordId = (int) red_admin_user_scalar($post['RecordID'] ?? 0);
        $currentRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
        if ($recordId <= 0 || $recordId === $currentRecordId) {
            return $recordId === $currentRecordId ? 'self' : 'invalid';
        }

        $target = red_admin_user_lookup($connection, $recordId);
        if (!$target) {
            return 'invalid';
        }

        if (red_admin_addon_is_owner($connection, $recordId)) {
            return 'owner_protected';
        }

        if (red_admin_user_is_manager_type($target['AdminType'] ?? '')) {
            if (red_admin_user_manager_count($connection) <= 1) {
                return 'last_manager';
            }
        }

        try {
            $stmt = mysqli_prepare($connection, 'DELETE FROM RED_Admin WHERE RecordID=?');
            if (!$stmt) {
                return 'no';
            }
            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            $deleted = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);

            return $deleted ? 'yes' : 'no';
        } catch (mysqli_sql_exception $e) {
            error_log('RED administrator delete failed: ' . $e->getMessage());
            return 'no';
        }
    }
}

?>
