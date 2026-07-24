<?php
/** Production three-level navigation. Data is prepared by RED-CMS core. */
$navigation = $redThemeNavigationContext ?? null;
if (!is_array($navigation)
    || ($navigation['mode'] ?? '') !== 'production'
    || !is_array($navigation['items'] ?? null)
    || !is_bool($navigation['breadcrumbsEnabled'] ?? null)
) {
    throw new RuntimeException('Adriana production navigation context is unavailable.');
}
?>
<nav id="site-nav" class="site-nav" aria-label="Navegación principal" data-site-nav>
    <?php foreach ($navigation['items'] as $rootIndex => $item) : ?>
        <?php
        $rootTarget = (string) ($item['newWindow'] ?? '');
        $rootActive = strpos((string) ($item['itemClass'] ?? ''), 'active') !== false;
        $rootChildren = is_array($item['children'] ?? null) ? $item['children'] : [];
        ?>
        <?php if ($rootChildren === []) : ?>
            <a
                href="<?= htmlspecialchars((string) $item['link'], ENT_QUOTES, 'UTF-8') ?>"
                class="<?= $rootActive ? 'is-active' : '' ?>"
                <?php if ($rootTarget !== '') : ?>target="<?= htmlspecialchars($rootTarget, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                <?php if ($rootTarget === '_blank') : ?>rel="noopener"<?php endif; ?>
                <?php if ($rootActive) : ?>aria-current="page"<?php endif; ?>
            ><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php else : ?>
            <div class="nav-group<?= $rootActive ? ' is-active' : '' ?>" data-nav-group>
                <a
                    class="nav-group__label<?= $rootActive ? ' is-active' : '' ?>"
                    href="<?= htmlspecialchars((string) $item['link'], ENT_QUOTES, 'UTF-8') ?>"
                    <?php if ($rootTarget !== '') : ?>target="<?= htmlspecialchars($rootTarget, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                    <?php if ($rootTarget === '_blank') : ?>rel="noopener"<?php endif; ?>
                    <?php if ($rootActive) : ?>aria-current="page"<?php endif; ?>
                ><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <button
                    class="nav-group__button"
                    type="button"
                    aria-expanded="false"
                    aria-controls="adriana-nav-<?= (int) $rootIndex ?>"
                    data-dropdown-toggle
                ><span class="sr-only">Mostrar opciones de <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></span></button>
                <div class="nav-dropdown" id="adriana-nav-<?= (int) $rootIndex ?>">
                    <?php foreach ($rootChildren as $child) : ?>
                        <?php
                        $childTarget = (string) ($child['newWindow'] ?? '');
                        $childActive = strpos((string) ($child['itemClass'] ?? ''), 'active') !== false;
                        $grandchildren = is_array($child['children'] ?? null) ? $child['children'] : [];
                        ?>
                        <div class="nav-dropdown__item<?= $grandchildren === [] ? '' : ' nav-dropdown__item--nested' ?>">
                            <a
                                href="<?= htmlspecialchars((string) $child['link'], ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= $childActive ? 'is-active' : '' ?>"
                                <?php if ($childTarget !== '') : ?>target="<?= htmlspecialchars($childTarget, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                                <?php if ($childTarget === '_blank') : ?>rel="noopener"<?php endif; ?>
                                <?php if ($childActive) : ?>aria-current="page"<?php endif; ?>
                            ><?= htmlspecialchars((string) $child['label'], ENT_QUOTES, 'UTF-8') ?></a>
                            <?php if ($grandchildren !== []) : ?>
                                <div class="nav-dropdown__nested" aria-label="Opciones de <?= htmlspecialchars((string) $child['label'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($grandchildren as $grandchild) : ?>
                                        <?php
                                        $grandchildTarget = (string) ($grandchild['newWindow'] ?? '');
                                        $grandchildActive = strpos((string) ($grandchild['itemClass'] ?? ''), 'active') !== false;
                                        ?>
                                        <a
                                            href="<?= htmlspecialchars((string) $grandchild['link'], ENT_QUOTES, 'UTF-8') ?>"
                                            class="<?= $grandchildActive ? 'is-active' : '' ?>"
                                            <?php if ($grandchildTarget !== '') : ?>target="<?= htmlspecialchars($grandchildTarget, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                                            <?php if ($grandchildTarget === '_blank') : ?>rel="noopener"<?php endif; ?>
                                            <?php if ($grandchildActive) : ?>aria-current="page"<?php endif; ?>
                                        ><?= htmlspecialchars((string) $grandchild['label'], ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?php if (!$navigation['breadcrumbsEnabled']) : ?>
<nav class="spacer-like-breadcrumb" aria-label="Miga de pan"></nav>
<?php endif; ?>
