<?php
/**
 * Authenticated, session-isolated previews for audited portable theme packages.
 */

$projectRoot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
    ? rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR)
    : dirname(__DIR__, 2);

require_once $projectRoot . '/includes/bootstrap.php';
require_once $projectRoot . '/includes/theme_preview_admin_helpers.php';
require_once $projectRoot . '/includes/theme_activation_helpers.php';
require_once $projectRoot . '/includes/theme_runtime.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
header(
    "Content-Security-Policy: default-src 'none'; base-uri 'none'; " .
    "form-action 'self'; frame-ancestors 'self'; frame-src 'self'; " .
    "img-src data:; style-src 'unsafe-inline'"
);

$method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
    ? strtoupper($_SERVER['REQUEST_METHOD'])
    : '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    echo 'no';
    exit;
}

red_start_session();
red_require_admin_site_manager($method === 'POST');

$adminRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
try {
    $sessionBinding = red_theme_admin_preview_session_binding(session_id());
} catch (Throwable $exception) {
    http_response_code(403);
    echo 'no';
    exit;
}

try {
    $themeInventory = red_theme_admin_preview_inventory($projectRoot);
} catch (Throwable $exception) {
    error_log('RED-CMS theme preview inventory failed: ' . $exception->getMessage());
    $themeInventory = [];
}
$inventoryScope = red_theme_admin_preview_inventory_scope();
$contactPreviewLaunchAvailable = red_theme_admin_preview_can_launch($themeInventory, 'contact');
$homePreviewLaunchAvailable = red_theme_admin_preview_can_launch($themeInventory, 'home');
$adrianaHomePreviewLaunchAvailable = red_theme_admin_preview_can_launch($themeInventory, 'adriana-home');

if ($method === 'POST') {
    try {
        if ($_GET !== []) {
            throw new InvalidArgumentException('Theme preview POST query is invalid.');
        }
        $requestedAction = isset($_POST['action']) && is_string($_POST['action'])
            ? trim($_POST['action'])
            : '';
        if (in_array($requestedAction, ['activate', 'rollback'], true)) {
            $request = red_theme_activation_request($_POST);
            $activationConnection = red_theme_activation_open_connection($projectRoot);
            if (!($activationConnection instanceof mysqli)) {
                throw new RuntimeException('Theme activation database state is unavailable.');
            }
            try {
                $before = red_theme_activation_read_state($activationConnection);
                $targetThemeId = $request['action'] === 'activate'
                    ? $request['themeId']
                    : $before['previousThemeId'];
                red_theme_activation_validate_candidate($targetThemeId, $projectRoot);
                $preflightRuntime = red_theme_runtime_bootstrap(
                    $targetThemeId,
                    $projectRoot,
                    'legacy-bootstrap',
                    true
                );
                if (($preflightRuntime['themeId'] ?? '') !== $targetThemeId
                    || !empty($preflightRuntime['resolution']['usedFallback'])
                ) {
                    throw new RuntimeException('Theme activation candidate failed production runtime preflight.');
                }
                $transition = red_theme_activation_apply(
                    $activationConnection,
                    $request['action'],
                    $request['themeId'],
                    $projectRoot
                );
            } finally {
                mysqli_close($activationConnection);
            }

            red_theme_admin_preview_exit($_SESSION);
            $status = $transition['status'] === 'unchanged'
                ? 'unchanged'
                : ($request['action'] === 'rollback' ? 'rolled-back' : 'activated');
            header('Location: /admin/bin/theme_preview.php?status=' . $status, true, 303);
            exit;
        }

        $action = red_theme_admin_preview_request_action($_POST);
        if (in_array($action, ['start', 'start-home', 'start-adriana-home'], true)) {
            $mode = $action === 'start-adriana-home'
                ? 'adriana-home'
                : ($action === 'start-home' ? 'home' : 'contact');
            $launchAvailable = $mode === 'adriana-home'
                ? $adrianaHomePreviewLaunchAvailable
                : ($mode === 'home' ? $homePreviewLaunchAvailable : $contactPreviewLaunchAvailable);
            if (!$launchAvailable) {
                throw new RuntimeException('The fixed theme preview is unavailable.');
            }
            red_theme_admin_preview_start(
                $_SESSION,
                $adminRecordId,
                $sessionBinding,
                null,
                null,
                $mode
            );
            header('Location: /admin/bin/theme_preview.php', true, 303);
        } else {
            red_theme_admin_preview_exit($_SESSION);
            header('Location: /admin/bin/theme_preview.php?status=exited', true, 303);
        }
        exit;
    } catch (Throwable $exception) {
        http_response_code(400);
        echo 'no';
        exit;
    }
}

