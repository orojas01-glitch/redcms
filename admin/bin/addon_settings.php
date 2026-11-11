<?php
/**
 * Authenticated site-manager page that lists installed add-ons with declared
 * settings and opens the core-owned settings editor for one of them.
 *
 * The page adds no new write path: the editor fragment, its per-setting
 * permission checks, and the save endpoint (update_addon_settings.php) are
 * the existing core settings editor. Secret references stay read-only here.
 */

$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/includes/bootstrap.php';
require_once $projectRoot . '/includes/addon_setting_editor_helpers.php';
require_once $projectRoot . '/includes/addon_registry_helpers.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header(
    "Content-Security-Policy: default-src 'none'; base-uri 'none'; "
    . "form-action 'self'; frame-ancestors 'self'; script-src 'self'; "
    . "connect-src 'self'; style-src 'self' 'unsafe-inline'; img-src data:"
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
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';

$escape = static fn ($value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
    'UTF-8'
);

$adminRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
$csrfToken = red_csrf_token();
$packages = [];
$selectedId = '';
$editorHtml = '';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
try {
    try {
        $catalog = red_addon_discover(
            $projectRoot,
            ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
        );
    } catch (Throwable $throwable) {
        $catalog = ['packages' => []];
    }
    $packages = red_addon_settings_page_packages(
        red_addon_registry_installations($db->connection),
        $catalog['packages'] ?? []
    );
    if ($method === 'POST') {
        $request = red_addon_setting_editor_request($_POST, 'edit');
        $selectedId = !empty($request['valid'])
            && array_key_exists($request['packageId'], $packages)
                ? $request['packageId']
                : '';
        $package = $selectedId !== ''
            ? red_addon_setting_editor_package($projectRoot, $selectedId)
            : null;
        $context = is_array($package)
            ? red_addon_setting_editor_context(
                $db->connection,
                $package,
                $adminRecordId
            )
            : null;
        if (is_array($context) && !empty($context['ready'])) {
            $editorHtml = red_addon_setting_editor_render($context, $csrfToken);
        } elseif (is_array($context)
            && ($context['reason'] ?? '') === 'permission_denied'
        ) {
            $editorHtml = '<p class="red-admin-error" role="alert">Your account'
                . ' cannot change every setting of this add-on. Ask the Owner'
                . ' to grant the add-on\'s settings permission.</p>';
        } else {
            $editorHtml = red_addon_setting_editor_ui_unavailable();
        }
    }
} catch (Throwable $throwable) {
    error_log('RED-CMS add-on settings page failed.');
    $editorHtml = $method === 'POST'
        ? red_addon_setting_editor_ui_unavailable()
        : '';
} finally {
    $db->close();
}

$stateLabels = [
    'enabled' => 'On',
    'installed_disabled' => 'Installed, switched off',
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Add-on settings | RED-CMS</title>
<style>
:root{color-scheme:light;--ink:#171717;--muted:#5c554d;--line:#d7d0c5;--accent:#9b321e;--bg:#f4f1eb;--card:#fff}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
main{width:min(880px,100%);margin:0 auto;padding:32px 16px 64px}
.back{display:inline-block;margin-bottom:20px;color:var(--accent);font-weight:650;text-decoration:none}
h1{margin:0 0 6px;font-size:clamp(1.8rem,5vw,2.6rem);letter-spacing:-.03em}
.lede{margin:0 0 28px;color:var(--muted);max-width:60ch}
.list{display:grid;gap:12px;margin:0;padding:0;list-style:none}
.item{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;padding:16px 20px;border:1px solid var(--line);border-radius:16px;background:var(--card)}
.item[aria-current="true"]{border-color:var(--accent)}
.item strong{display:block}
.item span{color:var(--muted);font-size:.9rem}
button{min-height:44px;padding:0 18px;border:0;border-radius:999px;background:var(--ink);color:#fff;font:inherit;font-weight:700;cursor:pointer}
button:focus-visible,a:focus-visible,select:focus-visible,input:focus-visible{outline:3px solid #e26140;outline-offset:2px}
.editor{margin-top:28px;padding:24px;border:1px solid var(--line);border-radius:20px;background:var(--card)}
.red-admin-addon-settings__header{display:flex;flex-wrap:wrap;gap:8px;align-items:baseline;justify-content:space-between}
.red-admin-addon-settings__header span{color:var(--accent);font-size:.78rem;font-weight:750;letter-spacing:.12em;text-transform:uppercase}
.red-admin-addon-settings__header h2{margin:4px 0 0;font-size:1.4rem}
.red-admin-addon-settings__fields{margin:16px 0 0;padding:0;border:0;display:grid;gap:18px}
.red-admin-addon-settings__fields legend{font-weight:700;margin-bottom:8px}
.red-admin-addon-settings__field{display:grid;gap:6px}
.red-admin-addon-settings__field label,.red-admin-addon-settings__label{font-weight:650}
.red-admin-addon-settings__field input,.red-admin-addon-settings__field select{min-height:44px;width:100%;max-width:520px;padding:8px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff}
.red-admin-addon-settings__hint,.red-admin-addon-settings__lifecycle{margin:0;color:var(--muted);font-size:.88rem}
.red-admin-addon-settings__secret{margin:0}
.red-admin-addon-settings__actions{display:flex;flex-wrap:wrap;gap:16px;align-items:center;margin-top:24px}
[data-red-addon-settings-status]{font-weight:650}
.red-admin-error{color:#9b1c1c;font-weight:650}
.empty{padding:20px;border:1px dashed var(--line);border-radius:16px;color:var(--muted)}
</style>
</head>
<body>
<main>
<a class="back" href="/">&larr; Back to website</a>
<h1>Add-on settings</h1>
<p class="lede">Settings for the add-ons installed on this site, such as Store Lite and Wompi. Keys and other secrets are never typed here: those show only whether they are configured on the server.</p>
<?php if ($packages === []): ?>
<p class="empty">No installed add-on has settings to change.</p>
<?php else: ?>
<ul class="list">
<?php foreach ($packages as $packageId => $item): ?>
<li class="item"<?php echo $packageId === $selectedId ? ' aria-current="true"' : ''; ?>>
<div><strong><?php echo $escape($item['name']); ?></strong>
<span><?php echo $escape($packageId); ?> · <?php echo $escape($item['installedVersion']); ?> · <?php echo $escape($stateLabels[$item['state']] ?? $item['state']); ?></span>
<?php if ($item['version'] !== $item['installedVersion']): ?>
<span>Version <?php echo $escape($item['version']); ?> is on the server but not installed yet. Run the upgrade before changing settings.</span>
<?php endif; ?></div>
<form method="post" action="/admin/bin/addon_settings.php" data-red-addon-settings-open="<?php echo $escape($packageId); ?>">
<input type="hidden" name="PackageID" value="<?php echo $escape($packageId); ?>">
<input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
<button type="submit" aria-label="Open settings for <?php echo $escape($item['name']); ?>">Open settings</button>
</form>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php if ($editorHtml !== ''): ?>
<div class="editor" id="editor"><?php echo $editorHtml; ?></div>
<?php endif; ?>
</main>
<script src="/admin/assets/js/addon-settings-page.js?v=<?php echo rawurlencode((string) @filemtime($projectRoot . '/admin/assets/js/addon-settings-page.js')); ?>" defer></script>
</body>
</html>
