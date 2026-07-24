<?php
/** Fixture-preview index-3 layout. Slots contain core-rendered component HTML. */
$layout = $redThemeLayoutContext;
?>
<main id="preview-content" class="starter-layout starter-layout--index-3" tabindex="-1">
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
    <div class="starter-layout__row starter-layout__row--sidebar">
        <section class="starter-layout__slot" aria-label="Main content">
            <div class="starter-layout__row"><?= $layout['slots'][1] ?></div>
        </section>
        <aside class="starter-layout__slot" aria-label="Right sidebar">
            <div class="starter-layout__row"><?= $layout['slots'][2] ?></div>
        </aside>
    </div>
</main>
