<?php
/**
 * Fixed read-only selected Contact provider for the isolated starter theme.
 *
 * Three fixed SELECT reads reconstruct only active Spanish Contact Article
 * 459269660, its paired Form child 93039112 and parent Section 24, the two
 * root navigation rows, and two bounded text settings. The Form remains
 * display-only: no action, endpoint, payload, validation, response, request,
 * or session state enters this provider or the portable theme contract.
 */

require_once __DIR__ . '/theme_preview_administration_helpers.php';
require_once __DIR__ . '/theme_preview_contact_helpers.php';

if (!function_exists('red_theme_selected_contact_preview_canary')) {
    function red_theme_selected_contact_preview_canary()
    {
        return [
            'articleRecordId' => 459269660,
            'articleTitle' => 'Contact',
            'articleAlias' => 'contact',
            'component' => 'Form',
            'formRecordId' => 93039112,
            'formTitle' => 'Contact',
            'formAlias' => 'contact',
            'formType' => 'Contact',
            'sectionRecordId' => 24,
            'section' => 'contacto',
            'sectionTitle' => 'contacto',
            'legacyLanguage' => 'sp',
            'documentLanguage' => 'es',
            'route' => '/contacto/contact',
            'sectionRoute' => '/contacto/',
            'layout' => 'index-1',
            'sectionLayout' => 'index-1',
            'sectionPosition' => 1,
            'sectionPositionOrder' => 1,
            'pagePosition' => 1,
            'pagePositionOrder' => 0,
            'startDate' => '1970-01-01 00:00:00',
            'expiryDate' => '9999-12-31 23:59:59',
            'summaryBytes' => 0,
            'summarySha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'templateBytes' => 686,
            'templateSha256' => '5f84ca1244b3c9a66884783469ef6ee2bed4d469f2a75d73a337acc72c43d1a1',
        ];
    }
}

if (!function_exists('red_theme_selected_contact_preview_query_inventory')) {
    function red_theme_selected_contact_preview_query_inventory()
    {
        return [
            'selected-contact-article-form-section' =>
                "SELECT a.RecordID AS ArticleRecordID, a.Title AS ArticleTitle, a.Alias AS ArticleAlias,\n" .
                "a.Component, a.Sections AS ArticleSection, a.Categories, a.SubCategories,\n" .
                "a.Layout AS ArticleLayout, a.SectionPosition, a.SectionPositionOrder,\n" .
                "a.PagePosition, a.PagePositionOrder, a.ShortDesc, a.Link, a.NewWindow,\n" .
                "a.Language AS ArticleLanguage, a.Active AS ArticleActive, a.StartDate, a.ExpDate,\n" .
                "f.RecordID AS FormRecordID, f.RefID AS FormRefID, f.Title AS FormTitle,\n" .
                "f.Alias AS FormAlias, f.FormType, f.LongDesc AS FormTemplate,\n" .
                "s.RecordID AS SectionRecordID, s.Sections AS SectionAlias, s.Title AS SectionTitle,\n" .
                "s.Layout AS SectionLayout, s.Language AS SectionLanguage, s.Active AS SectionActive\n" .
                "FROM RED_Articles AS a\n" .
                "INNER JOIN RED_C_Form AS f ON f.RecordID=93039112 AND CAST(f.RefID AS UNSIGNED)=a.RecordID\n" .
                "INNER JOIN RED_Sections AS s ON s.RecordID=24 AND s.Sections=a.Sections AND s.Language=a.Language\n" .
                "WHERE a.RecordID=459269660 AND a.Alias='contact' AND a.Language='sp' AND a.Active='Y'\n" .
                'ORDER BY a.RecordID ASC LIMIT 2',
            'selected-contact-navigation' =>
                "SELECT RecordID, RootOrder, Label, Link, NewWindow, MenuOrder, Active, Language\n" .
                "FROM RED_Menu\n" .
                "WHERE RootOrder='1' AND Parent=0 AND Language='sp' AND Active='Y'\n" .
                'ORDER BY MenuOrder ASC, RecordID ASC LIMIT 20',
            'selected-contact-settings' =>
                "SELECT RecordID, Item, Content, Language\n" .
                "FROM RED_Advanced\n" .
                "WHERE Language='sp' AND Item IN ('Website_Footer', 'Website_Title')\n" .
                'ORDER BY Item ASC, RecordID ASC LIMIT 3',
        ];
    }
}

