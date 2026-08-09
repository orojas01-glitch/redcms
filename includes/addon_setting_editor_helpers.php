<?php
/**
 * Core-owned authenticated add-on settings editor boundary.
 *
 * This helper discovers only validated data-only manifests. It renders only
 * core-owned controls, preserves opaque secret-reference rows internally, and
 * delegates ordinary writes to the existing atomic settings writer. It never
 * executes package PHP, resolves a secret, or changes package lifecycle.
 */

require_once __DIR__ . '/addon_setting_read_helpers.php';
require_once __DIR__ . '/addon_setting_write_helpers.php';

if (!function_exists('red_addon_setting_editor_request_result')) {
    function red_addon_setting_editor_request_result($reason)
    {
        return [
            'valid' => false,
            'packageId' => '',
            'expectedPlanSha256' => '',
            'csrfToken' => '',
            'settings' => [],
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_setting_editor_request')) {
    function red_addon_setting_editor_request(array $post, $mode = 'update')
    {
        $mode = $mode === 'edit' ? 'edit' : 'update';
        $result = red_addon_setting_editor_request_result('invalid_request');
        $expectedKeys = $mode === 'edit'
            ? ['PackageID', 'csrf_token']
            : [
                'ExpectedPlanSha256',
                'PackageID',
                'Settings',
                'csrf_token',
            ];
        $keys = array_keys($post);
        sort($keys, SORT_STRING);
        sort($expectedKeys, SORT_STRING);
        if ($keys !== $expectedKeys
            || !is_string($post['PackageID'] ?? null)
            || !red_addon_valid_package_id($post['PackageID'])
            || !is_string($post['csrf_token'] ?? null)
            || $post['csrf_token'] === ''
        ) {
            return $result;
        }

        $result['packageId'] = $post['PackageID'];
        $result['csrfToken'] = $post['csrf_token'];
        if ($mode === 'edit') {
            $result['valid'] = true;
            $result['reason'] = 'valid';
            return $result;
        }

        if (!is_string($post['ExpectedPlanSha256'] ?? null)
            || !red_addon_valid_sha256($post['ExpectedPlanSha256'])
            || !is_array($post['Settings'])
            || $post['Settings'] === []
            || array_is_list($post['Settings'])
            || count($post['Settings']) > 200
        ) {
            return $result;
        }

        $settings = [];
        foreach ($post['Settings'] as $key => $value) {
            if (!is_string($key)
                || !red_addon_valid_permission($key)
                || !is_string($value)
                || strlen($value) > 4096
            ) {
                return $result;
            }
            $settings[$key] = $value;
        }
        ksort($settings, SORT_STRING);
        $result['expectedPlanSha256'] = $post['ExpectedPlanSha256'];
        $result['settings'] = $settings;
        $result['valid'] = true;
        $result['reason'] = 'valid';
        return $result;
    }
}

if (!function_exists('red_addon_setting_editor_decode_values')) {
    function red_addon_setting_editor_decode_values(
        array $manifest,
        array $submitted,
        array $secretReferences
    ) {
        $result = [
            'valid' => false,
            'configuredValues' => [],
            'ordinaryValues' => [],
            'errors' => [],
        ];
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema) || $schema === []) {
            $result['errors'][] = 'settings_not_declared';
            return $result;
        }
        $definitions = [];
        foreach ($schema as $definition) {
            $key = $definition['key'] ?? null;
            if (!is_string($key)) {
                $result['errors'][] = 'schema_invalid';
                return $result;
            }
            $definitions[$key] = $definition;
        }
        if (count($submitted) > 200) {
            $result['errors'][] = 'payload_too_large';
            return $result;
        }

        $ordinary = [];
        foreach ($submitted as $key => $rawValue) {
            $definition = is_string($key) ? ($definitions[$key] ?? null) : null;
            if (!is_array($definition)) {
                $result['errors'][] = 'unknown_setting';
                return $result;
            }
            if (($definition['type'] ?? '') === 'secret-reference') {
                $result['errors'][] = 'secret_submission';
                return $result;
            }
            if (!is_string($rawValue)) {
                $result['errors'][] = 'invalid_scalar';
                return $result;
            }
            $type = (string) ($definition['type'] ?? '');
            if ($type === 'boolean') {
                if ($rawValue !== '0' && $rawValue !== '1') {
                    $result['errors'][] = 'invalid_boolean';
                    return $result;
                }
                $ordinary[$key] = $rawValue === '1';
            } elseif ($type === 'integer') {
                if (preg_match('/\A(?:0|-?[1-9][0-9]*)\z/D', $rawValue) !== 1
                    || filter_var(
                        $rawValue,
                        FILTER_VALIDATE_INT,
                        ['options' => [
                            'min_range' => -2147483648,
                            'max_range' => 2147483647,
                        ]]
                    ) === false
                ) {
                    $result['errors'][] = 'invalid_integer';
                    return $result;
                }
                $ordinary[$key] = (int) $rawValue;
            } elseif (in_array(
                $type,
                ['text', 'select', 'url', 'email'],
                true
            )) {
                $ordinary[$key] = $rawValue;
            } else {
                $result['errors'][] = 'unsupported_type';
                return $result;
            }
        }

        $configured = $ordinary;
        foreach ($schema as $definition) {
            $key = $definition['key'];
            if (($definition['type'] ?? '') !== 'secret-reference') {
                continue;
            }
            $reference = $secretReferences[$key] ?? null;
            if (!red_addon_setting_string_is_valid('secret-reference', $reference)) {
                $result['errors'][] = 'secret_unconfigured';
                return $result;
            }
            $configured[$key] = $reference;
        }

        $validated = red_addon_settings_validate_values(
            $manifest,
            $configured
        );
        if (empty($validated['valid'])) {
            $result['errors'][] = 'invalid_values';
            return $result;
        }
        $result['valid'] = true;
        $result['configuredValues'] = $configured;
        $result['ordinaryValues'] = $validated['values'];
        return $result;
    }
}

