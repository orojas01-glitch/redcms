<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$mode = (string) ($argv[1] ?? '');
if (!in_array($mode, ['normal', 'mismatch'], true)) {
    fwrite(STDERR, "Usage: php other-content-browser-fixture.php normal|mismatch\n");
    exit(64);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
require_once dirname(__DIR__) . '/includes/admin_other_ui_helpers.php';

$short = "<section data-source=\"listing\">\n  <template><x-card>Listing Ω</x-card></template>\n</section>";
$long = "<article data-source=\"dedicated\">\n  <iframe srcdoc=\"<p>Dedicated</p>\"></iframe>\n</article>";
$isMismatch = $mode === 'mismatch';
$html = $isMismatch ? $short : $long;

?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Other content browser QA</title></head>
<body><main id="advanced">
<?php
red_admin_render_other_form([
    'mode' => 'edit',
    'title' => $isMismatch ? 'Legacy Other mismatch' : 'Canonical Other source',
    'alias' => $isMismatch ? 'legacy-other-mismatch' : 'canonical-other-source',
    'tags' => 'other,qa',
    'active' => 'Y',
    'position' => 1,
    'positionOrder' => 1,
    'positionOptions' => [1 => 'Primary content'],
    'varPosition' => 'PagePosition',
    'html' => $html,
    'shortHtml' => $isMismatch ? $short : $html,
    'longHtml' => $isMismatch ? $long : $html,
    'legacyMismatch' => $isMismatch,
    'preferredEditorMode' => 'html',
    'startDateMeta' => ['display' => ''],
    'expirationDateMeta' => ['display' => ''],
    'recordId' => $isMismatch ? 2147000862 : 2147000861,
    'currentHash' => str_repeat($isMismatch ? 'b' : 'a', 64),
    'editedBy' => 'OtherQA',
    'csrfToken' => str_repeat('c', 64),
]);
?>
</main></body></html>
