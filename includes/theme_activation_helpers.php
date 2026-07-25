<?php
/**
 * Persistent active-theme state and atomic Webmaster activation helpers.
 *
 * Theme packages remain file based. RED_Advanced stores only two global,
 * system-owned ids: the active theme and the immediately previous theme.
 */

require_once __DIR__ . '/theme_helpers.php';
require_once __DIR__ . '/theme_compatibility_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';

if (!function_exists('red_theme_activation_items')) {
    function red_theme_activation_items()
    {
        return [
            'active' => 'System_Active_Theme',
            'previous' => 'System_Previous_Theme',
        ];
    }
}

if (!function_exists('red_theme_activation_default_state')) {
    function red_theme_activation_default_state()
    {
        return [
            'activeThemeId' => 'legacy-bootstrap',
            'previousThemeId' => 'legacy-bootstrap',
            'activeRecordId' => 0,
            'previousRecordId' => 0,
            'persisted' => false,
        ];
    }
}

if (!function_exists('red_theme_activation_state_from_rows')) {
    function red_theme_activation_state_from_rows(array $rows, $allowMissing = false)
    {
        if ($rows === [] && $allowMissing) {
            return red_theme_activation_default_state();
        }

        $items = red_theme_activation_items();
        $byItem = [];
        foreach ($rows as $row) {
            if (!is_array($row)
                || array_keys($row) !== ['RecordID', 'Item', 'Content', 'Language']
                || (string) $row['Language'] !== ''
                || !in_array((string) $row['Item'], array_values($items), true)
            ) {
                throw new InvalidArgumentException('Theme activation state row is invalid.');
            }

            $item = (string) $row['Item'];
            $recordId = (int) $row['RecordID'];
            $themeId = trim((string) $row['Content']);
            if (isset($byItem[$item]) || $recordId <= 0 || !red_theme_valid_id($themeId)) {
                throw new InvalidArgumentException('Theme activation state is duplicated or malformed.');
            }
            $byItem[$item] = ['recordId' => $recordId, 'themeId' => $themeId];
        }

        if (array_keys($byItem) !== [$items['active'], $items['previous']]
            && array_keys($byItem) !== [$items['previous'], $items['active']]
        ) {
            throw new InvalidArgumentException('Theme activation state requires exactly two system rows.');
        }

        return [
            'activeThemeId' => $byItem[$items['active']]['themeId'],
            'previousThemeId' => $byItem[$items['previous']]['themeId'],
            'activeRecordId' => $byItem[$items['active']]['recordId'],
            'previousRecordId' => $byItem[$items['previous']]['recordId'],
            'persisted' => true,
        ];
    }
}

if (!function_exists('red_theme_activation_read_state')) {
    function red_theme_activation_read_state($connection, $forUpdate = false, $allowMissing = false)
    {
        if (!($connection instanceof mysqli)) {
            throw new InvalidArgumentException('Theme activation state requires a mysqli connection.');
        }

        $sql = "SELECT RecordID, Item, Content, Language\n" .
            "FROM RED_Advanced\n" .
            "WHERE Language='' AND Item IN ('System_Active_Theme', 'System_Previous_Theme')\n" .
            'ORDER BY Item, RecordID' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt || !mysqli_stmt_execute($stmt)) {
            if ($stmt) {
                mysqli_stmt_close($stmt);
            }
            throw new RuntimeException('Theme activation state could not be read.');
        }

        $result = mysqli_stmt_get_result($stmt);
        $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);

        return red_theme_activation_state_from_rows($rows, $allowMissing);
    }
}

if (!function_exists('red_theme_activation_config_value')) {
    function red_theme_activation_config_value(array $localConfig, $key, array $environmentKeys, $default = '')
    {
        foreach ($environmentKeys as $environmentKey) {
            $value = getenv($environmentKey);
            if ($value !== false && $value !== '') {
                return $value;
            }
        }

        return array_key_exists($key, $localConfig) ? $localConfig[$key] : $default;
    }
}

if (!function_exists('red_theme_activation_open_connection')) {
    function red_theme_activation_open_connection($projectRoot = null)
    {
        if (!extension_loaded('mysqli')) {
            return null;
        }

        $projectRoot = red_theme_project_root($projectRoot);
        $localConfig = [];
        $localConfigFile = $projectRoot . '/includes/config.local.php';
        if (is_file($localConfigFile)) {
            $loaded = require $localConfigFile;
            if (is_array($loaded)) {
                $localConfig = $loaded;
            }
        }

        $host = (string) red_theme_activation_config_value(
            $localConfig,
            'DBHOST',
            ['RED_DB_HOST', 'DBHOST'],
            'localhost'
        );
        $port = (int) red_theme_activation_config_value(
            $localConfig,
            'DBPORT',
            ['RED_DB_PORT', 'DBPORT'],
            3306
        );
        if (substr_count($host, ':') === 1 && preg_match('/\A(.+):(\d+)\z/', $host, $matches) === 1) {
            $host = $matches[1];
            $port = (int) $matches[2];
        }

        $connection = mysqli_init();
        if ($connection === false
            || !@mysqli_real_connect(
                $connection,
                $host,
                (string) red_theme_activation_config_value($localConfig, 'DBUSER', ['RED_DB_USER', 'DBUSER']),
                (string) red_theme_activation_config_value($localConfig, 'DBPASS', ['RED_DB_PASS', 'DBPASS']),
                (string) red_theme_activation_config_value($localConfig, 'DBNAME', ['RED_DB_NAME', 'DBNAME']),
                $port
            )
            || !mysqli_set_charset($connection, 'utf8mb4')
        ) {
            if ($connection instanceof mysqli) {
                mysqli_close($connection);
            }
            return null;
        }

        return $connection;
    }
}

