<?php
/** Production Other wrapper around CMS-prepared compatibility HTML. */
$other = $redThemeOtherContext ?? null;
if (!is_array($other) || ($other['mode'] ?? '') !== 'production' || !is_string($other['html'] ?? null)) {
    throw new RuntimeException('Production Other context is unavailable.');
}
?>
<section class="starter-component starter-component--other starter-live-component" data-red-component="Other">
    <div class="starter-legacy-content"><?= $other['html'] ?></div>
</section>

