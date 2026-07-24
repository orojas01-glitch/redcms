<?php
if (!isset($redThemeHeroContext)
    || !is_array($redThemeHeroContext)
    || !array_key_exists('enabled', $redThemeHeroContext)
    || !isset($redThemeHeroContext['slides'])
    || !is_array($redThemeHeroContext['slides'])
) {
    throw new RuntimeException('Legacy hero context is unavailable.');
}

if (!$redThemeHeroContext['enabled'] || $redThemeHeroContext['slides'] === []) {
    return;
}

$slides = array_values($redThemeHeroContext['slides']);
$slideCount = count($slides);
?>
<section class="container red-hero" aria-label="Contenido destacado">
    <div id="red-hero-slider" class="carousel slide red-hero__carousel">
        <?php if ($slideCount > 1) : ?>
            <div class="carousel-indicators red-hero__indicators">
                <?php foreach ($slides as $index => $slide) : ?>
                    <button
                        type="button"
                        data-bs-target="#red-hero-slider"
                        data-bs-slide-to="<?= (int) $index ?>"
                        class="<?= $index === 0 ? 'active' : '' ?>"
                        <?= $index === 0 ? 'aria-current="true"' : '' ?>
                        aria-label="Mostrar diapositiva <?= (int) $index + 1 ?> de <?= $slideCount ?>"
                    ></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="carousel-inner">
            <?php foreach ($slides as $index => $slide) : ?>
                <?php
                $title = (string) ($slide['title'] ?? '');
                $description = (string) ($slide['description'] ?? '');
                $link = (string) ($slide['link'] ?? '');
                $target = ($slide['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                $image = rawurlencode(basename((string) ($slide['image'] ?? '')));
                ?>
                <article
                    class="carousel-item red-hero__slide<?= $index === 0 ? ' active' : '' ?>"
                    role="group"
                    aria-roledescription="diapositiva"
                    aria-label="<?= (int) $index + 1 ?> de <?= $slideCount ?>"
                >
                    <?php if ($image !== '') : ?>
                        <img
                            class="red-hero__image"
                            src="/images/articles/<?= $image ?>"
                            alt=""
                            <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                            decoding="async"
                        >
                    <?php endif; ?>
                    <div class="carousel-caption red-hero__caption">
                        <p class="red-hero__eyebrow">Destacado</p>
                        <?php if ($title !== '') : ?>
                            <h2 class="red-hero__title"><?= red_public_html($title) ?></h2>
                        <?php endif; ?>
                        <?php if ($description !== '') : ?>
                            <p class="red-hero__summary"><?= red_public_html($description) ?></p>
                        <?php endif; ?>
                        <?php if ($link !== '') : ?>
                            <a
                                href="<?= red_public_html($link) ?>"
                                target="<?= $target ?>"
                                <?= $target === '_blank' ? 'rel="noopener"' : '' ?>
                                class="red-hero__action"
                            >Leer más</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($slideCount > 1) : ?>
            <button class="carousel-control-prev red-hero__previous" type="button" data-bs-target="#red-hero-slider" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Diapositiva anterior</span>
            </button>
            <button class="carousel-control-next red-hero__next" type="button" data-bs-target="#red-hero-slider" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Diapositiva siguiente</span>
            </button>
        <?php endif; ?>
    </div>
</section>
