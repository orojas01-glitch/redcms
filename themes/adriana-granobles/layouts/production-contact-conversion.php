<?php
$layout = $redThemeLayoutContext ?? null;
if (!is_array($layout) || ($layout['mode'] ?? '') !== 'production') {
    throw new RuntimeException('Adriana contact-conversion layout context is unavailable.');
}
$sourcePageHtml = (string) ($layout['slots'][1] ?? '');
$distributedSourceSectionHtml = implode('', array_map('strval', array_slice($layout['slots'], 1, null, true)));
$isSourceSectionPage = strpos($sourcePageHtml, 'data-redcms-source-section=') !== false
    && strpos($distributedSourceSectionHtml, 'data-redcms-source-section=') !== false;
$isSourcePage = !$isSourceSectionPage
    && strpos($sourcePageHtml, 'data-redcms-source-page=') !== false;
$sourceOutputHtml = $sourcePageHtml;
if ($isSourceSectionPage) {
    $sourceOutputHtml = $sourcePageHtml;
    foreach ($layout['slots'] as $position => $slotHtml) {
        if ((int) $position !== 1 && (int) $position !== 2) {
            $sourceOutputHtml .= (string) $slotHtml;
        }
    }
}
if ($isSourcePage || $isSourceSectionPage) {
    $nativeFormHtml = (string) ($layout['slots'][2] ?? '');
    $replacementCount = 0;
    $sourceOutputHtml = preg_replace_callback(
        '/<div\b[^>]*data-redcms-native-form-anchor(?:="[^"]*")?[^>]*>\s*<\/div>/i',
        static function () use ($nativeFormHtml) {
            return $nativeFormHtml;
        },
        $sourceOutputHtml,
        1,
        $replacementCount
    );
    if (!is_string($sourceOutputHtml) || $replacementCount !== 1 || trim($nativeFormHtml) === '') {
        throw new RuntimeException('Adriana migrated Contact page requires exactly one native Form anchor and component.');
    }
}
?>
<main id="main-content" class="adriana-layout adriana-layout--contact-conversion" tabindex="-1">
    <?php if ($isSourcePage || $isSourceSectionPage) : ?>
        <?= $sourceOutputHtml ?>
    <?php else : ?>
    <?php if ($layout['breadcrumb'] !== []) : ?><nav class="breadcrumb wrapper" aria-label="Miga de pan"><ol><?php foreach ($layout['breadcrumb'] as $item) : ?><li><?php if ($item['url'] !== '') : ?><a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a><?php else : ?><span aria-current="page"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></li><?php endforeach; ?></ol></nav><?php endif; ?>
    <section class="section"><div class="wrapper adriana-layout__slot"><?= $layout['slots'][1] ?></div></section>
    <section class="section ivory"><div class="wrapper contact-grid"><div class="adriana-layout__slot"><?= $layout['slots'][2] ?></div><aside class="adriana-layout__slot"><?= $layout['slots'][3] ?></aside></div></section>
    <?php endif; ?>
</main>