if (!function_exists('red_addon_setting_editor_current_configuration')) {
    function red_addon_setting_editor_current_configuration(
        $connection,
        array $manifest,
        $packageId,
        $allowMissingSecrets = false
    ) {
        $invalid = [
            'valid' => false,
            'configuredValues' => [],
            'ordinaryValues' => [],
            'secretConfigured' => [],
            'currentStateSha256' => '',
            'errors' => ['storage_unavailable'],
        ];
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema)
            || !red_addon_valid_package_id($packageId)
            || !red_addon_setting_storage_available($connection)
        ) {
            return $invalid;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey, ValueType, ValueJSON, SecretReference
                 FROM RED_Addon_Settings
                 WHERE PackageID=?
                 ORDER BY SettingKey ASC'
            );
            if (!$statement) {
                return $invalid;
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return $invalid;
            }
            $query = mysqli_stmt_get_result($statement);
            $rows = [];
            while ($query && ($row = mysqli_fetch_assoc($query))) {
                $rows[] = $row;
            }
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            return $invalid;
        }

        $definitions = [];
        foreach ($schema as $definition) {
            $definitions[$definition['key']] = $definition;
        }
        $stored = [];
        foreach ($rows as $row) {
            $key = (string) ($row['SettingKey'] ?? '');
            $type = (string) ($row['ValueType'] ?? '');
            $definition = $definitions[$key] ?? null;
            if (!is_array($definition)
                || isset($stored[$key])
                || $type !== ($definition['type'] ?? '')
            ) {
                $invalid['errors'] = ['stored_schema_drift'];
                return $invalid;
            }
            if ($type === 'secret-reference') {
                $reference = $row['SecretReference'] ?? null;
                if ($row['ValueJSON'] !== null
                    || !red_addon_setting_string_is_valid(
                        'secret-reference',
                        $reference
                    )
                ) {
                    $invalid['errors'] = ['stored_value_invalid'];
                    return $invalid;
                }
                $stored[$key] = $reference;
                continue;
            }
            if ($row['SecretReference'] !== null
                || !is_string($row['ValueJSON'] ?? null)
            ) {
                $invalid['errors'] = ['stored_value_invalid'];
                return $invalid;
            }
            try {
                $value = json_decode(
                    $row['ValueJSON'],
                    true,
                    8,
                    JSON_THROW_ON_ERROR
                );
            } catch (Throwable $throwable) {
                $invalid['errors'] = ['stored_value_invalid'];
                return $invalid;
            }
            $check = red_addon_setting_value_result();
            $normalized = red_addon_setting_normalize_value(
                $definition,
                $value,
                $check
            );
            if ($check['errors'] !== [] || $normalized !== $value) {
                $invalid['errors'] = ['stored_value_invalid'];
                return $invalid;
            }
            $stored[$key] = $value;
        }

        $configured = [];
        $ordinary = [];
        $secretConfigured = [];
        foreach ($schema as $definition) {
            $key = $definition['key'];
            $type = $definition['type'];
            if (array_key_exists($key, $stored)) {
                $value = $stored[$key];
                $configured[$key] = $value;
                if ($type === 'secret-reference') {
                    $secretConfigured[$key] = true;
                } else {
                    $ordinary[$key] = $value;
                }
                continue;
            }
            if ($type === 'secret-reference') {
                if ($allowMissingSecrets) {
                    $secretConfigured[$key] = false;
                    continue;
                }
                $invalid['errors'] = ['secret_unconfigured'];
                return $invalid;
            }
            if (array_key_exists('default', $definition)
                && $definition['default'] !== null
            ) {
                $configured[$key] = $definition['default'];
                $ordinary[$key] = $definition['default'];
                continue;
            }
            $invalid['errors'] = ['setting_required'];
            return $invalid;
        }
        $validated = red_addon_settings_validate_values(
            $manifest,
            $configured
        );
        if ($allowMissingSecrets && $validated['errors'] !== []) {
            $missingSecrets = [];
            foreach ($schema as $definition) {
                if (($definition['type'] ?? '') !== 'secret-reference') {
                    continue;
                }
                $key = $definition['key'];
                if (!array_key_exists($key, $configured)) {
                    $missingSecrets[] = $key;
                }
            }
            if ($missingSecrets !== []) {
                foreach ($schema as $definition) {
                    if (($definition['type'] ?? '') === 'secret-reference') {
                        continue;
                    }
                    $key = $definition['key'];
                    if (!array_key_exists($key, $ordinary)) {
                        $invalid['errors'] = ['setting_required'];
                        return $invalid;
                    }
                    $check = red_addon_setting_value_result();
                    $normalized = red_addon_setting_normalize_value(
                        $definition,
                        $ordinary[$key],
                        $check
                    );
                    if ($check['errors'] !== []
                        || $normalized !== $ordinary[$key]
                    ) {
                        $invalid['errors'] = ['stored_value_invalid'];
                        return $invalid;
                    }
                }
                sort($missingSecrets, SORT_STRING);
                $validated = [
                    'valid' => true,
                    'values' => $ordinary,
                ];
            }
        }
        $currentState = red_addon_setting_current_state(
            $connection,
            $manifest,
            $packageId
        );
        if (empty($validated['valid']) || empty($currentState['valid'])) {
            $invalid['errors'] = ['current_state_invalid'];
            return $invalid;
        }
        return [
            'valid' => true,
            'configuredValues' => $configured,
            'ordinaryValues' => $validated['values'],
            'secretConfigured' => $secretConfigured,
            'currentStateSha256' => (string) $currentState['stateSha256'],
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_setting_editor_package')) {
    function red_addon_setting_editor_package($projectRoot, $packageId)
    {
        if (!red_addon_valid_package_id($packageId)) {
            return null;
        }
        $catalog = red_addon_discover(
            $projectRoot,
            ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
        );
        $package = $catalog['packages'][$packageId] ?? null;
        return is_array($package) && !empty($package['valid'])
            ? $package
            : null;
    }
}

