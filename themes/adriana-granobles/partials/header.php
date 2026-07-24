<?php
/** Fixture-preview header. Data is prepared and validated by RED-CMS core. */
$header = $redThemeHeaderContext;
?>
<header class="site-header site-header--solid preview-header">
    <a class="brand brand--preview" href="<?= htmlspecialchars($header['homeUrl'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($header['siteTitle'], ENT_QUOTES, 'UTF-8') ?>, inicio">
        <span class="brand__preview-mark" aria-hidden="true">AG</span>
        <span><?= htmlspecialchars($header['siteTitle'], ENT_QUOTES, 'UTF-8') ?></span>
    </a>
</header>
