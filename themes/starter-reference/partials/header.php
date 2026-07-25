<?php
/** Fixture-preview header view. Data is prepared and validated by RED-CMS core. */
$header = $redThemeHeaderContext;
?>
<header class="starter-shell starter-header">
    <a class="starter-brand" href="<?= htmlspecialchars($header['homeUrl'], ENT_QUOTES, 'UTF-8') ?>">
        <span class="starter-brand__mark" aria-hidden="true">R</span>
        <span><?= htmlspecialchars($header['siteTitle'], ENT_QUOTES, 'UTF-8') ?></span>
    </a>
    <span class="starter-header__label">Theme contract preview</span>
</header>
