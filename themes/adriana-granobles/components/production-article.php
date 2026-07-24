<?php
/** Production Article wrapper around CMS-prepared compatibility HTML. */
$article = $redThemeArticleContext ?? null;
if (!is_array($article) || ($article['mode'] ?? '') !== 'production' || !is_string($article['html'] ?? null)) {
    throw new RuntimeException('Adriana production Article context is unavailable.');
}
?>
<div class="redcms-component redcms-component--article redcms-live-component" data-red-component="Article" data-reveal>
    <div class="redcms-legacy-content"><?= $article['html'] ?></div>
</div>
