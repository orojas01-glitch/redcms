<?php
/** Fixture-preview footer view. Data is prepared and validated by RED-CMS core. */
$footer = $redThemeFooterContext;
?>
<footer class="starter-shell starter-footer">
    <small><?= htmlspecialchars($footer['copyright'], ENT_QUOTES, 'UTF-8') ?></small>
    <small>Generated from local fixture data</small>
</footer>
