<?php
/** Production header region. Data is prepared by RED-CMS core. */
$header = $redThemeHeaderContext ?? null;
if (!is_array($header) || ($header['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Adriana production header context is unavailable.');
}
$siteTitle = (string) ($header['siteTitle'] ?? 'Adriana Granobles');
$logo = is_array($header['logo'] ?? null) ? $header['logo'] : null;
$logoUrl = $logo !== null
    ? (string) $logo['url']
    : '/themes/adriana-granobles/assets/images/logo.png';
$logoWidth = $logo !== null ? (int) $logo['width'] : 264;
$logoHeight = $logo !== null ? (int) $logo['height'] : 104;
?>
<header class="site-header site-header--solid" data-site-header>
    <a class="brand" href="<?= htmlspecialchars((string) $header['homeUrl'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?>, inicio">
        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?>" width="<?= $logoWidth ?>" height="<?= $logoHeight ?>">
    </a>
    <?php if ((string) ($header['customHtml'] ?? '') !== '') : ?>
        <div class="site-header__custom"><?= $header['customHtml'] ?></div>
    <?php endif; ?>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-menu-toggle>
        <span class="menu-toggle__bar" aria-hidden="true"></span>
        <span class="sr-only">Abrir menú</span>
    </button>
</header>
