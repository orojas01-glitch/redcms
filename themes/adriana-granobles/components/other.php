<?php
/** Fixture-preview Other view. Data is prepared and validated by RED-CMS core. */
$other = $redThemeOtherContext;
?>
<aside id="preview-notes" class="redcms-component redcms-component--other method-panel" aria-labelledby="preview-notes-title" data-reveal>
    <div><p class="section-kicker">Plantilla compatible</p><h2 id="preview-notes-title"><?= htmlspecialchars($other['title'], ENT_QUOTES, 'UTF-8') ?></h2></div>
    <p><?= htmlspecialchars($other['text'], ENT_QUOTES, 'UTF-8') ?></p>
</aside>
