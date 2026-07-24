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
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
red_require_admin();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_feature_helpers.php';

$VarFeatures = red_admin_text($_POST['VarFeatures'] ?? '');
$Language = red_admin_text($_POST['Language'] ?? '');
$positionColumn = red_admin_feature_position_column($VarFeatures);
if (red_admin_feature_order_column($VarFeatures) === '' || $positionColumn === '' || $Language === '') {
    echo 'no';
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$articleRows = red_admin_feature_articles($db->connection, $Language, $VarFeatures, true);
$db->close();

$scopeLabel = red_admin_feature_scope_label($VarFeatures);
$selectedCount = 0;
foreach ($articleRows as $articleRow) {
    if (red_admin_feature_list_contains($articleRow[$VarFeatures] ?? '', 'slider')) {
        $selectedCount++;
    }
}
$availableCount = count($articleRows);
$availableLabel = $availableCount === 1 ? '1 available Article' : $availableCount.' available Articles';
$selectedLabel = $selectedCount === 1 ? '1 selected slide' : $selectedCount.' selected slides';
?>
<form
    id="update_slider"
    name="update_slider"
    class="cp red-admin-slider-form"
    method="post"
    onSubmit="return run_update_slider(this);"
    data-slider-workspace
    data-slider-scope="<?php echo red_admin_feature_html($scopeLabel); ?>"
>
    <div class="red-admin-slider-return">
        <button type="button" class="red-admin-slider-return__button" data-slider-return>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"></path></svg>
            <span>Back to page content</span>
        </button>
    </div>

    <div class="red-admin-slider-shell">
        <header class="red-admin-slider-header">
            <span class="red-admin-slider-header__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <rect x="3.5" y="5" width="17" height="14" rx="2"></rect>
                    <path d="M7 15l3.2-3.2 2.7 2.4 2.6-2.2 2.5 3"></path>
                    <circle cx="8.5" cy="9" r="1.2"></circle>
                </svg>
            </span>
            <div class="red-admin-slider-header__copy">
                <span class="red-admin-slider-header__eyebrow"><?php echo red_admin_feature_html($scopeLabel); ?> presentation</span>
                <h2>Edit slider</h2>
                <p>Select the Articles that should appear in this hero, then set their presentation order.</p>
            </div>
            <div class="red-admin-slider-header__stats" aria-label="Slider inventory">
                <span><strong><?php echo $availableCount; ?></strong> available</span>
                <span class="is-selected"><strong data-slider-selected-count><?php echo $selectedCount; ?></strong> selected</span>
            </div>
        </header>

        <section class="red-admin-slider-panel" aria-labelledby="slider-articles-title">
            <div class="red-admin-slider-panel__heading">
                <div>
                    <span class="red-admin-slider-panel__step">1</span>
                    <div>
                        <h3 id="slider-articles-title">Choose slider Articles</h3>
                        <p>Selection controls membership. Slide order controls the sequence visitors see.</p>
                    </div>
                </div>
                <span class="red-admin-slider-panel__selection" data-slider-selection-label><?php echo red_admin_feature_html($selectedLabel); ?></span>
            </div>

            <?php if ($availableCount > 0) : ?>
                <div class="red-admin-slider-toolbar">
                    <label class="red-admin-slider-search" for="slider-article-filter">
                        <span>Find an Article</span>
                        <span class="red-admin-slider-search__field">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path d="M16 16l4 4"></path></svg>
                            <input
                                type="search"
                                id="slider-article-filter"
                                placeholder="Search title, alias, or slider summary"
                                autocomplete="off"
                                data-slider-filter
                            >
                        </span>
                    </label>
                    <p><?php echo red_admin_feature_html($availableLabel); ?>. Changes are applied only when you save.</p>
                </div>

                <div class="red-admin-slider-card-grid" role="list" data-slider-card-list>
                    <?php foreach ($articleRows as $index => $row) : ?>
                        <?php
                        $recordId = (int) ($row['RecordID'] ?? 0);
                        $title = trim((string) ($row['Title'] ?? ''));
                        $alias = trim((string) ($row['Alias'] ?? ''));
                        $summary = trim((string) preg_replace(
                            '/\s+/',
                            ' ',
                            strip_tags(html_entity_decode((string) ($row['SliderDesc'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                        ));
                        $bigPict = trim((string) ($row['BigPict'] ?? ''));
                        $featureOrder = (int) ($row['FeatureOrder'] ?? 0);
                        $isSelected = red_admin_feature_list_contains($row[$VarFeatures] ?? '', 'slider');
                        $searchText = strtolower(trim($title.' '.$alias.' '.$summary));
                        ?>
                        <article
                            class="red-admin-slider-card<?php echo $isSelected ? ' is-selected' : ''; ?>"
                            role="listitem"
                            data-slider-card
                            data-slider-search="<?php echo red_admin_feature_html($searchText); ?>"
                        >
                            <div class="red-admin-slider-card__topline">
                                <label class="red-admin-slider-choice" for="slider-select-<?php echo $recordId; ?>">
                                    <input type="hidden" name="sliderSelect[<?php echo $index; ?>]" value="">
                                    <input
                                        name="sliderSelect[<?php echo $index; ?>]"
                                        type="checkbox"
                                        id="slider-select-<?php echo $recordId; ?>"
                                        value="Y"
                                        data-slider-select
                                        <?php echo $isSelected ? 'checked="checked"' : ''; ?>
                                    >
                                    <span class="red-admin-slider-choice__control" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M6.5 12.5l3.4 3.4L17.8 8"></path></svg>
                                    </span>
                                    <span class="red-admin-slider-choice__copy">
                                        <strong data-slider-choice-label><?php echo $isSelected ? 'Included in slider' : 'Add to slider'; ?></strong>
                                        <small>Feature this Article in the <?php echo red_admin_feature_html(strtolower($scopeLabel)); ?> hero.</small>
                                    </span>
                                </label>
                                <span class="red-admin-slider-card__status" data-slider-status><?php echo $isSelected ? 'Selected' : 'Available'; ?></span>
                            </div>

                            <div class="red-admin-slider-card__content">
                                <div class="red-admin-slider-card__media">
                                    <?php if ($bigPict !== '') : ?>
                                        <img
                                            src="/images/resize.php?w=180&amp;h=126&amp;img=/images/articles/<?php echo red_admin_feature_html($bigPict); ?>"
                                            alt=""
                                        >
                                    <?php else : ?>
                                        <span class="red-admin-slider-card__media-empty" aria-label="No feature image">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 18l5-5 3 3 3-2 5 4"></path><circle cx="9" cy="9" r="1.5"></circle><rect x="3.5" y="4.5" width="17" height="15" rx="2"></rect></svg>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="red-admin-slider-card__details">
                                    <h4><?php echo red_admin_feature_html($title !== '' ? $title : 'Untitled Article'); ?></h4>
                                    <span class="red-admin-slider-card__alias"><?php echo red_admin_feature_html($alias !== '' ? $alias : 'No URL alias'); ?></span>
                                    <div class="red-admin-slider-card__summary">
                                        <span>Slider summary</span>
                                        <p><?php echo red_admin_feature_html($summary !== '' ? $summary : 'No slider summary has been added yet.'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="red-admin-slider-card__footer">
                                <label class="red-admin-slider-order" for="slider-order-<?php echo $recordId; ?>">
                                    <span>Slide order</span>
                                    <input
                                        name="FeatureOrder[<?php echo $index; ?>]"
                                        type="number"
                                        id="slider-order-<?php echo $recordId; ?>"
                                        min="0"
                                        max="999"
                                        step="1"
                                        inputmode="numeric"
                                        value="<?php echo $featureOrder; ?>"
                                    >
                                </label>
                                <button
                                    type="button"
                                    class="red-admin-slider-edit"
                                    data-slider-edit-article
                                    data-record-id="<?php echo $recordId; ?>"
                                    data-position-column="<?php echo red_admin_feature_html($positionColumn); ?>"
                                    aria-label="Edit Article: <?php echo red_admin_feature_html($title !== '' ? $title : 'Untitled Article'); ?>"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19h4l10-10-4-4L5 15v4Z"></path><path d="m13.5 6.5 4 4"></path></svg>
                                    <span>Edit Article</span>
                                </button>
                            </div>
                            <input type="hidden" name="RecordID[<?php echo $index; ?>]" value="<?php echo $recordId; ?>">
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="red-admin-slider-filter-empty" data-slider-filter-empty hidden>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path d="M16 16l4 4"></path></svg>
                    <strong>No matching Articles</strong>
                    <span>Try a shorter title, alias, or summary phrase.</span>
                </div>
            <?php else : ?>
                <div class="red-admin-slider-empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2"></rect><path d="M7 15l3.2-3.2 2.7 2.4 2.6-2.2 2.5 3"></path></svg>
                    <strong>No active Articles are available</strong>
                    <p>Create or activate an Article first, then return here to build this slider.</p>
                </div>
            <?php endif; ?>
        </section>

        <?php echo red_csrf_input(); ?>
        <input type="hidden" name="VarFeatures" id="VarFeatures" value="<?php echo red_admin_feature_html($VarFeatures); ?>">

        <div class="red-admin-slider-actions">
            <span id="msggbox_update_slider" class="red-admin-slider-message" role="status" aria-live="polite" hidden></span>
            <span class="red-admin-slider-actions__summary">
                <strong data-slider-selected-count><?php echo $selectedCount; ?></strong>
                <span data-slider-actions-label><?php echo $selectedCount === 1 ? 'slide selected' : 'slides selected'; ?></span>
            </span>
            <button type="submit" name="submit" value="Save" id="save" class="red-admin-slider-save"<?php echo $availableCount === 0 ? ' disabled="disabled"' : ''; ?>>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5V4Z"></path><path d="M8 4v6h8V4M8 20v-6h8v6"></path></svg>
                <span>Save slider</span>
            </button>
        </div>
    </div>
</form>
<script type="text/javascript">
if (window.redAdminSliderInit) {
    window.redAdminSliderInit(document.getElementById('update_slider'));
}
</script>
