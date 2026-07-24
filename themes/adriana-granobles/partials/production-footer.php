<?php
/** Production footer region. Data is prepared by RED-CMS core. */
$footer = $redThemeFooterContext ?? null;
if (!is_array($footer) || ($footer['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Adriana production footer context is unavailable.');
}
?>
<footer class="site-footer">
    <?php if (strpos((string) ($footer['customHtml'] ?? ''), 'data-redcms-source-footer=') !== false) : ?>
        <?= $footer['customHtml'] ?>
    <?php else : ?>
    <div class="footer-grid wrapper">
        <div class="footer-brand">
            <img src="/themes/adriana-granobles/assets/images/logo.png" alt="Adriana Granobles" width="264" height="104" loading="lazy">
            <p>Voz, música y transformación con una experiencia editorial, cercana y accesible.</p>
        </div>
        <div>
            <h2>Adriana Granobles</h2>
            <?php if ((string) ($footer['customHtml'] ?? '') !== '') : ?>
                <div class="footer-custom"><?= $footer['customHtml'] ?></div>
            <?php else : ?>
                <p>Clases, programas artísticos, composición, producción y experiencias de voz.</p>
            <?php endif; ?>
        </div>
        <div>
            <h2>Plantilla RED-CMS</h2>
            <p>La navegación, el contenido, las galerías y los formularios permanecen administrados por el CMS.</p>
        </div>
    </div>
    <div class="footer-note wrapper">
        <small><?= htmlspecialchars((string) $footer['copyright'], ENT_QUOTES, 'UTF-8') ?></small>
        <small>Powered by RED-CMS</small>
    </div>
    <?php endif; ?>
    <?php red_public_render_red_sphere_credit($footer); ?>
</footer>
