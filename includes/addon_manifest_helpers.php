<?php
/**
 * Read-only RED-CMS add-on discovery and trust-manifest validation.
 *
 * This file deliberately contains no include/require of package code, no
 * database access, and no package lifecycle mutations.
 */

if (!function_exists('red_addon_project_root')) {
    function red_addon_project_root($projectRoot = null)
    {
        $candidate = $projectRoot === null || $projectRoot === ''
            ? dirname(__DIR__)
            : (string) $projectRoot;
        $resolved = realpath($candidate);

        return $resolved !== false ? $resolved : rtrim($candidate, '/\\');
    }
}

if (!function_exists('red_addon_root')) {
    function red_addon_root($projectRoot = null)
    {
        return red_addon_project_root($projectRoot) . DIRECTORY_SEPARATOR . 'addons';
    }
}

if (!function_exists('red_addon_valid_slug')) {
    function red_addon_valid_slug($value)
    {
        return is_string($value)
            && preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?\z/', $value) === 1;
    }
}

if (!function_exists('red_addon_package_parts')) {
    function red_addon_package_parts($packageId)
    {
        if (!is_string($packageId) || substr_count($packageId, '.') !== 1) {
            return null;
        }

        [$vendor, $package] = explode('.', $packageId, 2);
        if (!red_addon_valid_slug($vendor) || !red_addon_valid_slug($package)) {
            return null;
        }

        return [$vendor, $package];
    }
}

if (!function_exists('red_addon_valid_package_id')) {
    function red_addon_valid_package_id($packageId)
    {
        return red_addon_package_parts($packageId) !== null;
    }
}

