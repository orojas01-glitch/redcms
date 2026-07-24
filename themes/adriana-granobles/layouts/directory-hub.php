<?php $layout = $redThemeLayoutContext; ?>
<main id="preview-content" class="adriana-layout adriana-layout--directory-hub" tabindex="-1">
    <nav class="breadcrumb wrapper" aria-label="Miga de pan"><ol><?php foreach ($layout['breadcrumb'] as $item) : ?><li><?php if ($item['url'] !== '') : ?><a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a><?php else : ?><span aria-current="page"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></li><?php endforeach; ?></ol></nav>
    <section class="section"><div class="wrapper two-column adriana-layout__slot"><?= $layout['slots'][1] ?></div></section>
    <section class="section ivory"><div class="wrapper instrument-grid adriana-layout__slot"><?= $layout['slots'][2] ?></div></section>
    <section class="section dark-band"><div class="wrapper method-panel adriana-layout__slot"><?= $layout['slots'][3] ?></div></section>
    <section class="section"><div class="wrapper faq adriana-layout__slot"><?= $layout['slots'][4] ?></div></section>
    <section class="section cta-band"><div class="wrapper adriana-layout__slot"><?= $layout['slots'][5] ?></div></section>
</main>
