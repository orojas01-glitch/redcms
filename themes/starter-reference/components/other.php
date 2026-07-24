<?php
/** Fixture-preview Other view. Data is prepared and validated by RED-CMS core. */
$other = $redThemeOtherContext;
?>
<section id="preview-notes" class="starter-component starter-component--other" aria-labelledby="preview-notes-title">
    <p class="starter-component__label">Other component</p>
    <h2 id="preview-notes-title"><?= htmlspecialchars($other['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <p><?= htmlspecialchars($other['text'], ENT_QUOTES, 'UTF-8') ?></p>
</section>
