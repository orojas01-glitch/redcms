<?php
/** Production Article wrapper around CMS-prepared compatibility HTML. */
$article = $redThemeArticleContext ?? null;
if (!is_array($article) || ($article['mode'] ?? '') !== 'production' || !is_string($article['html'] ?? null)) {
    throw new RuntimeException('Production Article context is unavailable.');
}
?>
<section class="starter-component starter-component--article starter-live-component" data-red-component="Article">
    <div class="starter-legacy-content"><?= $article['html'] ?></div>
</section>

