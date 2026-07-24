<?php
/** Production Gallery wrapper around CMS-prepared compatibility HTML. */
$gallery = $redThemeGalleryContext ?? null;
if (!is_array($gallery) || ($gallery['mode'] ?? '') !== 'production' || !is_string($gallery['html'] ?? null)) {
    throw new RuntimeException('Adriana production Gallery context is unavailable.');
}
?>
<div class="redcms-component redcms-component--gallery redcms-live-component" data-red-component="Gallery" data-reveal>
    <div class="redcms-legacy-content"><?= $gallery['html'] ?></div>
</div>