if (!function_exists('red_theme_activation_active_id_from_project')) {
    function red_theme_activation_active_id_from_project($projectRoot = null)
    {
        $connection = red_theme_activation_open_connection($projectRoot);
        if (!($connection instanceof mysqli)) {
            error_log('RED-CMS active-theme state unavailable; using legacy-bootstrap.');
            return 'legacy-bootstrap';
        }

        try {
            $state = red_theme_activation_read_state($connection, false, true);
            return $state['persisted'] ? $state['activeThemeId'] : 'legacy-bootstrap';
        } catch (Throwable $exception) {
            error_log('RED-CMS active-theme state invalid; using legacy-bootstrap: ' . $exception->getMessage());
            return 'legacy-bootstrap';
        } finally {
            mysqli_close($connection);
        }
    }
}

if (!function_exists('red_theme_activation_validate_candidate')) {
    function red_theme_activation_validate_candidate($themeId, $projectRoot = null)
    {
        $themeId = is_string($themeId) ? trim($themeId) : '';
        if (!red_theme_valid_id($themeId)) {
            throw new InvalidArgumentException('Theme activation candidate id is invalid.');
        }

        $validation = red_theme_validate_manifest($themeId, $projectRoot);
        if (empty($validation['valid']) || !is_array($validation['manifest'] ?? null)) {
            throw new InvalidArgumentException('Theme activation candidate does not pass manifest validation.');
        }

        $type = (string) ($validation['manifest']['type'] ?? '');
        if ($type !== 'standard' && !($type === 'legacy-adapter' && $themeId === 'legacy-bootstrap')) {
            throw new InvalidArgumentException('Theme activation candidate is not production supported.');
        }
        if ($type === 'standard') {
            $production = red_theme_standard_production_validation(
                $validation['manifest'],
                $validation['path']
            );
            if (empty($production['valid'])) {
                throw new InvalidArgumentException(
                    'Theme activation candidate has no valid production contract: ' .
                    implode(' ', $production['errors'])
                );
            }
            $validation['production'] = $production;
        }

        return $validation;
    }
}

if (!function_exists('red_theme_activation_request')) {
    function red_theme_activation_request(array $post)
    {
        $allowed = ['action' => true, 'csrf_token' => true, 'theme_id' => true];
        foreach ($post as $key => $value) {
            if (!isset($allowed[$key]) || is_array($value)) {
                throw new InvalidArgumentException('Theme activation request contains unexpected input.');
            }
        }

        $action = isset($post['action']) && is_string($post['action']) ? $post['action'] : '';
        if (!in_array($action, ['activate', 'rollback'], true)) {
            throw new InvalidArgumentException('Theme activation action is invalid.');
        }

        $themeId = isset($post['theme_id']) && is_string($post['theme_id'])
            ? trim($post['theme_id'])
            : '';
        if ($action === 'activate') {
            if (!red_theme_valid_id($themeId)) {
                throw new InvalidArgumentException('Theme activation candidate id is required.');
            }
        } elseif ($themeId !== '' || array_key_exists('theme_id', $post)) {
            throw new InvalidArgumentException('Theme rollback does not accept a caller-selected target.');
        }

        return ['action' => $action, 'themeId' => $themeId];
    }
}

if (!function_exists('red_theme_activation_compatibility_preflight')) {
    function red_theme_activation_compatibility_preflight($themeId, $connection, $projectRoot = null)
    {
        $report = red_theme_compatibility_live_preflight($themeId, $connection, $projectRoot);

        // legacy-bootstrap is the audited recovery renderer, not a portable
        // standard theme. It remains a valid activation/rollback target when
        // its manifest and current id coverage are complete.
        if ((string) $themeId === 'legacy-bootstrap') {
            $legacyCompatible = !empty($report['checks']['manifestValid'])
                && !empty($report['checks']['assignedLayoutsCovered'])
                && !empty($report['checks']['assignedLayoutPositionsCovered'])
                && !empty($report['checks']['assignedComponentsCovered']);
            $report['activationCompatible'] = $legacyCompatible;
            if ($legacyCompatible) {
                $report['blockingReasons'] = [];
            }
        } else {
            $report['activationCompatible'] = !empty($report['compatible']);
        }

        return $report;
    }
}

