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
<body>
    <p class="starter-preview-notice" role="status">
        <?php if ($document['mode'] === 'read-only-contact-preview') : ?>Read-only Contact data preview — four fixed database reads; no session, activation, or live website change.<?php elseif ($document['mode'] === 'read-only-home-preview') : ?>Read-only Home data preview — five fixed database reads; no session, activation, or live website change.<?php elseif ($document['mode'] === 'read-only-administration-preview') : ?>Read-only Administration data preview — four fixed database reads; forms and video remain offline; no session, activation, or live website change.<?php elseif ($document['mode'] === 'read-only-instructions-preview') : ?>Read-only Instructions data preview — three fixed database reads; trusted local manual media is embedded; no session, activation, or live website change.<?php elseif ($document['mode'] === 'read-only-login-preview') : ?>Read-only Login data preview — three fixed database reads; the form remains display-only; no session, activation, or live website change.<?php elseif ($document['mode'] === 'read-only-selected-contact-preview') : ?>Read-only selected Contact data preview — three fixed database reads; the form remains display-only; no session, activation, or live website change.<?php else : ?>Isolated fixture preview — no database, session, activation, or live website access.<?php endif; ?>

    </p>
    <?= $document['regions']['header'] ?>
    <?= $document['regions']['navigation'] ?>
    <?= $document['regions']['hero'] ?>
    <?= $document['contentHtml'] ?>
    <?= $document['regions']['footer'] ?>
    <?= $document['bodyAssetsHtml'] ?>
</body>
</html>
