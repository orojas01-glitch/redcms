<?php
/** Fixture-preview index-2 layout. Slots contain core-rendered component HTML. */
$layout = $redThemeLayoutContext;
?>
<main id="preview-content" class="starter-layout starter-layout--index-2" tabindex="-1">
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
    <section class="starter-layout__slot" aria-label="Row one"><?= $layout['slots'][1] ?></section>
    <section class="starter-layout__slot" aria-label="Row two"><?= $layout['slots'][2] ?></section>
    <section class="starter-layout__slot" aria-label="Row three"><?= $layout['slots'][3] ?></section>
    <section class="starter-layout__slot" aria-label="Row four"><?= $layout['slots'][4] ?></section>
</main>
