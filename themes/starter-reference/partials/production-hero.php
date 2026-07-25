<?php
/** Production feature hero. Data is prepared by RED-CMS core. */
$hero = $redThemeHeroContext ?? null;
if (!is_array($hero)
    || ($hero['mode'] ?? '') !== 'production'
    || !is_array($hero['slides'] ?? null)
) {
    throw new RuntimeException('Production hero context is unavailable.');
}
if (empty($hero['enabled']) || $hero['slides'] === []) {
    return;
}
$slides = array_values($hero['slides']);
$slideCount = count($slides);
?>
<section
    class="starter-shell starter-hero starter-hero--live starter-hero--slider"
    data-starter-hero-slider
    aria-roledescription="carrusel"
    aria-label="Contenido destacado"
>
    <div class="starter-hero__viewport">
        <?php foreach ($slides as $index => $slide) : ?>
            <?php
            $title = (string) ($slide['title'] ?? '');
            $description = (string) ($slide['description'] ?? '');
            $link = (string) ($slide['link'] ?? '');
            $target = ($slide['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
            $image = rawurlencode(basename((string) ($slide['image'] ?? '')));
            ?>
            <article
                class="starter-hero__slide<?= $index === 0 ? ' is-active' : '' ?>"
                data-starter-hero-slide
                role="group"
                aria-roledescription="diapositiva"
                aria-label="<?= (int) $index + 1 ?> de <?= $slideCount ?>"
                <?= $index === 0 ? '' : 'hidden' ?>
            >
                <?php if ($image !== '') : ?>
                    <img
                        class="starter-hero__media"
                        src="/images/articles/<?= $image ?>"
                        alt=""
                        <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                        decoding="async"
                    >
                <?php endif; ?>
                <div class="starter-hero__content">
                    <p class="starter-eyebrow">Destacado</p>
                    <?php if ($title !== '') : ?>
                        <h1 id="starter-hero-title-<?= (int) $index + 1 ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php endif; ?>
                    <?php if ($description !== '') : ?>
                        <p class="starter-hero__summary"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($link !== '') : ?>
                        <a
                            class="starter-action"
                            href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"
                            target="<?= $target ?>"
                            <?= $target === '_blank' ? 'rel="noopener"' : '' ?>
                        >Leer más <span aria-hidden="true">&rarr;</span></a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($slideCount > 1) : ?>
        <div class="starter-hero__controls" aria-label="Controles del carrusel">
            <button class="starter-hero__control" type="button" data-starter-hero-previous>
                <span aria-hidden="true">&larr;</span>
                <span class="starter-visually-hidden">Diapositiva anterior</span>
            </button>
            <div class="starter-hero__dots" aria-label="Elegir diapositiva">
                <?php foreach ($slides as $index => $slide) : ?>
                    <button
                        class="starter-hero__dot<?= $index === 0 ? ' is-active' : '' ?>"
                        type="button"
                        data-starter-hero-go-to="<?= (int) $index ?>"
                        aria-label="Mostrar diapositiva <?= (int) $index + 1 ?> de <?= $slideCount ?>"
                        <?= $index === 0 ? 'aria-current="true"' : '' ?>
                    ></button>
                <?php endforeach; ?>
            </div>
            <p class="starter-hero__status" aria-live="polite" aria-atomic="true">
                <span data-starter-hero-current>1</span> / <?= $slideCount ?>
            </p>
            <button class="starter-hero__control" type="button" data-starter-hero-next>
                <span aria-hidden="true">&rarr;</span>
                <span class="starter-visually-hidden">Diapositiva siguiente</span>
            </button>
        </div>
    <?php endif; ?>
</section>
