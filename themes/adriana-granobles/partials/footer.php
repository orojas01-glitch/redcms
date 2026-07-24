<?php
/** Fixture-preview footer. Data is prepared and validated by RED-CMS core. */
$footer = $redThemeFooterContext;
?>
<footer class="site-footer preview-footer">
    <div class="footer-note wrapper">
        <small><?= htmlspecialchars($footer['copyright'], ENT_QUOTES, 'UTF-8') ?></small>
        <small>Local RED-CMS theme fixture</small>
    </div>
</footer>
