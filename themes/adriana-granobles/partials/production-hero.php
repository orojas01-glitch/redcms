<?php
/** Production feature hero. Data is prepared by RED-CMS core. */
$hero = $redThemeHeroContext ?? null;
if (!is_array($hero)
    || ($hero['mode'] ?? '') !== 'production'
    || !is_array($hero['slides'] ?? null)
) {
    throw new RuntimeException('Adriana production hero context is unavailable.');
}
if (empty($hero['enabled']) || $hero['slides'] === []) {
    return;
}
$slides = array_values($hero['slides']);
$slideCount = count($slides);
?>
<section
    class="hero hero--home hero--cms"
    data-adriana-hero-slider
    aria-roledescription="carrusel"
    aria-label="Contenido destacado"
>
    <div class="hero-slider__viewport">
        <?php foreach ($slides as $index => $slide) : ?>
            <?php
            $title = (string) ($slide['title'] ?? '');
            $description = (string) ($slide['description'] ?? '');
            $link = (string) ($slide['link'] ?? '');
            $target = ($slide['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
            $image = rawurlencode(basename((string) ($slide['image'] ?? '')));
            ?>
            <article
                class="hero-slide<?= $index === 0 ? ' is-active' : '' ?>"
                data-adriana-hero-slide
                role="group"
                aria-roledescription="diapositiva"
                aria-label="<?= (int) $index + 1 ?> de <?= $slideCount ?>"
                <?= $index === 0 ? '' : 'hidden' ?>
            >
                <?php if ($image !== '') : ?>
                    <div class="hero__media" aria-hidden="true">
                        <img
                            src="/images/articles/<?= $image ?>"
                            alt=""
                            width="1920"
                            height="1080"
                            <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                            decoding="async"
                        >
                    </div>
                <?php endif; ?>
                <div class="hero__scrim" aria-hidden="true"></div>
                <div class="hero__content wrapper" data-reveal>
                    <div class="ornament" aria-hidden="true"><span></span></div>
                    <p class="section-kicker">Destacado</p>
                    <?php if ($title !== '') : ?><h1 id="adriana-hero-title-<?= (int) $index + 1 ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1><?php endif; ?>
                    <?php if ($description !== '') : ?><p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    <?php if ($link !== '') : ?>
                        <div class="hero__actions">
                            <a class="button button--primary" href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" target="<?= $target ?>"<?= $target === '_blank' ? ' rel="noopener"' : '' ?>>Conocer más</a>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($slideCount > 1) : ?>
        <div class="hero-slider__controls" aria-label="Controles del carrusel">
            <button class="hero-slider__control" type="button" data-adriana-hero-previous>
                <span aria-hidden="true">←</span><span class="sr-only">Diapositiva anterior</span>
            </button>
            <div class="hero-slider__dots" aria-label="Elegir diapositiva">
                <?php foreach ($slides as $index => $slide) : ?>
                    <button
                        class="hero-slider__dot<?= $index === 0 ? ' is-active' : '' ?>"
                        type="button"
                        data-adriana-hero-go-to="<?= (int) $index ?>"
                        aria-label="Mostrar diapositiva <?= (int) $index + 1 ?> de <?= $slideCount ?>"
                        <?= $index === 0 ? 'aria-current="true"' : '' ?>
                    ></button>
                <?php endforeach; ?>
            </div>
            <p class="hero-slider__status" aria-live="polite" aria-atomic="true"><span data-adriana-hero-current>1</span> / <?= $slideCount ?></p>
            <button class="hero-slider__control" type="button" data-adriana-hero-next>
                <span aria-hidden="true">→</span><span class="sr-only">Diapositiva siguiente</span>
            </button>
        </div>
    <?php endif; ?>
</section>
