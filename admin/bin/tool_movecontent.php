<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
red_require_admin();
red_require_admin_tool(1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin_tool_helpers.php';

$type = red_admin_tool_post_text('Type');
$countPage = red_admin_tool_count_page($_POST['CountPage'] ?? '');
$section = red_admin_tool_post_text('Section');
$category = red_admin_tool_post_text('Category');
$subCategory = red_admin_tool_post_text('SubCategory');
$article = red_admin_tool_post_text('Article');
$language = substr(red_admin_tool_post_text('Language'), 0, 2);
$layout = red_admin_tool_post_text('Layout');
$cparea = red_admin_tool_post_text('cparea');
$cpareaConfig = red_admin_tool_area_config($cparea);
if ($countPage === 0 || ($cparea !== 'Content' && !$cpareaConfig)) {
    echo 'no';
    exit;
}

$cpareaStyle = $cparea === 'Content' ? 'content' : $cpareaConfig['style'];
$compGroup = red_admin_tool_post_text('compgroup');
$sortBy = red_admin_tool_post_text('SortBy');
$filterPosition = red_admin_tool_post_text('SelectPosition');
$rowPosition = $cparea === 'Content'
    ? red_admin_tool_position_column($_POST['VarPosition'] ?? '')
    : $cpareaConfig['position'];
if ($filterPosition === '') {
    $filterPosition = 'all';
    if (strcasecmp($section, 'home') === 0) {
        $rowPosition = 'HomePosition';
    }
}
$varPosition = red_admin_tool_position_column($_POST['VarPosition'] ?? '', $rowPosition);
if ($varPosition === '') {
    echo 'no';
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$sourcePositionOptions = red_admin_tool_layout_position_options($db->connection, $layout, true);
$articles = red_admin_tool_move_articles(
    $db->connection,
    $countPage,
    $section,
    $category,
    $subCategory,
    $article,
    $varPosition,
    'all',
    $sortBy,
    $rowPosition,
    $language
);
$destinationCatalog = red_admin_tool_move_destination_catalog($db->connection, $language);
$adminComponentIds = red_admin_tool_admin_component_ids($_SESSION['AdminComponents'] ?? '');
$sourcePath = red_admin_tool_public_article_link($section, $category, $subCategory, $article);
$sourceRows = [];

foreach ($articles as $row) {
    $recordId = (int) ($row['RecordID'] ?? 0);
    $componentName = red_admin_tool_text($row['Component'] ?? '');
    $access = red_admin_tool_component_access(
        $db->connection,
        $componentName,
        $adminComponentIds,
        $recordId
    );
    if (!$access['authorized']) {
        continue;
    }

    $componentEndpoint = preg_replace('/[^a-z0-9_-]/', '', strtolower($componentName));
    if ($componentEndpoint === '') {
        continue;
    }
    $positionId = (int) ($row[$varPosition] ?? 0);
    $sourceRows[] = [
        'recordId' => $recordId,
        'title' => red_admin_tool_text($row['Title'] ?? ''),
        'component' => $componentName,
        'endpoint' => '/admin/bin/edit_' . $componentEndpoint . '.php',
        'compGroup' => red_admin_tool_text($access['comp_group'] ?? ''),
        'contentRecordId' => (int) ($access['component_record_id'] ?? 0),
        'position' => $positionId,
        'positionLabel' => array_key_exists($positionId, $sourcePositionOptions)
            ? red_admin_tool_text($sourcePositionOptions[$positionId])
            : 'Outside current layout',
        'homeFeature' => red_admin_tool_text($row['HomeFeature'] ?? '') === 'Y',
        'updated' => red_admin_tool_text($row['Updated'] ?? ''),
    ];
}

$catalogJson = json_encode(
    $destinationCatalog,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT |
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
if (!is_string($catalogJson)) {
    $catalogJson = '{"language":"","sections":[],"categories":[],"subcategories":[],"articles":[],"layouts":{}}';
}
$sourceCount = count($sourceRows);
$sourceLabel = $sourcePath === '/' ? 'Home' : $sourcePath;
?>

<div class="red-admin-move-return">
    <button type="button" class="red-admin-move-return__button" onclick="showdiv('tools_<?php echo red_admin_tool_html($cpareaStyle); ?>_grid'); return false;">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14 6-6 6 6 6"></path></svg>
        Back to tools
    </button>
</div>

<form
    id="toolmove"
    name="toolmove"
    class="cp red-admin-move-form"
    method="post"
    data-red-move-content
    data-red-move-catalog="red-move-content-catalog"
    data-red-move-layout="<?php echo red_admin_tool_html($layout); ?>"
    data-red-move-position-column="<?php echo red_admin_tool_html($varPosition); ?>"
    onsubmit="return window.REDMoveContent ? window.REDMoveContent.submit(this) : false;"
>
    <fieldset>
        <?php echo red_csrf_input(); ?>
        <div class="red-admin-move-shell">
            <header class="red-admin-move-header">
                <span class="red-admin-move-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="6" height="13" rx="1.5"></rect><rect x="14.5" y="5.5" width="6" height="13" rx="1.5"></rect><path d="M8 9h8M13.5 6.5 16 9l-2.5 2.5M16 15H8M10.5 12.5 8 15l2.5 2.5"></path></svg>
                </span>
                <div class="red-admin-move-header__copy">
                    <span class="red-admin-move-header__eyebrow">Content tools</span>
                    <h2>Move content</h2>
                    <p>Select one or more items, build the destination path, then place them on its actual template layout.</p>
                </div>
                <span class="red-admin-move-header__badge"><?php echo (int) $sourceCount; ?> <?php echo $sourceCount === 1 ? 'item' : 'items'; ?></span>
            </header>

            <section class="red-admin-move-panel" aria-labelledby="red-move-source-title">
                <div class="red-admin-move-panel__heading">
                    <div>
                        <span class="red-admin-move-panel__step">1</span>
                        <div>
                            <h3 id="red-move-source-title">Choose content</h3>
                            <p>Currently shown at <strong><?php echo red_admin_tool_html($sourceLabel); ?></strong></p>
                        </div>
                    </div>
                    <span class="red-admin-move-selected-count" data-red-move-selected-count>0 selected</span>
                </div>

                <?php if ($sourceCount === 0): ?>
                    <div class="red-admin-move-empty">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h10l4 4v12H5z"></path><path d="M15 4v5h5M8 14h8M8 17h5"></path></svg>
                        <div>
                            <strong>No movable content is available here.</strong>
                            <span>Only active items assigned to your administrator account appear in this list.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="red-admin-move-source-toolbar">
                        <div class="red-admin-move-control red-admin-move-control--search">
                            <label for="red-move-source-search">Search items</label>
                            <span>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m15.5 15.5 4 4"></path></svg>
                                <input type="search" id="red-move-source-search" placeholder="Search by title or component" autocomplete="off" data-red-move-search />
                            </span>
                        </div>
                        <div class="red-admin-move-control">
                            <label for="red-move-source-position">Current position</label>
                            <select id="red-move-source-position" data-red-move-source-position>
                                <option value="all">All positions</option>
                                <?php foreach ($sourcePositionOptions as $positionId => $positionLabel): ?>
                                    <option value="<?php echo (int) $positionId; ?>"><?php echo red_admin_tool_html($positionLabel); ?> (<?php echo (int) $positionId; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="red-admin-move-control">
                            <label for="red-move-source-sort">Sort items</label>
                            <select id="red-move-source-sort" data-red-move-sort>
                                <option value="updated">Recently updated</option>
                                <option value="title-asc">Title A–Z</option>
                                <option value="title-desc">Title Z–A</option>
                                <option value="position-asc">Position low–high</option>
                                <option value="position-desc">Position high–low</option>
                                <option value="component-asc">Component A–Z</option>
                            </select>
                        </div>
                    </div>

                    <div class="red-admin-move-source-list" role="table" aria-label="Movable content">
                        <div class="red-admin-move-source-row red-admin-move-source-row--header" role="row">
                            <div role="columnheader">
                                <label class="red-admin-move-check">
                                    <input type="checkbox" data-red-move-select-all />
                                    <span class="red-admin-visually-hidden">Select all visible content</span>
                                </label>
                            </div>
                            <div role="columnheader">Content</div>
                            <div role="columnheader">Position</div>
                            <div role="columnheader">Component</div>
                            <div role="columnheader"><span class="red-admin-visually-hidden">Actions</span></div>
                        </div>
                        <div class="red-admin-move-source-scroll" data-red-move-source-list>
                            <?php foreach ($sourceRows as $index => $sourceRow): ?>
                                <?php
                                $componentKey = preg_replace('/[^a-z0-9]+/', '-', strtolower($sourceRow['component']));
                                $componentKey = trim((string) $componentKey, '-');
                                ?>
                                <div
                                    class="red-admin-move-source-row"
                                    role="row"
                                    data-red-move-source-row
                                    data-title="<?php echo red_admin_tool_html(strtolower($sourceRow['title'])); ?>"
                                    data-component="<?php echo red_admin_tool_html(strtolower($sourceRow['component'])); ?>"
                                    data-position="<?php echo (int) $sourceRow['position']; ?>"
                                    data-updated="<?php echo red_admin_tool_html($sourceRow['updated']); ?>"
                                >
                                    <div role="cell" data-label="Select">
                                        <label class="red-admin-move-check">
                                            <input
                                                type="checkbox"
                                                class="checkbox1"
                                                name="Articles_Sel[<?php echo (int) $index; ?>]"
                                                value="<?php echo (int) $sourceRow['recordId']; ?>"
                                                data-red-move-item
                                            />
                                            <span class="red-admin-visually-hidden">Select <?php echo red_admin_tool_html($sourceRow['title']); ?></span>
                                        </label>
                                        <input type="hidden" name="RecordID[<?php echo (int) $index; ?>]" value="<?php echo (int) $sourceRow['recordId']; ?>" />
                                    </div>
                                    <div class="red-admin-move-source-row__title" role="cell" data-label="Content">
                                        <strong><?php echo red_admin_tool_html($sourceRow['title']); ?></strong>
                                        <small>Updated <?php echo red_admin_tool_html($sourceRow['updated']); ?></small>
                                        <?php if ($sourceRow['homeFeature']): ?>
                                            <span class="red-admin-move-featured">Home feature</span>
                                        <?php endif; ?>
                                    </div>
                                    <div role="cell" data-label="Position">
                                        <span class="red-admin-move-position-pill<?php echo array_key_exists($sourceRow['position'], $sourcePositionOptions) ? '' : ' is-outside'; ?>">
                                            <strong><?php echo (int) $sourceRow['position']; ?></strong>
                                            <span><?php echo red_admin_tool_html($sourceRow['positionLabel']); ?></span>
                                        </span>
                                    </div>
                                    <div role="cell" data-label="Component">
                                        <span class="red-admin-move-component red-admin-move-component--<?php echo red_admin_tool_html($componentKey); ?>"><?php echo red_admin_tool_html($sourceRow['component']); ?></span>
                                    </div>
                                    <div class="red-admin-move-source-row__actions" role="cell" data-label="Actions">
                                        <button
                                            type="button"
                                            class="red-admin-move-edit"
                                            aria-label="Edit <?php echo red_admin_tool_html($sourceRow['title']); ?>"
                                            title="Edit content"
                                            data-red-move-edit
                                            data-endpoint="<?php echo red_admin_tool_html($sourceRow['endpoint']); ?>"
                                            data-article-id="<?php echo (int) $sourceRow['recordId']; ?>"
                                            data-content-id="<?php echo (int) $sourceRow['contentRecordId']; ?>"
                                            data-component-group="<?php echo red_admin_tool_html($sourceRow['compGroup']); ?>"
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 16.5-.8 3.3 3.3-.8L18 8.5 14.5 5z"></path><path d="m12.8 6.7 3.5 3.5"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="red-admin-move-no-results" data-red-move-no-results hidden>No items match these filters.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="red-admin-move-panel" aria-labelledby="red-move-destination-title">
                <div class="red-admin-move-panel__heading">
                    <div>
                        <span class="red-admin-move-panel__step">2</span>
                        <div>
                            <h3 id="red-move-destination-title">Build the destination</h3>
                            <p>The deepest selection controls the layout: Section, Category, Subcategory, or Article page.</p>
                        </div>
                    </div>
                    <span class="red-admin-move-destination-badge" data-red-move-destination-badge>Choose a section</span>
                </div>

                <div class="red-admin-move-destination-grid">
                    <div class="red-admin-move-destination-fields">
                        <div class="red-admin-move-control">
                            <label for="red-move-section">Section <span aria-hidden="true">*</span></label>
                            <select name="Sections" id="red-move-section" required data-red-move-section>
                                <option value="">Choose a section…</option>
                                <?php foreach ($destinationCatalog['sections'] as $destinationSection): ?>
                                    <option value="<?php echo red_admin_tool_html($destinationSection['value']); ?>"><?php echo red_admin_tool_html($destinationSection['title'] !== '' ? $destinationSection['title'] : $destinationSection['value']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>Required. Selecting only this field uses the Section layout.</small>
                        </div>

                        <div class="red-admin-move-control">
                            <label for="red-move-category">Category <span>Optional</span></label>
                            <select name="Categories" id="red-move-category" disabled data-red-move-category>
                                <option value="">No category</option>
                            </select>
                            <small>Categories become available after a Section is chosen.</small>
                        </div>

                        <div class="red-admin-move-control">
                            <label for="red-move-subcategory">Subcategory <span>Optional</span></label>
                            <select name="SubCategories" id="red-move-subcategory" disabled data-red-move-subcategory>
                                <option value="">No subcategory</option>
                            </select>
                            <small>Subcategories become available after a Category is chosen.</small>
                        </div>

                        <div class="red-admin-move-control">
                            <label for="red-move-article">Article page <span>Optional</span></label>
                            <select name="Article" id="red-move-article" disabled data-red-move-article>
                                <option value="">No article page</option>
                            </select>
                            <input type="hidden" name="DestinationArticleRecordID" value="" data-red-move-article-id />
                            <small>Only pages belonging to the selected path are shown.</small>
                        </div>

                        <div class="red-admin-move-control red-admin-move-control--position">
                            <label for="red-move-position">Layout position <span aria-hidden="true">*</span></label>
                            <select name="Position" id="red-move-position" required disabled data-red-move-position>
                                <option value="">Choose a destination first</option>
                            </select>
                            <small>Select from the menu or click a position in the map.</small>
                        </div>

                        <div class="red-admin-move-path" aria-live="polite">
                            <span>Destination path</span>
                            <strong data-red-move-path>Choose a section to begin</strong>
                        </div>
                    </div>

                    <section class="red-admin-move-map" aria-labelledby="red-move-map-title" data-red-move-map>
                        <header class="red-admin-move-map__header">
                            <div>
                                <span class="red-admin-move-map__eyebrow">Future page layout</span>
                                <h4 id="red-move-map-title" data-red-move-map-title>No destination selected</h4>
                            </div>
                            <span class="red-admin-move-map__count" data-red-move-map-count>0 positions</span>
                        </header>
                        <div class="red-admin-move-map__empty" data-red-move-map-empty>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="8" height="7" rx="1"></rect><rect x="13" y="4" width="8" height="7" rx="1"></rect><rect x="3" y="13" width="18" height="7" rx="1"></rect></svg>
                            <div>
                                <strong>Your destination map will appear here.</strong>
                                <span>Choose a Section, then refine the path only as far as needed.</span>
                            </div>
                        </div>
                        <div class="red-admin-move-map__diagram" data-red-move-map-diagram hidden></div>
                        <button type="button" class="red-admin-move-map__hidden" data-red-move-position-shortcut="0" disabled hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12s3.5-5 9-5 9 5 9 5-3.5 5-9 5-9-5-9-5z"></path><path d="m4 4 16 16"></path></svg>
                            <span><strong>Hidden (0)</strong><small>Keep the item assigned here but do not show it publicly.</small></span>
                        </button>
                        <p class="red-admin-move-map__note" data-red-move-map-note hidden></p>
                        <p class="red-admin-visually-hidden" role="status" aria-live="polite" data-red-move-live-status></p>
                    </section>
                </div>
            </section>

            <div class="red-admin-move-guidance">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 10v6M12 7h.01"></path></svg>
                <p><strong>This moves placement; it does not duplicate content.</strong> If the destination uses a different placement level, only this source placement is cleared. Existing item order values and unrelated placements are preserved.</p>
            </div>

            <div class="red-admin-move-actions">
                <span id="msggbox_tool_content" class="red-admin-move-message" role="status" aria-live="polite"></span>
                <button type="button" class="red-admin-move-clear" data-red-move-clear>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6h14M9 6V4h6v2M8 9v9M12 9v9M16 9v9M6.5 6l1 15h9l1-15"></path></svg>
                    Clear destination
                </button>
                <button type="submit" name="submit" value="Update" class="red-admin-move-submit" data-red-move-submit disabled>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5.5" width="6" height="13" rx="1.5"></rect><rect x="14.5" y="5.5" width="6" height="13" rx="1.5"></rect><path d="M8 9h8M13.5 6.5 16 9l-2.5 2.5"></path></svg>
                    Move selected content
                </button>
            </div>
        </div>

        <input type="hidden" name="EditedBy" value="<?php echo red_admin_tool_html($_SESSION['alias'] ?? ''); ?>" />
        <input type="hidden" name="VarPosition" value="<?php echo red_admin_tool_html($varPosition); ?>" />
        <input type="hidden" name="Layout" value="<?php echo red_admin_tool_html($layout); ?>" />
        <input type="hidden" name="SourceCountPage" value="<?php echo (int) $countPage; ?>" />
        <input type="hidden" name="SourceSection" value="<?php echo red_admin_tool_html($section); ?>" />
        <input type="hidden" name="SourceCategory" value="<?php echo red_admin_tool_html($category); ?>" />
        <input type="hidden" name="SourceSubCategory" value="<?php echo red_admin_tool_html($subCategory); ?>" />
        <input type="hidden" name="SourceArticle" value="<?php echo red_admin_tool_html($article); ?>" />
        <input type="hidden" name="SourceLanguage" value="<?php echo red_admin_tool_html($language); ?>" />
        <input type="hidden" name="SourcePositionColumn" value="<?php echo red_admin_tool_html($varPosition); ?>" />
    </fieldset>
</form>

<script type="application/json" id="red-move-content-catalog"><?php echo $catalogJson; ?></script>
<script type="text/javascript">
if (window.REDMoveContent) {
    window.REDMoveContent.init(document.getElementById('toolmove'));
}
</script>

<?php $db->close(); ?>
