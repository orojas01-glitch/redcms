<?php
/** Dependency-free contracts for persisted theme state and production gating. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_activation_helpers.php';
require_once $repositoryRoot . '/includes/theme_preview_admin_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_activation_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_activation_test_expect(callable $callback, $fragment, $message)
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    red_theme_activation_test_assert(
        $caught instanceof Throwable && strpos($caught->getMessage(), $fragment) !== false,
        $message
    );
}

try {
    red_theme_activation_test_assert(
        red_theme_activation_items() === [
            'active' => 'System_Active_Theme',
            'previous' => 'System_Previous_Theme',
        ],
        'two fixed system-owned state keys are used'
    );
    red_theme_activation_test_assert(
        red_theme_activation_default_state() === [
            'activeThemeId' => 'legacy-bootstrap',
            'previousThemeId' => 'legacy-bootstrap',
            'activeRecordId' => 0,
            'previousRecordId' => 0,
            'persisted' => false,
        ],
        'missing state fails closed to the legacy renderer'
    );
    red_theme_activation_test_assert(
        red_theme_activation_state_from_rows([], true) === red_theme_activation_default_state(),
        'runtime may explicitly accept a missing pre-migration state only as legacy fallback'
    );
    red_theme_activation_test_expect(
        function () {
            red_theme_activation_state_from_rows([]);
        },
        'exactly two',
        'write paths refuse missing persisted state'
    );

    $rows = [
        ['RecordID' => '8', 'Item' => 'System_Active_Theme', 'Content' => 'legacy-bootstrap', 'Language' => ''],
        ['RecordID' => '9', 'Item' => 'System_Previous_Theme', 'Content' => 'starter-reference', 'Language' => ''],
    ];
    $state = red_theme_activation_state_from_rows($rows);
    red_theme_activation_test_assert(
        $state === [
            'activeThemeId' => 'legacy-bootstrap',
            'previousThemeId' => 'starter-reference',
            'activeRecordId' => 8,
            'previousRecordId' => 9,
            'persisted' => true,
        ],
        'exact persisted rows map to a bounded active and previous state'
    );
    $reversed = array_reverse($rows);
    red_theme_activation_test_assert(
        red_theme_activation_state_from_rows($reversed) === $state,
        'database row order cannot alter the state'
    );
    $duplicate = $rows;
    $duplicate[] = $rows[0];
    red_theme_activation_test_expect(
        function () use ($duplicate) {
            red_theme_activation_state_from_rows($duplicate);
        },
        'duplicated',
        'duplicate active rows fail closed'
    );
    $malformed = $rows;
    $malformed[0]['Content'] = '../starter-reference';
    red_theme_activation_test_expect(
        function () use ($malformed) {
            red_theme_activation_state_from_rows($malformed);
        },
        'malformed',
        'unsafe persisted theme ids fail closed'
    );

    red_theme_activation_test_assert(
        red_theme_activation_request([
            'csrf_token' => 'token',
            'action' => 'activate',
            'theme_id' => 'starter-reference',
        ]) === ['action' => 'activate', 'themeId' => 'starter-reference'],
        'Activate accepts one bounded theme id plus the shared CSRF field'
    );
    red_theme_activation_test_assert(
        red_theme_activation_request(['action' => 'rollback', 'csrf_token' => 'token']) === [
            'action' => 'rollback',
            'themeId' => '',
        ],
        'Roll Back accepts no caller-selected target'
    );
    $invalidRequests = [
        [['action' => 'activate'], 'required'],
        [['action' => 'activate', 'theme_id' => '../starter'], 'required'],
        [['action' => 'rollback', 'theme_id' => 'legacy-bootstrap'], 'does not accept'],
        [['action' => 'delete', 'theme_id' => 'starter-reference'], 'invalid'],
        [['action' => ['activate']], 'unexpected'],
        [['action' => 'activate', 'theme_id' => 'starter-reference', 'path' => '/tmp'], 'unexpected'],
    ];
    foreach ($invalidRequests as $case) {
        red_theme_activation_test_expect(
            function () use ($case) {
                red_theme_activation_request($case[0]);
            },
            $case[1],
            'activation request tampering is rejected'
        );
    }

    $validation = red_theme_activation_validate_candidate('starter-reference', $repositoryRoot);
    red_theme_activation_test_assert(
        $validation['valid'] === true
            && ($validation['manifest']['version'] ?? '') === '1.2.0'
            && !empty($validation['production']['valid']),
        'starter-reference has a valid, explicit production contract'
    );
    red_theme_activation_test_assert(
        count($validation['production']['files']) === 16,
        'production contract resolves five regions, five layouts, four components, and two production assets'
    );
    $tamperedManifest = $validation['manifest'];
    $tamperedManifest['production']['regions']['document']['template'] = '../outside.php';
    red_theme_activation_test_assert(
        red_theme_standard_production_validation($tamperedManifest, $validation['path'])['valid'] === false,
        'production traversal fails validation before execution'
    );
    unset($tamperedManifest['production']['regions']['footer']);
    red_theme_activation_test_assert(
        red_theme_standard_production_validation($tamperedManifest, $validation['path'])['valid'] === false,
        'incomplete production regions fail validation before execution'
    );

    $compatibilityRuntime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_activation_test_assert(
        $compatibilityRuntime['themeId'] === 'legacy-bootstrap'
            && !empty($compatibilityRuntime['resolution']['usedFallback']),
        'standard execution remains closed for compatibility and preview callers'
    );
    $productionRuntime = red_theme_runtime_bootstrap(
        'starter-reference',
        $repositoryRoot,
        'legacy-bootstrap',
        true
    );
    red_theme_activation_test_assert(
        $productionRuntime['themeId'] === 'starter-reference'
            && $productionRuntime['themeType'] === 'standard'
            && $productionRuntime['standardExecutionEnabled'] === true
            && empty($productionRuntime['resolution']['usedFallback']),
        'explicit persisted-state gate selects the standard production adapter'
    );
    red_theme_activation_test_assert(
        get_class($productionRuntime['adapter']) === 'RedStandardThemeAdapter',
        'production runtime uses the core standard adapter'
    );
    red_theme_activation_test_assert(
        $productionRuntime['adapter']->supportsPublicLayout('Full-Width'),
        'production runtime accepts the starter manifest explicit legacy layout alias'
    );

    $inventory = red_theme_admin_preview_inventory($repositoryRoot);
    $byId = [];
    foreach ($inventory as $theme) {
        $byId[$theme['themeId']] = $theme;
    }
    red_theme_activation_test_assert(
        !empty($byId['legacy-bootstrap']['productionSupported'])
            && !empty($byId['starter-reference']['productionSupported'])
            && ($byId['starter-reference']['layoutCount'] ?? 0) === 5
            && count($byId['starter-reference']['layouts'] ?? []) === 5
            && in_array(
                'feature-grid',
                array_column($byId['starter-reference']['layouts'] ?? [], 'id'),
                true
            ),
        'Themes UI exposes Activate only for production-supported validated packages'
    );

    $indexSource = file_get_contents($repositoryRoot . '/index.php');
    $endpointSource = file_get_contents($repositoryRoot . '/admin/bin/theme_preview.php');
    $migrationSource = file_get_contents(
        $repositoryRoot . '/database/migrations/2026-07-17-active-theme-state.sql'
    );
    red_theme_activation_test_assert(
        is_string($indexSource)
            && strpos($indexSource, 'red_theme_activation_active_id_from_project') !== false
            && strpos($indexSource, "red_theme_runtime_bootstrap(\n        \$redThemeRequestedId") !== false,
        'public entrypoint requests only the persisted active id through the guarded runtime'
    );
    red_theme_activation_test_assert(
        is_string($endpointSource)
            && strpos($endpointSource, 'value="activate"') !== false
            && strpos($endpointSource, 'value="rollback"') !== false
            && strpos($endpointSource, 'red_require_admin_site_manager') !== false
            && strpos($endpointSource, 'red_theme_activation_compatibility_preflight') !== false
            && strpos($endpointSource, 'missingLayouts') !== false
            && strpos($endpointSource, 'missingLayoutPositions') !== false
            && strpos($endpointSource, "['layoutCount']") !== false,
        'Webmaster Themes UI exposes gated actions plus layout inventory and blocking ids'
    );
    red_theme_activation_test_assert(
        is_string($migrationSource)
            && substr_count($migrationSource, 'System_Active_Theme') >= 3
            && substr_count($migrationSource, 'System_Previous_Theme') >= 3
            && strpos($migrationSource, 'START TRANSACTION') !== false
            && strpos($migrationSource, 'COMMIT') !== false,
        'migration creates and validates both state rows transactionally'
    );

    echo 'Theme activation self-test passed: ' . $assertions . " assertions.\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Theme activation self-test failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
