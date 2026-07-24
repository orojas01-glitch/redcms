<?php
/** Production document phases. Data is prepared by RED-CMS core. */
$document = $redThemeDocumentContext ?? null;
if (!is_array($document) || ($document['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Adriana production document context is unavailable.');
}
if (($document['phase'] ?? '') === 'end') {
    echo "</div>\n";
    echo (string) ($document['bodyAssetsHtml'] ?? '');
    echo "</body>\n</html>\n";
    return;
}
if (($document['phase'] ?? '') !== 'start') {
    throw new RuntimeException('A valid Adriana production document phase is required.');
}
$adminOverlayHtml = (string) ($document['adminOverlayHtml'] ?? '');
$bodyClasses = [
    'red-standard-theme',
    'red-standard-theme--' . (string) $document['themeId'],
];
if (trim($adminOverlayHtml) !== '') {
    $bodyClasses[] = 'red-standard-theme--with-admin';
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars((string) $document['language'], ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $document['titleHtml'] ?></title>
    <?= $document['metaHtml'] ?>
    <meta name="theme-color" content="#160d20">
    <link rel="icon" href="/themes/adriana-granobles/assets/images/favicon.svg" type="image/svg+xml">
    <?= $document['headAssetsHtml'] ?>
</head>
<body class="<?= htmlspecialchars(implode(' ', $bodyClasses), ENT_QUOTES, 'UTF-8') ?>">
<?= $adminOverlayHtml ?>
<a class="skip-link" href="#main-content">Saltar al contenido</a>
<div class="adriana-site">