if (!function_exists('red_addon_setting_editor_context_result')) {
    function red_addon_setting_editor_context_result($reason)
    {
        return [
            'ready' => false,
            'packageId' => '',
            'version' => '',
            'lifecycleState' => '',
            'manifest' => [],
            'settings' => [],
            'ordinaryValues' => [],
            'secretConfigured' => [],
            'modelSha256' => '',
            'planSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_setting_editor_context')) {
    function red_addon_setting_editor_context(
        $connection,
        array $package,
        $adminRecordId
    ) {
        $result = red_addon_setting_editor_context_result('invalid_request');
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($adminRecordId === false) {
            return $result;
        }
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        if (!is_array($snapshot) || !is_array($manifest)) {
            $result['reason'] = 'package_invalid';
            return $result;
        }
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema) || $schema === []) {
            $result['reason'] = 'settings_not_declared';
            return $result;
        }
        $read = red_addon_setting_read_model(
            $connection,
            $package,
            $adminRecordId
        );
        if (empty($read['readable'])) {
            $result['reason'] = (string) ($read['reason'] ?? 'unavailable');
            return $result;
        }
        if (count($read['settings']) !== count($schema)) {
            $result['reason'] = 'permission_denied';
            return $result;
        }
        $current = red_addon_setting_editor_current_configuration(
            $connection,
            $manifest,
            $snapshot['id']
        );
        if (empty($current['valid'])) {
            $result['reason'] = (string) ($current['errors'][0]
                ?? 'current_state_invalid');
            return $result;
        }
        $plan = red_addon_setting_write_preflight(
            $connection,
            $package,
            $adminRecordId,
            $current['configuredValues']
        );
        if (empty($plan['valid']) || !red_addon_valid_sha256($plan['planSha256'] ?? '')) {
            $result['reason'] = (string) ($plan['errors'][0]
                ?? 'write_unavailable');
            return $result;
        }
        return [
            'ready' => true,
            'packageId' => $snapshot['id'],
            'version' => $snapshot['version'],
            'lifecycleState' => (string) ($read['lifecycleState'] ?? ''),
            'manifest' => $manifest,
            'settings' => $read['settings'],
            'ordinaryValues' => $current['ordinaryValues'],
            'secretConfigured' => $current['secretConfigured'],
            'modelSha256' => (string) $read['modelSha256'],
            'planSha256' => $plan['planSha256'],
            'reason' => 'ready',
        ];
    }
}

