<?php
/** Fixture-preview navigation view. Data is prepared and validated by RED-CMS core. */
$navigation = $redThemeNavigationContext;
?>
<nav class="starter-shell starter-navigation" aria-label="Primary navigation">
    <ul>
        <?php foreach ($navigation['items'] as $item) : ?>
            <li>
                <a
                    href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                    <?php if ($item['current']) : ?>aria-current="page"<?php endif; ?>
                ><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