try {
    $query = red_theme_admin_preview_query($_GET);
} catch (Throwable $exception) {
    http_response_code(400);
    echo 'no';
    exit;
}

$previewState = red_theme_admin_preview_state(
    $_SESSION,
    $adminRecordId,
    $sessionBinding
);
if ($previewState !== null) {
    $stateLaunchAvailable = $previewState['mode'] === 'adriana-home'
        ? $adrianaHomePreviewLaunchAvailable
        : ($previewState['mode'] === 'home'
            ? $homePreviewLaunchAvailable
            : $contactPreviewLaunchAvailable);
    if (!$stateLaunchAvailable) {
        red_theme_admin_preview_exit($_SESSION);
        $previewState = null;
    }
}

if (in_array($query['view'], ['contact', 'home', 'adriana-home'], true)) {
    if ($previewState === null || $previewState['mode'] !== $query['view']) {
        http_response_code(403);
        echo 'no';
        exit;
    }

    session_write_close();
    if ($query['view'] === 'adriana-home') {
        require_once $projectRoot . '/includes/theme_preview_helpers.php';
    } elseif ($query['view'] === 'home') {
        require_once $projectRoot . '/includes/theme_preview_home_helpers.php';
    } else {
        require_once $projectRoot . '/includes/theme_preview_contact_helpers.php';
    }

    $connection = null;
    try {
        if ($query['view'] === 'adriana-home') {
            $result = red_theme_preview_render_allowed_fixture('adriana-granobles', $projectRoot);
            $expectedScope = red_theme_preview_scope();
        } else {
            $localConfig = [];
            $localConfigFile = $projectRoot . '/includes/config.local.php';
            if (is_file($localConfigFile)) {
                $loadedConfig = require $localConfigFile;
                if (is_array($loadedConfig)) {
                    $localConfig = $loadedConfig;
                }
            }
            $configValue = function ($localKey, array $environmentKeys, $default = '') use ($localConfig) {
                foreach ($environmentKeys as $environmentKey) {
                    $value = getenv($environmentKey);
                    if ($value !== false && $value !== '') {
                        return $value;
                    }
                }
                return array_key_exists($localKey, $localConfig) ? $localConfig[$localKey] : $default;
            };
            $host = (string) $configValue('DBHOST', ['RED_DB_HOST', 'DBHOST'], 'localhost');
            $port = (int) $configValue('DBPORT', ['RED_DB_PORT', 'DBPORT'], 3306);
            if (substr_count($host, ':') === 1
                && preg_match('/\A(.+):(\d+)\z/', $host, $matches) === 1
            ) {
                $host = $matches[1];
                $port = (int) $matches[2];
            }
            mysqli_report(MYSQLI_REPORT_OFF);
            $connection = mysqli_init();
            if ($connection === false
                || !@mysqli_real_connect(
                    $connection,
                    $host,
                    (string) $configValue('DBUSER', ['RED_DB_USER', 'DBUSER']),
                    (string) $configValue('DBPASS', ['RED_DB_PASS', 'DBPASS']),
                    (string) $configValue('DBNAME', ['RED_DB_NAME', 'DBNAME']),
                    $port
                )
                || !mysqli_set_charset($connection, 'utf8mb4')
            ) {
                throw new RuntimeException('Could not open the isolated preview database connection.');
            }
            if ($query['view'] === 'home') {
                $result = red_theme_home_preview_render($connection, $projectRoot);
                $expectedScope = red_theme_home_preview_scope(5);
            } else {
                $result = red_theme_contact_preview_render($connection, $projectRoot);
                $expectedScope = red_theme_contact_preview_scope(4);
            }
        }
        if ($result['scope'] !== $expectedScope) {
            throw new RuntimeException('Authenticated preview scope changed unexpectedly.');
        }
        echo $result['html'];
        exit;
    } catch (Throwable $exception) {
        error_log('RED-CMS authenticated theme preview failed: ' . $exception->getMessage());
        http_response_code(503);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Preview unavailable</title></head>' .
            '<body><main><h1>Preview unavailable</h1><p>The live site was not changed.</p></main></body></html>';
        exit;
    } finally {
        if ($connection instanceof mysqli) {
            mysqli_close($connection);
        }
    }
}