if (!function_exists('red_theme_selected_contact_preview_normalize_query')) {
    function red_theme_selected_contact_preview_normalize_query($sql)
    {
        return preg_replace('/\s+/', ' ', trim((string) $sql));
    }
}

if (!function_exists('red_theme_selected_contact_preview_assert_query_inventory')) {
    function red_theme_selected_contact_preview_assert_query_inventory(array $queries)
    {
        $expected = red_theme_selected_contact_preview_query_inventory();
        if (array_keys($queries) !== array_keys($expected)) {
            throw new RuntimeException(
                'Selected Contact preview query inventory must contain exactly three fixed reads.'
            );
        }
        $allowedTables = [
            'RED_Articles' => true,
            'RED_C_Form' => true,
            'RED_Sections' => true,
            'RED_Menu' => true,
            'RED_Advanced' => true,
        ];
        foreach ($queries as $id => $sql) {
            if (!is_string($sql)
                || red_theme_selected_contact_preview_normalize_query($sql)
                    !== red_theme_selected_contact_preview_normalize_query($expected[$id])
                || preg_match('/\ASELECT\s/i', ltrim($sql)) !== 1
                || strpos($sql, ';') !== false
                || preg_match(
                    '/\b(?:ALTER|CALL|CREATE|DELETE|DROP|GRANT|INSERT|LOAD|LOCK|RENAME|REPLACE|REVOKE|TRUNCATE|UPDATE)\b/i',
                    $sql
                ) === 1
                || preg_match('/(?:--|#|\/\*)/', $sql) === 1
            ) {
                throw new RuntimeException(
                    'Selected Contact preview query "' . $id . '" changed from its fixed SELECT.'
                );
            }
            if (preg_match_all('/\b(?:FROM|JOIN)\s+([A-Za-z0-9_]+)/i', $sql, $matches) < 1) {
                throw new RuntimeException(
                    'Selected Contact preview query "' . $id . '" has no declared source table.'
                );
            }
            foreach ($matches[1] as $table) {
                if (!isset($allowedTables[$table])) {
                    throw new RuntimeException(
                        'Selected Contact preview query "' . $id . '" uses an unexpected table.'
                    );
                }
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_selected_contact_preview_scope')) {
    function red_theme_selected_contact_preview_scope($databaseReads)
    {
        return red_theme_preview_scope([
            'databaseReads' => $databaseReads,
            'databaseWrites' => 0,
            'sessionReads' => 0,
            'sessionWrites' => 0,
            'liveRuntimeChanges' => 0,
        ]);
    }
}

if (!function_exists('red_theme_selected_contact_preview_read_rows')) {
    function red_theme_selected_contact_preview_read_rows($connection)
    {
        if (!is_object($connection)
            || !method_exists($connection, 'query')
            || !method_exists($connection, 'commit')
            || !method_exists($connection, 'rollback')
        ) {
            throw new InvalidArgumentException(
                'Selected Contact preview requires a valid mysqli connection.'
            );
        }

        $queries = red_theme_selected_contact_preview_query_inventory();
        red_theme_selected_contact_preview_assert_query_inventory($queries);
        $started = false;
        try {
            if ($connection->query('START TRANSACTION READ ONLY') !== true) {
                throw new RuntimeException(
                    'Selected Contact preview could not start a read-only transaction.'
                );
            }
            $started = true;
            $rows = [];
            foreach ($queries as $id => $sql) {
                $result = $connection->query($sql);
                if (!is_object($result) || !method_exists($result, 'fetch_assoc')) {
                    throw new RuntimeException(
                        'Selected Contact preview fixed read "' . $id . '" failed.'
                    );
                }
                $resultRows = [];
                while ($row = $result->fetch_assoc()) {
                    if (!is_array($row)) {
                        throw new RuntimeException(
                            'Selected Contact preview received an invalid database row.'
                        );
                    }
                    $resultRows[] = $row;
                    if (count($resultRows) > 20) {
                        throw new RuntimeException(
                            'Selected Contact preview query exceeded its fixed row boundary.'
                        );
                    }
                }
                if (method_exists($result, 'free')) {
                    $result->free();
                }
                $rows[$id] = $resultRows;
            }
            if (!$connection->commit()) {
                throw new RuntimeException(
                    'Selected Contact preview could not close its read-only transaction.'
                );
            }
            $started = false;
        } catch (Throwable $exception) {
            if ($started) {
                $connection->rollback();
            }
            throw $exception;
        }

        return [
            'rows' => [
                'articleFormSection' => $rows['selected-contact-article-form-section'],
                'navigation' => $rows['selected-contact-navigation'],
                'settings' => $rows['selected-contact-settings'],
            ],
            'scope' => red_theme_selected_contact_preview_scope(count($queries)),
        ];
    }
}

if (!function_exists('red_theme_selected_contact_preview_article_form')) {
    function red_theme_selected_contact_preview_article_form($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1) {
            throw new InvalidArgumentException(
                'Selected Contact preview requires exactly one joined Article, Form, and Section row.'
            );
        }
        $row = red_theme_administration_preview_require_row_keys(
            $rows[0],
            [
                'ArticleRecordID', 'ArticleTitle', 'ArticleAlias', 'Component', 'ArticleSection',
                'Categories', 'SubCategories', 'ArticleLayout', 'SectionPosition',
                'SectionPositionOrder', 'PagePosition', 'PagePositionOrder', 'ShortDesc',
                'Link', 'NewWindow', 'ArticleLanguage', 'ArticleActive', 'StartDate', 'ExpDate',
                'FormRecordID', 'FormRefID', 'FormTitle', 'FormAlias', 'FormType', 'FormTemplate',
                'SectionRecordID', 'SectionAlias', 'SectionTitle', 'SectionLayout',
                'SectionLanguage', 'SectionActive',
            ],
            'Selected Contact joined Article/Form/Section row'
        );
        $canary = red_theme_selected_contact_preview_canary();
        $articleRecordId = red_theme_administration_preview_integer(
            $row['ArticleRecordID'],
            'Selected Contact Article RecordID',
            1
        );
        $articleTitle = red_theme_administration_preview_plain_text(
            $row['ArticleTitle'],
            'Selected Contact Article title',
            false,
            180
        );
        $articleAlias = red_theme_administration_preview_source_string(
            $row['ArticleAlias'],
            'Selected Contact Article alias',
            false,
            100
        );
        $component = red_theme_administration_preview_source_string(
            $row['Component'],
            'Selected Contact Article component',
            false,
            50
        );
        $articleSection = red_theme_administration_preview_source_string(
            $row['ArticleSection'],
            'Selected Contact Article section',
            false,
            100
        );
        $articleLayout = red_theme_administration_preview_source_string(
            $row['ArticleLayout'],
            'Selected Contact Article layout',
            false,
            64
        );
        $articleLanguage = red_theme_administration_preview_source_string(
            $row['ArticleLanguage'],
            'Selected Contact Article language',
            false,
            2
        );
        $articleActive = red_theme_administration_preview_source_string(
            $row['ArticleActive'],
            'Selected Contact Article active state',
            false,
            1
        );
        $summary = red_theme_administration_preview_plain_text(
            $row['ShortDesc'],
            'Selected Contact Article summary',
            true,
            180
        );
        $formRecordId = red_theme_administration_preview_integer(
            $row['FormRecordID'],
            'Selected Contact Form RecordID',
            1
        );
        $formRefId = red_theme_administration_preview_integer(
            $row['FormRefID'],
            'Selected Contact Form RefID',
            1
        );
        $formTitle = red_theme_administration_preview_plain_text(
            $row['FormTitle'],
            'Selected Contact Form title',
            false,
            180
        );
        $formAlias = red_theme_administration_preview_source_string(
            $row['FormAlias'],
            'Selected Contact Form alias',
            false,
            100
        );
        $formType = red_theme_administration_preview_source_string(
            $row['FormType'],
            'Selected Contact Form type',
            false,
            20
        );
        $formTemplate = red_theme_administration_preview_source_string(
            $row['FormTemplate'],
            'Selected Contact Form template',
            false,
            10000
        );
        $sectionRecordId = red_theme_administration_preview_integer(
            $row['SectionRecordID'],
            'Selected Contact Section RecordID',
            1
        );
        $sectionAlias = red_theme_administration_preview_source_string(
            $row['SectionAlias'],
            'Selected Contact Section alias',
            false,
            100
        );
        $sectionTitle = red_theme_administration_preview_plain_text(
            $row['SectionTitle'],
            'Selected Contact Section title',
            false,
            120
        );
        $sectionLayout = red_theme_administration_preview_source_string(
            $row['SectionLayout'],
            'Selected Contact Section layout',
            false,
            64
        );
        $sectionLanguage = red_theme_administration_preview_source_string(
            $row['SectionLanguage'],
            'Selected Contact Section language',
            false,
            2
        );
        $sectionActive = red_theme_administration_preview_source_string(
            $row['SectionActive'],
            'Selected Contact Section active state',
            false,
            1
        );

        if ($articleRecordId !== $canary['articleRecordId']
            || $articleTitle !== $canary['articleTitle']
            || $articleAlias !== $canary['articleAlias']
            || $component !== $canary['component']
            || $articleSection !== $canary['section']
            || red_theme_administration_preview_source_string(
                $row['Categories'],
                'Selected Contact category alias',
                true,
                100
            ) !== ''
            || red_theme_administration_preview_source_string(
                $row['SubCategories'],
                'Selected Contact subcategory alias',
                true,
                100
            ) !== ''
            || $articleLayout !== $canary['layout']
            || red_theme_administration_preview_integer(
                $row['SectionPosition'],
                'Selected Contact section position',
                0,
                20
            ) !== $canary['sectionPosition']
            || red_theme_administration_preview_integer(
                $row['SectionPositionOrder'],
                'Selected Contact section order',
                0,
                1000
            ) !== $canary['sectionPositionOrder']
            || red_theme_administration_preview_integer(
                $row['PagePosition'],
                'Selected Contact page position',
                0,
                20
            ) !== $canary['pagePosition']
            || red_theme_administration_preview_integer(
                $row['PagePositionOrder'],
                'Selected Contact page order',
                0,
                1000
            ) !== $canary['pagePositionOrder']
            || strlen($summary) !== $canary['summaryBytes']
            || hash('sha256', $summary) !== $canary['summarySha256']
            || red_theme_administration_preview_source_string(
                $row['Link'],
                'Selected Contact Article link',
                true,
                500
            ) !== ''
            || red_theme_administration_preview_source_string(
                $row['NewWindow'],
                'Selected Contact Article target',
                true,
                10
            ) !== ''
            || $articleLanguage !== $canary['legacyLanguage']
            || $articleActive !== 'Y'
            || (string) $row['StartDate'] !== $canary['startDate']
            || (string) $row['ExpDate'] !== $canary['expiryDate']
            || $formRecordId !== $canary['formRecordId']
            || $formRefId !== $articleRecordId
            || $formTitle !== $canary['formTitle']
            || $formAlias !== $canary['formAlias']
            || $formType !== $canary['formType']
            || strlen($formTemplate) !== $canary['templateBytes']
            || hash('sha256', $formTemplate) !== $canary['templateSha256']
            || $sectionRecordId !== $canary['sectionRecordId']
            || $sectionAlias !== $canary['section']
            || $sectionTitle !== $canary['sectionTitle']
            || $sectionLayout !== $canary['sectionLayout']
            || $sectionLanguage !== $canary['legacyLanguage']
            || $sectionActive !== 'Y'
        ) {
            throw new InvalidArgumentException(
                'Selected Contact joined Article/Form/Section row does not match the fixed route canary.'
            );
        }
        $template = red_theme_contact_preview_form_template($formTemplate);

        return [
            'articleRecordId' => $articleRecordId,
            'articleTitle' => $articleTitle,
            'articleAlias' => $articleAlias,
            'articleLayout' => $articleLayout,
            'summary' => $summary,
            'formRecordId' => $formRecordId,
            'formTitle' => $formTitle,
            'formAlias' => $formAlias,
            'formType' => $formType,
            'fields' => $template['fields'],
            'submitLabel' => $template['submitLabel'],
            'sectionRecordId' => $sectionRecordId,
            'sectionAlias' => $sectionAlias,
            'sectionTitle' => $sectionTitle,
            'sectionLayout' => $sectionLayout,
        ];
    }
}

if (!function_exists('red_theme_selected_contact_preview_prepare_rows')) {
    function red_theme_selected_contact_preview_prepare_rows(array $rows)
    {
        red_theme_preview_require_exact_keys(
            $rows,
            ['articleFormSection', 'navigation', 'settings'],
            [],
            'Selected Contact preview rows'
        );
        $contact = red_theme_selected_contact_preview_article_form($rows['articleFormSection']);
        $navigation = red_theme_administration_preview_navigation($rows['navigation']);
        $settings = red_theme_administration_preview_settings($rows['settings']);
        $canary = red_theme_selected_contact_preview_canary();
        $siteTitle = trim((string) ($settings['Website_Title'] ?? ''));
        $siteTitleSource = 'advanced.Website_Title';
        if ($siteTitle === '') {
            $siteTitle = $contact['sectionTitle'];
            $siteTitleSource = 'section.Title';
        }
        $footer = trim((string) ($settings['Website_Footer'] ?? ''));
        $footerSource = 'advanced.Website_Footer';
        if ($footer === '') {
            $footer = $contact['sectionTitle'];
            $footerSource = 'section.Title';
        }
        $description = $contact['summary'];
        $descriptionSource = 'article.ShortDesc';
        if ($description === '') {
            $description = $contact['formTitle'];
            $descriptionSource = 'form.Title';
        }
        $navigationItems = array_map(static function (array $item) use ($canary) {
            return [
                'label' => $item['label'],
                'url' => $item['url'],
                'current' => $item['url'] === $canary['sectionRoute'],
            ];
        }, $navigation);

        $fixture = [
            'schemaVersion' => 1,
            'theme' => 'starter-reference',
            'document' => [
                'language' => $canary['documentLanguage'],
                'title' => $contact['articleTitle'] . ' — ' . $contact['sectionTitle'],
                'description' => $description,
            ],
            'regions' => [
                'header' => [
                    'siteTitle' => $siteTitle,
                    'homeUrl' => '/',
                ],
                'navigation' => [
                    'items' => $navigationItems,
                ],
                'hero' => [
                    'title' => $contact['articleTitle'],
                    'summary' => $description,
                    'action' => [
                        'label' => 'Back to Contacto',
                        'url' => $canary['sectionRoute'],
                    ],
                ],
                'footer' => [
                    'copyright' => $footer,
                ],
            ],
            'page' => [
                'layout' => $contact['articleLayout'],
                'breadcrumb' => [
                    ['label' => $navigation[0]['label'], 'url' => $navigation[0]['url']],
                    ['label' => $contact['sectionTitle'], 'url' => $canary['sectionRoute']],
                    ['label' => $contact['articleTitle'], 'url' => ''],
                ],
                'slots' => [
                    '1' => [[
                        'component' => 'Form',
                        'data' => [
                            'title' => $contact['formTitle'],
                            'fields' => $contact['fields'],
                            'submitLabel' => $contact['submitLabel'],
                        ],
                    ]],
                    '2' => [],
                    '3' => [],
                    '4' => [],
                ],
            ],
        ];
        $source = [
            'mode' => 'read-only-selected-contact-preview',
            'canary' => [
                'articleRecordId' => $contact['articleRecordId'],
                'articleAlias' => $contact['articleAlias'],
                'formRecordId' => $contact['formRecordId'],
                'formAlias' => $contact['formAlias'],
                'sectionRecordId' => $contact['sectionRecordId'],
                'section' => $contact['sectionAlias'],
                'legacyLanguage' => $canary['legacyLanguage'],
                'route' => $canary['route'],
                'layout' => $contact['articleLayout'],
                'pagePosition' => $canary['pagePosition'],
                'pagePositionOrder' => $canary['pagePositionOrder'],
            ],
            'form' => [
                'type' => $contact['formType'],
                'templateBytes' => $canary['templateBytes'],
                'templateSha256' => $canary['templateSha256'],
                'fieldCount' => count($contact['fields']),
                'fieldNames' => array_column($contact['fields'], 'name'),
                'buttonType' => 'button',
                'submissionConnected' => false,
            ],
            'queryIds' => array_keys(red_theme_selected_contact_preview_query_inventory()),
            'rowCounts' => [
                'articleFormSection' => count($rows['articleFormSection']),
                'navigation' => count($rows['navigation']),
                'settings' => count($rows['settings']),
            ],
            'fallbacks' => [
                'siteTitle' => $siteTitleSource,
                'description' => $descriptionSource,
                'footer' => $footerSource,
            ],
        ];
        red_theme_preview_assert_non_executable($fixture, 'Selected Contact prepared preview input');
        red_theme_preview_assert_non_executable($source, 'Selected Contact preview source metadata');

        return ['fixture' => $fixture, 'source' => $source];
    }
}

if (!function_exists('red_theme_selected_contact_preview_render_rows')) {
    function red_theme_selected_contact_preview_render_rows(
        array $rows,
        $projectRoot = null,
        $databaseReads = 0
    ) {
        if (!in_array($databaseReads, [0, 3], true)) {
            throw new InvalidArgumentException(
                'Selected Contact preview database-read count must be zero or three.'
            );
        }
        $validation = red_theme_preview_validate_reference_theme('starter-reference', $projectRoot);
        $prepared = red_theme_selected_contact_preview_prepare_rows($rows);
        $contract = red_theme_preview_contract($prepared['fixture'], $validation);
        $result = red_theme_preview_render_prepared_contract(
            $validation,
            $contract,
            'read-only-selected-contact-preview',
            red_theme_selected_contact_preview_scope($databaseReads)
        );
        $result['source'] = $prepared['source'];

        return $result;
    }
}

if (!function_exists('red_theme_selected_contact_preview_render')) {
    function red_theme_selected_contact_preview_render($connection, $projectRoot = null)
    {
        $read = red_theme_selected_contact_preview_read_rows($connection);
        $result = red_theme_selected_contact_preview_render_rows(
            $read['rows'],
            $projectRoot,
            $read['scope']['databaseReads']
        );
        if ($result['scope'] !== $read['scope']) {
            throw new RuntimeException(
                'Selected Contact preview side-effect scope changed during rendering.'
            );
        }

        return $result;
    }
}
