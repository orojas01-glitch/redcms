<?php
/** Fixture-preview Article view. Data is prepared and validated by RED-CMS core. */
$article = $redThemeArticleContext;
?>
<?php if (isset($article['bodyHtml'])) : ?>
<article id="instructions-manual" class="starter-component starter-component--article">
    <p class="starter-component__label">Trusted Article component</p>
    <h2><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <?= $article['bodyHtml'] ?>
</article>
<?php else : ?>
<article id="overview" class="starter-component starter-component--article">
    <p class="starter-component__label">Article component</p>
    <h2><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <p><?= htmlspecialchars($article['summary'], ENT_QUOTES, 'UTF-8') ?></p>
    <a class="starter-text-link" href="<?= htmlspecialchars($article['url'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($article['linkLabel'], ENT_QUOTES, 'UTF-8') ?>
        <span aria-hidden="true">&rarr;</span>
    </a>
</article>
<?php endif; ?>
