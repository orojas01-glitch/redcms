<?php
/** Production header region. Data is prepared by RED-CMS core. */
$header = $redThemeHeaderContext ?? null;
if (!is_array($header) || ($header['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Production header context is unavailable.');
}
$logo = is_array($header['logo'] ?? null) ? $header['logo'] : null;
?>
<header class="starter-shell starter-header">
    <a class="starter-brand" href="<?= htmlspecialchars((string) $header['homeUrl'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars((string) $header['siteTitle'], ENT_QUOTES, 'UTF-8') ?>, home">
        <?php if ($logo !== null) : ?>
            <img
                class="starter-brand__logo"
                src="<?= htmlspecialchars((string) $logo['url'], ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars((string) $header['siteTitle'], ENT_QUOTES, 'UTF-8') ?>"
                width="<?= (int) $logo['width'] ?>"
                height="<?= (int) $logo['height'] ?>"
            >
        <?php else : ?>
            <span class="starter-brand__mark" aria-hidden="true">R</span>
            <span><?= htmlspecialchars((string) $header['siteTitle'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </a>
    <?php if ((string) ($header['customHtml'] ?? '') !== '') : ?>
        <div class="starter-header__custom"><?= $header['customHtml'] ?></div>
    <?php endif; ?>
</header>
