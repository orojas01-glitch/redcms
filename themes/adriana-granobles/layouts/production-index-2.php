<?php
$layout = $redThemeLayoutContext ?? null;
if (!is_array($layout) || ($layout['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Adriana index-2 layout context is unavailable.');
}
?>
<main id="main-content" class="adriana-layout adriana-layout--index-2" tabindex="-1">
    <?php if ($layout['breadcrumb'] !== []) : ?><nav class="breadcrumb wrapper" aria-label="Miga de pan"><ol><?php foreach ($layout['breadcrumb'] as $item) : ?><li><?php if ($item['url'] !== '') : ?><a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a><?php else : ?><span aria-current="page"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></li><?php endforeach; ?></ol></nav><?php endif; ?>
    <section class="section"><div class="wrapper adriana-layout__slot"><?= $layout['slots'][1] ?></div></section>
    <section class="section ivory"><div class="wrapper adriana-layout__slot"><?= $layout['slots'][2] ?></div></section>
    <section class="section dark-band"><div class="wrapper adriana-layout__slot"><?= $layout['slots'][3] ?></div></section>
    <section class="section"><div class="wrapper adriana-layout__slot"><?= $layout['slots'][4] ?></div></section>
</main>
