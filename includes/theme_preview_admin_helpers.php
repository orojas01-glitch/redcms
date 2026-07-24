<?php
/**
 * Fixed administrator-session boundary for isolated allowlisted theme previews.
 *
 * The state is server-side, short-lived, bound to one authenticated session and
 * administrator record, and never participates in live theme resolution.
 */

require_once __DIR__ . '/theme_helpers.php';

if (!function_exists('red_theme_admin_preview_inventory_scope')) {
    function red_theme_admin_preview_inventory_scope()
    {
        return [
            'liveCompatibilityThemeId' => 'legacy-bootstrap',
            'previewThemeIds' => ['starter-reference', 'adriana-granobles'],
            'previewModes' => [
                'starter-reference' => ['contact', 'home'],
                'adriana-granobles' => ['home'],
            ],
            'databaseReads' => 0,
            'databaseWrites' => 0,
            'selectionWrites' => 0,
            'settingWrites' => 0,
            'standardRuntimeExecution' => false,
        ];
    }
}

if (!function_exists('red_theme_admin_preview_inventory_from_discovery')) {
    function red_theme_admin_preview_inventory_from_discovery(array $discovery)
    {
        $inventory = [];
        foreach ($discovery as $themeId => $result) {
            if (!is_string($themeId)
                || !red_theme_valid_id($themeId)
                || !is_array($result)
                || ($result['valid'] ?? false) !== true
                || !is_array($result['manifest'] ?? null)
            ) {
                continue;
            }

            $manifest = $result['manifest'];
            $manifestId = $manifest['id'] ?? null;
            $name = $manifest['name'] ?? null;
            $description = $manifest['description'] ?? null;
            $version = $manifest['version'] ?? null;
            $type = $manifest['type'] ?? null;
            if ($manifestId !== $themeId
                || !is_string($name)
                || trim($name) === ''
                || !is_string($description)
                || trim($description) === ''
                || !is_string($version)
                || !is_string($type)
                || !in_array($type, ['legacy-adapter', 'standard'], true)
            ) {
                continue;
            }

            $isLiveCompatibility = $themeId === 'legacy-bootstrap' && $type === 'legacy-adapter';
            $previewModes = [];
            if ($type === 'standard' && $themeId === 'starter-reference') {
                $previewModes = ['Contact canary', 'Home route'];
            } elseif ($type === 'standard' && $themeId === 'adriana-granobles') {
                $previewModes = ['Home route'];
            }
            $previewAvailable = $previewModes !== [];
            $productionValidation = $type === 'standard'
                ? red_theme_standard_production_validation($manifest, $result['path'] ?? '')
                : ['valid' => $isLiveCompatibility];
            $layoutCatalog = red_theme_layout_manifest_catalog($manifest);
            $layoutSummary = [];
            foreach ($layoutCatalog as $layoutId => $layoutDefinition) {
                $layoutSummary[] = [
                    'id' => $layoutId,
                    'label' => $layoutDefinition['label'],
                    'positions' => count($layoutDefinition['positions']),
                ];
            }
            $inventory[] = [
                'themeId' => $themeId,
                'name' => trim($name),
                'description' => trim($description),
                'version' => trim($version),
                'type' => $type,
                'typeLabel' => $type === 'legacy-adapter' ? 'Compatibility adapter' : 'Portable standard',
                'isLiveCompatibility' => $isLiveCompatibility,
                'productionSupported' => !empty($productionValidation['valid']),
                'layoutCount' => count($layoutSummary),
                'layouts' => $layoutSummary,
                'previewAvailable' => $previewAvailable,
                'previewModes' => $previewModes,
            ];
        }

        usort(
            $inventory,
            function (array $left, array $right) {
                if ($left['isLiveCompatibility'] !== $right['isLiveCompatibility']) {
                    return $left['isLiveCompatibility'] ? -1 : 1;
                }
                return strcmp($left['themeId'], $right['themeId']);
            }
        );

        return $inventory;
    }
}

if (!function_exists('red_theme_admin_preview_inventory')) {
    function red_theme_admin_preview_inventory($projectRoot = null)
    {
        return red_theme_admin_preview_inventory_from_discovery(
            red_theme_discover($projectRoot)
        );
    }
}

if (!function_exists('red_theme_admin_preview_mode')) {
    function red_theme_admin_preview_mode($mode)
    {
        if (!is_string($mode) || !in_array($mode, ['contact', 'home', 'adriana-home'], true)) {
            throw new InvalidArgumentException('Theme preview mode is outside the fixed allowlist.');
        }

        return $mode;
    }
}

