<?php
/** Production footer region. Data is prepared by RED-CMS core. */
$footer = $redThemeFooterContext ?? null;
if (!is_array($footer) || ($footer['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Production footer context is unavailable.');
}
?>
<footer class="starter-shell starter-footer">
    <div>
        <?php if ((string) ($footer['customHtml'] ?? '') !== '') : ?><?= $footer['customHtml'] ?><?php endif; ?>
        <small><?= htmlspecialchars((string) $footer['copyright'], ENT_QUOTES, 'UTF-8') ?></small>
    </div>
    <small>Powered by RED-CMS</small>
    <?php red_public_render_red_sphere_credit($footer); ?>
</footer>
