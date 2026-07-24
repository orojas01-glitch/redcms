<?php
/** Production Form wrapper preserving CMS-owned operational behavior. */
$form = $redThemeFormContext ?? null;
if (!is_array($form) || ($form['mode'] ?? '') !== 'production' || !is_string($form['html'] ?? null)) {
    throw new RuntimeException('Adriana production Form context is unavailable.');
}
$formHtml = str_replace('</label>&nbsp;<select', '</label><select', $form['html']);
?>
<div class="redcms-component redcms-component--form redcms-live-component" data-red-component="Form" data-reveal>
    <div class="redcms-legacy-content"><?= $formHtml ?></div>
</div>
