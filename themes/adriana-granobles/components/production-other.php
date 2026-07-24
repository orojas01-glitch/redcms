<?php
/** Production Other wrapper around CMS-prepared compatibility HTML. */
$other = $redThemeOtherContext ?? null;
if (!is_array($other) || ($other['mode'] ?? '') !== 'production' || !is_string($other['html'] ?? null)) {
    throw new RuntimeException('Adriana production Other context is unavailable.');
}
?>
<?php if (strpos($other['html'], 'data-redcms-source-page=') !== false
    || strpos($other['html'], 'data-redcms-source-section=') !== false
) : ?>
    <?= $other['html'] ?>
<?php else : ?>
    <div class="redcms-component redcms-component--other redcms-live-component" data-red-component="Other" data-reveal>
        <div class="redcms-legacy-content"><?= $other['html'] ?></div>
    </div>
<?php endif; ?>
