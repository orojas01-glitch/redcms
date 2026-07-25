<?php
/** Production document phases. Data is prepared by RED-CMS core. */
$document = $redThemeDocumentContext ?? null;
if (!is_array($document) || ($document['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Production document context is unavailable.');
}
if (($document['phase'] ?? '') === 'end') {
    echo (string) ($document['bodyAssetsHtml'] ?? '');
    echo "</body>\n</html>\n";
    return;
}
if (($document['phase'] ?? '') !== 'start') {
    throw new RuntimeException('A valid production document phase is required.');
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
    <meta name="author" content="Oscar Rojas">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/logoico.ico" sizes="any">
    <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#c81918">
    <?= $document['headAssetsHtml'] ?>
</head>
<body class="<?= htmlspecialchars(implode(' ', $bodyClasses), ENT_QUOTES, 'UTF-8') ?>">
<?= $adminOverlayHtml ?>