$activationState = red_theme_activation_default_state();
$activationStateAvailable = false;
$themeCompatibilityById = [];
$activationConnection = red_theme_activation_open_connection($projectRoot);
if ($activationConnection instanceof mysqli) {
    try {
        $activationState = red_theme_activation_read_state($activationConnection);
        $activationStateAvailable = true;
        foreach ($themeInventory as $theme) {
            if (empty($theme['productionSupported'])) {
                continue;
            }
            $themeId = (string) ($theme['themeId'] ?? '');
            try {
                $themeCompatibilityById[$themeId] = red_theme_activation_compatibility_preflight(
                    $themeId,
                    $activationConnection,
                    $projectRoot
                );
            } catch (Throwable $exception) {
                error_log(
                    'RED-CMS theme activation compatibility unavailable for ' .
                    $themeId . ': ' . $exception->getMessage()
                );
                $themeCompatibilityById[$themeId] = [
                    'activationCompatible' => false,
                    'coverage' => [
                        'missingLayouts' => [],
                        'missingLayoutPositions' => [],
                        'missingComponents' => [],
                    ],
                    'blockingReasons' => ['Compatibility preflight could not be completed.'],
                ];
            }
        }
    } catch (Throwable $exception) {
        error_log('RED-CMS theme activation state unavailable in Themes UI: ' . $exception->getMessage());
    } finally {
        mysqli_close($activationConnection);
    }
}
$inventoryById = [];
foreach ($themeInventory as $theme) {
    $inventoryById[$theme['themeId']] = $theme;
}
$activeThemeId = $activationState['activeThemeId'];
$previousThemeId = $activationState['previousThemeId'];
$rollbackAvailable = $activationStateAvailable
    && $previousThemeId !== $activeThemeId
    && isset($inventoryById[$previousThemeId])
    && !empty($inventoryById[$previousThemeId]['productionSupported'])
    && !empty($themeCompatibilityById[$previousThemeId]['activationCompatible']);

