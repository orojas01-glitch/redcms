<?php
/** Fixture-preview Gallery view. Data is prepared and validated by RED-CMS core. */
$gallery = $redThemeGalleryContext;
if (isset($gallery['video'])) {
?>
<section id="preview-gallery" class="starter-component starter-component--gallery" aria-labelledby="preview-gallery-title">
    <p class="starter-component__label">Gallery component</p>
    <h2 id="preview-gallery-title"><?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <div
        class="starter-video-contract"
        role="group"
        aria-label="<?= htmlspecialchars($gallery['video']['providerLabel'], ENT_QUOTES, 'UTF-8') ?> video preview"
        data-video-provider="<?= htmlspecialchars($gallery['video']['provider'], ENT_QUOTES, 'UTF-8') ?>"
        data-video-id="<?= htmlspecialchars($gallery['video']['id'], ENT_QUOTES, 'UTF-8') ?>"
    >
        <p class="starter-video-contract__provider">
            <?= htmlspecialchars($gallery['video']['providerLabel'], ENT_QUOTES, 'UTF-8') ?> video
        </p>
        <p class="starter-video-contract__caption">
            <?= htmlspecialchars($gallery['video']['caption'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p class="starter-video-contract__status">
            External playback is intentionally disabled in this offline safety preview.
        </p>
    </div>
</section>
<?php
    return;
}
?>
<section id="preview-gallery" class="starter-component starter-component--gallery" aria-labelledby="preview-gallery-title">
    <p class="starter-component__label">Gallery component</p>
    <h2 id="preview-gallery-title"><?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <ul class="starter-gallery">
        <?php foreach ($gallery['items'] as $item) : ?>
            <li>
                <figure>
                    <img
                        src="<?= htmlspecialchars($item['src'], ENT_QUOTES, 'UTF-8') ?>"
                        alt=""
                        width="720"
                        height="450"
                    >
                    <figcaption><?= htmlspecialchars($item['caption'], ENT_QUOTES, 'UTF-8') ?></figcaption>
                </figure>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
