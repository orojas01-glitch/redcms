<?php
$layout = $redThemeLayoutContext ?? null;
if (!is_array($layout) || ($layout['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Production index-3 layout context is unavailable.');
}
?>
<main id="main-content" class="starter-layout starter-layout--index-3" tabindex="-1">
    <?php if ($layout['breadcrumb'] !== []) : ?><nav class="starter-breadcrumb" aria-label="Breadcrumb"><ol><?php foreach ($layout['breadcrumb'] as $item) : ?><li><?php if ($item['url'] !== '') : ?><a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a><?php else : ?><span aria-current="page"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></li><?php endforeach; ?></ol></nav><?php endif; ?>
    <div class="starter-layout__row starter-layout__row--sidebar">
        <section class="starter-layout__slot" aria-label="Main content"><div class="starter-layout__row"><?= $layout['slots'][1] ?></div></section>
        <aside class="starter-layout__slot" aria-label="Right sidebar"><div class="starter-layout__row"><?= $layout['slots'][2] ?></div></aside>
    </div>
</main>

