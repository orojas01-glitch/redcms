<?php
/** Fixture-preview Gallery view. Data is prepared and validated by RED-CMS core. */
$gallery = $redThemeGalleryContext;
?>
<?php if (isset($gallery['video'])) : ?>
<section id="preview-gallery" class="redcms-component redcms-component--gallery" aria-labelledby="preview-gallery-title" data-reveal>
    <p class="section-kicker">Galería y medios</p>
    <h2 id="preview-gallery-title"><?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="video-frame preview-video" role="group" aria-label="<?= htmlspecialchars($gallery['video']['providerLabel'], ENT_QUOTES, 'UTF-8') ?> video preview" data-video-provider="<?= htmlspecialchars($gallery['video']['provider'], ENT_QUOTES, 'UTF-8') ?>" data-video-id="<?= htmlspecialchars($gallery['video']['id'], ENT_QUOTES, 'UTF-8') ?>">
        <p><?= htmlspecialchars($gallery['video']['caption'], ENT_QUOTES, 'UTF-8') ?></p>
        <p class="template-note">La reproducción externa permanece desactivada en esta vista segura.</p>
    </div>
</section>
<?php return; endif; ?>
<section id="preview-gallery" class="redcms-component redcms-component--gallery" aria-labelledby="preview-gallery-title" data-reveal>
    <p class="section-kicker">Galería y medios</p>
    <h2 id="preview-gallery-title"><?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <ul class="preview-gallery-grid">
        <?php foreach ($gallery['items'] as $item) : ?>
            <li><figure><img src="<?= htmlspecialchars($item['src'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="720" height="450"><figcaption><?= htmlspecialchars($item['caption'], ENT_QUOTES, 'UTF-8') ?></figcaption></figure></li>
        <?php endforeach; ?>
    </ul>
</section>
