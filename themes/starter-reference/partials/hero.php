<?php
/** Fixture-preview hero view. Data is prepared and validated by RED-CMS core. */
$hero = $redThemeHeroContext;
?>
<section class="starter-shell starter-hero" aria-labelledby="starter-hero-title">
    <p class="starter-eyebrow">Portable theme architecture</p>
    <h1 id="starter-hero-title"><?= htmlspecialchars($hero['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="starter-hero__summary"><?= htmlspecialchars($hero['summary'], ENT_QUOTES, 'UTF-8') ?></p>
    <a class="starter-action" href="<?= htmlspecialchars($hero['action']['url'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($hero['action']['label'], ENT_QUOTES, 'UTF-8') ?>
        <span aria-hidden="true">&rarr;</span>
    </a>
</section>
