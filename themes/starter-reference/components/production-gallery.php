<?php
/** Production Gallery wrapper around CMS-prepared compatibility HTML. */
$gallery = $redThemeGalleryContext ?? null;
if (!is_array($gallery) || ($gallery['mode'] ?? '') !== 'production' || !is_string($gallery['html'] ?? null)) {
    throw new RuntimeException('Production Gallery context is unavailable.');
}
?>
<section class="starter-component starter-component--gallery starter-live-component" data-red-component="Gallery">
    <div class="starter-legacy-content"><?= $gallery['html'] ?></div>
</section>

