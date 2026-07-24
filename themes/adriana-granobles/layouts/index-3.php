<?php $layout = $redThemeLayoutContext; ?>
<main id="preview-content" class="adriana-layout adriana-layout--index-3" tabindex="-1">
    <nav class="breadcrumb wrapper" aria-label="Miga de pan"><ol><?php foreach ($layout['breadcrumb'] as $item) : ?><li><?php if ($item['url'] !== '') : ?><a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a><?php else : ?><span aria-current="page"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></li><?php endforeach; ?></ol></nav>
    <section class="section"><div class="wrapper two-column adriana-layout__sidebar-row"><div class="adriana-layout__slot"><?= $layout['slots'][1] ?></div><aside class="adriana-layout__slot adriana-layout__sidebar"><?= $layout['slots'][2] ?></aside></div></section>
</main>
