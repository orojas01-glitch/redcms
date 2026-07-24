<?php
/** Fixture-preview document view. Data is prepared and validated by RED-CMS core. */
$document = $redThemeDocumentContext;
?>
<!doctype html>
<html lang="<?= htmlspecialchars($document['language'], ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($document['description'], ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($document['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <?= $document['headAssetsHtml'] ?>
</head>
<body class="adriana-preview">
    <p class="theme-preview-notice" role="status">Isolated Adriana Granobles theme fixture — display only; no database, session, external service, or live website access.</p>
    <?= $document['regions']['header'] ?>
    <?= $document['regions']['navigation'] ?>
    <?= $document['regions']['hero'] ?>
    <?= $document['contentHtml'] ?>
    <?= $document['regions']['footer'] ?>
    <?= $document['bodyAssetsHtml'] ?>
</body>
</html>
