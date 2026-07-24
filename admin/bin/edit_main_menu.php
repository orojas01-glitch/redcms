<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 4.0 - (2025/03/06)
 * @requires linux v1.2.2 or later
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.tv/documentation/
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_require_admin_site_manager();
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/admin_menu_helpers.php';

if (!function_exists('red_admin_menu_editor_item')) {
    function red_admin_menu_editor_item($config, $linkChoices)
    {
        $id = red_admin_menu_scalar($config['id'] ?? '');
        $level = max(1, min(3, (int) ($config['level'] ?? 1)));
        $isNew = !empty($config['new']);
        $label = red_admin_menu_scalar($config['label'] ?? '');
        $link = red_admin_menu_scalar($config['link'] ?? '');
        $order = red_admin_menu_scalar($config['order'] ?? '');
        $windowChecked = red_admin_menu_new_window($config['newWindow'] ?? '') === '_blank';
        $heading = red_admin_menu_scalar($config['heading'] ?? '');
        $heading = $heading !== '' ? $heading : ($label !== '' ? $label : 'Untitled button');
        $description = red_admin_menu_scalar($config['description'] ?? '');
        $recordId = (int) ($config['recordId'] ?? 0);
        $itemClasses = [
            'red-admin-menu-item',
            'red-admin-menu-item--level-' . $level,
            $isNew ? 'red-admin-menu-item--new' : 'red-admin-menu-item--saved',
        ];
        ?>
        <section class="<?php echo red_admin_menu_html(implode(' ', $itemClasses)); ?>"
            data-menu-item data-menu-level="<?php echo $level; ?>">
            <header class="red-admin-menu-item__header">
                <div class="red-admin-menu-item__identity">
                    <span class="red-admin-menu-item__level">Level <?php echo $level; ?></span>
                    <strong><?php echo red_admin_menu_html($heading); ?></strong>
                    <span class="red-admin-menu-item__state"><?php echo $isNew ? 'Ready to add' : 'Saved'; ?></span>
                </div>
                <?php if (!$isNew && $recordId > 0) : ?>
                    <button type="button" class="red-admin-menu-item__delete"
                        data-menu-delete="<?php echo $recordId; ?>"
                        data-menu-delete-label="<?php echo red_admin_menu_html($heading); ?>"
                        aria-label="Delete <?php echo red_admin_menu_html($heading); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5"></path>
                        </svg>
                        <span>Delete</span>
                    </button>
                <?php endif; ?>
            </header>

            <?php if ($description !== '') : ?>
                <p class="red-admin-menu-item__description"><?php echo red_admin_menu_html($description); ?></p>
            <?php endif; ?>

            <div class="red-admin-menu-item__fields">
                <label class="red-admin-menu-field red-admin-menu-field--label" for="<?php echo red_admin_menu_html($id); ?>-label">
                    <span>Button label</span>
                    <input id="<?php echo red_admin_menu_html($id); ?>-label"
                        name="<?php echo red_admin_menu_html($config['labelName'] ?? ''); ?>"
                        type="text" value="<?php echo red_admin_menu_html($label); ?>"
                        autocomplete="off" placeholder="What visitors will see">
                </label>

                <label class="red-admin-menu-field red-admin-menu-field--order" for="<?php echo red_admin_menu_html($id); ?>-order">
                    <span>Order</span>
                    <input id="<?php echo red_admin_menu_html($id); ?>-order"
                        name="<?php echo red_admin_menu_html($config['orderName'] ?? ''); ?>"
                        type="number" min="0" step="1" inputmode="numeric"
                        value="<?php echo red_admin_menu_html($order); ?>" placeholder="0">
                </label>

                <label class="red-admin-menu-field red-admin-menu-field--picker" for="<?php echo red_admin_menu_html($id); ?>-picker">
                    <span>Choose an existing page</span>
                    <select id="<?php echo red_admin_menu_html($id); ?>-picker"
                        data-menu-link-picker aria-describedby="<?php echo red_admin_menu_html($id); ?>-route">
                        <?php echo red_admin_main_menu_link_options_from_choices($linkChoices, $link); ?>
                    </select>
                </label>

                <label class="red-admin-menu-field red-admin-menu-field--link" for="<?php echo red_admin_menu_html($id); ?>-link">
                    <span>Destination</span>
                    <input id="<?php echo red_admin_menu_html($id); ?>-link"
                        name="<?php echo red_admin_menu_html($config['linkName'] ?? ''); ?>"
                        type="text" value="<?php echo red_admin_menu_html($link); ?>"
                        data-menu-link-input autocomplete="off" spellcheck="false"
                        placeholder="/page/ or https://example.com">
                </label>

                <div class="red-admin-menu-field red-admin-menu-field--window">
                    <span>Opening behavior</span>
                    <input type="hidden"
                        name="<?php echo red_admin_menu_html($config['windowName'] ?? ''); ?>" value="">
                    <label class="red-admin-menu-switch" for="<?php echo red_admin_menu_html($id); ?>-window">
                        <input id="<?php echo red_admin_menu_html($id); ?>-window"
                            name="<?php echo red_admin_menu_html($config['windowName'] ?? ''); ?>"
                            type="checkbox" value="_blank"<?php echo $windowChecked ? ' checked="checked"' : ''; ?>>
                        <span aria-hidden="true"></span>
                        Open in a new window
                    </label>
                </div>
            </div>

            <div class="red-admin-menu-item__route" id="<?php echo red_admin_menu_html($id); ?>-route">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M10 14a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.1 1.1M14 10a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.1-1.1"></path>
                </svg>
                <span>Destination</span>
                <code data-menu-link-preview><?php echo red_admin_menu_html($link !== '' ? $link : 'No destination selected'); ?></code>
            </div>

            <?php if (!$isNew && $recordId > 0) : ?>
                <input name="<?php echo red_admin_menu_html($config['recordName'] ?? ''); ?>"
                    type="hidden" value="<?php echo $recordId; ?>">
            <?php endif; ?>
            <?php if ($isNew && !empty($config['parentName'])) : ?>
                <input name="<?php echo red_admin_menu_html($config['parentName']); ?>"
                    type="hidden" value="<?php echo (int) ($config['parentId'] ?? 0); ?>">
            <?php endif; ?>
        </section>
        <?php
    }
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$language = red_admin_menu_language();
$title = red_admin_main_menu_title($db->connection, $language);
$linkChoices = red_admin_main_menu_link_choices($db->connection, $language);
$mainMenuRows = red_admin_main_menu_items($db->connection, $language);
$menuTree = [];
$totalMenuItems = 0;

foreach ($mainMenuRows as $mainRow) {
    $mainRow['children'] = [];
    $rootRecordId = (int) ($mainRow['RecordID'] ?? 0);
    foreach (red_admin_main_menu_children($db->connection, $rootRecordId, $language, 2) as $subRow) {
        $subRecordId = (int) ($subRow['RecordID'] ?? 0);
        $subRow['children'] = red_admin_main_menu_children(
            $db->connection,
            $subRecordId,
            $language,
            3
        );
        $totalMenuItems += 1 + count($subRow['children']);
        $mainRow['children'][] = $subRow;
    }
    $totalMenuItems++;
    $menuTree[] = $mainRow;
}
?>
<div class="red-admin-menu-editor-shell">
    <button type="button" class="red-admin-workspace-back" data-menu-return>
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="m15 18-6-6 6-6"></path>
        </svg>
        <span>Back to page content</span>
    </button>

    <form id="update_main_menu" name="update_main_menu"
        class="cp red-admin-menu-editor" method="post"
        data-red-menu-editor onSubmit="return run_update_main_menu(this);">
        <fieldset>
            <legend class="red-admin-visually-hidden">Edit top navigation</legend>

            <header class="red-admin-menu-editor__hero">
                <div class="red-admin-menu-editor__hero-copy">
                    <span class="red-admin-menu-editor__eyebrow">Navigation workspace</span>
                    <h2>Edit top menu</h2>
                    <p>Build up to three levels, choose a real page or enter a custom destination, and save everything together.</p>
                </div>
                <dl class="red-admin-menu-editor__stats" aria-label="Menu summary">
                    <div>
                        <dt>Buttons</dt>
                        <dd><?php echo $totalMenuItems; ?></dd>
                    </div>
                    <div>
                        <dt>Levels</dt>
                        <dd>3</dd>
                    </div>
                </dl>
            </header>

            <section class="red-admin-menu-panel red-admin-menu-panel--settings" aria-labelledby="red-admin-menu-settings-title">
                <div class="red-admin-menu-panel__heading">
                    <span class="red-admin-menu-panel__step">1</span>
                    <div>
                        <h3 id="red-admin-menu-settings-title">Menu settings</h3>
                        <p>This internal name identifies the navigation set.</p>
                    </div>
                </div>
                <label class="red-admin-menu-field red-admin-menu-field--title" for="red-admin-menu-title">
                    <span>Menu name</span>
                    <input name="Title" type="text" id="red-admin-menu-title"
                        value="<?php echo red_admin_menu_html($title); ?>" autocomplete="off">
                </label>
            </section>

            <section class="red-admin-menu-panel" aria-labelledby="red-admin-menu-new-root-title">
                <div class="red-admin-menu-panel__heading">
                    <span class="red-admin-menu-panel__step">2</span>
                    <div>
                        <h3 id="red-admin-menu-new-root-title">Add a top-level button</h3>
                        <p>The destination can now be assigned before the button is saved for the first time.</p>
                    </div>
                </div>
                <?php
                red_admin_menu_editor_item([
                    'id' => 'menu-new-root',
                    'level' => 1,
                    'new' => true,
                    'heading' => 'New top-level button',
                    'description' => 'Leave this card empty when you only want to update existing buttons.',
                    'labelName' => 'NewLabel',
                    'orderName' => 'NewMenuOrder',
                    'linkName' => 'NewLabelLink',
                    'windowName' => 'NewLabelNewWindow',
                ], $linkChoices);
                ?>
            </section>

            <section class="red-admin-menu-panel red-admin-menu-panel--structure" aria-labelledby="red-admin-menu-structure-title">
                <div class="red-admin-menu-panel__heading">
                    <span class="red-admin-menu-panel__step">3</span>
                    <div>
                        <h3 id="red-admin-menu-structure-title">Menu structure</h3>
                        <p>Open a top-level branch to edit its button and add or update nested buttons.</p>
                    </div>
                </div>

                <div class="red-admin-menu-tree">
                    <?php if ($menuTree === []) : ?>
                        <div class="red-admin-menu-empty">
                            <strong>No saved buttons yet</strong>
                            <p>Use the top-level card above to create the first destination in this menu.</p>
                        </div>
                    <?php endif; ?>

                    <?php
                    $subGroupIndex = 0;
                    foreach ($menuTree as $rootIndex => $mainRow) :
                        $rootGroup = $rootIndex + 1;
                        $rootRecordId = (int) ($mainRow['RecordID'] ?? 0);
                        $rootLabel = red_admin_menu_scalar($mainRow['Label'] ?? '');
                        $rootLink = red_admin_menu_scalar($mainRow['Link'] ?? '');
                        $children = is_array($mainRow['children'] ?? null) ? $mainRow['children'] : [];
                        $descendantCount = count($children);
                        foreach ($children as $childRow) {
                            $descendantCount += count($childRow['children'] ?? []);
                        }
                    ?>
                        <details class="red-admin-menu-branch"<?php echo $rootIndex === 0 ? ' open' : ''; ?>>
                            <summary class="red-admin-menu-branch__summary">
                                <span class="red-admin-menu-branch__number"><?php echo $rootGroup; ?></span>
                                <span class="red-admin-menu-branch__identity">
                                    <strong><?php echo red_admin_menu_html($rootLabel !== '' ? $rootLabel : 'Untitled button'); ?></strong>
                                    <code><?php echo red_admin_menu_html($rootLink !== '' ? $rootLink : 'No destination'); ?></code>
                                </span>
                                <span class="red-admin-menu-branch__count">
                                    <?php echo $descendantCount; ?> <?php echo $descendantCount === 1 ? 'nested button' : 'nested buttons'; ?>
                                </span>
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="m8 10 4 4 4-4"></path>
                                </svg>
                            </summary>

                            <div class="red-admin-menu-branch__body">
                                <?php
                                red_admin_menu_editor_item([
                                    'id' => 'menu-root-' . $rootRecordId,
                                    'level' => 1,
                                    'heading' => $rootLabel,
                                    'label' => $rootLabel,
                                    'order' => $mainRow['MenuOrder'] ?? '',
                                    'link' => $rootLink,
                                    'newWindow' => $mainRow['NewWindow'] ?? '',
                                    'recordId' => $rootRecordId,
                                    'labelName' => 'MainLabel[' . $rootGroup . '][0]',
                                    'orderName' => 'MainMenuOrder[' . $rootGroup . '][0]',
                                    'linkName' => 'MainLabelLink[' . $rootGroup . '][0]',
                                    'windowName' => 'MainLabelNewWindow[' . $rootGroup . '][0]',
                                    'recordName' => 'MainLabelRecordID[' . $rootGroup . '][0]',
                                ], $linkChoices);
                                ?>

                                <div class="red-admin-menu-children">
                                    <div class="red-admin-menu-children__heading">
                                        <span>Level 2</span>
                                        <strong>Buttons nested under <?php echo red_admin_menu_html($rootLabel !== '' ? $rootLabel : 'this item'); ?></strong>
                                    </div>

                                    <?php foreach ($children as $subIndex => $subRow) :
                                        $subRecordId = (int) ($subRow['RecordID'] ?? 0);
                                        $subLabel = red_admin_menu_scalar($subRow['Label'] ?? '');
                                        $subLink = red_admin_menu_scalar($subRow['Link'] ?? '');
                                        $grandchildren = is_array($subRow['children'] ?? null) ? $subRow['children'] : [];
                                    ?>
                                        <div class="red-admin-menu-child-group">
                                            <?php
                                            red_admin_menu_editor_item([
                                                'id' => 'menu-sub-' . $subRecordId,
                                                'level' => 2,
                                                'heading' => $subLabel,
                                                'label' => $subLabel,
                                                'order' => $subRow['MenuOrder'] ?? '',
                                                'link' => $subLink,
                                                'newWindow' => $subRow['NewWindow'] ?? '',
                                                'recordId' => $subRecordId,
                                                'labelName' => 'SubLabel[' . $rootGroup . '][' . $subIndex . ']',
                                                'orderName' => 'SubMenuOrder[' . $rootGroup . '][' . $subIndex . ']',
                                                'linkName' => 'SubLabelLink[' . $rootGroup . '][' . $subIndex . ']',
                                                'windowName' => 'SubLabelNewWindow[' . $rootGroup . '][' . $subIndex . ']',
                                                'recordName' => 'SubLabelRecordID[' . $rootGroup . '][' . $subIndex . ']',
                                            ], $linkChoices);
                                            ?>

                                            <div class="red-admin-menu-grandchildren">
                                                <div class="red-admin-menu-grandchildren__heading">
                                                    <span>Level 3 under <?php echo red_admin_menu_html($subLabel !== '' ? $subLabel : 'this item'); ?></span>
                                                </div>
                                                <?php foreach ($grandchildren as $grandIndex => $grandRow) :
                                                    $grandRecordId = (int) ($grandRow['RecordID'] ?? 0);
                                                    $grandLabel = red_admin_menu_scalar($grandRow['Label'] ?? '');
                                                ?>
                                                    <?php
                                                    red_admin_menu_editor_item([
                                                        'id' => 'menu-subsub-' . $grandRecordId,
                                                        'level' => 3,
                                                        'heading' => $grandLabel,
                                                        'label' => $grandLabel,
                                                        'order' => $grandRow['MenuOrder'] ?? '',
                                                        'link' => $grandRow['Link'] ?? '',
                                                        'newWindow' => $grandRow['NewWindow'] ?? '',
                                                        'recordId' => $grandRecordId,
                                                        'labelName' => 'SubSubLabel[' . $subGroupIndex . '][' . $grandIndex . ']',
                                                        'orderName' => 'SubSubMenuOrder[' . $subGroupIndex . '][' . $grandIndex . ']',
                                                        'linkName' => 'SubSubLabelLink[' . $subGroupIndex . '][' . $grandIndex . ']',
                                                        'windowName' => 'SubSubLabelNewWindow[' . $subGroupIndex . '][' . $grandIndex . ']',
                                                        'recordName' => 'SubSubLabelRecordID[' . $subGroupIndex . '][' . $grandIndex . ']',
                                                    ], $linkChoices);
                                                    ?>
                                                <?php endforeach; ?>

                                                <details class="red-admin-menu-add-card">
                                                    <summary>
                                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                            <path d="M12 5v14M5 12h14"></path>
                                                        </svg>
                                                        Add a level 3 button under <?php echo red_admin_menu_html($subLabel !== '' ? $subLabel : 'this item'); ?>
                                                    </summary>
                                                    <?php
                                                    red_admin_menu_editor_item([
                                                        'id' => 'menu-new-subsub-' . $subGroupIndex,
                                                        'level' => 3,
                                                        'new' => true,
                                                        'heading' => 'New level 3 button',
                                                        'description' => 'This button will be nested under ' . ($subLabel !== '' ? $subLabel : 'the selected level 2 item') . '.',
                                                        'labelName' => 'NewSubSubLabel[' . $subGroupIndex . '][0]',
                                                        'orderName' => 'NewSubSubMenuOrder[' . $subGroupIndex . '][0]',
                                                        'linkName' => 'NewSubSubLabelLink[' . $subGroupIndex . '][0]',
                                                        'windowName' => 'NewSubSubLabelNewWindow[' . $subGroupIndex . '][0]',
                                                        'parentName' => 'NewSubLabelRecordID[' . $subGroupIndex . '][0]',
                                                        'parentId' => $subRecordId,
                                                    ], $linkChoices);
                                                    ?>
                                                </details>
                                            </div>
                                        </div>
                                        <?php $subGroupIndex++; ?>
                                    <?php endforeach; ?>

                                    <details class="red-admin-menu-add-card">
                                        <summary>
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M12 5v14M5 12h14"></path>
                                            </svg>
                                            Add a level 2 button under <?php echo red_admin_menu_html($rootLabel !== '' ? $rootLabel : 'this item'); ?>
                                        </summary>
                                        <?php
                                        red_admin_menu_editor_item([
                                            'id' => 'menu-new-sub-' . $rootRecordId,
                                            'level' => 2,
                                            'new' => true,
                                            'heading' => 'New level 2 button',
                                            'description' => 'This button will be nested under ' . ($rootLabel !== '' ? $rootLabel : 'the selected top-level item') . '.',
                                            'labelName' => 'NewSubLabel[' . $rootGroup . '][0]',
                                            'orderName' => 'NewSubMenuOrder[' . $rootGroup . '][0]',
                                            'linkName' => 'NewSubLabelLink[' . $rootGroup . '][0]',
                                            'windowName' => 'NewSubLabelNewWindow[' . $rootGroup . '][0]',
                                            'parentName' => 'NewMainLabelRecordID[' . $rootGroup . '][0]',
                                            'parentId' => $rootRecordId,
                                        ], $linkChoices);
                                        ?>
                                    </details>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php echo red_csrf_input(); ?>
            <input type="hidden" name="CurTitle" value="<?php echo red_admin_menu_html($title); ?>">
            <input type="hidden" name="Language" value="<?php echo red_admin_menu_html($language); ?>">

            <footer class="red-admin-menu-actions">
                <div class="red-admin-menu-actions__status" id="msggbox_update_main_menu"
                    data-menu-status role="status" aria-live="polite">
                    Changes are applied only when you save.
                </div>
                <button type="submit" name="submit" value="Save" class="red-admin-menu-save">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M5 4h12l2 2v14H5zM8 4v6h8V4M8 17h8"></path>
                    </svg>
                    <span>Save navigation</span>
                </button>
            </footer>
        </fieldset>
    </form>
</div>
<?php
$db->close();
?>