if (!function_exists('red_addon_valid_relative_path')) {
    function red_addon_valid_relative_path($path)
    {
        if (!is_string($path) || $path === '' || strlen($path) > 240 || strpos($path, "\0") !== false) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        if ($normalized[0] === '/' || preg_match('/\A[A-Za-z]:\//', $normalized) === 1) {
            return false;
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === ''
                || $segment === '.'
                || $segment === '..'
                || preg_match('/\A[A-Za-z0-9_.-]+\z/', $segment) !== 1
            ) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_addon_valid_semantic_version')) {
    function red_addon_valid_semantic_version($version)
    {
        return is_string($version)
            && preg_match(
                '/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?\z/',
                $version
            ) === 1;
    }
}

if (!function_exists('red_addon_normalize_version')) {
    function red_addon_normalize_version($version)
    {
        if (!is_string($version)) {
            return null;
        }
        $version = trim($version);
        if (preg_match('/\A(\d+)\.(\d+)(\.\d+)?((?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?)\z/', $version, $matches) !== 1) {
            return null;
        }

        return $matches[1] . '.' . $matches[2] . ($matches[3] !== '' ? $matches[3] : '.0') . $matches[4];
    }
}

if (!function_exists('red_addon_version_range_valid')) {
    function red_addon_version_range_valid($range)
    {
        if (!is_string($range) || $range === '' || strlen($range) > 120 || trim($range) !== $range) {
            return false;
        }

        $constraints = explode(' ', $range);
        foreach ($constraints as $constraint) {
            if (preg_match(
                '/\A(?:>=|<=|>|<|==|=)?\d+\.\d+(?:\.\d+)?(?:-[0-9A-Za-z.-]+)?\z/',
                $constraint
            ) !== 1) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_addon_version_satisfies')) {
    function red_addon_version_satisfies($version, $range)
    {
        $version = red_addon_normalize_version($version);
        if ($version === null || !red_addon_version_range_valid($range)) {
            return false;
        }

        foreach (explode(' ', $range) as $constraint) {
            if (preg_match('/\A(>=|<=|>|<|==|=)?(.+)\z/', $constraint, $matches) !== 1) {
                return false;
            }
            $operator = $matches[1] !== '' ? $matches[1] : '==';
            $operator = $operator === '=' ? '==' : $operator;
            $target = red_addon_normalize_version($matches[2]);
            if ($target === null || !version_compare($version, $target, $operator)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_addon_valid_capability')) {
    function red_addon_valid_capability($value)
    {
        return is_string($value)
            && strlen($value) <= 160
            && preg_match('/\A[a-z0-9][a-z0-9.-]*(?:\/[a-z0-9][a-z0-9.-]*)?\z/', $value) === 1;
    }
}

if (!function_exists('red_addon_valid_route_path')) {
    function red_addon_valid_route_path($path)
    {
        if (!is_string($path)
            || $path === ''
            || strlen($path) > 240
            || $path[0] !== '/'
            || strpos($path, "\0") !== false
            || strpbrk($path, '?#\\') !== false
        ) {
            return false;
        }

        foreach (explode('/', substr($path, 1)) as $segment) {
            if ($segment === ''
                || $segment === '.'
                || $segment === '..'
                || preg_match('/\A[A-Za-z0-9_{}.-]+\z/', $segment) !== 1
            ) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_addon_valid_permission')) {
    function red_addon_valid_permission($value)
    {
        return is_string($value)
            && strlen($value) <= 160
            && preg_match('/\A[a-z0-9][a-z0-9.-]*\z/', $value) === 1;
    }
}

if (!function_exists('red_addon_valid_sha256')) {
    function red_addon_valid_sha256($value)
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }
}

if (!function_exists('red_addon_validation_result')) {
    function red_addon_validation_result($packageId, $packageDirectory)
    {
        return [
            'valid' => false,
            'id' => (string) $packageId,
            'path' => (string) $packageDirectory,
            'manifest' => null,
            'integrity' => [
                'declaredFiles' => 0,
                'verifiedFiles' => 0,
                'inventoryComplete' => false,
            ],
            'errors' => [],
            'warnings' => [],
        ];
    }
}

if (!function_exists('red_addon_add_error')) {
    function red_addon_add_error(array &$result, $message)
    {
        $message = (string) $message;
        if (!in_array($message, $result['errors'], true)) {
            $result['errors'][] = $message;
        }
    }
}

if (!function_exists('red_addon_add_warning')) {
    function red_addon_add_warning(array &$result, $message)
    {
        $message = (string) $message;
        if (!in_array($message, $result['warnings'], true)) {
            $result['warnings'][] = $message;
        }
    }
}

if (!function_exists('red_addon_validate_object_keys')) {
    function red_addon_validate_object_keys($value, array $required, array $allowed, $context, array &$result)
    {
        if (!is_array($value) || (array_is_list($value) && $value !== [])) {
            red_addon_add_error($result, $context . ' must be an object.');
            return false;
        }

        foreach ($required as $key) {
            if (!array_key_exists($key, $value)) {
                red_addon_add_error($result, $context . ' is missing required field "' . $key . '".');
            }
        }
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                red_addon_add_error($result, $context . ' contains unsupported field "' . (string) $key . '".');
            }
        }

        return true;
    }
}

if (!function_exists('red_addon_required_string')) {
    function red_addon_required_string(array $value, $key, $context, $maxLength, array &$result)
    {
        $rawString = isset($value[$key]) && is_string($value[$key]) ? $value[$key] : '';
        $string = trim($rawString);
        if ($string === '' || strlen($string) > $maxLength) {
            red_addon_add_error(
                $result,
                $context . ' field "' . $key . '" must be a non-empty string no longer than ' . $maxLength . ' bytes.'
            );
            return '';
        }
        if ($rawString !== $string) {
            red_addon_add_error(
                $result,
                $context . ' field "' . $key . '" must not contain surrounding whitespace.'
            );
        }

        return $string;
    }
}

if (!function_exists('red_addon_validate_string_list')) {
    function red_addon_validate_string_list(
        $values,
        $context,
        $maximum,
        callable $validator,
        array &$result
    ) {
        if (!is_array($values) || !array_is_list($values)) {
            red_addon_add_error($result, $context . ' must be an array.');
            return [];
        }
        if (count($values) > $maximum) {
            red_addon_add_error($result, $context . ' exceeds its ' . $maximum . '-item limit.');
        }

        $validValues = [];
        foreach ($values as $index => $value) {
            if (!$validator($value)) {
                red_addon_add_error($result, $context . '[' . $index . '] is invalid.');
                continue;
            }
            if (isset($validValues[$value])) {
                red_addon_add_error($result, $context . ' repeats "' . $value . '".');
                continue;
            }
            $validValues[$value] = true;
        }

        return array_keys($validValues);
    }
}

if (!function_exists('red_addon_valid_component_field_key')) {
    function red_addon_valid_component_field_key($value)
    {
        return is_string($value)
            && strlen($value) <= 64
            && preg_match('/\A[a-z][a-z0-9-]*\z/', $value) === 1;
    }
}

if (!function_exists('red_addon_setting_string_is_valid')) {
    function red_addon_setting_string_is_valid($type, $value)
    {
        if (!is_string($value)
            || preg_match('//u', $value) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            return false;
        }
        if ($type === 'text') {
            return strlen($value) <= 2000;
        }
        if ($type === 'select') {
            return $value !== ''
                && trim($value) === $value
                && strlen($value) <= 120;
        }
        if ($type === 'url') {
            if ($value === ''
                || trim($value) !== $value
                || strlen($value) > 2048
                || filter_var($value, FILTER_VALIDATE_URL) === false
            ) {
                return false;
            }
            $parts = parse_url($value);
            return is_array($parts)
                && in_array(
                    strtolower((string) ($parts['scheme'] ?? '')),
                    ['http', 'https'],
                    true
                )
                && is_string($parts['host'] ?? null)
                && $parts['host'] !== ''
                && !isset($parts['user'])
                && !isset($parts['pass']);
        }
        if ($type === 'email') {
            return $value !== ''
                && trim($value) === $value
                && strlen($value) <= 254
                && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }
        if ($type === 'secret-reference') {
            return strlen($value) <= 160
                && preg_match(
                    '/\Aconfig:[a-z0-9][a-z0-9.-]*\z/',
                    $value
                ) === 1;
        }
        return false;
    }
}

if (!function_exists('red_addon_validate_settings')) {
    function red_addon_validate_settings($settings, array &$result)
    {
        if (!is_array($settings) || !array_is_list($settings)) {
            red_addon_add_error($result, 'Settings must be an array.');
            return [];
        }
        if (count($settings) > 200) {
            red_addon_add_error($result, 'Settings exceeds 200 entries.');
        }

        $normalized = [];
        $settingKeys = [];
        $allowedTypes = [
            'text',
            'boolean',
            'integer',
            'select',
            'url',
            'email',
            'secret-reference',
        ];
        foreach ($settings as $index => $setting) {
            $context = 'Setting[' . $index . ']';
            if (!red_addon_validate_object_keys(
                $setting,
                ['key', 'label', 'type', 'secret'],
                ['key', 'label', 'type', 'secret', 'default', 'options'],
                $context,
                $result
            )) {
                continue;
            }
            $key = is_string($setting['key'] ?? null)
                ? $setting['key']
                : '';
            if (!red_addon_valid_permission($key)) {
                red_addon_add_error($result, $context . ' key is invalid.');
            } elseif (isset($settingKeys[$key])) {
                red_addon_add_error(
                    $result,
                    'Setting key "' . $key . '" is duplicated.'
                );
            } else {
                $settingKeys[$key] = true;
            }
            $label = red_addon_required_string(
                $setting,
                'label',
                $context,
                120,
                $result
            );
            $type = is_string($setting['type'] ?? null)
                ? $setting['type']
                : '';
            if (!in_array($type, $allowedTypes, true)) {
                red_addon_add_error($result, $context . ' type is unsupported.');
            }
            $secret = $setting['secret'] ?? null;
            if (!is_bool($secret)) {
                red_addon_add_error($result, $context . ' secret must be boolean.');
            } elseif (($type === 'secret-reference') !== $secret) {
                red_addon_add_error(
                    $result,
                    $context . ' must use secret-reference exactly when secret is true.'
                );
            }

            $options = [];
            if ($type === 'select') {
                $options = red_addon_validate_string_list(
                    $setting['options'] ?? null,
                    $context . ' options',
                    100,
                    static function ($value) {
                        return red_addon_setting_string_is_valid(
                            'select',
                            $value
                        );
                    },
                    $result
                );
                if ($options === []) {
                    red_addon_add_error(
                        $result,
                        $context . ' options must not be empty.'
                    );
                }
            } elseif (array_key_exists('options', $setting)) {
                red_addon_add_error(
                    $result,
                    $context . ' options are allowed only for select settings.'
                );
            }

            $hasDefault = array_key_exists('default', $setting);
            $default = $hasDefault ? $setting['default'] : null;
            $defaultValid = $default === null;
            if ($secret === true && $hasDefault) {
                red_addon_add_error(
                    $result,
                    $context . ' must not contain a secret default.'
                );
                $defaultValid = false;
            } elseif ($default !== null) {
                if ($type === 'boolean') {
                    $defaultValid = is_bool($default);
                } elseif ($type === 'integer') {
                    $defaultValid = is_int($default)
                        && $default >= -2147483648
                        && $default <= 2147483647;
                } elseif ($type === 'select') {
                    $defaultValid = is_string($default)
                        && in_array($default, $options, true);
                } elseif (in_array(
                    $type,
                    ['text', 'url', 'email'],
                    true
                )) {
                    $defaultValid = red_addon_setting_string_is_valid(
                        $type,
                        $default
                    );
                } else {
                    $defaultValid = false;
                }
                if (!$defaultValid) {
                    red_addon_add_error(
                        $result,
                        $context . ' default is invalid for its type.'
                    );
                }
            }

            $definition = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'secret' => is_bool($secret) ? $secret : false,
            ];
            if ($hasDefault && $secret !== true && $defaultValid) {
                $definition['default'] = $default;
            }
            if ($type === 'select') {
                $definition['options'] = $options;
            }
            $normalized[] = $definition;
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_settings_schema')) {
    function red_addon_settings_schema(array $manifest)
    {
        $result = ['errors' => [], 'warnings' => []];
        $settings = red_addon_validate_settings(
            $manifest['settings'] ?? null,
            $result
        );
        return $result['errors'] === [] ? $settings : null;
    }
}

if (!function_exists('red_addon_validate_admin_tool_contracts')) {
    function red_addon_validate_admin_tool_contracts(
        $contracts,
        array $providedTools,
        array $declaredPermissions,
        array &$result
    ) {
        if (!is_array($contracts) || !array_is_list($contracts)) {
            red_addon_add_error(
                $result,
                'Administrator tool contracts must be an array.'
            );
            return [];
        }
        if (count($contracts) > 200) {
            red_addon_add_error(
                $result,
                'Administrator tool contracts exceeds 200 entries.'
            );
        }

        $normalized = [];
        $seen = [];
        foreach ($contracts as $index => $contract) {
            $context = 'Administrator tool contract[' . $index . ']';
            if (!red_addon_validate_object_keys(
                $contract,
                ['tool', 'label', 'description', 'icon', 'permission', 'mode'],
                ['tool', 'label', 'description', 'icon', 'permission', 'mode'],
                $context,
                $result
            )) {
                continue;
            }
            $tool = is_string($contract['tool'] ?? null)
                ? $contract['tool']
                : '';
            if (!red_addon_valid_capability($tool)) {
                red_addon_add_error($result, $context . ' tool is invalid.');
            } elseif (!in_array($tool, $providedTools, true)) {
                red_addon_add_error(
                    $result,
                    $context . ' tool must appear in Provides adminTools.'
                );
            } elseif (isset($seen[$tool])) {
                red_addon_add_error(
                    $result,
                    'Administrator tool contract for "' . $tool .
                        '" is duplicated.'
                );
            } else {
                $seen[$tool] = true;
            }

            $label = red_addon_required_string(
                $contract,
                'label',
                $context,
                120,
                $result
            );
            $description = red_addon_required_string(
                $contract,
                'description',
                $context,
                500,
                $result
            );
            foreach (['label' => $label, 'description' => $description] as $key => $value) {
                if (preg_match('//u', $value) !== 1
                    || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
                        === 1
                ) {
                    red_addon_add_error(
                        $result,
                        $context . ' field "' . $key . '" contains unsafe text.'
                    );
                }
            }
            $icon = is_string($contract['icon'] ?? null)
                ? $contract['icon']
                : '';
            if (preg_match('/\A[a-z][a-z0-9-]{0,39}\z/', $icon) !== 1) {
                red_addon_add_error(
                    $result,
                    $context . ' icon token is invalid.'
                );
            }
            $permission = is_string($contract['permission'] ?? null)
                ? $contract['permission']
                : '';
            if (!red_addon_valid_permission($permission)) {
                red_addon_add_error(
                    $result,
                    $context . ' permission is invalid.'
                );
            } elseif (!in_array($permission, $declaredPermissions, true)) {
                red_addon_add_error(
                    $result,
                    $context . ' permission must appear in Permissions.'
                );
            }
            $mode = is_string($contract['mode'] ?? null)
                ? $contract['mode']
                : '';
            if ($mode !== 'read-only') {
                red_addon_add_error(
                    $result,
                    $context . ' mode must be read-only.'
                );
            }

            $normalized[] = [
                'tool' => $tool,
                'label' => $label,
                'description' => $description,
                'icon' => $icon,
                'permission' => $permission,
                'mode' => $mode,
            ];
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_admin_tool_contract')) {
    function red_addon_admin_tool_contract(array $manifest, $toolId)
    {
        if (!is_string($toolId)
            || !red_addon_valid_capability($toolId)
            || !array_key_exists('adminToolContracts', $manifest)
        ) {
            return null;
        }
        $result = ['errors' => [], 'warnings' => []];
        $tools = red_addon_validate_string_list(
            $manifest['provides']['adminTools'] ?? null,
            'Provides adminTools',
            200,
            'red_addon_valid_capability',
            $result
        );
        $permissions = red_addon_validate_string_list(
            $manifest['permissions'] ?? null,
            'Permissions',
            200,
            'red_addon_valid_permission',
            $result
        );
        $contracts = red_addon_validate_admin_tool_contracts(
            $manifest['adminToolContracts'] ?? null,
            $tools,
            $permissions,
            $result
        );
        if ($result['errors'] !== []) {
            return null;
        }
        foreach ($contracts as $contract) {
            if (hash_equals($toolId, $contract['tool'])) {
                return $contract;
            }
        }
        return null;
    }
}

if (!function_exists('red_addon_validate_component_editors')) {
    function red_addon_validate_component_editors(
        $editors,
        array $providedComponents,
        array $declaredPermissions,
        array &$result
    ) {
        if (!is_array($editors) || !array_is_list($editors)) {
            red_addon_add_error($result, 'Component editors must be an array.');
            return [];
        }
        if (count($editors) > 200) {
            red_addon_add_error($result, 'Component editors exceeds 200 entries.');
        }

        $normalized = [];
        $seenComponents = [];
        foreach ($editors as $editorIndex => $editor) {
            $editorContext = 'Component editor[' . $editorIndex . ']';
            if (!red_addon_validate_object_keys(
                $editor,
                ['component', 'label', 'description', 'icon', 'permissions', 'fields'],
                ['component', 'label', 'description', 'icon', 'permissions', 'fields'],
                $editorContext,
                $result
            )) {
                continue;
            }

            $componentId = isset($editor['component']) && is_string($editor['component'])
                ? $editor['component']
                : '';
            if (!red_addon_valid_capability($componentId)) {
                red_addon_add_error($result, $editorContext . ' component is invalid.');
            } elseif (!in_array($componentId, $providedComponents, true)) {
                red_addon_add_error(
                    $result,
                    $editorContext . ' component must appear in Provides components.'
                );
            } elseif (isset($seenComponents[$componentId])) {
                red_addon_add_error(
                    $result,
                    'Component editor for "' . $componentId . '" is duplicated.'
                );
            } else {
                $seenComponents[$componentId] = true;
            }

            $label = red_addon_required_string(
                $editor,
                'label',
                $editorContext,
                120,
                $result
            );
            $description = red_addon_required_string(
                $editor,
                'description',
                $editorContext,
                500,
                $result
            );
            $icon = isset($editor['icon']) && is_string($editor['icon'])
                ? $editor['icon']
                : '';
            if (preg_match('/\A[a-z][a-z0-9-]{0,39}\z/', $icon) !== 1) {
                red_addon_add_error($result, $editorContext . ' icon token is invalid.');
            }

            $permissionKeys = [
                'create',
                'view',
                'edit',
                'delete',
                'publish',
                'restore',
            ];
            $permissions = $editor['permissions'] ?? null;
            $normalizedPermissions = [];
            if (red_addon_validate_object_keys(
                $permissions,
                $permissionKeys,
                $permissionKeys,
                $editorContext . ' permissions',
                $result
            )) {
                foreach ($permissionKeys as $permissionKey) {
                    $permission = isset($permissions[$permissionKey])
                        && is_string($permissions[$permissionKey])
                        ? $permissions[$permissionKey]
                        : '';
                    if (!red_addon_valid_permission($permission)) {
                        red_addon_add_error(
                            $result,
                            $editorContext . ' permission "' . $permissionKey . '" is invalid.'
                        );
                    } elseif (!in_array($permission, $declaredPermissions, true)) {
                        red_addon_add_error(
                            $result,
                            $editorContext . ' permission "' . $permissionKey
                                . '" must appear in Permissions.'
                        );
                    }
                    $normalizedPermissions[$permissionKey] = $permission;
                }
            }

            $fields = $editor['fields'] ?? null;
            $normalizedFields = [];
            $seenFieldKeys = [];
            if (!is_array($fields) || !array_is_list($fields)) {
                red_addon_add_error($result, $editorContext . ' fields must be an array.');
            } else {
                if ($fields === []) {
                    red_addon_add_error($result, $editorContext . ' fields must not be empty.');
                }
                if (count($fields) > 100) {
                    red_addon_add_error($result, $editorContext . ' fields exceeds 100 entries.');
                }
                foreach ($fields as $fieldIndex => $field) {
                    $fieldContext = $editorContext . ' field[' . $fieldIndex . ']';
                    if (!red_addon_validate_object_keys(
                        $field,
                        ['key', 'label', 'type', 'required'],
                        [
                            'key',
                            'label',
                            'type',
                            'required',
                            'help',
                            'minLength',
                            'maxLength',
                            'minimum',
                            'maximum',
                            'options',
                        ],
                        $fieldContext,
                        $result
                    )) {
                        continue;
                    }

                    $fieldKey = isset($field['key']) && is_string($field['key'])
                        ? $field['key']
                        : '';
                    if (!red_addon_valid_component_field_key($fieldKey)) {
                        red_addon_add_error($result, $fieldContext . ' key is invalid.');
                    } elseif (isset($seenFieldKeys[$fieldKey])) {
                        red_addon_add_error(
                            $result,
                            $editorContext . ' field key "' . $fieldKey . '" is duplicated.'
                        );
                    } else {
                        $seenFieldKeys[$fieldKey] = true;
                    }
                    $fieldLabel = red_addon_required_string(
                        $field,
                        'label',
                        $fieldContext,
                        120,
                        $result
                    );
                    $fieldType = isset($field['type']) && is_string($field['type'])
                        ? $field['type']
                        : '';
                    $allowedFieldTypes = [
                        'text',
                        'textarea',
                        'integer',
                        'boolean',
                        'select',
                        'url',
                        'email',
                        'date',
                        'datetime',
                        'media-reference',
                    ];
                    if (!in_array($fieldType, $allowedFieldTypes, true)) {
                        red_addon_add_error($result, $fieldContext . ' type is unsupported.');
                    }
                    $required = $field['required'] ?? null;
                    if (!is_bool($required)) {
                        red_addon_add_error($result, $fieldContext . ' required must be boolean.');
                    }
                    $help = null;
                    if (array_key_exists('help', $field)) {
                        $help = red_addon_required_string(
                            $field,
                            'help',
                            $fieldContext,
                            500,
                            $result
                        );
                    }

                    $lengthTypes = [
                        'text' => 500,
                        'textarea' => 10000,
                        'url' => 2048,
                        'email' => 254,
                        'media-reference' => 255,
                    ];
                    $minLength = $field['minLength'] ?? null;
                    $maxLength = $field['maxLength'] ?? null;
                    if (isset($lengthTypes[$fieldType])) {
                        if ($minLength !== null
                            && (!is_int($minLength)
                                || $minLength < 0
                                || $minLength > $lengthTypes[$fieldType])
                        ) {
                            red_addon_add_error($result, $fieldContext . ' minLength is invalid.');
                        }
                        if (!is_int($maxLength)
                            || $maxLength < 1
                            || $maxLength > $lengthTypes[$fieldType]
                        ) {
                            red_addon_add_error($result, $fieldContext . ' maxLength is invalid.');
                        }
                        if (is_int($minLength)
                            && is_int($maxLength)
                            && $minLength > $maxLength
                        ) {
                            red_addon_add_error(
                                $result,
                                $fieldContext . ' minLength must not exceed maxLength.'
                            );
                        }
                    } elseif (array_key_exists('minLength', $field)
                        || array_key_exists('maxLength', $field)
                    ) {
                        red_addon_add_error(
                            $result,
                            $fieldContext . ' length limits are unsupported for this type.'
                        );
                    }

                    $minimum = $field['minimum'] ?? null;
                    $maximum = $field['maximum'] ?? null;
                    if ($fieldType === 'integer') {
                        if (!is_int($minimum)
                            || !is_int($maximum)
                            || $minimum < -2147483648
                            || $maximum > 2147483647
                            || $minimum > $maximum
                        ) {
                            red_addon_add_error(
                                $result,
                                $fieldContext . ' integer bounds are invalid.'
                            );
                        }
                    } elseif (array_key_exists('minimum', $field)
                        || array_key_exists('maximum', $field)
                    ) {
                        red_addon_add_error(
                            $result,
                            $fieldContext . ' numeric bounds are unsupported for this type.'
                        );
                    }

                    $options = $field['options'] ?? null;
                    $normalizedOptions = [];
                    if ($fieldType === 'select') {
                        if (!is_array($options) || !array_is_list($options)) {
                            red_addon_add_error(
                                $result,
                                $fieldContext . ' options must be an array.'
                            );
                        } else {
                            if ($options === []) {
                                red_addon_add_error(
                                    $result,
                                    $fieldContext . ' options must not be empty.'
                                );
                            }
                            if (count($options) > 100) {
                                red_addon_add_error(
                                    $result,
                                    $fieldContext . ' options exceeds 100 entries.'
                                );
                            }
                            $seenOptions = [];
                            foreach ($options as $optionIndex => $option) {
                                $optionContext = $fieldContext
                                    . ' option[' . $optionIndex . ']';
                                if (!red_addon_validate_object_keys(
                                    $option,
                                    ['value', 'label'],
                                    ['value', 'label'],
                                    $optionContext,
                                    $result
                                )) {
                                    continue;
                                }
                                $optionValue = red_addon_required_string(
                                    $option,
                                    'value',
                                    $optionContext,
                                    120,
                                    $result
                                );
                                $optionLabel = red_addon_required_string(
                                    $option,
                                    'label',
                                    $optionContext,
                                    120,
                                    $result
                                );
                                if (isset($seenOptions[$optionValue])) {
                                    red_addon_add_error(
                                        $result,
                                        $fieldContext . ' option value "'
                                            . $optionValue . '" is duplicated.'
                                    );
                                } else {
                                    $seenOptions[$optionValue] = true;
                                }
                                $normalizedOptions[] = [
                                    'value' => $optionValue,
                                    'label' => $optionLabel,
                                ];
                            }
                        }
                    } elseif (array_key_exists('options', $field)) {
                        red_addon_add_error(
                            $result,
                            $fieldContext . ' options are allowed only for select fields.'
                        );
                    }

                    $normalizedField = [
                        'key' => $fieldKey,
                        'label' => $fieldLabel,
                        'type' => $fieldType,
                        'required' => is_bool($required) ? $required : false,
                    ];
                    if ($help !== null) {
                        $normalizedField['help'] = $help;
                    }
                    if (isset($lengthTypes[$fieldType])) {
                        if ($minLength !== null) {
                            $normalizedField['minLength'] = $minLength;
                        }
                        $normalizedField['maxLength'] = $maxLength;
                    }
                    if ($fieldType === 'integer') {
                        $normalizedField['minimum'] = $minimum;
                        $normalizedField['maximum'] = $maximum;
                    }
                    if ($fieldType === 'select') {
                        $normalizedField['options'] = $normalizedOptions;
                    }
                    $normalizedFields[] = $normalizedField;
                }
            }

            $normalized[] = [
                'component' => $componentId,
                'label' => $label,
                'description' => $description,
                'icon' => $icon,
                'permissions' => $normalizedPermissions,
                'fields' => $normalizedFields,
            ];
        }

        return $normalized;
    }
}

if (!function_exists('red_addon_component_editor_schema')) {
    function red_addon_component_editor_schema(array $manifest, $componentId)
    {
        if (!is_string($componentId) || !red_addon_valid_capability($componentId)) {
            return null;
        }
        if (!array_key_exists('componentEditors', $manifest)) {
            return null;
        }
        $result = ['errors' => [], 'warnings' => []];
        $providedComponents = red_addon_validate_string_list(
            $manifest['provides']['components'] ?? null,
            'Provides components',
            200,
            'red_addon_valid_capability',
            $result
        );
        $permissions = red_addon_validate_string_list(
            $manifest['permissions'] ?? null,
            'Permissions',
            200,
            'red_addon_valid_permission',
            $result
        );
        $editors = red_addon_validate_component_editors(
            $manifest['componentEditors'] ?? null,
            $providedComponents,
            $permissions,
            $result
        );
        if ($result['errors'] !== []) {
            return null;
        }
        foreach ($editors as $editor) {
            if (isset($editor['component'])
                && is_string($editor['component'])
                && hash_equals($componentId, $editor['component'])
            ) {
                return $editor;
            }
        }
        return null;
    }
}

if (!function_exists('red_addon_safe_package_file')) {
    function red_addon_safe_package_file($packageDirectory, $relativePath)
    {
        if (!red_addon_valid_relative_path($relativePath)) {
            return null;
        }

        $base = realpath((string) $packageDirectory);
        if ($base === false || !is_dir($base) || is_link($packageDirectory)) {
            return null;
        }

        $current = $base;
        foreach (explode('/', str_replace('\\', '/', $relativePath)) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                return null;
            }
        }

        $resolved = realpath($current);
        if ($resolved === false
            || !is_file($resolved)
            || is_link($resolved)
            || strpos($resolved, $base . DIRECTORY_SEPARATOR) !== 0
        ) {
            return null;
        }

        return $resolved;
    }
}

if (!function_exists('red_addon_package_inventory')) {
    function red_addon_package_inventory($packageDirectory, array &$result)
    {
        $base = realpath((string) $packageDirectory);
        if ($base === false || !is_dir($base) || is_link($packageDirectory)) {
            red_addon_add_error($result, 'Package inventory root is missing or unsafe.');
            return [];
        }

        $inventory = [];
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $fileInfo) {
                $absolutePath = $fileInfo->getPathname();
                $relativePath = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    ltrim(substr($absolutePath, strlen($base)), DIRECTORY_SEPARATOR)
                );
                if ($relativePath === 'addon.json') {
                    continue;
                }
                if ($fileInfo->isLink()) {
                    red_addon_add_error($result, 'Package inventory contains a symbolic link: ' . $relativePath);
                    continue;
                }
                if (!$fileInfo->isFile()) {
                    red_addon_add_error($result, 'Package inventory contains a non-file entry: ' . $relativePath);
                    continue;
                }
                if (!red_addon_valid_relative_path($relativePath)) {
                    red_addon_add_error($result, 'Package inventory contains an unsafe path: ' . $relativePath);
                    continue;
                }
                if ($fileInfo->getSize() > 104857600) {
                    red_addon_add_error($result, 'Package file exceeds the 100 MiB trust-gate limit: ' . $relativePath);
                    continue;
                }
                $inventory[$relativePath] = $absolutePath;
                if (count($inventory) > 2048) {
                    red_addon_add_error($result, 'Package inventory exceeds the 2048-file trust-gate limit.');
                    break;
                }
            }
        } catch (Throwable $throwable) {
            red_addon_add_error($result, 'Package inventory could not be read safely.');
            return [];
        }

        ksort($inventory, SORT_STRING);
        return $inventory;
    }
}

if (!function_exists('red_addon_validate_file_definition')) {
    function red_addon_validate_file_definition($definition, $context, array &$result)
    {
        if (!red_addon_validate_object_keys(
            $definition,
            ['path', 'sha256'],
            ['path', 'sha256'],
            $context,
            $result
        )) {
            return null;
        }

        $path = isset($definition['path']) && is_string($definition['path']) ? $definition['path'] : '';
        $sha256 = isset($definition['sha256']) && is_string($definition['sha256'])
            ? strtolower($definition['sha256'])
            : '';
        if (!red_addon_valid_relative_path($path)) {
            red_addon_add_error($result, $context . ' path is unsafe.');
        }
        if (!red_addon_valid_sha256($sha256)) {
            red_addon_add_error($result, $context . ' sha256 must be 64 lowercase hexadecimal characters.');
        }

        return red_addon_valid_relative_path($path) && red_addon_valid_sha256($sha256)
            ? ['path' => $path, 'sha256' => $sha256]
            : null;
    }
}

if (!function_exists('red_addon_record_integrity_reference')) {
    function red_addon_record_integrity_reference(
        array &$references,
        $path,
        $sha256,
        $context,
        array &$result
    ) {
        if (isset($references[$path])
            && !hash_equals($references[$path], $sha256)
        ) {
            red_addon_add_error(
                $result,
                $context . ' checksum conflicts with another declaration for ' . $path . '.'
            );
            return;
        }

        $references[$path] = $sha256;
    }
}

if (!function_exists('red_addon_validate_manifest')) {
    function red_addon_validate_manifest($packageId, $projectRoot = null, array $context = [])
    {
        $projectRoot = red_addon_project_root($projectRoot);
        $parts = red_addon_package_parts($packageId);
        $packageDirectory = $parts === null
            ? red_addon_root($projectRoot) . DIRECTORY_SEPARATOR . (string) $packageId
            : red_addon_root($projectRoot) . DIRECTORY_SEPARATOR . $parts[0] . DIRECTORY_SEPARATOR . $parts[1];
        $result = red_addon_validation_result($packageId, $packageDirectory);

        if ($parts === null) {
            red_addon_add_error($result, 'Package id must be two lowercase dot-separated slugs.');
            return $result;
        }

        $addonRoot = red_addon_root($projectRoot);
        $vendorDirectory = $addonRoot . DIRECTORY_SEPARATOR . $parts[0];
        $resolvedRoot = realpath($addonRoot);
        $resolvedPackage = realpath($packageDirectory);
        if ($resolvedRoot === false || !is_dir($resolvedRoot) || is_link($addonRoot)) {
            red_addon_add_error($result, 'The server-owned add-on root is missing or unsafe.');
            return $result;
        }
        if (is_link($vendorDirectory) || is_link($packageDirectory)) {
            red_addon_add_error($result, 'Package path must not contain symbolic-link directories.');
            return $result;
        }
        if ($resolvedPackage === false
            || !is_dir($resolvedPackage)
            || strpos($resolvedPackage, $resolvedRoot . DIRECTORY_SEPARATOR) !== 0
        ) {
            red_addon_add_error($result, 'Package directory is missing or resolves outside the add-on root.');
            return $result;
        }
        $result['path'] = $resolvedPackage;

        $manifestPath = $resolvedPackage . DIRECTORY_SEPARATOR . 'addon.json';
        if (!is_file($manifestPath) || !is_readable($manifestPath) || is_link($manifestPath)) {
            red_addon_add_error($result, 'Package manifest is missing, unreadable, or symbolic-linked.');
            return $result;
        }
        $manifestSize = filesize($manifestPath);
        if (!is_int($manifestSize) || $manifestSize < 2 || $manifestSize > 262144) {
            red_addon_add_error($result, 'Package manifest must be between 2 bytes and 256 KiB.');
            return $result;
        }

        $json = file_get_contents($manifestPath);
        if (!is_string($json)) {
            red_addon_add_error($result, 'Package manifest could not be read.');
            return $result;
        }
        try {
            $decodedObject = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            red_addon_add_error($result, 'Package manifest contains invalid JSON.');
            return $result;
        }
        if (!is_object($decodedObject) || !is_array($manifest)) {
            red_addon_add_error($result, 'Package manifest root must be an object.');
            return $result;
        }
        $result['manifest'] = $manifest;

        $requiredTopLevel = [
            'schemaVersion', 'id', 'name', 'description', 'version', 'type',
            'compatibility', 'provides', 'dependencies', 'permissions',
            'settings', 'migrations', 'routes', 'jobs', 'outboundHosts',
            'assets', 'integrity', 'uninstall',
        ];
        red_addon_validate_object_keys(
            $manifest,
            $requiredTopLevel,
            array_merge(
                ['$schema', 'componentEditors', 'adminToolContracts'],
                $requiredTopLevel
            ),
            'Manifest',
            $result
        );
        if (array_key_exists('$schema', $manifest)
            && ($manifest['$schema'] ?? null) !== 'https://red-sphere.com/schemas/addon-manifest-v1.json'
        ) {
            red_addon_add_error(
                $result,
                'Manifest $schema must identify the RED-CMS add-on manifest v1 schema.'
            );
        }

        if (($manifest['schemaVersion'] ?? null) !== 1) {
            red_addon_add_error($result, 'Manifest field "schemaVersion" must be the integer 1.');
        }
        $manifestId = red_addon_required_string($manifest, 'id', 'Manifest', 127, $result);
        if (!red_addon_valid_package_id($manifestId)) {
            red_addon_add_error($result, 'Manifest id must be two lowercase dot-separated slugs.');
        } elseif ($manifestId !== $packageId) {
            red_addon_add_error($result, 'Manifest id must match its vendor/package directory.');
        }
        red_addon_required_string($manifest, 'name', 'Manifest', 120, $result);
        red_addon_required_string($manifest, 'description', 'Manifest', 500, $result);
        $version = red_addon_required_string($manifest, 'version', 'Manifest', 120, $result);
        if (!red_addon_valid_semantic_version($version)) {
            red_addon_add_error($result, 'Manifest version must use semantic version format.');
        }
        $packageType = isset($manifest['type']) && is_string($manifest['type']) ? $manifest['type'] : '';
        if (!in_array($packageType, ['component', 'service', 'adapter', 'content-package', 'cross-cutting'], true)) {
            red_addon_add_error($result, 'Manifest type is unsupported.');
        }

        $compatibility = $manifest['compatibility'] ?? null;
        if (red_addon_validate_object_keys(
            $compatibility,
            ['cms', 'php'],
            ['cms', 'php'],
            'Compatibility',
            $result
        )) {
            $cmsRange = red_addon_required_string($compatibility, 'cms', 'Compatibility', 120, $result);
            $phpRange = red_addon_required_string($compatibility, 'php', 'Compatibility', 120, $result);
            if (!red_addon_version_range_valid($cmsRange)) {
                red_addon_add_error($result, 'Compatibility cms range uses an unsupported format.');
            }
            if (!red_addon_version_range_valid($phpRange)) {
                red_addon_add_error($result, 'Compatibility php range uses an unsupported format.');
            }
            $cmsVersion = isset($context['cmsVersion']) ? (string) $context['cmsVersion'] : (
                defined('RED_CMS_VERSION') ? (string) RED_CMS_VERSION : '5.0.0'
            );
            $phpVersion = isset($context['phpVersion']) ? (string) $context['phpVersion'] : PHP_VERSION;
            if (red_addon_version_range_valid($cmsRange) && !red_addon_version_satisfies($cmsVersion, $cmsRange)) {
                red_addon_add_error($result, 'Package is incompatible with RED-CMS ' . $cmsVersion . '.');
            }
            if (red_addon_version_range_valid($phpRange) && !red_addon_version_satisfies($phpVersion, $phpRange)) {
                red_addon_add_error($result, 'Package is incompatible with PHP ' . $phpVersion . '.');
            }
        }

        $provides = $manifest['provides'] ?? null;
        $providedCapabilities = [
            'components' => [],
            'services' => [],
            'adminTools' => [],
            'adapters' => [],
        ];
        if (red_addon_validate_object_keys(
            $provides,
            ['components', 'services', 'adminTools', 'adapters'],
            ['components', 'services', 'adminTools', 'adapters'],
            'Provides',
            $result
        )) {
            foreach (['components', 'services', 'adminTools', 'adapters'] as $provideType) {
                $providedCapabilities[$provideType] = red_addon_validate_string_list(
                    $provides[$provideType] ?? null,
                    'Provides ' . $provideType,
                    200,
                    'red_addon_valid_capability',
                    $result
                );
            }
        }

        $dependencies = $manifest['dependencies'] ?? null;
        if (red_addon_validate_object_keys(
            $dependencies,
            ['required', 'optional'],
            ['required', 'optional'],
            'Dependencies',
            $result
        )) {
            $dependencyIds = [];
            foreach (['required', 'optional'] as $dependencyType) {
                $dependencyList = $dependencies[$dependencyType] ?? null;
                if (!is_array($dependencyList) || !array_is_list($dependencyList)) {
                    red_addon_add_error($result, 'Dependencies ' . $dependencyType . ' must be an array.');
                    continue;
                }
                if (count($dependencyList) > 100) {
                    red_addon_add_error($result, 'Dependencies ' . $dependencyType . ' exceeds 100 entries.');
                }
                foreach ($dependencyList as $index => $dependency) {
                    $dependencyContext = 'Dependency ' . $dependencyType . '[' . $index . ']';
                    if (!red_addon_validate_object_keys(
                        $dependency,
                        ['id', 'version'],
                        ['id', 'version'],
                        $dependencyContext,
                        $result
                    )) {
                        continue;
                    }
                    $dependencyId = isset($dependency['id']) && is_string($dependency['id'])
                        ? $dependency['id']
                        : '';
                    $dependencyRange = isset($dependency['version']) && is_string($dependency['version'])
                        ? $dependency['version']
                        : '';
                    if (!red_addon_valid_package_id($dependencyId)) {
                        red_addon_add_error($result, $dependencyContext . ' id is invalid.');
                    } elseif ($dependencyId === $packageId) {
                        red_addon_add_error($result, $dependencyContext . ' cannot reference its own package.');
                    } elseif (isset($dependencyIds[$dependencyId])) {
                        red_addon_add_error($result, 'Dependency "' . $dependencyId . '" is duplicated.');
                    } else {
                        $dependencyIds[$dependencyId] = true;
                    }
                    if (!red_addon_version_range_valid($dependencyRange)) {
                        red_addon_add_error($result, $dependencyContext . ' version range is invalid.');
                    }
                }
            }
        }

        $declaredPermissions = red_addon_validate_string_list(
            $manifest['permissions'] ?? null,
            'Permissions',
            200,
            'red_addon_valid_permission',
            $result
        );

        if (array_key_exists('componentEditors', $manifest)) {
            red_addon_validate_component_editors(
                $manifest['componentEditors'],
                $providedCapabilities['components'],
                $declaredPermissions,
                $result
            );
        }

        if (array_key_exists('adminToolContracts', $manifest)) {
            red_addon_validate_admin_tool_contracts(
                $manifest['adminToolContracts'],
                $providedCapabilities['adminTools'],
                $declaredPermissions,
                $result
            );
        }

        red_addon_validate_settings($manifest['settings'] ?? null, $result);

        $integrityReferences = [];
        $migrationIds = [];
        $migrationPaths = [];
        $migrations = $manifest['migrations'] ?? null;
        if (!is_array($migrations) || !array_is_list($migrations)) {
            red_addon_add_error($result, 'Migrations must be an array.');
        } else {
            if (count($migrations) > 200) {
                red_addon_add_error($result, 'Migrations exceeds 200 entries.');
            }
            foreach ($migrations as $index => $migration) {
                $migrationContext = 'Migration[' . $index . ']';
                if (!red_addon_validate_object_keys(
                    $migration,
                    ['id', 'path', 'sha256'],
                    ['id', 'path', 'sha256'],
                    $migrationContext,
                    $result
                )) {
                    continue;
                }
                $migrationId = isset($migration['id']) && is_string($migration['id']) ? $migration['id'] : '';
                if (preg_match('/\A\d{4}-\d{2}-\d{2}-[a-z0-9][a-z0-9-]*\z/', $migrationId) !== 1) {
                    red_addon_add_error($result, $migrationContext . ' id is invalid.');
                } elseif (isset($migrationIds[$migrationId])) {
                    red_addon_add_error($result, 'Migration id "' . $migrationId . '" is duplicated.');
                } else {
                    $migrationIds[$migrationId] = true;
                }
                $file = red_addon_validate_file_definition(
                    [
                        'path' => $migration['path'] ?? null,
                        'sha256' => $migration['sha256'] ?? null,
                    ],
                    $migrationContext,
                    $result
                );
                if ($file !== null) {
                    if (preg_match('/\Amigrations\/[A-Za-z0-9_.-]+\.sql\z/', $file['path']) !== 1) {
                        red_addon_add_error($result, $migrationContext . ' path must be a direct migrations/*.sql file.');
                    } elseif (isset($migrationPaths[$file['path']])) {
                        red_addon_add_error(
                            $result,
                            'Migration path "' . $file['path'] . '" is duplicated.'
                        );
                    } else {
                        $migrationPaths[$file['path']] = true;
                    }
                    red_addon_record_integrity_reference(
                        $integrityReferences,
                        $file['path'],
                        $file['sha256'],
                        $migrationContext,
                        $result
                    );
                }
            }
        }

        $routes = $manifest['routes'] ?? null;
        $routeIds = [];
        if (!is_array($routes) || !array_is_list($routes)) {
            red_addon_add_error($result, 'Routes must be an array.');
        } else {
            if (count($routes) > 200) {
                red_addon_add_error($result, 'Routes exceeds 200 entries.');
            }
            foreach ($routes as $index => $route) {
                $routeContext = 'Route[' . $index . ']';
                if (!red_addon_validate_object_keys(
                    $route,
                    ['id', 'scope', 'path', 'methods', 'authentication', 'csrf'],
                    ['id', 'scope', 'path', 'methods', 'authentication', 'csrf'],
                    $routeContext,
                    $result
                )) {
                    continue;
                }
                $routeId = isset($route['id']) && is_string($route['id']) ? $route['id'] : '';
                if (!red_addon_valid_capability($routeId)) {
                    red_addon_add_error($result, $routeContext . ' id is invalid.');
                } elseif (isset($routeIds[$routeId])) {
                    red_addon_add_error($result, 'Route id "' . $routeId . '" is duplicated.');
                } else {
                    $routeIds[$routeId] = true;
                }
                $scope = isset($route['scope']) && is_string($route['scope']) ? $route['scope'] : '';
                if (!in_array($scope, ['public', 'admin'], true)) {
                    red_addon_add_error($result, $routeContext . ' scope is invalid.');
                }
                $path = isset($route['path']) && is_string($route['path']) ? $route['path'] : '';
                $publicPrefix = '/addons/' . $parts[0] . '/' . $parts[1];
                $adminPrefix = '/admin/addons/' . $parts[0] . '/' . $parts[1];
                $expectedPrefix = $scope === 'admin' ? $adminPrefix : $publicPrefix;
                if (!red_addon_valid_route_path($path)) {
                    red_addon_add_error($result, $routeContext . ' path is malformed or unsafe.');
                } elseif ($path !== $expectedPrefix && strpos($path, $expectedPrefix . '/') !== 0) {
                    red_addon_add_error($result, $routeContext . ' path must stay in its reserved package namespace.');
                }
                $methods = red_addon_validate_string_list(
                    $route['methods'] ?? null,
                    $routeContext . ' methods',
                    5,
                    static function ($method) {
                        return in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true);
                    },
                    $result
                );
                if ($methods === []) {
                    red_addon_add_error($result, $routeContext . ' methods must not be empty.');
                }
                $authentication = isset($route['authentication']) && is_string($route['authentication'])
                    ? $route['authentication']
                    : '';
                if (!in_array($authentication, ['public', 'admin', 'member'], true)) {
                    red_addon_add_error($result, $routeContext . ' authentication is invalid.');
                }
                if ($scope === 'admin' && $authentication !== 'admin') {
                    red_addon_add_error($result, $routeContext . ' administrator routes require admin authentication.');
                }
                $csrf = isset($route['csrf']) && is_string($route['csrf']) ? $route['csrf'] : '';
                if (!in_array($csrf, ['required', 'not-applicable'], true)) {
                    red_addon_add_error($result, $routeContext . ' csrf policy is invalid.');
                }
                if (array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE']) && $csrf !== 'required') {
                    red_addon_add_error($result, $routeContext . ' unsafe methods require CSRF protection.');
                }
            }
        }

        $jobs = $manifest['jobs'] ?? null;
        $jobIds = [];
        if (!is_array($jobs) || !array_is_list($jobs)) {
            red_addon_add_error($result, 'Jobs must be an array.');
        } else {
            if (count($jobs) > 100) {
                red_addon_add_error($result, 'Jobs exceeds 100 entries.');
            }
            foreach ($jobs as $index => $job) {
                $jobContext = 'Job[' . $index . ']';
                if (!red_addon_validate_object_keys(
                    $job,
                    ['id', 'maxAttempts', 'backoffSeconds'],
                    ['id', 'maxAttempts', 'backoffSeconds'],
                    $jobContext,
                    $result
                )) {
                    continue;
                }
                $jobId = isset($job['id']) && is_string($job['id']) ? $job['id'] : '';
                if (!red_addon_valid_capability($jobId)) {
                    red_addon_add_error($result, $jobContext . ' id is invalid.');
                } elseif (isset($jobIds[$jobId])) {
                    red_addon_add_error($result, 'Job id "' . $jobId . '" is duplicated.');
                } else {
                    $jobIds[$jobId] = true;
                }
                $maxAttempts = $job['maxAttempts'] ?? null;
                $backoffSeconds = $job['backoffSeconds'] ?? null;
                if (!is_int($maxAttempts) || $maxAttempts < 1 || $maxAttempts > 25) {
                    red_addon_add_error($result, $jobContext . ' maxAttempts must be an integer from 1 to 25.');
                }
                if (!is_int($backoffSeconds) || $backoffSeconds < 1 || $backoffSeconds > 86400) {
                    red_addon_add_error($result, $jobContext . ' backoffSeconds must be an integer from 1 to 86400.');
                }
            }
        }

        red_addon_validate_string_list(
            $manifest['outboundHosts'] ?? null,
            'Outbound hosts',
            100,
            static function ($host) {
                return is_string($host)
                    && strlen($host) <= 253
                    && preg_match(
                        '/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/',
                        $host
                    ) === 1;
            },
            $result
        );

        $assets = $manifest['assets'] ?? null;
        if (red_addon_validate_object_keys(
            $assets,
            ['public', 'admin'],
            ['public', 'admin'],
            'Assets',
            $result
        )) {
            foreach (['public', 'admin'] as $assetType) {
                $assetList = $assets[$assetType] ?? null;
                if (!is_array($assetList) || !array_is_list($assetList)) {
                    red_addon_add_error($result, 'Assets ' . $assetType . ' must be an array.');
                    continue;
                }
                if (count($assetList) > 200) {
                    red_addon_add_error($result, 'Assets ' . $assetType . ' exceeds 200 entries.');
                }
                foreach ($assetList as $index => $asset) {
                    $assetContext = 'Asset ' . $assetType . '[' . $index . ']';
                    if (!red_addon_validate_object_keys(
                        $asset,
                        ['path', 'sha256', 'location'],
                        ['path', 'sha256', 'location'],
                        $assetContext,
                        $result
                    )) {
                        continue;
                    }
                    $file = red_addon_validate_file_definition(
                        [
                            'path' => $asset['path'] ?? null,
                            'sha256' => $asset['sha256'] ?? null,
                        ],
                        $assetContext,
                        $result
                    );
                    if ($file !== null) {
                        red_addon_record_integrity_reference(
                            $integrityReferences,
                            $file['path'],
                            $file['sha256'],
                            $assetContext,
                            $result
                        );
                    }
                    if (!in_array($asset['location'] ?? null, ['head', 'body-end'], true)) {
                        red_addon_add_error($result, $assetContext . ' location is invalid.');
                    }
                }
            }
        }

        $declaredFiles = [];
        $integrity = $manifest['integrity'] ?? null;
        if (red_addon_validate_object_keys(
            $integrity,
            ['entrypoint', 'files'],
            ['entrypoint', 'files'],
            'Integrity',
            $result
        )) {
            if (($integrity['entrypoint'] ?? null) !== 'addon.php') {
                red_addon_add_error($result, 'Integrity entrypoint must be the fixed path "addon.php".');
            }
            $files = $integrity['files'] ?? null;
            if (!is_array($files) || !array_is_list($files) || $files === []) {
                red_addon_add_error($result, 'Integrity files must be a non-empty array.');
            } else {
                if (count($files) > 2048) {
                    red_addon_add_error($result, 'Integrity files exceeds the 2048-file limit.');
                }
                foreach ($files as $index => $fileDefinition) {
                    $file = red_addon_validate_file_definition(
                        $fileDefinition,
                        'Integrity file[' . $index . ']',
                        $result
                    );
                    if ($file === null) {
                        continue;
                    }
                    if ($file['path'] === 'addon.json') {
                        red_addon_add_error($result, 'Integrity files must not include the self-referential addon.json.');
                    } elseif (isset($declaredFiles[$file['path']])) {
                        red_addon_add_error($result, 'Integrity path "' . $file['path'] . '" is duplicated.');
                    } else {
                        $declaredFiles[$file['path']] = $file['sha256'];
                    }
                }
            }
        }
        $result['integrity']['declaredFiles'] = count($declaredFiles);
        if (!isset($declaredFiles['addon.php'])) {
            red_addon_add_error($result, 'Integrity files must declare the fixed addon.php entry point.');
        }

        foreach ($integrityReferences as $path => $sha256) {
            if (!isset($declaredFiles[$path])) {
                red_addon_add_error($result, 'Referenced file is absent from integrity inventory: ' . $path);
            } elseif (!hash_equals($declaredFiles[$path], $sha256)) {
                red_addon_add_error($result, 'Referenced checksum disagrees with integrity inventory: ' . $path);
            }
        }

        $inventory = red_addon_package_inventory($resolvedPackage, $result);
        $inventoryPaths = array_keys($inventory);
        $declaredPaths = array_keys($declaredFiles);
        sort($inventoryPaths, SORT_STRING);
        sort($declaredPaths, SORT_STRING);
        if ($inventoryPaths !== $declaredPaths) {
            $missingDeclarations = array_values(array_diff($inventoryPaths, $declaredPaths));
            $missingFiles = array_values(array_diff($declaredPaths, $inventoryPaths));
            if ($missingDeclarations) {
                red_addon_add_error(
                    $result,
                    'Package files missing from integrity inventory: ' . implode(', ', $missingDeclarations)
                );
            }
            if ($missingFiles) {
                red_addon_add_error(
                    $result,
                    'Integrity inventory references missing package files: ' . implode(', ', $missingFiles)
                );
            }
        } else {
            $result['integrity']['inventoryComplete'] = true;
        }

        $verifiedFiles = 0;
        foreach ($declaredFiles as $relativePath => $expectedSha256) {
            $absolutePath = red_addon_safe_package_file($resolvedPackage, $relativePath);
            if ($absolutePath === null) {
                red_addon_add_error($result, 'Integrity file is missing or unsafe: ' . $relativePath);
                continue;
            }
            $actualSha256 = hash_file('sha256', $absolutePath);
            if (!is_string($actualSha256) || !hash_equals($expectedSha256, $actualSha256)) {
                red_addon_add_error($result, 'Integrity checksum mismatch: ' . $relativePath);
                continue;
            }
            $verifiedFiles++;
        }
        $result['integrity']['verifiedFiles'] = $verifiedFiles;

        $uninstall = $manifest['uninstall'] ?? null;
        if (red_addon_validate_object_keys(
            $uninstall,
            ['defaultDataAction', 'allowExplicitPurge'],
            ['defaultDataAction', 'allowExplicitPurge'],
            'Uninstall',
            $result
        )) {
            if (($uninstall['defaultDataAction'] ?? null) !== 'retain') {
                red_addon_add_error($result, 'Uninstall defaultDataAction must be "retain".');
            }
            if (!is_bool($uninstall['allowExplicitPurge'] ?? null)) {
                red_addon_add_error($result, 'Uninstall allowExplicitPurge must be boolean.');
            }
        }

        $result['valid'] = $result['errors'] === [];
        return $result;
    }
}

if (!function_exists('red_addon_dependency_preflight')) {
    function red_addon_dependency_preflight(array $packages)
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'graph' => [],
        ];

        foreach ($packages as $packageId => $packageResult) {
            $result['graph'][$packageId] = [];
            $manifest = isset($packageResult['manifest']) && is_array($packageResult['manifest'])
                ? $packageResult['manifest']
                : null;
            if ($manifest === null) {
                continue;
            }
            foreach (['required', 'optional'] as $dependencyType) {
                $dependencies = $manifest['dependencies'][$dependencyType] ?? [];
                if (!is_array($dependencies)) {
                    continue;
                }
                foreach ($dependencies as $dependency) {
                    if (!is_array($dependency)) {
                        continue;
                    }
                    $dependencyId = isset($dependency['id']) && is_string($dependency['id'])
                        ? $dependency['id']
                        : '';
                    $dependencyRange = isset($dependency['version']) && is_string($dependency['version'])
                        ? $dependency['version']
                        : '';
                    if (!red_addon_valid_package_id($dependencyId)
                        || !red_addon_version_range_valid($dependencyRange)
                    ) {
                        continue;
                    }
                    if (!isset($packages[$dependencyId])) {
                        if ($dependencyType === 'required') {
                            $result['errors'][] = $packageId . ' requires missing package ' . $dependencyId . '.';
                        }
                        continue;
                    }
                    $result['graph'][$packageId][] = $dependencyId;
                    $dependencyResult = $packages[$dependencyId];
                    $dependencyValid = !empty($dependencyResult['valid']);
                    $dependencyVersion = is_array($dependencyResult['manifest'] ?? null)
                        ? (string) ($dependencyResult['manifest']['version'] ?? '')
                        : '';
                    $messagePrefix = $packageId . ' ' . $dependencyType . ' dependency ' . $dependencyId;
                    if (!$dependencyValid) {
                        $message = $messagePrefix . ' is invalid.';
                        if ($dependencyType === 'required') {
                            $result['errors'][] = $message;
                        } else {
                            $result['warnings'][] = $message;
                        }
                    } elseif (!red_addon_version_satisfies($dependencyVersion, $dependencyRange)) {
                        $message = $messagePrefix . ' does not satisfy ' . $dependencyRange . '.';
                        if ($dependencyType === 'required') {
                            $result['errors'][] = $message;
                        } else {
                            $result['warnings'][] = $message;
                        }
                    }
                }
            }
            $result['graph'][$packageId] = array_values(array_unique($result['graph'][$packageId]));
            sort($result['graph'][$packageId], SORT_STRING);
        }

        $state = [];
        $stack = [];
        $cycles = [];
        $visit = function ($packageId) use (&$visit, &$state, &$stack, &$cycles, $result) {
            $state[$packageId] = 1;
            $stack[] = $packageId;
            foreach ($result['graph'][$packageId] ?? [] as $dependencyId) {
                if (!isset($result['graph'][$dependencyId])) {
                    continue;
                }
                if (($state[$dependencyId] ?? 0) === 0) {
                    $visit($dependencyId);
                    continue;
                }
                if (($state[$dependencyId] ?? 0) === 1) {
                    $offset = array_search($dependencyId, $stack, true);
                    if ($offset !== false) {
                        $cycle = array_slice($stack, $offset);
                        $cycle[] = $dependencyId;
                        $cycleText = implode(' -> ', $cycle);
                        $cycles[$cycleText] = true;
                    }
                }
            }
            array_pop($stack);
            $state[$packageId] = 2;
        };
        foreach (array_keys($result['graph']) as $packageId) {
            if (($state[$packageId] ?? 0) === 0) {
                $visit($packageId);
            }
        }
        foreach (array_keys($cycles) as $cycle) {
            $result['errors'][] = 'Dependency cycle detected: ' . $cycle . '.';
        }

        $result['errors'] = array_values(array_unique($result['errors']));
        $result['warnings'] = array_values(array_unique($result['warnings']));
        sort($result['errors'], SORT_STRING);
        sort($result['warnings'], SORT_STRING);
        ksort($result['graph'], SORT_STRING);
        $result['valid'] = $result['errors'] === [];
        return $result;
    }
}

if (!function_exists('red_addon_discover')) {
    function red_addon_discover($projectRoot = null, array $context = [])
    {
        $projectRoot = red_addon_project_root($projectRoot);
        $addonRoot = red_addon_root($projectRoot);
        $catalog = [
            'valid' => true,
            'root' => $addonRoot,
            'packages' => [],
            'dependency' => [
                'valid' => true,
                'errors' => [],
                'warnings' => [],
                'graph' => [],
            ],
            'errors' => [],
            'warnings' => [],
        ];

        if (!file_exists($addonRoot)) {
            return $catalog;
        }
        if (!is_dir($addonRoot) || !is_readable($addonRoot) || is_link($addonRoot)) {
            $catalog['errors'][] = 'The server-owned add-on root is not a readable real directory.';
            $catalog['valid'] = false;
            return $catalog;
        }

        $vendorEntries = scandir($addonRoot);
        if ($vendorEntries === false) {
            $catalog['errors'][] = 'The server-owned add-on root could not be scanned.';
            $catalog['valid'] = false;
            return $catalog;
        }
        foreach ($vendorEntries as $vendor) {
            if ($vendor === '.' || $vendor === '..' || $vendor === '.gitignore' || $vendor === '.gitkeep') {
                continue;
            }
            $vendorDirectory = $addonRoot . DIRECTORY_SEPARATOR . $vendor;
            if (!red_addon_valid_slug($vendor)
                || !is_dir($vendorDirectory)
                || is_link($vendorDirectory)
            ) {
                $catalog['errors'][] = 'Unsafe add-on vendor entry: ' . $vendor;
                continue;
            }
            $packageEntries = scandir($vendorDirectory);
            if ($packageEntries === false) {
                $catalog['errors'][] = 'Add-on vendor directory could not be scanned: ' . $vendor;
                continue;
            }
            foreach ($packageEntries as $package) {
                if ($package === '.' || $package === '..' || $package === '.gitignore' || $package === '.gitkeep') {
                    continue;
                }
                $packageDirectory = $vendorDirectory . DIRECTORY_SEPARATOR . $package;
                if (!red_addon_valid_slug($package)
                    || !is_dir($packageDirectory)
                    || is_link($packageDirectory)
                ) {
                    $catalog['errors'][] = 'Unsafe add-on package entry: ' . $vendor . '/' . $package;
                    continue;
                }
                $packageId = $vendor . '.' . $package;
                $catalog['packages'][$packageId] = red_addon_validate_manifest(
                    $packageId,
                    $projectRoot,
                    $context
                );
            }
        }
        ksort($catalog['packages'], SORT_STRING);
        sort($catalog['errors'], SORT_STRING);
        $catalog['dependency'] = red_addon_dependency_preflight($catalog['packages']);
        $invalidPackages = array_filter(
            $catalog['packages'],
            static function ($package) {
                return empty($package['valid']);
            }
        );
        $catalog['valid'] = $catalog['errors'] === []
            && $invalidPackages === []
            && !empty($catalog['dependency']['valid']);
        return $catalog;
    }
}

?>
