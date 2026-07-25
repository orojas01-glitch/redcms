<?php
/** Fixture-preview feature-grid layout. Slots contain core-rendered component HTML. */
$layout = $redThemeLayoutContext;
?>
<main id="preview-content" class="starter-layout starter-layout--feature-grid" tabindex="-1">
    <nav class="starter-breadcrumb" aria-label="Breadcrumb">
        <ol>
            <?php foreach ($layout['breadcrumb'] as $item) : ?>
                <li>
                    <?php if ($item['url'] !== '') : ?>
                        <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
                    <?php else : ?>
                        <span aria-current="page"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <section class="starter-layout__slot" aria-label="Lead feature"><?= $layout['slots'][1] ?></section>
    <div class="starter-layout__row starter-layout__row--three">
        <section class="starter-layout__slot" aria-label="Left card"><?= $layout['slots'][2] ?></section>
        <section class="starter-layout__slot" aria-label="Center card"><?= $layout['slots'][3] ?></section>
        <section class="starter-layout__slot" aria-label="Right card"><?= $layout['slots'][4] ?></section>
    </div>
    <section class="starter-layout__slot" aria-label="Closing row"><?= $layout['slots'][5] ?></section>
</main>