if (!function_exists('red_addon_setting_editor_ui_unavailable')) {
    function red_addon_setting_editor_ui_unavailable()
    {
        return '<p class="red-admin-error">Add-on settings are temporarily unavailable.</p>';
    }
}

if (!function_exists('red_addon_setting_editor_escape')) {
    function red_addon_setting_editor_escape($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('red_addon_setting_editor_control_id')) {
    function red_addon_setting_editor_control_id($key)
    {
        $id = preg_replace('/[^a-z0-9_-]+/i', '-', (string) $key);
        $id = trim((string) $id, '-_');
        return 'red-addon-setting-' . ($id !== '' ? strtolower($id) : 'value');
    }
}

if (!function_exists('red_addon_setting_editor_render')) {
    function red_addon_setting_editor_render(array $context, $csrfToken)
    {
        $expectedKeys = [
            'ready',
            'packageId',
            'version',
            'lifecycleState',
            'manifest',
            'settings',
            'ordinaryValues',
            'secretConfigured',
            'modelSha256',
            'planSha256',
            'reason',
        ];
        if (array_keys($context) !== $expectedKeys
            || ($context['ready'] ?? null) !== true
            || !red_addon_valid_package_id($context['packageId'] ?? null)
            || !red_addon_valid_semantic_version($context['version'] ?? null)
            || !in_array(
                $context['lifecycleState'] ?? '',
                ['installed_disabled', 'enabled'],
                true
            )
            || !is_array($context['manifest'])
            || !is_array($context['settings'])
            || !is_array($context['ordinaryValues'])
            || !is_array($context['secretConfigured'])
            || !red_addon_valid_sha256($context['modelSha256'] ?? '')
            || !red_addon_valid_sha256($context['planSha256'] ?? '')
            || !is_string($csrfToken)
            || !red_addon_valid_sha256($csrfToken)
        ) {
            return red_addon_setting_editor_ui_unavailable();
        }
        $schema = red_addon_settings_schema($context['manifest']);
        if (!is_array($schema) || count($schema) !== count($context['settings'])) {
            return red_addon_setting_editor_ui_unavailable();
        }
        $settings = [];
        foreach ($context['settings'] as $setting) {
            if (!is_array($setting)
                || !red_addon_valid_permission($setting['key'] ?? null)
                || !is_string($setting['type'] ?? null)
                || !array_key_exists($setting['key'], $context['ordinaryValues'])
                    && $setting['type'] !== 'secret-reference'
            ) {
                return red_addon_setting_editor_ui_unavailable();
            }
            $settings[$setting['key']] = $setting;
        }

        $html = '<section class="red-admin-addon-settings-workspace"'
            . ' data-red-addon-settings-workspace>'
            . '<form class="red-admin-addon-settings-form" method="post"'
            . ' action="/admin/bin/update_addon_settings.php"'
            . ' data-red-addon-settings-form>'
            . '<header class="red-admin-addon-settings__header"><div>'
            . '<span>Add-on settings</span><h2>'
            . red_addon_setting_editor_escape($context['packageId'])
            . '</h2></div><code>'
            . red_addon_setting_editor_escape($context['version'])
            . '</code></header>'
            . '<p class="red-admin-addon-settings__lifecycle">Lifecycle: '
            . red_addon_setting_editor_escape($context['lifecycleState'])
            . '</p><fieldset class="red-admin-addon-settings__fields">'
            . '<legend>Configuration</legend>';

        foreach ($schema as $definition) {
            $key = $definition['key'];
            $setting = $settings[$key] ?? null;
            if (!is_array($setting)) {
                return red_addon_setting_editor_ui_unavailable();
            }
            $type = $definition['type'];
            $controlId = red_addon_setting_editor_control_id($key);
            $helpId = $controlId . '-help';
            $name = 'Settings[' . $key . ']';
            $label = red_addon_setting_editor_escape($definition['label']);
            $html .= '<div class="red-admin-addon-settings__field"'
                . ' data-red-addon-setting-type="'
                . red_addon_setting_editor_escape($type) . '">'
                . ($type === 'secret-reference'
                    ? '<span class="red-admin-addon-settings__label">'
                        . $label . '</span>'
                    : '<label for="'
                        . red_addon_setting_editor_escape($controlId)
                        . '">' . $label . '</label>');
            if ($type === 'secret-reference') {
                $configured = !empty($context['secretConfigured'][$key]);
                $html .= '<p id="' . red_addon_setting_editor_escape($controlId)
                    . '" class="red-admin-addon-settings__secret"'
                    . ' role="status" aria-describedby="'
                    . red_addon_setting_editor_escape($helpId) . '"'
                    . ' data-red-addon-secret-state="'
                    . ($configured ? 'configured' : 'not-configured') . '">'
                    . ($configured ? 'Configured' : 'Not configured')
                    . '</p><p id="'
                    . red_addon_setting_editor_escape($helpId)
                    . '" class="red-admin-addon-settings__hint">'
                    . 'Secret references are managed separately.</p>';
            } else {
                $value = $context['ordinaryValues'][$key] ?? '';
                $escapedValue = red_addon_setting_editor_escape($value);
                $attributes = ' id="' . red_addon_setting_editor_escape($controlId)
                    . '" name="' . red_addon_setting_editor_escape($name)
                    . '" aria-describedby="'
                    . red_addon_setting_editor_escape($helpId) . '"';
                if ($type === 'boolean') {
                    $selected = $value === true ? '1' : '0';
                    $html .= '<select' . $attributes . '><option value="0"'
                        . ($selected === '0' ? ' selected' : '') . '>Off</option>'
                        . '<option value="1"'
                        . ($selected === '1' ? ' selected' : '') . '>On</option>'
                        . '</select>';
                } elseif ($type === 'select') {
                    $html .= '<select' . $attributes . '>';
                    foreach ($definition['options'] as $option) {
                        $optionEscaped = red_addon_setting_editor_escape($option);
                        $html .= '<option value="' . $optionEscaped . '"'
                            . ($value === $option ? ' selected' : '') . '>'
                            . $optionEscaped . '</option>';
                    }
                    $html .= '</select>';
                } else {
                    $inputType = $type === 'email' ? 'email'
                        : ($type === 'url' ? 'url'
                        : ($type === 'integer' ? 'number' : 'text'));
                    $html .= '<input type="' . $inputType . '"' . $attributes
                        . ' value="' . $escapedValue . '"'
                        . ($type === 'integer' ? ' step="1"' : '') . ' />';
                }
                $html .= '<p id="' . red_addon_setting_editor_escape($helpId)
                    . '" class="red-admin-addon-settings__hint">'
                    . 'Setting key: <code>'
                    . red_addon_setting_editor_escape($key)
                    . '</code></p>';
            }
            $html .= '</div>';
        }
        $html .= '</fieldset>'
            . '<input type="hidden" name="PackageID" value="'
            . red_addon_setting_editor_escape($context['packageId']) . '" />'
            . '<input type="hidden" name="ExpectedPlanSha256" value="'
            . red_addon_setting_editor_escape($context['planSha256']) . '" />'
            . '<input type="hidden" name="csrf_token" value="'
            . red_addon_setting_editor_escape($csrfToken) . '" />'
            . '<div class="red-admin-addon-settings__actions">'
            . '<span data-red-addon-settings-status role="status"'
            . ' aria-live="polite" hidden></span>'
            . '<button type="submit">Save settings</button></div>'
            . '</form></section>';
        return $html;
    }
}

if (!function_exists('red_addon_setting_editor_update')) {
    function red_addon_setting_editor_update(
        $connection,
        array $package,
        $adminRecordId,
        array $submitted,
        $expectedPlanSha256
    ) {
        $failure = [
            'ok' => false,
            'status' => '',
            'reason' => 'settings_unavailable',
            'stateSha256' => '',
        ];
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        if (!is_array($snapshot)
            || !is_array($manifest)
            || !red_addon_valid_sha256($expectedPlanSha256)
        ) {
            return $failure;
        }
        $current = red_addon_setting_editor_current_configuration(
            $connection,
            $manifest,
            $snapshot['id']
        );
        if (empty($current['valid'])) {
            return $failure;
        }
        $decoded = red_addon_setting_editor_decode_values(
            $manifest,
            $submitted,
            array_filter(
                $current['configuredValues'],
                static function ($value, $key) use ($manifest) {
                    $schema = red_addon_settings_schema($manifest);
                    foreach ($schema ?? [] as $definition) {
                        if (($definition['key'] ?? '') === $key) {
                            return ($definition['type'] ?? '') ===
                                'secret-reference';
                        }
                    }
                    return false;
                },
                ARRAY_FILTER_USE_BOTH
            )
        );
        if (empty($decoded['valid'])) {
            $failure['reason'] = (string) ($decoded['errors'][0]
                ?? 'invalid_values');
            return $failure;
        }
        $written = red_addon_setting_write(
            $connection,
            $package,
            $adminRecordId,
            $decoded['configuredValues'],
            $expectedPlanSha256
        );
        if (($written['status'] ?? '') === 'updated') {
            return [
                'ok' => true,
                'status' => 'updated',
                'reason' => '',
                'stateSha256' => (string) ($written['stateSha256'] ?? ''),
            ];
        }
        if (($written['status'] ?? '') === 'unchanged') {
            return [
                'ok' => true,
                'status' => 'unchanged',
                'reason' => '',
                'stateSha256' => (string) ($written['stateSha256'] ?? ''),
            ];
        }
        return [
            'ok' => false,
            'status' => '',
            'reason' => ($written['status'] ?? '') === 'plan_changed'
                ? 'stale_plan'
                : 'settings_unavailable',
            'stateSha256' => '',
        ];
    }
}

?>