$csrfToken = red_csrf_token();
$previewActive = $previewState !== null;
$previewMode = $previewActive ? $previewState['mode'] : '';
$previewThemeId = $previewActive ? $previewState['themeId'] : '';
$previewLabel = $previewMode === 'contact' ? 'Contact canary' : 'Home route';
$previewView = $previewMode === '' ? 'contact' : $previewMode;
$showExited = !$previewActive && $query['status'] === 'exited';
$activationStatus = !$previewActive ? $query['status'] : '';
$escape = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>RED-CMS Themes</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/logoico.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    <link rel="manifest" href="/site.webmanifest">
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #eff1ed; color: #172019; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: #eff1ed; }
        main { width: min(100% - 32px, 1180px); margin: 0 auto; padding: 28px 0 44px; }
        a { color: inherit; }
        .preview-card { overflow: hidden; border: 1px solid #cbd4c9; border-radius: 20px; background: #fff; box-shadow: 0 20px 55px rgba(31, 51, 35, .12); }
        .preview-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 18px 20px; border-bottom: 1px solid #dce3da; background: #f9fbf8; }
        .preview-heading { margin: 0; font-size: clamp(1.05rem, 2vw, 1.35rem); line-height: 1.2; }
        .preview-kicker { margin: 0 0 4px; color: #4d6651; font-size: .75rem; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
        .preview-copy { max-width: 720px; margin: 8px 0 0; color: #536057; line-height: 1.55; }
        .preview-action { appearance: none; min-height: 44px; padding: 10px 16px; border: 1px solid #183f24; border-radius: 999px; background: #183f24; color: #fff; cursor: pointer; font: inherit; font-weight: 720; white-space: nowrap; }
        .preview-action:hover { background: #0e2f18; }
        .preview-action:focus-visible { outline: 3px solid #d19c33; outline-offset: 3px; }
        .preview-action.is-secondary { border-color: #8a988b; background: #fff; color: #263229; }
        .preview-action.is-secondary:hover { border-color: #465b49; background: #f1f5f0; }
        .preview-action.is-activate { border-color: #8d2d23; background: #a9382d; }
        .preview-action.is-activate:hover { background: #84251d; }
        .preview-frame { display: block; width: 100%; min-height: 760px; border: 0; background: #f7f5ef; }
        .preview-frame.is-home { min-height: 960px; }
        .themes-shell { padding: clamp(24px, 5vw, 54px); }
        .themes-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 28px; }
        .themes-header h1 { max-width: 760px; margin: 0; font-size: clamp(2.25rem, 5vw, 4.75rem); letter-spacing: -.052em; line-height: .94; }
        .themes-header p { max-width: 720px; margin: 18px 0 0; color: #536057; font-size: 1.03rem; line-height: 1.65; }
        .back-link { flex: 0 0 auto; padding: 10px 14px; border: 1px solid #cbd4c9; border-radius: 999px; background: #f9fbf8; font-size: .9rem; font-weight: 700; text-decoration: none; }
        .back-link:hover { border-color: #6e826f; }
        .back-link:focus-visible { outline: 3px solid #d19c33; outline-offset: 3px; }
        .preview-status, .live-notice { margin: 24px 0 0; padding: 14px 16px; border-radius: 12px; line-height: 1.55; }
        .preview-status { border: 1px solid #abc9af; background: #edf8ee; color: #214a29; }
        .live-notice { border: 1px solid #d5c38e; background: #fff8df; color: #594810; }
        .live-notice strong { color: #302706; }
        .inventory-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin: 38px 0 14px; }
        .inventory-heading h2 { margin: 0; font-size: 1.15rem; }
        .inventory-heading p { margin: 0; color: #657067; font-size: .86rem; }
        .theme-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .theme-card { display: flex; min-height: 280px; padding: 22px; border: 1px solid #d7ded5; border-radius: 16px; background: #fbfcfa; flex-direction: column; }
        .theme-card.is-live, .theme-card.is-active { border-color: #92a895; background: #f4f8f3; }
        .theme-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .theme-name { margin: 0; font-size: 1.3rem; letter-spacing: -.02em; }
        .theme-id { margin: 5px 0 0; color: #68736b; font: 600 .78rem/1.4 ui-monospace, SFMono-Regular, Menlo, monospace; }
        .theme-badge { flex: 0 0 auto; padding: 6px 9px; border-radius: 999px; background: #e7ece5; color: #334337; font-size: .72rem; font-weight: 780; }
        .theme-card.is-live .theme-badge, .theme-card.is-active .theme-badge { background: #183f24; color: #fff; }
        .theme-description { margin: 22px 0 0; color: #536057; line-height: 1.58; }
        .theme-layouts { margin: 10px 0 0; color: #68736b; font: 600 .75rem/1.5 ui-monospace, SFMono-Regular, Menlo, monospace; overflow-wrap: anywhere; }
        .theme-meta { display: flex; gap: 10px 18px; margin: 22px 0 0; padding: 14px 0 0; border-top: 1px solid #e0e5df; color: #59645c; flex-wrap: wrap; font-size: .82rem; }
        .theme-meta strong { color: #263229; }
        .theme-card-footer { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-top: auto; padding-top: 24px; }
        .theme-state { margin: 0; color: #5e6961; font-size: .82rem; line-height: 1.45; }
        .theme-card form { margin: 0; }
        .theme-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .no-themes { padding: 24px; border: 1px dashed #aeb9ad; border-radius: 14px; background: #f9fbf8; color: #4d5a50; }
        .boundary-note { margin: 24px 0 0; padding-top: 20px; border-top: 1px solid #e1e5df; color: #626d65; font-size: .84rem; line-height: 1.55; }
        @media (max-width: 760px) {
            main { width: min(100% - 20px, 1180px); padding-top: 10px; }
            .preview-toolbar { align-items: flex-start; flex-direction: column; }
            .preview-toolbar form, .preview-toolbar .preview-action { width: 100%; }
            .preview-frame { min-height: 680px; }
            .preview-frame.is-home { min-height: 860px; }
            .themes-header, .inventory-heading { align-items: flex-start; flex-direction: column; }
            .theme-grid { grid-template-columns: 1fr; }
            .theme-card { min-height: 0; }
            .theme-card-footer { align-items: stretch; flex-direction: column; }
            .theme-actions { display: grid; grid-template-columns: 1fr; justify-content: stretch; }
            .theme-card form, .theme-card .preview-action { width: 100%; }
        }
    </style>
</head>
<body>
<main>
    <section class="preview-card" aria-labelledby="preview-title">
        <?php if ($previewActive): ?>
            <header class="preview-toolbar">
                <div>
                    <p class="preview-kicker">Private session preview</p>
                    <h1 class="preview-heading" id="preview-title"><?php echo $escape($previewThemeId); ?> · <?php echo $escape($previewLabel); ?></h1>
                    <p class="preview-copy">This read-only preview exists only in your current Webmaster session. The live site continues to render <?php echo $escape($activeThemeId); ?>.</p>
                </div>
                <form method="post" action="/admin/bin/theme_preview.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="exit" />
                    <button class="preview-action" type="submit">Exit preview</button>
                </form>
            </header>
            <iframe
                class="preview-frame<?php echo $previewMode !== 'contact' ? ' is-home' : ''; ?>"
                src="/admin/bin/theme_preview.php?view=<?php echo $escape($previewView); ?>"
                title="<?php echo $escape($previewThemeId); ?> <?php echo $escape($previewLabel); ?> preview"
                sandbox="allow-same-origin"
                referrerpolicy="no-referrer"
            ></iframe>
        <?php else: ?>
            <div class="themes-shell">
                <header class="themes-header">
                    <div>
                        <p class="preview-kicker">Webmaster control panel · themes</p>
                        <h1 id="preview-title">Installed themes</h1>
                        <p>Preview validated local packages, activate a production-supported theme, or return to the immediately previous theme. Invalid manifests remain hidden and never enter the public runtime.</p>
                    </div>
                    <a class="back-link" href="/">Back to website</a>
                </header>
                <?php if ($showExited): ?>
                    <p class="preview-status" role="status">Preview state cleared. The active production theme was not changed.</p>
                <?php endif; ?>
                <?php if ($activationStatus === 'activated'): ?>
                    <p class="preview-status" role="status">Theme activated. Public requests now use <strong><?php echo $escape($activeThemeId); ?></strong>, with <?php echo $escape($previousThemeId); ?> retained for one-step rollback.</p>
                <?php elseif ($activationStatus === 'rolled-back'): ?>
                    <p class="preview-status" role="status">Theme rollback completed. <strong><?php echo $escape($activeThemeId); ?></strong> is active.</p>
                <?php elseif ($activationStatus === 'unchanged'): ?>
                    <p class="preview-status" role="status">No theme change was needed. <strong><?php echo $escape($activeThemeId); ?></strong> remains active.</p>
                <?php endif; ?>
                <?php if ($activationStateAvailable): ?>
                    <p class="live-notice"><strong><?php echo $escape($activeThemeId); ?> is active.</strong> Previous theme: <?php echo $escape($previousThemeId); ?>. Changes are stored atomically; invalid state or a production render failure falls back to legacy-bootstrap.</p>
                <?php else: ?>
                    <p class="live-notice"><strong>Activation state is unavailable.</strong> Apply the pending database migration before using Activate or Roll Back. The public runtime remains fail-closed on legacy-bootstrap.</p>
                <?php endif; ?>

                <div class="inventory-heading">
                    <h2 id="installed-theme-list">Validated local packages</h2>
                    <p><?php echo count($themeInventory); ?> available · invalid manifests are hidden</p>
                </div>

                <?php if ($themeInventory === []): ?>
                    <p class="no-themes">No valid local theme packages are available. The live website was not changed.</p>
                <?php else: ?>
                    <section class="theme-grid" aria-labelledby="installed-theme-list">
                        <?php foreach ($themeInventory as $theme): ?>
                            <?php
                            $isActive = $theme['themeId'] === $activeThemeId;
                            $canPreviewContact = $theme['themeId'] === 'starter-reference'
                                && $contactPreviewLaunchAvailable;
                            $homePreviewAction = $theme['themeId'] === 'starter-reference'
                                ? 'start-home'
                                : ($theme['themeId'] === 'adriana-granobles' ? 'start-adriana-home' : '');
                            $canPreviewHome = ($theme['themeId'] === 'starter-reference'
                                    && $homePreviewLaunchAvailable)
                                || ($theme['themeId'] === 'adriana-granobles'
                                    && $adrianaHomePreviewLaunchAvailable);
                            $compatibility = $themeCompatibilityById[$theme['themeId']] ?? null;
                            $activationCompatible = is_array($compatibility)
                                && !empty($compatibility['activationCompatible']);
                            $canActivate = $activationStateAvailable
                                && !$isActive
                                && !empty($theme['productionSupported'])
                                && $activationCompatible;
                            $layoutIds = array_map(
                                function (array $layout) {
                                    return (string) ($layout['id'] ?? '');
                                },
                                is_array($theme['layouts'] ?? null) ? $theme['layouts'] : []
                            );
                            $missingLayouts = is_array($compatibility)
                                ? array_values($compatibility['coverage']['missingLayouts'] ?? [])
                                : [];
                            $missingComponents = is_array($compatibility)
                                ? array_values($compatibility['coverage']['missingComponents'] ?? [])
                                : [];
                            $missingLayoutPositions = is_array($compatibility)
                                ? array_values($compatibility['coverage']['missingLayoutPositions'] ?? [])
                                : [];
                            $missingLayoutPositionLabels = [];
                            foreach ($missingLayoutPositions as $missingLayoutPosition) {
                                if (!is_array($missingLayoutPosition)) {
                                    continue;
                                }
                                $assignedLayoutId = (string) ($missingLayoutPosition['layoutId'] ?? '');
                                $resolvedLayoutId = (string) ($missingLayoutPosition['resolvedLayoutId'] ?? '');
                                $positionId = (int) ($missingLayoutPosition['positionId'] ?? 0);
                                if ($assignedLayoutId !== '' && $resolvedLayoutId !== '' && $positionId > 0) {
                                    $missingLayoutPositionLabels[] = $assignedLayoutId . ':' . $positionId .
                                        ($assignedLayoutId === $resolvedLayoutId ? '' : ' → ' . $resolvedLayoutId);
                                }
                            }
                            ?>
                            <article class="theme-card<?php echo $isActive ? ' is-active' : ''; ?>">
                                <div class="theme-card-head">
                                    <div>
                                        <h3 class="theme-name"><?php echo $escape($theme['name']); ?></h3>
                                        <p class="theme-id"><?php echo $escape($theme['themeId']); ?></p>
                                    </div>
                                    <span class="theme-badge"><?php echo $isActive ? 'Active' : 'Validated'; ?></span>
                                </div>
                                <p class="theme-description"><?php echo $escape($theme['description']); ?></p>
                                <p class="theme-layouts">Layouts: <?php echo $escape(implode(' · ', $layoutIds)); ?></p>
                                <div class="theme-meta">
                                    <span><strong>Version</strong> <?php echo $escape($theme['version']); ?></span>
                                    <span><strong>Type</strong> <?php echo $escape($theme['typeLabel']); ?></span>
                                    <span><strong>Layouts</strong> <?php echo (int) ($theme['layoutCount'] ?? 0); ?></span>
                                </div>
                                <div class="theme-card-footer">
                                    <p class="theme-state">
                                        <?php if ($isActive): ?>Current public renderer.<?php elseif ($canActivate): ?>Ready for production activation.<?php elseif (!empty($theme['productionSupported']) && !$activationCompatible): ?>Activation blocked by current content compatibility.<?php else: ?>Installed, but not production supported.<?php endif; ?>
                                        <?php if ($missingLayouts !== []): ?><br>Missing layout ids: <?php echo $escape(implode(', ', $missingLayouts)); ?>.<?php endif; ?>
                                        <?php if ($missingLayoutPositionLabels !== []): ?><br>Missing layout positions: <?php echo $escape(implode(', ', $missingLayoutPositionLabels)); ?>.<?php endif; ?>
                                        <?php if ($missingComponents !== []): ?><br>Missing component views: <?php echo $escape(implode(', ', $missingComponents)); ?>.<?php endif; ?>
                                        <?php if ($theme['previewAvailable']): ?><br>Fixed <?php echo $escape(implode(' · ', $theme['previewModes'])); ?> previews available.<?php endif; ?>
                                    </p>
                                    <div class="theme-actions" aria-label="Available actions for <?php echo $escape($theme['name']); ?>">
                                        <?php if ($canPreviewContact): ?>
                                            <form method="post" action="/admin/bin/theme_preview.php">
                                                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                                                <input type="hidden" name="action" value="start" />
                                                <button class="preview-action is-secondary" type="submit">Preview Contact</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($canPreviewHome): ?>
                                            <form method="post" action="/admin/bin/theme_preview.php">
                                                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                                                <input type="hidden" name="action" value="<?php echo $escape($homePreviewAction); ?>" />
                                                <button class="preview-action is-secondary" type="submit">Preview Home</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($canActivate): ?>
                                            <form method="post" action="/admin/bin/theme_preview.php">
                                                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                                                <input type="hidden" name="action" value="activate" />
                                                <input type="hidden" name="theme_id" value="<?php echo $escape($theme['themeId']); ?>" />
                                                <button class="preview-action is-activate" type="submit">Activate</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($isActive && $rollbackAvailable): ?>
                                            <form method="post" action="/admin/bin/theme_preview.php">
                                                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                                                <input type="hidden" name="action" value="rollback" />
                                                <button class="preview-action" type="submit">Roll Back to <?php echo $escape($previousThemeId); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <p class="boundary-note"><strong>Activation boundary.</strong> Activate accepts only a validated local theme whose declared layouts, numbered positions, and component views cover current assignments. Roll Back accepts no caller-selected target and passes the same compatibility gate. Neither action installs files, changes URLs, rewrites stored layout assignments, changes content, or alters the optional shared-logo override.</p>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
