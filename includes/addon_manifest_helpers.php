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
    function red_addon_validate_settings(
        $settings,
        array &$result,
        array $declaredPermissions = []
    )
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
                [
                    'key',
                    'label',
                    'type',
                    'secret',
                    'permission',
                    'default',
                    'options',
                ],
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

            $permission = null;
            if (array_key_exists('permission', $setting)) {
                $permission = is_string($setting['permission'])
                    ? $setting['permission']
                    : '';
                if (!red_addon_valid_permission($permission)) {
                    red_addon_add_error(
                        $result,
                        $context . ' permission is invalid.'
                    );
                } elseif (!in_array(
                    $permission,
                    $declaredPermissions,
                    true
                )) {
                    red_addon_add_error(
                        $result,
                        $context .
                            ' permission must be declared by the package.'
                    );
                }
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
            if (is_string($permission)
                && red_addon_valid_permission($permission)
                && in_array($permission, $declaredPermissions, true)
            ) {
                $definition['permission'] = $permission;
            }
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
        $permissions = red_addon_validate_string_list(
            $manifest['permissions'] ?? [],
            'Permissions',
            200,
            'red_addon_valid_permission',
            $result
        );
        $settings = red_addon_validate_settings(
            $manifest['settings'] ?? null,
            $result,
            $permissions
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

if (!function_exists('red_addon_validate_admin_tool_form_fields')) {
    function red_addon_validate_admin_tool_form_fields(
        $fields,
        $context,
        $depth,
        array &$result,
        int &$fieldCount
    ) {
        if (!is_array($fields) || !array_is_list($fields)) {
            red_addon_add_error($result, $context . ' must be an array.');
            return [];
        }
        $maximumAtLevel = $depth === 0 ? 100 : 32;
        if ($fields === []) {
            red_addon_add_error($result, $context . ' must not be empty.');
        }
        if (count($fields) > $maximumAtLevel) {
            red_addon_add_error(
                $result,
                $context . ' exceeds ' . $maximumAtLevel . ' entries.'
            );
        }

        $normalized = [];
        $seenKeys = [];
        foreach ($fields as $index => $field) {
            $fieldCount++;
            if ($fieldCount > 200) {
                red_addon_add_error(
                    $result,
                    'Administrator tool form schema exceeds 200 total fields.'
                );
            }
            $fieldContext = $context . '[' . $index . ']';
            $fieldType = is_array($field)
                && is_string($field['type'] ?? null)
                    ? $field['type']
                    : '';
            $isCollection = $fieldType === 'collection';
            $requiredKeys = $isCollection
                ? [
                    'key',
                    'label',
                    'type',
                    'required',
                    'itemLabel',
                    'minItems',
                    'maxItems',
                    'fields',
                ]
                : ['key', 'label', 'type', 'required'];
            $allowedKeys = $isCollection
                ? array_merge($requiredKeys, ['help'])
                : [
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
                ];
            if (!red_addon_validate_object_keys(
                $field,
                $requiredKeys,
                $allowedKeys,
                $fieldContext,
                $result
            )) {
                continue;
            }

            $fieldKey = is_string($field['key'] ?? null)
                ? $field['key']
                : '';
            if (!red_addon_valid_component_field_key($fieldKey)) {
                red_addon_add_error($result, $fieldContext . ' key is invalid.');
            } elseif (isset($seenKeys[$fieldKey])) {
                red_addon_add_error(
                    $result,
                    $context . ' repeats field key "' . $fieldKey . '".'
                );
            } else {
                $seenKeys[$fieldKey] = true;
            }
            $label = red_addon_required_string(
                $field,
                'label',
                $fieldContext,
                120,
                $result
            );
            $required = $field['required'] ?? null;
            if (!is_bool($required)) {
                red_addon_add_error(
                    $result,
                    $fieldContext . ' required must be boolean.'
                );
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

            if ($isCollection) {
                if ($depth >= 2) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' exceeds the two-level collection depth.'
                    );
                }
                $itemLabel = red_addon_required_string(
                    $field,
                    'itemLabel',
                    $fieldContext,
                    120,
                    $result
                );
                $minItems = $field['minItems'] ?? null;
                $maxItems = $field['maxItems'] ?? null;
                if (!is_int($minItems)
                    || !is_int($maxItems)
                    || $minItems < 0
                    || $maxItems < 1
                    || $maxItems > 128
                    || $minItems > $maxItems
                    || ($required === true && $minItems < 1)
                ) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' collection bounds are invalid.'
                    );
                }
                $childFields = red_addon_validate_admin_tool_form_fields(
                    $field['fields'] ?? null,
                    $fieldContext . ' fields',
                    $depth + 1,
                    $result,
                    $fieldCount
                );
                $normalizedField = [
                    'key' => $fieldKey,
                    'label' => $label,
                    'type' => 'collection',
                    'required' => is_bool($required) ? $required : false,
                    'itemLabel' => $itemLabel,
                    'minItems' => is_int($minItems) ? $minItems : 0,
                    'maxItems' => is_int($maxItems) ? $maxItems : 0,
                    'fields' => $childFields,
                ];
                if ($help !== null) {
                    $normalizedField['help'] = $help;
                }
                $normalized[] = $normalizedField;
                continue;
            }

            $allowedTypes = [
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
            if (!in_array($fieldType, $allowedTypes, true)) {
                red_addon_add_error(
                    $result,
                    $fieldContext . ' type is unsupported.'
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
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' minLength is invalid.'
                    );
                }
                if (!is_int($maxLength)
                    || $maxLength < 1
                    || $maxLength > $lengthTypes[$fieldType]
                ) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' maxLength is invalid.'
                    );
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

            $normalizedOptions = [];
            if ($fieldType === 'select') {
                $options = $field['options'] ?? null;
                if (!is_array($options) || !array_is_list($options)) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' options must be an array.'
                    );
                } else {
                    if ($options === [] || count($options) > 100) {
                        red_addon_add_error(
                            $result,
                            $fieldContext . ' options count is invalid.'
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
                                $fieldContext . ' repeats option value "'
                                    . $optionValue . '".'
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
                'label' => $label,
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
            $normalized[] = $normalizedField;
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_validate_admin_tool_form_contracts')) {
    function red_addon_validate_admin_tool_form_contracts(
        $contracts,
        array $providedTools,
        array $declaredPermissions,
        array &$result,
        array $declaredSettings = []
    ) {
        if (!is_array($contracts) || !array_is_list($contracts)) {
            red_addon_add_error(
                $result,
                'Administrator tool form contracts must be an array.'
            );
            return [];
        }
        if (count($contracts) > 200) {
            red_addon_add_error(
                $result,
                'Administrator tool form contracts exceeds 200 entries.'
            );
        }

        $normalized = [];
        $seenForms = [];
        foreach ($contracts as $index => $contract) {
            $context = 'Administrator tool form contract[' . $index . ']';
            if (!red_addon_validate_object_keys(
                $contract,
                [
                    'tool',
                    'form',
                    'label',
                    'description',
                    'permission',
                    'method',
                    'csrf',
                    'encoding',
                    'maxBodyBytes',
                ],
                [
                    'tool',
                    'form',
                    'label',
                    'description',
                    'permission',
                    'method',
                    'csrf',
                    'encoding',
                    'maxBodyBytes',
                    'fields',
                    'runtimeSettings',
                    'create',
                ],
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
            }

            $form = is_string($contract['form'] ?? null)
                ? $contract['form']
                : '';
            if (!red_addon_valid_capability($form)) {
                red_addon_add_error($result, $context . ' form is invalid.');
            } elseif (in_array($form, $providedTools, true)) {
                red_addon_add_error(
                    $result,
                    $context . ' form must differ from provided tool identifiers.'
                );
            } elseif (isset($seenForms[$form])) {
                red_addon_add_error(
                    $result,
                    'Administrator tool form contract for "' . $form .
                        '" is duplicated.'
                );
            } else {
                $seenForms[$form] = true;
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
            foreach (
                ['label' => $label, 'description' => $description]
                as $key => $value
            ) {
                if (preg_match('//u', $value) !== 1
                    || preg_match(
                        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                        $value
                    ) === 1
                ) {
                    red_addon_add_error(
                        $result,
                        $context . ' field "' . $key . '" contains unsafe text.'
                    );
                }
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

            $method = is_string($contract['method'] ?? null)
                ? $contract['method']
                : '';
            if ($method !== 'POST') {
                red_addon_add_error(
                    $result,
                    $context . ' method must be POST.'
                );
            }
            $csrf = is_string($contract['csrf'] ?? null)
                ? $contract['csrf']
                : '';
            if ($csrf !== 'required') {
                red_addon_add_error(
                    $result,
                    $context . ' csrf must be required.'
                );
            }
            $encoding = is_string($contract['encoding'] ?? null)
                ? $contract['encoding']
                : '';
            if ($encoding !== 'application/json') {
                red_addon_add_error(
                    $result,
                    $context . ' encoding must be application/json.'
                );
            }
            $maxBodyBytes = $contract['maxBodyBytes'] ?? null;
            if (!is_int($maxBodyBytes)
                || $maxBodyBytes < 1
                || $maxBodyBytes > 262144
            ) {
                red_addon_add_error(
                    $result,
                    $context . ' maxBodyBytes must be between 1 and 262144.'
                );
            }

            $normalizedFields = null;
            if (array_key_exists('fields', $contract)) {
                $fieldCount = 0;
                $normalizedFields =
                    red_addon_validate_admin_tool_form_fields(
                        $contract['fields'],
                        $context . ' fields',
                        0,
                        $result,
                        $fieldCount
                    );
            }

            $normalizedRuntimeSettings = null;
            if (array_key_exists('runtimeSettings', $contract)) {
                $runtimeSettings = red_addon_validate_string_list(
                    $contract['runtimeSettings'],
                    $context . ' runtimeSettings',
                    32,
                    'red_addon_valid_permission',
                    $result
                );
                if ($runtimeSettings === []) {
                    red_addon_add_error(
                        $result,
                        $context . ' runtimeSettings must not be empty.'
                    );
                }

                $settingsByKey = [];
                foreach ($declaredSettings as $setting) {
                    if (is_array($setting)
                        && is_string($setting['key'] ?? null)
                    ) {
                        $settingsByKey[$setting['key']] = $setting;
                    }
                }
                foreach ($runtimeSettings as $settingKey) {
                    $setting = $settingsByKey[$settingKey] ?? null;
                    if (!is_array($setting)) {
                        red_addon_add_error(
                            $result,
                            $context . ' runtime setting "' . $settingKey .
                                '" must be declared by the package.'
                        );
                        continue;
                    }
                    if (($setting['secret'] ?? false) === true
                        || ($setting['type'] ?? '') === 'secret-reference'
                    ) {
                        red_addon_add_error(
                            $result,
                            $context . ' runtime setting "' . $settingKey .
                                '" must be non-secret.'
                        );
                    }
                    if (array_key_exists('default', $setting)
                        && $setting['default'] !== null
                    ) {
                        red_addon_add_error(
                            $result,
                            $context . ' runtime setting "' . $settingKey .
                                '" must not have a non-null default.'
                        );
                    }
                }
                $normalizedRuntimeSettings = $runtimeSettings;
            }

            $normalizedCreate = null;
            if (array_key_exists('create', $contract)) {
                $createContext = $context . ' create';
                if (!is_array($normalizedFields)
                    || $normalizedFields === []
                ) {
                    red_addon_add_error(
                        $result,
                        $createContext . ' requires a non-empty fields schema.'
                    );
                }
                if (red_addon_validate_object_keys(
                    $contract['create'],
                    ['label', 'description'],
                    ['label', 'description'],
                    $createContext,
                    $result
                )) {
                    $createLabel = red_addon_required_string(
                        $contract['create'],
                        'label',
                        $createContext,
                        120,
                        $result
                    );
                    $createDescription = red_addon_required_string(
                        $contract['create'],
                        'description',
                        $createContext,
                        500,
                        $result
                    );
                    foreach (
                        [
                            'label' => $createLabel,
                            'description' => $createDescription,
                        ] as $key => $value
                    ) {
                        if (preg_match('//u', $value) !== 1
                            || preg_match(
                                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                                $value
                            ) === 1
                        ) {
                            red_addon_add_error(
                                $result,
                                $createContext . ' field "' . $key .
                                    '" contains unsafe text.'
                            );
                        }
                    }
                    $normalizedCreate = [
                        'label' => $createLabel,
                        'description' => $createDescription,
                    ];
                }
            }

            $normalizedContract = [
                'tool' => $tool,
                'form' => $form,
                'label' => $label,
                'description' => $description,
                'permission' => $permission,
                'method' => $method,
                'csrf' => $csrf,
                'encoding' => $encoding,
                'maxBodyBytes' => is_int($maxBodyBytes) ? $maxBodyBytes : 0,
            ];
            if (is_array($normalizedFields)) {
                $normalizedContract['fields'] = $normalizedFields;
            }
            if (is_array($normalizedRuntimeSettings)) {
                $normalizedContract['runtimeSettings'] =
                    $normalizedRuntimeSettings;
            }
            if (is_array($normalizedCreate)) {
                $normalizedContract['create'] = $normalizedCreate;
            }
            $normalized[] = $normalizedContract;
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_admin_tool_form_contract')) {
    function red_addon_admin_tool_form_contract(
        array $manifest,
        $toolId,
        $formId
    ) {
        if (!is_string($toolId)
            || !red_addon_valid_capability($toolId)
            || !is_string($formId)
            || !red_addon_valid_capability($formId)
            || !array_key_exists('adminToolFormContracts', $manifest)
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
        $settings = [];
        $hasRuntimeSettings = false;
        foreach ($manifest['adminToolFormContracts'] as $contract) {
            if (is_array($contract)
                && array_key_exists('runtimeSettings', $contract)
            ) {
                $hasRuntimeSettings = true;
                break;
            }
        }
        if ($hasRuntimeSettings) {
            $settings = red_addon_validate_settings(
                $manifest['settings'] ?? null,
                $result,
                $permissions
            );
        }
        $contracts = red_addon_validate_admin_tool_form_contracts(
            $manifest['adminToolFormContracts'] ?? null,
            $tools,
            $permissions,
            $result,
            $settings
        );
        if ($result['errors'] !== []) {
            return null;
        }
        foreach ($contracts as $contract) {
            if (hash_equals($toolId, $contract['tool'])
                && hash_equals($formId, $contract['form'])
            ) {
                return $contract;
            }
        }
        return null;
    }
}

if (!function_exists('red_addon_validate_admin_tool_action_contracts')) {
    function red_addon_validate_admin_tool_action_contracts(
        $contracts,
        array $providedTools,
        array $declaredPermissions,
        array &$result
    ) {
        if (!is_array($contracts) || !array_is_list($contracts)) {
            red_addon_add_error(
                $result,
                'Administrator tool action contracts must be an array.'
            );
            return [];
        }
        if (count($contracts) > 200) {
            red_addon_add_error(
                $result,
                'Administrator tool action contracts exceeds 200 entries.'
            );
        }

        $normalized = [];
        $seenActions = [];
        foreach ($contracts as $index => $contract) {
            $context = 'Administrator tool action contract[' . $index . ']';
            if (!red_addon_validate_object_keys(
                $contract,
                [
                    'tool',
                    'action',
                    'label',
                    'description',
                    'permission',
                    'method',
                    'csrf',
                    'idempotency',
                ],
                [
                    'tool',
                    'action',
                    'label',
                    'description',
                    'permission',
                    'method',
                    'csrf',
                    'idempotency',
                ],
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
            }

            $action = is_string($contract['action'] ?? null)
                ? $contract['action']
                : '';
            if (!red_addon_valid_capability($action)) {
                red_addon_add_error($result, $context . ' action is invalid.');
            } elseif ($action === $tool) {
                red_addon_add_error(
                    $result,
                    $context . ' action must differ from its tool.'
                );
            } elseif (isset($seenActions[$action])) {
                red_addon_add_error(
                    $result,
                    'Administrator tool action contract for "' . $action .
                        '" is duplicated.'
                );
            } else {
                $seenActions[$action] = true;
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

            $method = is_string($contract['method'] ?? null)
                ? $contract['method']
                : '';
            if ($method !== 'POST') {
                red_addon_add_error(
                    $result,
                    $context . ' method must be POST.'
                );
            }
            $csrf = is_string($contract['csrf'] ?? null)
                ? $contract['csrf']
                : '';
            if ($csrf !== 'required') {
                red_addon_add_error(
                    $result,
                    $context . ' csrf must be required.'
                );
            }
            $idempotency = is_string($contract['idempotency'] ?? null)
                ? $contract['idempotency']
                : '';
            if ($idempotency !== 'once-per-target') {
                red_addon_add_error(
                    $result,
                    $context . ' idempotency must be once-per-target.'
                );
            }

            $normalized[] = [
                'tool' => $tool,
                'action' => $action,
                'label' => $label,
                'description' => $description,
                'permission' => $permission,
                'method' => $method,
                'csrf' => $csrf,
                'idempotency' => $idempotency,
            ];
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_admin_tool_action_contract')) {
    function red_addon_admin_tool_action_contract(
        array $manifest,
        $toolId,
        $actionId
    ) {
        if (!is_string($toolId)
            || !red_addon_valid_capability($toolId)
            || !is_string($actionId)
            || !red_addon_valid_capability($actionId)
            || !array_key_exists('adminToolActionContracts', $manifest)
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
        $contracts = red_addon_validate_admin_tool_action_contracts(
            $manifest['adminToolActionContracts'] ?? null,
            $tools,
            $permissions,
            $result
        );
        if ($result['errors'] !== []) {
            return null;
        }
        foreach ($contracts as $contract) {
            if (hash_equals($toolId, $contract['tool'])
                && hash_equals($actionId, $contract['action'])
            ) {
                return $contract;
            }
        }
        return null;
    }
}

if (!function_exists('red_addon_valid_public_mutation_table')) {
    function red_addon_valid_public_mutation_table($value)
    {
        $reserved = [
            'red_addon_installations',
            'red_addon_migrations',
            'red_addon_activity_log',
            'red_addon_component_revisions',
            'red_addon_admin_action_executions',
            'red_addon_settings',
            'red_addon_public_mutation_executions',
            'red_addon_public_mutation_subjects',
            'red_addon_public_mutation_csrf_tokens',
            'red_addon_public_mutation_rate_limits',
            'red_addon_public_mutation_idempotency_keys',
        ];
        return is_string($value)
            && preg_match('/\ARED_Addon_[A-Za-z0-9_]{1,54}\z/', $value) === 1
            && !in_array(strtolower($value), $reserved, true);
    }
}

if (!function_exists('red_addon_valid_public_mutation_audit_category')) {
    function red_addon_valid_public_mutation_audit_category($value)
    {
        return is_string($value)
            && strlen($value) <= 120
            && preg_match('/\A[a-z][a-z0-9.-]*\z/', $value) === 1;
    }
}

if (!function_exists('red_addon_public_mutation_reserved_field_keys')) {
    function red_addon_public_mutation_reserved_field_keys()
    {
        return [
            'cart',
            'cart-id',
            'cart-owner',
            'callback',
            'csrf',
            'csrf-token',
            'currency',
            'database',
            'idempotency',
            'idempotency-key',
            'mutation',
            'order',
            'order-id',
            'order-state',
            'package',
            'payment',
            'permission',
            'plan',
            'price',
            'provider',
            'redirect',
            'return',
            'route',
            'state',
            'subject',
            'table',
            'tables',
            'total',
        ];
    }
}

if (!function_exists('red_addon_validate_public_mutation_request_fields')) {
    function red_addon_validate_public_mutation_request_fields(
        $fields,
        $context,
        array &$result
    ) {
        if (!is_array($fields) || !array_is_list($fields)) {
            red_addon_add_error(
                $result,
                $context . ' requestFields must be an array.'
            );
            return [];
        }
        if ($fields === [] || count($fields) > 16) {
            red_addon_add_error(
                $result,
                $context . ' requestFields must contain one to sixteen fields.'
            );
        }

        $normalized = [];
        $seenKeys = [];
        foreach ($fields as $index => $field) {
            $fieldContext = $context . ' requestField[' . $index . ']';
            if (!red_addon_validate_object_keys(
                $field,
                ['key', 'type', 'required'],
                [
                    'key',
                    'type',
                    'required',
                    'format',
                    'minLength',
                    'maxLength',
                    'minimum',
                    'maximum',
                ],
                $fieldContext,
                $result
            )) {
                continue;
            }

            $key = is_string($field['key'] ?? null) ? $field['key'] : '';
            $fieldValid = true;
            if (!red_addon_valid_component_field_key($key)) {
                red_addon_add_error($result, $fieldContext . ' key is invalid.');
                $fieldValid = false;
            } elseif (in_array(
                $key,
                red_addon_public_mutation_reserved_field_keys(),
                true
            )) {
                red_addon_add_error(
                    $result,
                    $fieldContext . ' key is reserved for core-owned state.'
                );
                $fieldValid = false;
            } elseif (isset($seenKeys[$key])) {
                red_addon_add_error(
                    $result,
                    $context . ' request field key "' . $key . '" is duplicated.'
                );
                $fieldValid = false;
            } else {
                $seenKeys[$key] = true;
            }

            $type = is_string($field['type'] ?? null) ? $field['type'] : '';
            if (!in_array(
                $type,
                ['identifier', 'positive-integer', 'string'],
                true
            )) {
                red_addon_add_error(
                    $result,
                    $fieldContext .
                        ' type must be identifier, positive-integer, or string.'
                );
                continue;
            }
            if (!is_bool($field['required'] ?? null)) {
                red_addon_add_error(
                    $result,
                    $fieldContext . ' required must be boolean.'
                );
                $fieldValid = false;
            }

            $keys = array_keys($field);
            sort($keys, SORT_STRING);
            if ($type === 'identifier') {
                $expectedKeys = [
                    'key',
                    'maxLength',
                    'minLength',
                    'required',
                    'type',
                ];
                if ($keys !== $expectedKeys) {
                    red_addon_add_error(
                        $result,
                        $fieldContext .
                            ' identifier fields require only minLength and maxLength bounds.'
                    );
                    continue;
                }
                $minLength = $field['minLength'] ?? null;
                $maxLength = $field['maxLength'] ?? null;
                if (!is_int($minLength) || $minLength < 1 || $minLength > 160) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' minLength must be an integer from 1 to 160.'
                    );
                    $fieldValid = false;
                }
                if (!is_int($maxLength) || $maxLength < 1 || $maxLength > 160) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' maxLength must be an integer from 1 to 160.'
                    );
                    $fieldValid = false;
                }
                if (is_int($minLength)
                    && is_int($maxLength)
                    && $minLength > $maxLength
                ) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' minLength must not exceed maxLength.'
                    );
                    $fieldValid = false;
                }
                if ($fieldValid) {
                    $normalized[] = [
                        'key' => $key,
                        'type' => $type,
                        'required' => $field['required'],
                        'minLength' => $minLength,
                        'maxLength' => $maxLength,
                    ];
                }
                continue;
            }

            if ($type === 'string') {
                $expectedKeys = [
                    'format',
                    'key',
                    'maxLength',
                    'minLength',
                    'required',
                    'type',
                ];
                if ($keys !== $expectedKeys) {
                    red_addon_add_error(
                        $result,
                        $fieldContext .
                            ' string fields require only format, minLength, and maxLength bounds.'
                    );
                    continue;
                }
                $format = is_string($field['format'] ?? null)
                    ? $field['format']
                    : '';
                if (!in_array(
                    $format,
                    [
                        'plain-text',
                        'email',
                        'telephone',
                        'iso-3166-1-alpha-2-uppercase',
                    ],
                    true
                )) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' format is invalid.'
                    );
                    $fieldValid = false;
                }
                $minLength = $field['minLength'] ?? null;
                $maxLength = $field['maxLength'] ?? null;
                if (!is_int($minLength)
                    || $minLength < 1
                    || $minLength > 2000
                ) {
                    red_addon_add_error(
                        $result,
                        $fieldContext .
                            ' minLength must be an integer from 1 to 2000.'
                    );
                    $fieldValid = false;
                }
                if (!is_int($maxLength)
                    || $maxLength < 1
                    || $maxLength > 2000
                ) {
                    red_addon_add_error(
                        $result,
                        $fieldContext .
                            ' maxLength must be an integer from 1 to 2000.'
                    );
                    $fieldValid = false;
                }
                if (is_int($minLength)
                    && is_int($maxLength)
                    && $minLength > $maxLength
                ) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' minLength must not exceed maxLength.'
                    );
                    $fieldValid = false;
                }
                if ($format === 'email' && $maxLength > 254) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' email maxLength must not exceed 254.'
                    );
                    $fieldValid = false;
                }
                if ($format === 'telephone' && $maxLength > 64) {
                    red_addon_add_error(
                        $result,
                        $fieldContext . ' telephone maxLength must not exceed 64.'
                    );
                    $fieldValid = false;
                }
                if ($format === 'iso-3166-1-alpha-2-uppercase'
                    && ($minLength !== 2 || $maxLength !== 2)
                ) {
                    red_addon_add_error(
                        $result,
                        $fieldContext .
                            ' ISO country-code strings require length 2.'
                    );
                    $fieldValid = false;
                }
                if ($fieldValid) {
                    $normalized[] = [
                        'key' => $key,
                        'type' => $type,
                        'format' => $format,
                        'required' => $field['required'],
                        'minLength' => $minLength,
                        'maxLength' => $maxLength,
                    ];
                }
                continue;
            }

            $expectedKeys = [
                'key',
                'maximum',
                'minimum',
                'required',
                'type',
            ];
            if ($keys !== $expectedKeys) {
                red_addon_add_error(
                    $result,
                    $fieldContext .
                        ' positive-integer fields require only minimum and maximum bounds.'
                );
                continue;
            }
            $minimum = $field['minimum'] ?? null;
            $maximum = $field['maximum'] ?? null;
            if (!is_int($minimum) || $minimum < 1 || $minimum > 2147483647) {
                red_addon_add_error(
                    $result,
                    $fieldContext . ' minimum must be an integer from 1 to 2147483647.'
                );
                $fieldValid = false;
            }
            if (!is_int($maximum) || $maximum < 1 || $maximum > 2147483647) {
                red_addon_add_error(
                    $result,
                    $fieldContext . ' maximum must be an integer from 1 to 2147483647.'
                );
                $fieldValid = false;
            }
            if (is_int($minimum)
                && is_int($maximum)
                && $minimum > $maximum
            ) {
                red_addon_add_error(
                    $result,
                    $fieldContext . ' minimum must not exceed maximum.'
                );
                $fieldValid = false;
            }
            if ($fieldValid) {
                $normalized[] = [
                    'key' => $key,
                    'type' => $type,
                    'required' => $field['required'],
                    'minimum' => $minimum,
                    'maximum' => $maximum,
                ];
            }
        }
        if ($normalized === []) {
            red_addon_add_error(
                $result,
                $context . ' requestFields must contain one valid field.'
            );
        }
        usort(
            $normalized,
            static function (array $left, array $right): int {
                return strcmp($left['key'], $right['key']);
            }
        );
        return $normalized;
    }
}

if (!function_exists('red_addon_validate_public_mutation_tables')) {
    function red_addon_validate_public_mutation_tables(
        $tables,
        $context,
        array &$result
    ) {
        if (!is_array($tables) || !array_is_list($tables)) {
            red_addon_add_error($result, $context . ' tables must be an array.');
            return [];
        }
        if ($tables === [] || count($tables) > 16) {
            red_addon_add_error(
                $result,
                $context . ' tables must contain one to sixteen package tables.'
            );
        }
        $normalized = [];
        foreach ($tables as $index => $table) {
            if (!red_addon_valid_public_mutation_table($table)) {
                red_addon_add_error(
                    $result,
                    $context . ' tables[' . $index . '] is not a package-owned table.'
                );
                continue;
            }
            $tableKey = strtolower($table);
            if (isset($normalized[$tableKey])) {
                red_addon_add_error(
                    $result,
                    $context . ' table "' . $table . '" is duplicated.'
                );
                continue;
            }
            $normalized[$tableKey] = $table;
        }
        $tables = array_values($normalized);
        sort($tables, SORT_STRING);
        if ($tables === []) {
            red_addon_add_error(
                $result,
                $context . ' tables must contain one valid package table.'
            );
        }
        return $tables;
    }
}

if (!function_exists('red_addon_public_mutation_static_route')) {
    function red_addon_public_mutation_static_route($route, $packageId)
    {
        $parts = red_addon_package_parts($packageId);
        $keys = is_array($route) ? array_keys($route) : [];
        sort($keys, SORT_STRING);
        if ($parts === null
            || $keys !== [
                'authentication',
                'csrf',
                'id',
                'methods',
                'path',
                'scope',
            ]
        ) {
            return null;
        }
        $routeId = is_string($route['id'] ?? null) ? $route['id'] : '';
        $path = is_string($route['path'] ?? null) ? $route['path'] : '';
        $publicPrefix = '/addons/' . $parts[0] . '/' . $parts[1];
        if (!red_addon_valid_capability($routeId)
            || ($route['scope'] ?? null) !== 'public'
            || ($route['authentication'] ?? null) !== 'public'
            || ($route['methods'] ?? null) !== ['POST']
            || ($route['csrf'] ?? null) !== 'required'
            || !red_addon_valid_route_path($path)
            || strpbrk($path, '{}') !== false
            || strpos($path, $publicPrefix . '/') !== 0
        ) {
            return null;
        }
        return [
            'id' => $routeId,
            'path' => $path,
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
        ];
    }
}

if (!function_exists('red_addon_validate_public_mutation_contracts')) {
    function red_addon_validate_public_mutation_contracts(
        $contracts,
        $routes,
        $packageId,
        array &$result,
        array $declaredSettings = []
    ) {
        if (!is_array($contracts) || !array_is_list($contracts)) {
            red_addon_add_error(
                $result,
                'Public mutation contracts must be an array.'
            );
            return [];
        }
        if (count($contracts) > 50) {
            red_addon_add_error(
                $result,
                'Public mutation contracts exceeds 50 entries.'
            );
        }
        if (!is_array($routes) || !array_is_list($routes)
            || !red_addon_valid_package_id($packageId)
        ) {
            red_addon_add_error(
                $result,
                'Public mutation contracts require a valid package routes declaration.'
            );
            return [];
        }

        $routeMap = [];
        foreach ($routes as $route) {
            $routeId = is_array($route) && is_string($route['id'] ?? null)
                ? $route['id']
                : '';
            if (!red_addon_valid_capability($routeId)) {
                continue;
            }
            if (isset($routeMap[$routeId])) {
                red_addon_add_error(
                    $result,
                    'Public mutation contracts cannot bind a duplicated route id.'
                );
                continue;
            }
            $routeMap[$routeId] = $route;
        }

        $normalized = [];
        $seenRoutes = [];
        $seenMutations = [];
        foreach ($contracts as $index => $contract) {
            $context = 'Public mutation contract[' . $index . ']';
            if (!red_addon_validate_object_keys(
                $contract,
                [
                    'route',
                    'mutation',
                    'scope',
                    'authentication',
                    'method',
                    'csrf',
                    'encoding',
                    'maxBodyBytes',
                    'requestFields',
                    'subject',
                    'idempotency',
                    'privacy',
                    'rateLimit',
                    'tables',
                    'postcondition',
                    'audit',
                    'outcomes',
                ],
                [
                    'route',
                    'mutation',
                    'scope',
                    'authentication',
                    'method',
                    'csrf',
                    'encoding',
                    'maxBodyBytes',
                    'requestFields',
                    'subject',
                    'idempotency',
                    'privacy',
                    'rateLimit',
                    'tables',
                    'postcondition',
                    'audit',
                    'outcomes',
                    'runtimeSettings',
                ],
                $context,
                $result
            )) {
                continue;
            }

            $routeId = is_string($contract['route'] ?? null)
                ? $contract['route']
                : '';
            $contractValid = true;
            if (!red_addon_valid_capability($routeId)) {
                red_addon_add_error($result, $context . ' route is invalid.');
                $contractValid = false;
            } elseif (strpos($routeId, $packageId . '/') !== 0) {
                red_addon_add_error(
                    $result,
                    $context . ' route must use the package capability namespace.'
                );
                $contractValid = false;
            } elseif (isset($seenRoutes[$routeId])) {
                red_addon_add_error(
                    $result,
                    'Public mutation contract for route "' . $routeId . '" is duplicated.'
                );
                $contractValid = false;
            } else {
                $seenRoutes[$routeId] = true;
            }
            $boundRoute = $routeMap[$routeId] ?? null;
            $normalizedRoute = red_addon_public_mutation_static_route(
                $boundRoute,
                $packageId
            );
            if (!is_array($normalizedRoute)
                || !hash_equals($routeId, $normalizedRoute['id'])
            ) {
                red_addon_add_error(
                    $result,
                    $context .
                        ' route must bind one exact static public POST/CSRF-required route.'
                );
                $contractValid = false;
            }

            $mutation = is_string($contract['mutation'] ?? null)
                ? $contract['mutation']
                : '';
            if (!red_addon_valid_capability($mutation)) {
                red_addon_add_error($result, $context . ' mutation is invalid.');
                $contractValid = false;
            } elseif (strpos($mutation, $packageId . '/') !== 0) {
                red_addon_add_error(
                    $result,
                    $context . ' mutation must use the package capability namespace.'
                );
                $contractValid = false;
            } elseif ($mutation === $routeId) {
                red_addon_add_error(
                    $result,
                    $context . ' mutation must differ from its route.'
                );
                $contractValid = false;
            } elseif (isset($seenMutations[$mutation])) {
                red_addon_add_error(
                    $result,
                    'Public mutation "' . $mutation . '" is duplicated.'
                );
                $contractValid = false;
            } else {
                $seenMutations[$mutation] = true;
            }

            foreach ([
                'scope' => 'public',
                'authentication' => 'public',
                'method' => 'POST',
                'csrf' => 'required',
                'encoding' => 'application/x-www-form-urlencoded',
                'subject' => 'anonymous',
                'idempotency' => 'core-issued-key',
                'privacy' => 'no-store',
                'rateLimit' => 'required',
                'postcondition' => 'server-derived-state',
            ] as $key => $expected) {
                if (($contract[$key] ?? null) !== $expected) {
                    red_addon_add_error(
                        $result,
                        $context . ' ' . $key . ' must be ' . $expected . '.'
                    );
                    $contractValid = false;
                }
            }
            $maxBodyBytes = $contract['maxBodyBytes'] ?? null;
            if (!is_int($maxBodyBytes)
                || $maxBodyBytes < 128
                || $maxBodyBytes > 8192
            ) {
                red_addon_add_error(
                    $result,
                    $context .
                        ' maxBodyBytes must be an integer from 128 to 8192.'
                );
                $contractValid = false;
            }
            $requestFields = red_addon_validate_public_mutation_request_fields(
                $contract['requestFields'] ?? null,
                $context,
                $result
            );
            if ($requestFields === []) {
                $contractValid = false;
            }
            $tables = red_addon_validate_public_mutation_tables(
                $contract['tables'] ?? null,
                $context,
                $result
            );
            if ($tables === []) {
                $contractValid = false;
            }
            $audit = is_string($contract['audit'] ?? null) ? $contract['audit'] : '';
            if (!red_addon_valid_public_mutation_audit_category($audit)) {
                red_addon_add_error(
                    $result,
                    $context . ' audit must be a bounded value-free category.'
                );
                $contractValid = false;
            }
            if (($contract['outcomes'] ?? null) !== ['accepted', 'unchanged']) {
                red_addon_add_error(
                    $result,
                    $context . ' outcomes must be exactly accepted then unchanged.'
                );
                $contractValid = false;
            }

            $runtimeSettings = null;
            if (array_key_exists('runtimeSettings', $contract)) {
                $runtimeSettings = red_addon_validate_string_list(
                    $contract['runtimeSettings'],
                    $context . ' runtimeSettings',
                    16,
                    'red_addon_valid_permission',
                    $result
                );
                if ($runtimeSettings === []) {
                    red_addon_add_error(
                        $result,
                        $context . ' runtimeSettings must not be empty.'
                    );
                    $contractValid = false;
                }
                $settingsByKey = [];
                foreach ($declaredSettings as $setting) {
                    if (is_array($setting)
                        && is_string($setting['key'] ?? null)
                    ) {
                        $settingsByKey[$setting['key']] = $setting;
                    }
                }
                foreach ($runtimeSettings as $settingKey) {
                    $setting = $settingsByKey[$settingKey] ?? null;
                    if (!is_array($setting)) {
                        red_addon_add_error(
                            $result,
                            $context . ' runtime setting "' . $settingKey .
                                '" must be declared by the package.'
                        );
                        $contractValid = false;
                        continue;
                    }
                    if (($setting['secret'] ?? false) === true
                        || ($setting['type'] ?? '') === 'secret-reference'
                    ) {
                        red_addon_add_error(
                            $result,
                            $context . ' runtime setting "' . $settingKey .
                                '" must be non-secret.'
                        );
                        $contractValid = false;
                    }
                    if (array_key_exists('default', $setting)
                        && $setting['default'] !== null
                    ) {
                        red_addon_add_error(
                            $result,
                            $context . ' runtime setting "' . $settingKey .
                                '" must not have a non-null default.'
                        );
                        $contractValid = false;
                    }
                }
            }

            if ($contractValid) {
                $normalized[] = [
                    'route' => $routeId,
                    'mutation' => $mutation,
                    'path' => $normalizedRoute['path'],
                    'scope' => 'public',
                    'authentication' => 'public',
                    'method' => 'POST',
                    'csrf' => 'required',
                    'encoding' => 'application/x-www-form-urlencoded',
                    'maxBodyBytes' => $maxBodyBytes,
                    'requestFields' => $requestFields,
                    'subject' => 'anonymous',
                    'idempotency' => 'core-issued-key',
                    'privacy' => 'no-store',
                    'rateLimit' => 'required',
                    'tables' => $tables,
                    'postcondition' => 'server-derived-state',
                    'audit' => $audit,
                    'outcomes' => ['accepted', 'unchanged'],
                ];
                if (is_array($runtimeSettings)) {
                    $normalized[array_key_last($normalized)]['runtimeSettings'] =
                        $runtimeSettings;
                }
            }
        }
        usort(
            $normalized,
            static function (array $left, array $right): int {
                return strcmp(
                    $left['route'] . "\0" . $left['mutation'],
                    $right['route'] . "\0" . $right['mutation']
                );
            }
        );
        return $normalized;
    }
}

if (!function_exists('red_addon_public_mutation_contract')) {
    function red_addon_public_mutation_contract(
        array $manifest,
        $routeId,
        $mutationId
    ) {
        if (!is_string($routeId)
            || !red_addon_valid_capability($routeId)
            || !is_string($mutationId)
            || !red_addon_valid_capability($mutationId)
            || !array_key_exists('publicMutationContracts', $manifest)
        ) {
            return null;
        }
        $result = ['errors' => [], 'warnings' => []];
        $declaredSettings = [];
        $hasRuntimeSettings = false;
        foreach ($manifest['publicMutationContracts'] as $contract) {
            if (is_array($contract)
                && array_key_exists('runtimeSettings', $contract)
            ) {
                $hasRuntimeSettings = true;
                break;
            }
        }
        if ($hasRuntimeSettings) {
            $declaredPermissions = red_addon_validate_string_list(
                $manifest['permissions'] ?? null,
                'Permissions',
                200,
                'red_addon_valid_permission',
                $result
            );
            $declaredSettings = red_addon_validate_settings(
                $manifest['settings'] ?? null,
                $result,
                $declaredPermissions
            );
        }
        $contracts = red_addon_validate_public_mutation_contracts(
            $manifest['publicMutationContracts'] ?? null,
            $manifest['routes'] ?? null,
            $manifest['id'] ?? null,
            $result,
            $declaredSettings
        );
        if ($result['errors'] !== []) {
            return null;
        }
        foreach ($contracts as $contract) {
            if (hash_equals($routeId, $contract['route'])
                && hash_equals($mutationId, $contract['mutation'])
            ) {
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
                [
                    '$schema',
                    'componentEditors',
                    'adminToolContracts',
                    'adminToolFormContracts',
                    'adminToolActionContracts',
                    'publicMutationContracts',
                ],
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
                defined('RED_CMS_VERSION') ? (string) RED_CMS_VERSION : '5.1.0'
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

        if (array_key_exists('adminToolActionContracts', $manifest)) {
            red_addon_validate_admin_tool_action_contracts(
                $manifest['adminToolActionContracts'],
                $providedCapabilities['adminTools'],
                $declaredPermissions,
                $result
            );
        }

        $declaredSettings = red_addon_validate_settings(
            $manifest['settings'] ?? null,
            $result,
            $declaredPermissions
        );

        if (array_key_exists('adminToolFormContracts', $manifest)) {
            red_addon_validate_admin_tool_form_contracts(
                $manifest['adminToolFormContracts'],
                $providedCapabilities['adminTools'],
                $declaredPermissions,
                $result,
                $declaredSettings
            );
        }

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
                if (!in_array(
                    $authentication,
                    ['public', 'admin', 'member', 'server-signature'],
                    true
                )) {
                    red_addon_add_error($result, $routeContext . ' authentication is invalid.');
                }
                if ($scope === 'admin' && $authentication !== 'admin') {
                    red_addon_add_error($result, $routeContext . ' administrator routes require admin authentication.');
                }
                $csrf = isset($route['csrf']) && is_string($route['csrf']) ? $route['csrf'] : '';
                if (!in_array($csrf, ['required', 'not-applicable'], true)) {
                    red_addon_add_error($result, $routeContext . ' csrf policy is invalid.');
                }
                $serverSignatureRoute = $scope === 'public'
                    && $authentication === 'server-signature'
                    && $methods === ['POST']
                    && $csrf === 'not-applicable';
                if ($authentication === 'server-signature'
                    && !$serverSignatureRoute
                ) {
                    red_addon_add_error(
                        $result,
                        $routeContext .
                            ' server-signature routes require one public POST with CSRF not-applicable.'
                    );
                }
                if (array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE'])
                    && $csrf !== 'required'
                    && !$serverSignatureRoute
                ) {
                    red_addon_add_error($result, $routeContext . ' unsafe methods require CSRF protection.');
                }
            }
        }

        if (array_key_exists('publicMutationContracts', $manifest)) {
            red_addon_validate_public_mutation_contracts(
                $manifest['publicMutationContracts'],
                $routes,
                $packageId,
                $result,
                $declaredSettings
            );
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
