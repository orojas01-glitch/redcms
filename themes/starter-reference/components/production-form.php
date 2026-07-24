<?php
/** Production Form wrapper preserving CMS-owned operational behavior. */
$form = $redThemeFormContext ?? null;
if (!is_array($form) || ($form['mode'] ?? '') !== 'production' || !is_string($form['html'] ?? null)) {
    throw new RuntimeException('Production Form context is unavailable.');
}
?>
<section class="starter-component starter-component--form starter-live-component" data-red-component="Form">
    <div class="starter-legacy-content"><?= $form['html'] ?></div>
</section>

