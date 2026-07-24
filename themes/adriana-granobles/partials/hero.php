<?php
/** Fixture-preview hero. Data is prepared and validated by RED-CMS core. */
$hero = $redThemeHeroContext;
?>
<section class="hero hero--home hero--preview" aria-labelledby="adriana-preview-hero-title">
    <div class="hero__scrim" aria-hidden="true"></div>
    <div class="hero__content wrapper" data-reveal>
        <div class="ornament" aria-hidden="true"><span></span></div>
        <p class="section-kicker">Voz, música y transformación</p>
        <h1 id="adriana-preview-hero-title"><?= htmlspecialchars($hero['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($hero['summary'], ENT_QUOTES, 'UTF-8') ?></p>
        <div class="hero__actions">
            <a class="button button--primary" href="<?= htmlspecialchars($hero['action']['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($hero['action']['label'], ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</section>