if (!function_exists('red_theme_admin_preview_theme_for_mode')) {
    function red_theme_admin_preview_theme_for_mode($mode)
    {
        $mode = red_theme_admin_preview_mode($mode);
        return $mode === 'adriana-home' ? 'adriana-granobles' : 'starter-reference';
    }
}

if (!function_exists('red_theme_admin_preview_can_launch')) {
    function red_theme_admin_preview_can_launch(array $inventory, $mode = 'contact')
    {
        $mode = red_theme_admin_preview_mode($mode);
        $expectedThemeId = red_theme_admin_preview_theme_for_mode($mode);
        $expectedLabel = $mode === 'contact' ? 'Contact canary' : 'Home route';
        $expectedModes = $expectedThemeId === 'starter-reference'
            ? ['Contact canary', 'Home route']
            : ['Home route'];
        foreach ($inventory as $theme) {
            if (is_array($theme)
                && ($theme['themeId'] ?? null) === $expectedThemeId
                && ($theme['type'] ?? null) === 'standard'
                && ($theme['previewAvailable'] ?? false) === true
                && ($theme['previewModes'] ?? null) === $expectedModes
                && in_array($expectedLabel, $theme['previewModes'], true)
            ) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('red_theme_admin_preview_state_key')) {
    function red_theme_admin_preview_state_key()
    {
        return 'red_theme_admin_preview';
    }
}

if (!function_exists('red_theme_admin_preview_ttl')) {
    function red_theme_admin_preview_ttl()
    {
        return 900;
    }
}

if (!function_exists('red_theme_admin_preview_session_binding')) {
    function red_theme_admin_preview_session_binding($sessionId)
    {
        if (!is_string($sessionId) || $sessionId === '' || strlen($sessionId) > 256) {
            throw new InvalidArgumentException('Theme preview requires an active bounded session id.');
        }

        return hash('sha256', $sessionId);
    }
}

if (!function_exists('red_theme_admin_preview_request_action')) {
    function red_theme_admin_preview_request_action(array $request)
    {
        $allowedKeys = ['action' => true, 'csrf_token' => true];
        foreach ($request as $key => $value) {
            if (!is_string($key) || !isset($allowedKeys[$key]) || is_array($value)) {
                throw new InvalidArgumentException('Theme preview action contains an unexpected input.');
            }
        }
        if (!isset($request['action']) || !is_string($request['action'])) {
            throw new InvalidArgumentException('Theme preview action is required.');
        }
        $action = trim($request['action']);
        if (!in_array($action, ['start', 'start-home', 'start-adriana-home', 'exit'], true)) {
            throw new InvalidArgumentException('Theme preview action is invalid.');
        }

        return $action;
    }
}

if (!function_exists('red_theme_admin_preview_query')) {
    function red_theme_admin_preview_query(array $query)
    {
        if ($query === []) {
            return ['view' => 'shell', 'status' => ''];
        }
        if (array_keys($query) === ['view'] && $query['view'] === 'contact') {
            return ['view' => 'contact', 'status' => ''];
        }
        if (array_keys($query) === ['view'] && $query['view'] === 'home') {
            return ['view' => 'home', 'status' => ''];
        }
        if (array_keys($query) === ['view'] && $query['view'] === 'adriana-home') {
            return ['view' => 'adriana-home', 'status' => ''];
        }
        if (array_keys($query) === ['status'] && $query['status'] === 'exited') {
            return ['view' => 'shell', 'status' => 'exited'];
        }
        if (array_keys($query) === ['status']
            && in_array($query['status'], ['activated', 'rolled-back', 'unchanged'], true)
        ) {
            return ['view' => 'shell', 'status' => $query['status']];
        }

        throw new InvalidArgumentException('Theme preview query is invalid.');
    }
}

if (!function_exists('red_theme_admin_preview_nonce')) {
    function red_theme_admin_preview_nonce($nonce = null)
    {
        if ($nonce === null) {
            $nonce = bin2hex(random_bytes(32));
        }
        if (!is_string($nonce) || preg_match('/\A[a-f0-9]{64}\z/', $nonce) !== 1) {
            throw new InvalidArgumentException('Theme preview nonce is invalid.');
        }

        return $nonce;
    }
}

if (!function_exists('red_theme_admin_preview_validate_state')) {
    function red_theme_admin_preview_validate_state(
        $state,
        $adminRecordId,
        $sessionBinding,
        $now = null
    ) {
        $expectedKeys = [
            'schemaVersion',
            'themeId',
            'mode',
            'rollbackThemeId',
            'adminRecordId',
            'sessionBinding',
            'issuedAt',
            'expiresAt',
            'nonce',
        ];
        if (!is_array($state) || array_keys($state) !== $expectedKeys) {
            throw new InvalidArgumentException('Theme preview state has an invalid shape.');
        }
        $adminRecordId = (int) $adminRecordId;
        if ($adminRecordId <= 0
            || !is_string($sessionBinding)
            || preg_match('/\A[a-f0-9]{64}\z/', $sessionBinding) !== 1
        ) {
            throw new InvalidArgumentException('Theme preview identity boundary is invalid.');
        }
        if ($now === null) {
            $now = time();
        }
        if (!is_int($now) || $now <= 0) {
            throw new InvalidArgumentException('Theme preview validation time is invalid.');
        }
        $expectedThemeId = null;
        try {
            $expectedThemeId = red_theme_admin_preview_theme_for_mode($state['mode'] ?? null);
        } catch (Throwable $exception) {
            $expectedThemeId = null;
        }
        if ($state['schemaVersion'] !== 1
            || $state['themeId'] !== $expectedThemeId
            || !is_string($state['mode'])
            || $state['rollbackThemeId'] !== 'legacy-bootstrap'
            || !is_int($state['adminRecordId'])
            || $state['adminRecordId'] !== $adminRecordId
            || !is_string($state['sessionBinding'])
            || !hash_equals($sessionBinding, $state['sessionBinding'])
            || !is_int($state['issuedAt'])
            || !is_int($state['expiresAt'])
            || $state['issuedAt'] <= 0
            || $state['expiresAt'] !== $state['issuedAt'] + red_theme_admin_preview_ttl()
            || $now < $state['issuedAt']
            || $now >= $state['expiresAt']
        ) {
            throw new InvalidArgumentException('Theme preview state failed its fixed identity or expiry boundary.');
        }
        red_theme_admin_preview_nonce($state['nonce']);

        return $state;
    }
}

if (!function_exists('red_theme_admin_preview_start')) {
    function red_theme_admin_preview_start(
        array &$session,
        $adminRecordId,
        $sessionBinding,
        $issuedAt = null,
        $nonce = null,
        $mode = 'contact'
    ) {
        $adminRecordId = (int) $adminRecordId;
        if ($adminRecordId <= 0
            || !is_string($sessionBinding)
            || preg_match('/\A[a-f0-9]{64}\z/', $sessionBinding) !== 1
        ) {
            throw new InvalidArgumentException('Theme preview requires a fixed administrator session identity.');
        }
        if ($issuedAt === null) {
            $issuedAt = time();
        }
        if (!is_int($issuedAt) || $issuedAt <= 0) {
            throw new InvalidArgumentException('Theme preview issue time is invalid.');
        }
        $mode = red_theme_admin_preview_mode($mode);
        $state = [
            'schemaVersion' => 1,
            'themeId' => red_theme_admin_preview_theme_for_mode($mode),
            'mode' => $mode,
            'rollbackThemeId' => 'legacy-bootstrap',
            'adminRecordId' => $adminRecordId,
            'sessionBinding' => $sessionBinding,
            'issuedAt' => $issuedAt,
            'expiresAt' => $issuedAt + red_theme_admin_preview_ttl(),
            'nonce' => red_theme_admin_preview_nonce($nonce),
        ];
        red_theme_admin_preview_validate_state($state, $adminRecordId, $sessionBinding, $issuedAt);
        $session[red_theme_admin_preview_state_key()] = $state;

        return $state;
    }
}

if (!function_exists('red_theme_admin_preview_state')) {
    function red_theme_admin_preview_state(
        array &$session,
        $adminRecordId,
        $sessionBinding,
        $now = null
    ) {
        $key = red_theme_admin_preview_state_key();
        if (!array_key_exists($key, $session)) {
            return null;
        }
        try {
            return red_theme_admin_preview_validate_state(
                $session[$key],
                $adminRecordId,
                $sessionBinding,
                $now
            );
        } catch (Throwable $exception) {
            unset($session[$key]);
            return null;
        }
    }
}

if (!function_exists('red_theme_admin_preview_exit')) {
    function red_theme_admin_preview_exit(array &$session)
    {
        $key = red_theme_admin_preview_state_key();
        $existed = array_key_exists($key, $session);
        unset($session[$key]);

        return $existed;
    }
}