if (!function_exists('red_theme_activation_update_row')) {
    function red_theme_activation_update_row($connection, $recordId, $item, $themeId)
    {
        $stmt = mysqli_prepare(
            $connection,
            "UPDATE RED_Advanced SET Content=? WHERE RecordID=? AND Item=? AND Language=''"
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'sis', $themeId, $recordId, $item);
        $updated = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $updated;
    }
}

if (!function_exists('red_theme_activation_apply_unlocked')) {
    function red_theme_activation_apply_unlocked($connection, $action, $themeId = '', $projectRoot = null)
    {
        if (!($connection instanceof mysqli) || !in_array($action, ['activate', 'rollback'], true)) {
            throw new InvalidArgumentException('Theme activation transition is invalid.');
        }

        $before = red_theme_activation_read_state($connection);
        $target = $action === 'activate' ? (string) $themeId : $before['previousThemeId'];
        red_theme_activation_validate_candidate($target, $projectRoot);
        $compatibility = red_theme_activation_compatibility_preflight(
            $target,
            $connection,
            $projectRoot
        );
        if (empty($compatibility['activationCompatible'])) {
            $missingLayouts = $compatibility['coverage']['missingLayouts'] ?? [];
            $missingLayoutPositions = $compatibility['coverage']['missingLayoutPositions'] ?? [];
            $missingComponents = $compatibility['coverage']['missingComponents'] ?? [];
            $details = [];
            if ($missingLayouts !== []) {
                $details[] = 'missing layout ids: ' . implode(', ', $missingLayouts);
            }
            if ($missingLayoutPositions !== []) {
                $missingPositionLabels = [];
                foreach ($missingLayoutPositions as $missingPosition) {
                    if (!is_array($missingPosition)) {
                        continue;
                    }
                    $assignedLayoutId = (string) ($missingPosition['layoutId'] ?? '');
                    $resolvedLayoutId = (string) ($missingPosition['resolvedLayoutId'] ?? '');
                    $positionId = (int) ($missingPosition['positionId'] ?? 0);
                    if ($assignedLayoutId === '' || $resolvedLayoutId === '' || $positionId < 1) {
                        continue;
                    }
                    $missingPositionLabels[] = $assignedLayoutId . ':' . $positionId .
                        ($assignedLayoutId === $resolvedLayoutId ? '' : '->' . $resolvedLayoutId);
                }
                if ($missingPositionLabels !== []) {
                    $details[] = 'missing layout positions: ' . implode(', ', $missingPositionLabels);
                }
            }
            if ($missingComponents !== []) {
                $details[] = 'missing component views: ' . implode(', ', $missingComponents);
            }
            if ($details === []) {
                $details = array_values($compatibility['blockingReasons'] ?? []);
            }
            throw new InvalidArgumentException(
                'Theme activation target is incompatible with current content' .
                ($details === [] ? '.' : ' (' . implode('; ', $details) . ').')
            );
        }
        $status = 'error';

        $committed = red_admin_write_transaction($connection, function () use (
            $connection,
            $before,
            $target,
            &$status
        ) {
            $locked = red_theme_activation_read_state($connection, true);
            if ($locked['activeThemeId'] !== $before['activeThemeId']
                || $locked['previousThemeId'] !== $before['previousThemeId']
                || $locked['activeRecordId'] !== $before['activeRecordId']
                || $locked['previousRecordId'] !== $before['previousRecordId']
            ) {
                return false;
            }

            if ($target === $locked['activeThemeId']) {
                $status = 'unchanged';
                return true;
            }

            $items = red_theme_activation_items();
            if (!red_theme_activation_update_row(
                $connection,
                $locked['previousRecordId'],
                $items['previous'],
                $locked['activeThemeId']
            ) || !red_theme_activation_update_row(
                $connection,
                $locked['activeRecordId'],
                $items['active'],
                $target
            )) {
                return false;
            }

            $status = 'changed';
            return true;
        }, ['RED_Advanced']);

        if (!$committed || $status === 'error') {
            throw new RuntimeException('Theme activation transition did not commit.');
        }

        $after = red_theme_activation_read_state($connection);
        if ($status === 'changed'
            && ($after['activeThemeId'] !== $target || $after['previousThemeId'] !== $before['activeThemeId'])
        ) {
            throw new RuntimeException('Theme activation state failed post-commit verification.');
        }
        if ($status === 'unchanged' && $after !== $before) {
            throw new RuntimeException('Unchanged theme activation state drifted unexpectedly.');
        }

        return [
            'action' => $action,
            'status' => $status,
            'targetThemeId' => $target,
            'compatibility' => $compatibility,
            'before' => $before,
            'after' => $after,
        ];
    }
}

if (!function_exists('red_theme_activation_apply')) {
    function red_theme_activation_apply($connection, $action, $themeId = '', $projectRoot = null)
    {
        $result = red_admin_with_theme_contract_lock(
            $connection,
            function () use ($connection, $action, $themeId, $projectRoot) {
                return red_theme_activation_apply_unlocked(
                    $connection,
                    $action,
                    $themeId,
                    $projectRoot
                );
            }
        );
        if (!is_array($result)) {
            throw new RuntimeException('Theme activation could not acquire the content-contract lock.');
        }

        return $result;
    }
}
