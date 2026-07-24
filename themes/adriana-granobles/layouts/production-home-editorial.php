<?php
$layout = $redThemeLayoutContext ?? null;
if (!is_array($layout) || ($layout['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Adriana home-editorial layout context is unavailable.');
}
$sourcePageHtml = (string) ($layout['slots'][1] ?? '');
$sourceSectionHtml = implode('', array_map('strval', $layout['slots']));
$distributedSourceSectionHtml = implode('', array_map('strval', array_slice($layout['slots'], 1, null, true)));
$isSourceSectionPage = strpos($sourcePageHtml, 'data-redcms-source-section=') !== false
    && strpos($distributedSourceSectionHtml, 'data-redcms-source-section=') !== false;
$isSourcePage = !$isSourceSectionPage
    && strpos($sourcePageHtml, 'data-redcms-source-page=') !== false;
?>
<main id="main-content" class="adriana-layout adriana-layout--home-editorial" tabindex="-1">
    <?php if ($isSourcePage) : ?>
        <?= $sourcePageHtml ?>
    <?php elseif ($isSourceSectionPage) : ?>
        <?= $sourceSectionHtml ?>
    <?php else : ?>
    <?php if ($layout['breadcrumb'] !== []) : ?><nav class="breadcrumb wrapper" aria-label="Miga de pan"><ol><?php foreach ($layout['breadcrumb'] as $item) : ?><li><?php if ($item['url'] !== '') : ?><a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a><?php else : ?><span aria-current="page"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></li><?php endforeach; ?></ol></nav><?php endif; ?>
    <section class="routes-section section ivory"><div class="wrapper route-grid adriana-layout__slot"><?= $layout['slots'][1] ?></div></section>
    <section class="section dark-band"><div class="wrapper split-section adriana-layout__slot"><?= $layout['slots'][2] ?></div></section>
    <section class="section"><div class="wrapper instrument-grid adriana-layout__slot"><?= $layout['slots'][3] ?></div></section>
    <section class="section testimonial-section ivory"><div class="wrapper adriana-layout__slot"><?= $layout['slots'][4] ?></div></section>
    <section class="section cta-band"><div class="wrapper adriana-layout__slot"><?= $layout['slots'][5] ?></div></section>
    <?php endif; ?>
</main>
