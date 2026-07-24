<?php
/**
 * Dependency-free contract and tamper tests for the authenticated preview state.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_preview_admin_helpers.php';
require_once $repositoryRoot . '/includes/theme_preview_contact_helpers.php';
require_once $repositoryRoot . '/includes/theme_preview_home_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_admin_preview_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_admin_preview_test_expect(callable $callback, $fragment, $message)
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    red_theme_admin_preview_test_assert(
        $caught instanceof Throwable
            && ($fragment === '' || strpos($caught->getMessage(), $fragment) !== false),
        $message
    );
}

function red_theme_admin_preview_test_remove_tree($path)
{
    if (!is_string($path) || $path === '' || (!file_exists($path) && !is_link($path))) {
        return;
    }
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    $entries = scandir($path);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                red_theme_admin_preview_test_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
    }
    @rmdir($path);
}

$temporaryRoot = '';
try {
    $now = 2000000000;
    $adminRecordId = 2147002051;
    $bindingA = red_theme_admin_preview_session_binding('admin-preview-session-a');
    $bindingB = red_theme_admin_preview_session_binding('admin-preview-session-b');
    $nonce = str_repeat('a', 64);

    $discovery = red_theme_discover($repositoryRoot);
    $inventory = red_theme_admin_preview_inventory_from_discovery($discovery);
    $inventoryById = [];
    foreach ($inventory as $theme) {
        $inventoryById[$theme['themeId']] = $theme;
    }
    red_theme_admin_preview_test_assert(
        array_keys($inventoryById) === ['legacy-bootstrap', 'adriana-granobles', 'starter-reference'],
        'chooser inventory lists the three currently validated local packages in fixed order'
    );
    red_theme_admin_preview_test_assert(
        $inventoryById['legacy-bootstrap']['isLiveCompatibility'] === true
            && $inventoryById['legacy-bootstrap']['previewAvailable'] === false
            && $inventoryById['legacy-bootstrap']['type'] === 'legacy-adapter',
        'legacy-bootstrap is visibly classified as the live compatibility theme without a preview action'
    );
    red_theme_admin_preview_test_assert(
        $inventoryById['adriana-granobles']['isLiveCompatibility'] === false
            && $inventoryById['adriana-granobles']['previewAvailable'] === true
            && $inventoryById['adriana-granobles']['previewModes'] === ['Home route']
            && $inventoryById['adriana-granobles']['productionSupported'] === true
            && $inventoryById['adriana-granobles']['layoutCount'] === 10
            && $inventoryById['adriana-granobles']['type'] === 'standard',
        'the Adriana package is production-supported with exactly one fixed Home fixture action'
    );
    red_theme_admin_preview_test_assert(
        $inventoryById['starter-reference']['isLiveCompatibility'] === false
            && $inventoryById['starter-reference']['previewAvailable'] === true
            && $inventoryById['starter-reference']['previewModes'] === ['Contact canary', 'Home route']
            && $inventoryById['starter-reference']['type'] === 'standard',
        'the validated starter package retains its fixed Contact and Home preview actions'
    );
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_inventory_scope() === [
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
        ],
        'chooser inventory declares an exact zero-read, zero-write, no-activation scope'
    );
    $inventoryWithoutStarter = array_values(
        array_filter(
            $inventory,
            function (array $theme) {
                return $theme['themeId'] !== 'starter-reference';
            }
        )
    );
    $inventoryWithoutAdriana = array_values(
        array_filter(
            $inventory,
            function (array $theme) {
                return $theme['themeId'] !== 'adriana-granobles';
            }
        )
    );
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_can_launch($inventory, 'contact')
            && red_theme_admin_preview_can_launch($inventory, 'home')
            && red_theme_admin_preview_can_launch($inventory, 'adriana-home')
            && !red_theme_admin_preview_can_launch($inventoryWithoutStarter, 'contact')
            && !red_theme_admin_preview_can_launch($inventoryWithoutStarter, 'home')
            && !red_theme_admin_preview_can_launch($inventoryWithoutAdriana, 'adriana-home'),
        'each fixed preview launch fails closed unless its exact validated package is present'
    );
    red_theme_admin_preview_test_expect(
        function () use ($inventory) {
            red_theme_admin_preview_can_launch($inventory, 'article');
        },
        'fixed allowlist',
        'an arbitrary preview mode is rejected'
    );

    $temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
        'redcms-theme-chooser-self-test-' . bin2hex(random_bytes(8));
    $invalidThemeDirectory = $temporaryRoot . '/themes/broken-preview';
    if (!mkdir($invalidThemeDirectory, 0700, true)
        || file_put_contents($invalidThemeDirectory . '/theme.json', '{"schemaVersion":1') === false
    ) {
        throw new RuntimeException('Could not create the invalid-manifest chooser fixture.');
    }
    $invalidManifest = red_theme_validate_manifest('broken-preview', $temporaryRoot);
    red_theme_admin_preview_test_assert(
        $invalidManifest['valid'] === false
            && implode(' ', $invalidManifest['errors']) !== ''
            && strpos(implode(' ', $invalidManifest['errors']), 'invalid JSON') !== false,
        'an actual malformed local manifest fails the shared validator'
    );
    $mixedDiscovery = $discovery;
    $mixedDiscovery['broken-preview'] = $invalidManifest;
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_inventory_from_discovery($mixedDiscovery) === $inventory,
        'invalid local manifests are omitted without changing the valid chooser inventory'
    );

    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_state_key() === 'red_theme_admin_preview',
        'preview state uses one dedicated session key'
    );
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_ttl() === 900,
        'preview state expires after exactly fifteen minutes'
    );
    red_theme_admin_preview_test_assert(
        preg_match('/\A[a-f0-9]{64}\z/', $bindingA) === 1 && $bindingA !== $bindingB,
        'each session id receives a distinct bounded binding'
    );
    red_theme_admin_preview_test_expect(
        function () {
            red_theme_admin_preview_session_binding('');
        },
        'active bounded session id',
        'an empty session id is rejected'
    );
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_request_action(['action' => 'start']) === 'start'
            && red_theme_admin_preview_request_action(['action' => 'start-home']) === 'start-home'
            && red_theme_admin_preview_request_action(['action' => 'start-adriana-home']) === 'start-adriana-home'
            && red_theme_admin_preview_request_action(['csrf_token' => 'token', 'action' => 'exit']) === 'exit',
        'only the three fixed preview starts and exit actions are accepted'
    );
    $invalidActions = [
        [[], 'required', 'a missing action is rejected'],
        [['action' => 'activate'], 'invalid', 'an activation-shaped action is rejected'],
        [['action' => 'start', 'theme' => 'legacy-bootstrap'], 'unexpected input', 'a caller-supplied theme is rejected'],
        [['action' => 'start-home', 'mode' => 'home'], 'unexpected input', 'a caller-supplied mode is rejected'],
        [['action' => ['start']], 'unexpected input', 'an array action is rejected'],
    ];
    foreach ($invalidActions as $case) {
        red_theme_admin_preview_test_expect(
            function () use ($case) {
                red_theme_admin_preview_request_action($case[0]);
            },
            $case[1],
            $case[2]
        );
    }

    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_query([]) === ['view' => 'shell', 'status' => '']
            && red_theme_admin_preview_query(['view' => 'contact']) === ['view' => 'contact', 'status' => '']
            && red_theme_admin_preview_query(['view' => 'home']) === ['view' => 'home', 'status' => '']
            && red_theme_admin_preview_query(['view' => 'adriana-home']) === ['view' => 'adriana-home', 'status' => '']
            && red_theme_admin_preview_query(['status' => 'exited']) === ['view' => 'shell', 'status' => 'exited'],
        'only the shell, three fixed preview frames, and exited status queries are accepted'
    );
    foreach (
        [
            ['view' => 'article'],
            ['view' => 'contact', 'theme' => 'starter-reference'],
            ['theme' => 'starter-reference'],
        ]
        as $invalidQuery
    ) {
        red_theme_admin_preview_test_expect(
            function () use ($invalidQuery) {
                red_theme_admin_preview_query($invalidQuery);
            },
            'query is invalid',
            'query tampering is rejected'
        );
    }

    $sessionA = [
        'alias' => 'Preview Test',
        'csrf_token' => 'sentinel-token',
        'unrelated' => ['preserve' => true],
    ];
    $beforeSessionA = $sessionA;
    $state = red_theme_admin_preview_start(
        $sessionA,
        $adminRecordId,
        $bindingA,
        $now,
        $nonce
    );
    red_theme_admin_preview_test_assert(
        array_keys($state) === [
            'schemaVersion',
            'themeId',
            'mode',
            'rollbackThemeId',
            'adminRecordId',
            'sessionBinding',
            'issuedAt',
            'expiresAt',
            'nonce',
        ],
        'preview state has the exact fixed ordered contract'
    );
    red_theme_admin_preview_test_assert(
        $state['schemaVersion'] === 1
            && $state['themeId'] === 'starter-reference'
            && $state['mode'] === 'contact'
            && $state['rollbackThemeId'] === 'legacy-bootstrap'
            && $state['adminRecordId'] === $adminRecordId
            && $state['sessionBinding'] === $bindingA
            && $state['issuedAt'] === $now
            && $state['expiresAt'] === $now + 900
            && $state['nonce'] === $nonce,
        'state fixes the theme, Contact mode, rollback target, identity, expiry, and nonce'
    );
    red_theme_admin_preview_test_assert(
        array_diff_key($sessionA, [red_theme_admin_preview_state_key() => true]) === $beforeSessionA,
        'starting preview changes only its dedicated session key'
    );
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_validate_state($state, $adminRecordId, $bindingA, $now) === $state
            && red_theme_admin_preview_validate_state($state, $adminRecordId, $bindingA, $now + 899) === $state,
        'state remains valid only inside its fixed fifteen-minute window'
    );
    red_theme_admin_preview_test_expect(
        function () use ($state, $adminRecordId, $bindingA, $now) {
            red_theme_admin_preview_validate_state($state, $adminRecordId, $bindingA, $now + 900);
        },
        'identity or expiry',
        'state expires exactly at its boundary'
    );
    red_theme_admin_preview_test_expect(
        function () use ($state, $adminRecordId, $bindingA, $now) {
            red_theme_admin_preview_validate_state($state, $adminRecordId, $bindingA, $now - 1);
        },
        'identity or expiry',
        'future-issued state is rejected'
    );
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_state($sessionA, $adminRecordId, $bindingA, $now + 1) === $state,
        'the owning administrator session can read its valid state'
    );

    $homeSession = ['unrelated' => 'home-preview'];
    $homeNonce = str_repeat('b', 64);
    $homeState = red_theme_admin_preview_start(
        $homeSession,
        $adminRecordId,
        $bindingA,
        $now,
        $homeNonce,
        'home'
    );
    red_theme_admin_preview_test_assert(
        $homeState['mode'] === 'home'
            && $homeState['themeId'] === 'starter-reference'
            && $homeState['nonce'] === $homeNonce
            && red_theme_admin_preview_validate_state(
                $homeState,
                $adminRecordId,
                $bindingA,
                $now + 1
            ) === $homeState
            && $homeSession[red_theme_admin_preview_state_key()] === $homeState,
        'the separate fixed Home action creates the same exact state shape with only Home mode changed'
    );

    $adrianaSession = ['unrelated' => 'adriana-home-preview'];
    $adrianaNonce = str_repeat('c', 64);
    $adrianaState = red_theme_admin_preview_start(
        $adrianaSession,
        $adminRecordId,
        $bindingA,
        $now,
        $adrianaNonce,
        'adriana-home'
    );
    red_theme_admin_preview_test_assert(
        $adrianaState['mode'] === 'adriana-home'
            && $adrianaState['themeId'] === 'adriana-granobles'
            && $adrianaState['nonce'] === $adrianaNonce
            && red_theme_admin_preview_validate_state(
                $adrianaState,
                $adminRecordId,
                $bindingA,
                $now + 1
            ) === $adrianaState
            && $adrianaSession[red_theme_admin_preview_state_key()] === $adrianaState,
        'the Adriana Home action binds the exact package and mode in the same isolated state shape'
    );

    $sessionB = ['unrelated' => 'second-session'];
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_state($sessionB, $adminRecordId, $bindingB, $now + 1) === null
            && $sessionB === ['unrelated' => 'second-session'],
        'another session has no preview state and is not mutated'
    );
    $copiedSession = ['unrelated' => 'copied', red_theme_admin_preview_state_key() => $state];
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_state($copiedSession, $adminRecordId, $bindingB, $now + 1) === null
            && $copiedSession === ['unrelated' => 'copied'],
        'copied state fails the second-session binding and is cleared'
    );
    $wrongAdminSession = ['unrelated' => 'wrong-admin', red_theme_admin_preview_state_key() => $state];
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_state($wrongAdminSession, $adminRecordId + 1, $bindingA, $now + 1) === null
            && $wrongAdminSession === ['unrelated' => 'wrong-admin'],
        'state cannot cross administrator identities'
    );

    $tamperCases = [
        ['schemaVersion', 2],
        ['themeId', 'legacy-bootstrap'],
        ['mode', 'article'],
        ['rollbackThemeId', 'starter-reference'],
        ['adminRecordId', $adminRecordId + 1],
        ['sessionBinding', $bindingB],
        ['issuedAt', $now + 1],
        ['expiresAt', $now + 901],
        ['nonce', str_repeat('z', 64)],
    ];
    foreach ($tamperCases as $case) {
        $tampered = $state;
        $tampered[$case[0]] = $case[1];
        red_theme_admin_preview_test_expect(
            function () use ($tampered, $adminRecordId, $bindingA, $now) {
                red_theme_admin_preview_validate_state($tampered, $adminRecordId, $bindingA, $now);
            },
            '',
            'tampering with ' . $case[0] . ' is rejected'
        );
    }
    $extraState = $state;
    $extraState['activeThemeId'] = 'starter-reference';
    red_theme_admin_preview_test_expect(
        function () use ($extraState, $adminRecordId, $bindingA, $now) {
            red_theme_admin_preview_validate_state($extraState, $adminRecordId, $bindingA, $now);
        },
        'invalid shape',
        'an activation-shaped state field is rejected'
    );
    $missingState = $state;
    unset($missingState['nonce']);
    red_theme_admin_preview_test_expect(
        function () use ($missingState, $adminRecordId, $bindingA, $now) {
            red_theme_admin_preview_validate_state($missingState, $adminRecordId, $bindingA, $now);
        },
        'invalid shape',
        'a missing state field is rejected'
    );

    $tamperedSession = ['unrelated' => 'safe', red_theme_admin_preview_state_key() => $extraState];
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_state($tamperedSession, $adminRecordId, $bindingA, $now) === null
            && $tamperedSession === ['unrelated' => 'safe'],
        'tampered state fails closed and cleanup preserves unrelated session values'
    );
    red_theme_admin_preview_test_assert(
        red_theme_admin_preview_exit($sessionA) === true
            && $sessionA === $beforeSessionA
            && red_theme_admin_preview_exit($sessionA) === false
            && $sessionA === $beforeSessionA,
        'explicit exit removes only preview state and is safely idempotent'
    );

    $randomSession = [];
    $firstRandomState = red_theme_admin_preview_start($randomSession, $adminRecordId, $bindingA, $now);
    $secondRandomState = red_theme_admin_preview_start($randomSession, $adminRecordId, $bindingA, $now + 1);
    red_theme_admin_preview_test_assert(
        $firstRandomState['nonce'] !== $secondRandomState['nonce']
            && preg_match('/\A[a-f0-9]{64}\z/', $firstRandomState['nonce']) === 1
            && preg_match('/\A[a-f0-9]{64}\z/', $secondRandomState['nonce']) === 1,
        'each preview start receives a fresh cryptographic nonce'
    );

    $endpointSource = file_get_contents($repositoryRoot . '/admin/bin/theme_preview.php');
    red_theme_admin_preview_test_assert(
        is_string($endpointSource)
            && strpos($endpointSource, "red_require_admin_site_manager(\$method === 'POST')") !== false
            && strpos($endpointSource, 'red_theme_admin_preview_request_action($_POST)') !== false,
        'endpoint requires site-manager authorization and CSRF for every mutation'
    );
    red_theme_admin_preview_test_assert(
        strpos($endpointSource, 'red_theme_admin_preview_inventory($projectRoot)') !== false
            && strpos($endpointSource, 'if ($_GET !== [])') !== false
            && strpos($endpointSource, 'red_theme_admin_preview_can_launch($themeInventory, \'contact\')') !== false
            && strpos($endpointSource, 'red_theme_admin_preview_can_launch($themeInventory, \'home\')') !== false
            && strpos($endpointSource, 'red_theme_admin_preview_can_launch($themeInventory, \'adriana-home\')') !== false,
        'endpoint validates local inventory for all three fixed modes and rejects POST query tampering'
    );
    red_theme_admin_preview_test_assert(
        strpos($endpointSource, "Content-Security-Policy: default-src 'none'") !== false
            && strpos($endpointSource, "header('Cache-Control: no-store") !== false
            && strpos($endpointSource, "header('X-Frame-Options: SAMEORIGIN')") !== false,
        'endpoint sends no-store, framing, and restrictive content-security headers'
    );
    red_theme_admin_preview_test_assert(
        strpos($endpointSource, 'session_write_close();') < strpos($endpointSource, 'red_theme_contact_preview_render(')
            && strpos($endpointSource, 'session_write_close();') < strpos($endpointSource, 'red_theme_home_preview_render(')
            && strpos($endpointSource, 'session_write_close();') < strpos($endpointSource, 'red_theme_preview_render_allowed_fixture('),
        'all child renders release the administrator session before fixed preview work'
    );
    red_theme_admin_preview_test_assert(
        strpos($endpointSource, '<select') === false
            && strpos($endpointSource, "\$_POST['theme'") === false
            && strpos($endpointSource, "\$_GET['theme'") === false
            && preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE)\s+[A-Z_]+\b/', $endpointSource) !== 1,
        'endpoint contains no selector, caller-supplied theme, or database write statement'
    );
    red_theme_admin_preview_test_assert(
        strpos($endpointSource, 'sandbox="allow-same-origin"') !== false
            && strpos($endpointSource, '>Exit preview</button>') !== false
            && strpos($endpointSource, "'start-home'") !== false
            && strpos($endpointSource, "'start-adriana-home'") !== false
            && strpos($endpointSource, '$homePreviewAction') !== false
            && strpos($endpointSource, '>Preview Home</button>') !== false
            && strpos($endpointSource, 'value="activate"') !== false
            && strpos($endpointSource, 'value="rollback"') !== false
            && strpos($endpointSource, 'red_theme_activation_apply') !== false,
        'previews remain sandboxed while each theme card exposes only its fixed preview, activation, and rollback actions'
    );
    $advancedSource = file_get_contents($repositoryRoot . '/admin/class/class_edit_advanced.php');
    $siteManagerCapability = is_string($advancedSource)
        ? strpos($advancedSource, '$canManageSite = red_admin_can_manage_site();')
        : false;
    $siteManagerBoundary = is_string($advancedSource)
        ? strpos($advancedSource, 'if ($canManageSite)')
        : false;
    $themesLink = is_string($advancedSource)
        ? strpos($advancedSource, "red_admin_list_ui_action_link('/admin/bin/theme_preview.php'")
        : false;
    red_theme_admin_preview_test_assert(
        $siteManagerCapability !== false
            && $siteManagerBoundary !== false
            && $themesLink !== false
            && $siteManagerCapability < $siteManagerBoundary
            && $siteManagerBoundary < $themesLink,
        'the existing Advanced control panel exposes Themes only inside its site-manager boundary'
    );

    $queries = red_theme_contact_preview_query_inventory();
    red_theme_admin_preview_test_assert(
        count($queries) === 4 && red_theme_contact_preview_assert_query_inventory($queries),
        'authenticated preview reuses the exact four-read Contact provider'
    );
    $homeQueries = red_theme_home_preview_query_inventory();
    red_theme_admin_preview_test_assert(
        count($homeQueries) === 5 && red_theme_home_preview_assert_query_inventory($homeQueries),
        'authenticated preview adds only the exact five-read Home provider'
    );
    $adrianaPreview = red_theme_preview_render_allowed_fixture('adriana-granobles', $repositoryRoot);
    $adrianaPreviewAgain = red_theme_preview_render_allowed_fixture('adriana-granobles', $repositoryRoot);
    red_theme_admin_preview_test_assert(
        $adrianaPreview['theme'] === 'adriana-granobles'
            && $adrianaPreview['layout'] === 'home-editorial'
            && $adrianaPreview['scope'] === red_theme_preview_scope()
            && $adrianaPreview['html'] === $adrianaPreviewAgain['html']
            && $adrianaPreview['sha256'] === $adrianaPreviewAgain['sha256']
            && strpos($adrianaPreview['html'], 'Isolated Adriana Granobles theme fixture') !== false
            && strpos($adrianaPreview['html'], '<form') !== false
            && strpos($adrianaPreview['html'], 'action=') === false,
        'Adriana Home renders deterministically through its zero-read display-only fixture boundary'
    );
    $runtime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_admin_preview_test_assert(
        $runtime['themeId'] === 'legacy-bootstrap'
            && !empty($runtime['resolution']['usedFallback']),
        'compatibility and preview callers still refuse standard execution without the persisted-state gate'
    );
    $starterManifest = json_decode(
        file_get_contents($repositoryRoot . '/themes/starter-reference/theme.json'),
        true
    );
    red_theme_admin_preview_test_assert(
        is_array($starterManifest)
            && $starterManifest['id'] === 'starter-reference'
            && $starterManifest['version'] === '1.3.1'
            && $starterManifest['type'] === 'standard',
        'the starter manifest retains the fixed preview contracts in the five-layout 1.3.1 package'
    );

    echo 'Authenticated theme preview self-test passed (' . $assertions . " assertions).\n";
} catch (Throwable $exception) {
    red_theme_admin_preview_test_remove_tree($temporaryRoot);
    fwrite(STDERR, 'Authenticated theme preview self-test failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    red_theme_admin_preview_test_remove_tree($temporaryRoot);
}

exit(0);
