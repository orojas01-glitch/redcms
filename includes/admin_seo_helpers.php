<?php
/**
 * Administrator helpers for nullable RED_Page_SEO overrides.
 */

require_once __DIR__ . '/seo_metadata_helpers.php';
require_once __DIR__ . '/admin_area_helpers.php';

if (!function_exists('red_admin_seo_post_present')) {
    function red_admin_seo_post_present(array $post)
    {
        foreach (array_keys(red_seo_field_definitions()) as $field) {
            if (array_key_exists($field, $post)) {
                return true;
            }
        }
        return false;
    }

    function red_admin_seo_collect_post(array $post)
    {
        return red_seo_collect_input($post);
    }

    function red_admin_seo_values($connection, $ownerType, $ownerRecordId)
    {
        $values = red_seo_empty_values();
        $row = red_seo_metadata_row($connection, $ownerType, $ownerRecordId);
        if (!$row) {
            return $values;
        }
        foreach ($values as $field => $unused) {
            $values[$field] = red_seo_scalar($row[$field] ?? '');
        }
        return $values;
    }

    function red_admin_seo_save($connection, $ownerType, $ownerRecordId, array $values)
    {
        return red_seo_save_metadata(
            $connection,
            $ownerType,
            $ownerRecordId,
            $values,
            (int) ($_SESSION['AdminRecordID'] ?? 0)
        );
    }

    function red_admin_seo_area_owner_type($table)
    {
        $owners = [
            'RED_Sections' => 'section',
            'RED_Categories' => 'category',
            'RED_SubCategories' => 'subcategory',
        ];
        return $owners[(string) $table] ?? '';
    }

    function red_admin_seo_insert_area(
        $connection,
        $table,
        $aliasColumn,
        $title,
        $alias,
        $layout,
        $queryLimit,
        $accessLevel,
        $features,
        $active,
        $description,
        $tags,
        $language,
        $parentRecordId,
        array $seoValues
    ) {
        $ownerType = red_admin_seo_area_owner_type($table);
        if ($ownerType === '' || !red_seo_table_available($connection)) {
            return false;
        }

        $insertedRecordId = 0;
        $success = red_admin_theme_contract_write_transaction(
            $connection,
            function () use (
                $connection,
                $table,
                $aliasColumn,
                $title,
                $alias,
                $layout,
                $queryLimit,
                $accessLevel,
                $features,
                $active,
                $description,
                $tags,
                $language,
                $parentRecordId,
                $seoValues,
                $ownerType,
                &$insertedRecordId
            ) {
                if (!red_admin_insert_area_unlocked(
                    $connection,
                    $table,
                    $aliasColumn,
                    $title,
                    $alias,
                    $layout,
                    $queryLimit,
                    $accessLevel,
                    $features,
                    $active,
                    $description,
                    $tags,
                    $language,
                    $parentRecordId
                )) {
                    return false;
                }
                $insertedRecordId = (int) mysqli_insert_id($connection);
                return $insertedRecordId > 0
                    && red_admin_seo_save($connection, $ownerType, $insertedRecordId, $seoValues);
            },
            [$table, 'RED_Page_SEO']
        );

        return $success ? $insertedRecordId : false;
    }

    function red_admin_seo_area_save_callback($connection, $ownerType, $ownerRecordId, array $values)
    {
        return static function () use ($connection, $ownerType, $ownerRecordId, $values) {
            return red_admin_seo_save($connection, $ownerType, $ownerRecordId, $values);
        };
    }

    function red_admin_seo_field_value(array $values, $field)
    {
        return red_admin_area_html($values[$field] ?? '');
    }

    function red_admin_seo_select_options($selected, array $options)
    {
        $selected = (string) $selected;
        $html = '';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . red_admin_area_html($value) . '"' .
                ((string) $value === $selected ? ' selected="selected"' : '') . '>' .
                red_admin_area_html($label) . '</option>';
        }
        return $html;
    }

    function red_admin_seo_fields_html(array $values, $idPrefix = 'article-seo')
    {
        $idPrefix = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $idPrefix));
        if ($idPrefix === '') {
            $idPrefix = 'page-seo';
        }

        ob_start();
        ?>
        <section class="red-admin-optional-card" aria-labelledby="<?php echo red_admin_area_html($idPrefix); ?>-title">
            <div class="red-admin-optional-card__heading">
                <span class="red-admin-optional-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4M8 11h6M11 8v6"></path></svg>
                </span>
                <div>
                    <h4 id="<?php echo red_admin_area_html($idPrefix); ?>-title">Search and social metadata</h4>
                    <p>Optional overrides use safe page-derived values when left blank.</p>
                </div>
            </div>

            <div class="red-admin-field-grid red-admin-field-grid--identity">
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-title-override">SEO title</label>
                    <input name="SEO_Title" type="text" id="<?php echo red_admin_area_html($idPrefix); ?>-title-override" maxlength="255" value="<?php echo red_admin_seo_field_value($values, 'SEO_Title'); ?>" autocomplete="off" />
                    <span class="red-admin-field__help">Rendered exactly as entered. Blank keeps the current RED-CMS title.</span>
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-canonical">Canonical URL override</label>
                    <input name="CanonicalURL" type="url" id="<?php echo red_admin_area_html($idPrefix); ?>-canonical" maxlength="2048" value="<?php echo red_admin_seo_field_value($values, 'CanonicalURL'); ?>" placeholder="https://example.com/page/" inputmode="url" />
                    <span class="red-admin-field__help">Advanced override. Blank uses the resolved public route.</span>
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-robots-index">Search indexing</label>
                    <select name="RobotsIndex" id="<?php echo red_admin_area_html($idPrefix); ?>-robots-index">
                        <?php echo red_admin_seo_select_options($values['RobotsIndex'] ?? '', [
                            '' => 'Default — index',
                            'Y' => 'Index',
                            'N' => 'Do not index',
                        ]); ?>
                    </select>
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-robots-follow">Link following</label>
                    <select name="RobotsFollow" id="<?php echo red_admin_area_html($idPrefix); ?>-robots-follow">
                        <?php echo red_admin_seo_select_options($values['RobotsFollow'] ?? '', [
                            '' => 'Default — follow',
                            'Y' => 'Follow',
                            'N' => 'Do not follow',
                        ]); ?>
                    </select>
                </div>
                <div class="red-admin-field red-admin-field--wide">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-description">Meta description</label>
                    <textarea name="MetaDescription" id="<?php echo red_admin_area_html($idPrefix); ?>-description" rows="3" maxlength="1000"><?php echo red_admin_seo_field_value($values, 'MetaDescription'); ?></textarea>
                    <span class="red-admin-field__help">Blank uses the page summary or area description.</span>
                </div>
            </div>

            <div class="red-admin-field-grid red-admin-field-grid--identity">
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-og-title">Social title</label>
                    <input name="OGTitle" type="text" id="<?php echo red_admin_area_html($idPrefix); ?>-og-title" maxlength="255" value="<?php echo red_admin_seo_field_value($values, 'OGTitle'); ?>" autocomplete="off" />
                    <span class="red-admin-field__help">Blank falls back to the SEO or visible title.</span>
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-og-type">Open Graph type</label>
                    <select name="OGType" id="<?php echo red_admin_area_html($idPrefix); ?>-og-type">
                        <?php echo red_admin_seo_select_options($values['OGType'] ?? '', [
                            '' => 'Automatic',
                            'website' => 'Website',
                            'article' => 'Article',
                            'profile' => 'Profile',
                            'book' => 'Book',
                        ]); ?>
                    </select>
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-og-locale">Open Graph locale</label>
                    <input name="OGLocale" type="text" id="<?php echo red_admin_area_html($idPrefix); ?>-og-locale" maxlength="20" value="<?php echo red_admin_seo_field_value($values, 'OGLocale'); ?>" placeholder="es_CO" pattern="[a-z]{2}(_[A-Z]{2})?" />
                    <span class="red-admin-field__help">Blank derives a locale from the page language.</span>
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-og-image">Social image</label>
                    <input name="OGImage" type="text" id="<?php echo red_admin_area_html($idPrefix); ?>-og-image" maxlength="2048" value="<?php echo red_admin_seo_field_value($values, 'OGImage'); ?>" placeholder="/images/page-social.jpg" />
                    <span class="red-admin-field__help">Use a root-relative CMS asset or complete HTTPS URL.</span>
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-og-image-alt">Social image alternative text</label>
                    <input name="OGImageAlt" type="text" id="<?php echo red_admin_area_html($idPrefix); ?>-og-image-alt" maxlength="255" value="<?php echo red_admin_seo_field_value($values, 'OGImageAlt'); ?>" autocomplete="off" />
                </div>
                <div class="red-admin-field red-admin-field--wide">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-og-description">Social description</label>
                    <textarea name="OGDescription" id="<?php echo red_admin_area_html($idPrefix); ?>-og-description" rows="3" maxlength="1000"><?php echo red_admin_seo_field_value($values, 'OGDescription'); ?></textarea>
                    <span class="red-admin-field__help">Blank falls back to the meta description.</span>
                </div>
            </div>

            <div class="red-admin-field-grid red-admin-field-grid--identity">
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-x-card">X/Twitter card</label>
                    <select name="XCard" id="<?php echo red_admin_area_html($idPrefix); ?>-x-card">
                        <?php echo red_admin_seo_select_options($values['XCard'] ?? '', [
                            '' => 'Automatic',
                            'summary' => 'Summary',
                            'summary_large_image' => 'Large image',
                        ]); ?>
                    </select>
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-x-title">X/Twitter title</label>
                    <input name="XTitle" type="text" id="<?php echo red_admin_area_html($idPrefix); ?>-x-title" maxlength="255" value="<?php echo red_admin_seo_field_value($values, 'XTitle'); ?>" autocomplete="off" />
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-x-image">X/Twitter image</label>
                    <input name="XImage" type="text" id="<?php echo red_admin_area_html($idPrefix); ?>-x-image" maxlength="2048" value="<?php echo red_admin_seo_field_value($values, 'XImage'); ?>" placeholder="/images/page-social.jpg" />
                </div>
                <div class="red-admin-field">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-schema-type">Structured-data type</label>
                    <select name="SchemaType" id="<?php echo red_admin_area_html($idPrefix); ?>-schema-type">
                        <?php echo red_admin_seo_select_options($values['SchemaType'] ?? '', [
                            '' => 'Automatic WebPage',
                            'WebPage' => 'WebPage',
                            'Course' => 'Course',
                            'Service' => 'Service',
                        ]); ?>
                    </select>
                    <span class="red-admin-field__help">JSON-LD is generated from visible, validated page content.</span>
                </div>
                <div class="red-admin-field red-admin-field--wide">
                    <label for="<?php echo red_admin_area_html($idPrefix); ?>-x-description">X/Twitter description</label>
                    <textarea name="XDescription" id="<?php echo red_admin_area_html($idPrefix); ?>-x-description" rows="3" maxlength="1000"><?php echo red_admin_seo_field_value($values, 'XDescription'); ?></textarea>
                    <span class="red-admin-field__help">Blank falls back through the Open Graph and meta descriptions.</span>
                </div>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
?>
