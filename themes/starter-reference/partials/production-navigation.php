<?php
/** Production three-level navigation. Data is prepared by RED-CMS core. */
$navigation = $redThemeNavigationContext ?? null;
if (!is_array($navigation)
    || ($navigation['mode'] ?? '') !== 'production'
    || !is_array($navigation['items'] ?? null)
) {
    throw new RuntimeException('Production navigation context is unavailable.');
}

$starterNavigationItemIsActive = static function (array $item) {
    return preg_match('/(?:^|\s)active(?:\s|$)/', (string) ($item['itemClass'] ?? '')) === 1;
};

$starterNavigationHasActiveDescendant = static function (array $item) use (&$starterNavigationHasActiveDescendant, $starterNavigationItemIsActive) {
    foreach ($item['children'] ?? [] as $child) {
        if (!is_array($child)) {
            continue;
        }
        if ($starterNavigationItemIsActive($child) || $starterNavigationHasActiveDescendant($child)) {
            return true;
        }
    }

    return false;
};

$renderStarterNavigationItems = static function (
    array $items,
    $level = 1,
    $path = 'root',
    $listId = ''
) use (
    &$renderStarterNavigationItems,
    $starterNavigationItemIsActive,
    $starterNavigationHasActiveDescendant
) {
    $listClasses = $level === 1
        ? 'starter-navigation__list starter-navigation__list--level-1'
        : 'starter-navigation__submenu starter-navigation__submenu--level-' . $level;
    ?>
    <ul class="<?= $listClasses ?>"<?= $listId !== '' ? ' id="' . htmlspecialchars($listId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <?php foreach ($items as $index => $item) : ?>
            <?php
            if (!is_array($item)) {
                continue;
            }
            $children = array_values(array_filter(
                is_array($item['children'] ?? null) ? $item['children'] : [],
                'is_array'
            ));
            $hasChildren = $children !== [];
            $isActive = $starterNavigationItemIsActive($item);
            $isCurrent = $isActive && !$starterNavigationHasActiveDescendant($item);
            $itemPath = $path . '-' . ((int) $index + 1);
            $submenuId = 'starter-navigation-submenu-' . $itemPath;
            $itemClasses = [
                'starter-navigation__item',
                'starter-navigation__item--level-' . $level,
            ];
            if ($hasChildren) {
                $itemClasses[] = 'starter-navigation__item--has-children';
            }
            if ($isActive) {
                $itemClasses[] = 'is-active';
            }
            $target = (string) ($item['newWindow'] ?? '');
            ?>
            <li class="<?= htmlspecialchars(implode(' ', $itemClasses), ENT_QUOTES, 'UTF-8') ?>">
                <div class="starter-navigation__entry">
                    <a
                        href="<?= htmlspecialchars((string) ($item['link'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        <?php if ($target !== '') : ?>target="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>" rel="noopener"<?php endif; ?>
                        <?php if ($isCurrent) : ?>aria-current="page"<?php endif; ?>
                    ><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php if ($hasChildren) : ?>
                        <button
                            class="starter-navigation__toggle"
                            type="button"
                            aria-expanded="false"
                            aria-controls="<?= htmlspecialchars($submenuId, ENT_QUOTES, 'UTF-8') ?>"
                            data-starter-navigation-toggle
                        >
                            <span class="starter-visually-hidden">Show submenu for <?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <svg aria-hidden="true" viewBox="0 0 20 20" focusable="false">
                                <path d="m5.75 7.5 4.25 4.25 4.25-4.25" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ($hasChildren) : ?>
                    <?php $renderStarterNavigationItems($children, $level + 1, $itemPath, $submenuId); ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<nav class="starter-shell starter-navigation" aria-label="Primary navigation" data-starter-navigation>
    <?php $renderStarterNavigationItems($navigation['items']); ?>
</nav>
