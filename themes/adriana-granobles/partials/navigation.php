<?php
/** Fixture-preview navigation. Data is prepared and validated by RED-CMS core. */
$navigation = $redThemeNavigationContext;
?>
<nav class="site-nav preview-navigation" aria-label="Navegación principal">
    <?php foreach ($navigation['items'] as $item) : ?>
        <a
            href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
            <?php if ($item['current']) : ?>class="is-active" aria-current="page"<?php endif; ?>
        ><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
</nav>
