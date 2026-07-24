<?php
/**
 * Fixed read-only selected Login provider for the isolated starter theme.
 *
 * Three fixed SELECT reads reconstruct only active Spanish Login Article
 * 966111194, its paired Form child 884542279 and parent Section 25, the two
 * root navigation rows, and two bounded text settings. The Form remains
 * display-only: no action, endpoint, payload, validation, response, request,
 * or session state enters this provider or the portable theme contract.
 */

require_once __DIR__ . '/theme_preview_administration_helpers.php';

if (!function_exists('red_theme_login_preview_canary')) {
    function red_theme_login_preview_canary()
    {
        return [
            'articleRecordId' => 966111194,
            'articleTitle' => 'Login',
            'articleAlias' => 'login',
            'component' => 'Form',
            'formRecordId' => 884542279,
            'formTitle' => 'Login',
            'formAlias' => 'login',
            'formType' => 'Login',
            'sectionRecordId' => 25,
            'section' => 'administracion',
            'sectionTitle' => 'administracion',
            'legacyLanguage' => 'sp',
            'documentLanguage' => 'es',
            'route' => '/administracion/login',
            'sectionRoute' => '/administracion/',
            'layout' => 'index-2',
            'sectionLayout' => 'index-3',
            'sectionPosition' => 1,
            'sectionPositionOrder' => 1,
            'pagePosition' => 1,
            'pagePositionOrder' => 0,
            'startDate' => '2012-06-01 00:00:00',
            'expiryDate' => '0000-00-00 00:00:00',
            'summaryBytes' => 10,
            'summarySha256' => '472ca799fe4a2463b35bf3c1d87ae468d434c82a573cc302606b35276385240f',
            'templateBytes' => 239,
            'templateSha256' => '2609b17e4e14419ac0c2117cfb699db242b193089e409d1aa0f6391da19049b5',
        ];
    }
}

if (!function_exists('red_theme_login_preview_query_inventory')) {
    function red_theme_login_preview_query_inventory()
    {
        return [
            'login-article-form-section' =>
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
                "INNER JOIN RED_C_Form AS f ON f.RecordID=884542279 AND CAST(f.RefID AS UNSIGNED)=a.RecordID\n" .
                "INNER JOIN RED_Sections AS s ON s.RecordID=25 AND s.Sections=a.Sections AND s.Language=a.Language\n" .
                "WHERE a.RecordID=966111194 AND a.Alias='login' AND a.Language='sp' AND a.Active='Y'\n" .
                'ORDER BY a.RecordID ASC LIMIT 2',
            'login-navigation' =>
                "SELECT RecordID, RootOrder, Label, Link, NewWindow, MenuOrder, Active, Language\n" .
                "FROM RED_Menu\n" .
                "WHERE RootOrder='1' AND Parent=0 AND Language='sp' AND Active='Y'\n" .
                'ORDER BY MenuOrder ASC, RecordID ASC LIMIT 20',
            'login-settings' =>
                "SELECT RecordID, Item, Content, Language\n" .
                "FROM RED_Advanced\n" .
                "WHERE Language='sp' AND Item IN ('Website_Footer', 'Website_Title')\n" .
                'ORDER BY Item ASC, RecordID ASC LIMIT 3',
        ];
    }
}

if (!function_exists('red_theme_login_preview_normalize_query')) {
    function red_theme_login_preview_normalize_query($sql)
    {
        return preg_replace('/\s+/', ' ', trim((string) $sql));
    }
}

if (!function_exists('red_theme_login_preview_assert_query_inventory')) {
    function red_theme_login_preview_assert_query_inventory(array $queries)
    {
        $expected = red_theme_login_preview_query_inventory();
        if (array_keys($queries) !== array_keys($expected)) {
            throw new RuntimeException('Login preview query inventory must contain exactly three fixed reads.');
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
                || red_theme_login_preview_normalize_query($sql)
                    !== red_theme_login_preview_normalize_query($expected[$id])
                || preg_match('/\ASELECT\s/i', ltrim($sql)) !== 1
                || strpos($sql, ';') !== false
                || preg_match(
                    '/\b(?:ALTER|CALL|CREATE|DELETE|DROP|GRANT|INSERT|LOAD|LOCK|RENAME|REPLACE|REVOKE|TRUNCATE|UPDATE)\b/i',
                    $sql
                ) === 1
                || preg_match('/(?:--|#|\/\*)/', $sql) === 1
            ) {
                throw new RuntimeException('Login preview query "' . $id . '" changed from its fixed SELECT.');
            }
            if (preg_match_all('/\b(?:FROM|JOIN)\s+([A-Za-z0-9_]+)/i', $sql, $matches) < 1) {
                throw new RuntimeException('Login preview query "' . $id . '" has no declared source table.');
            }
            foreach ($matches[1] as $table) {
                if (!isset($allowedTables[$table])) {
                    throw new RuntimeException('Login preview query "' . $id . '" uses an unexpected table.');
                }
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_login_preview_scope')) {
    function red_theme_login_preview_scope($databaseReads)
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

if (!function_exists('red_theme_login_preview_read_rows')) {
    function red_theme_login_preview_read_rows($connection)
    {
        if (!is_object($connection)
            || !method_exists($connection, 'query')
            || !method_exists($connection, 'commit')
            || !method_exists($connection, 'rollback')
        ) {
            throw new InvalidArgumentException('Login preview requires a valid mysqli connection.');
        }

        $queries = red_theme_login_preview_query_inventory();
        red_theme_login_preview_assert_query_inventory($queries);
        $started = false;
        try {
            if ($connection->query('START TRANSACTION READ ONLY') !== true) {
                throw new RuntimeException('Login preview could not start a read-only transaction.');
            }
            $started = true;
            $rows = [];
            foreach ($queries as $id => $sql) {
                $result = $connection->query($sql);
                if (!is_object($result) || !method_exists($result, 'fetch_assoc')) {
                    throw new RuntimeException('Login preview fixed read "' . $id . '" failed.');
                }
                $resultRows = [];
                while ($row = $result->fetch_assoc()) {
                    if (!is_array($row)) {
                        throw new RuntimeException('Login preview received an invalid database row.');
                    }
                    $resultRows[] = $row;
                    if (count($resultRows) > 20) {
                        throw new RuntimeException('Login preview query exceeded its fixed row boundary.');
                    }
                }
                if (method_exists($result, 'free')) {
                    $result->free();
                }
                $rows[$id] = $resultRows;
            }
            if (!$connection->commit()) {
                throw new RuntimeException('Login preview could not close its read-only transaction.');
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
                'articleFormSection' => $rows['login-article-form-section'],
                'navigation' => $rows['login-navigation'],
                'settings' => $rows['login-settings'],
            ],
            'scope' => red_theme_login_preview_scope(count($queries)),
        ];
    }
}

if (!function_exists('red_theme_login_preview_article_form')) {
    function red_theme_login_preview_article_form($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1) {
            throw new InvalidArgumentException(
                'Login preview requires exactly one joined Article, Form, and Section row.'
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
            'Login joined Article/Form/Section row'
        );
        $canary = red_theme_login_preview_canary();
        $articleRecordId = red_theme_administration_preview_integer(
            $row['ArticleRecordID'],
            'Login Article RecordID',
            1
        );
        $articleTitle = red_theme_administration_preview_plain_text(
            $row['ArticleTitle'],
            'Login Article title',
            false,
            180
        );
        $articleAlias = red_theme_administration_preview_source_string(
            $row['ArticleAlias'],
            'Login Article alias',
            false,
            100
        );
        $component = red_theme_administration_preview_source_string(
            $row['Component'],
            'Login Article component',
            false,
            50
        );
        $articleSection = red_theme_administration_preview_source_string(
            $row['ArticleSection'],
            'Login Article section',
            false,
            100
        );
        $articleLayout = red_theme_administration_preview_source_string(
            $row['ArticleLayout'],
            'Login Article layout',
            false,
            64
        );
        $articleLanguage = red_theme_administration_preview_source_string(
            $row['ArticleLanguage'],
            'Login Article language',
            false,
            2
        );
        $articleActive = red_theme_administration_preview_source_string(
            $row['ArticleActive'],
            'Login Article active state',
            false,
            1
        );
        $summary = red_theme_administration_preview_plain_text(
            $row['ShortDesc'],
            'Login Article summary',
            false,
            180
        );
        $formRecordId = red_theme_administration_preview_integer(
            $row['FormRecordID'],
            'Login Form RecordID',
            1
        );
        $formRefId = red_theme_administration_preview_integer(
            $row['FormRefID'],
            'Login Form RefID',
            1
        );
        $formTitle = red_theme_administration_preview_plain_text(
            $row['FormTitle'],
            'Login Form title',
            false,
            180
        );
        $formAlias = red_theme_administration_preview_source_string(
            $row['FormAlias'],
            'Login Form alias',
            false,
            100
        );
        $formType = red_theme_administration_preview_source_string(
            $row['FormType'],
            'Login Form type',
            false,
            20
        );
        $formTemplate = red_theme_administration_preview_source_string(
            $row['FormTemplate'],
            'Login Form template',
            false,
            3000
        );
        $sectionRecordId = red_theme_administration_preview_integer(
            $row['SectionRecordID'],
            'Login Section RecordID',
            1
        );
        $sectionAlias = red_theme_administration_preview_source_string(
            $row['SectionAlias'],
            'Login Section alias',
            false,
            100
        );
        $sectionTitle = red_theme_administration_preview_plain_text(
            $row['SectionTitle'],
            'Login Section title',
            false,
            120
        );
        $sectionLayout = red_theme_administration_preview_source_string(
            $row['SectionLayout'],
            'Login Section layout',
            false,
            64
        );
        $sectionLanguage = red_theme_administration_preview_source_string(
            $row['SectionLanguage'],
            'Login Section language',
            false,
            2
        );
        $sectionActive = red_theme_administration_preview_source_string(
            $row['SectionActive'],
            'Login Section active state',
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
                'Login category alias',
                true,
                100
            ) !== ''
            || red_theme_administration_preview_source_string(
                $row['SubCategories'],
                'Login subcategory alias',
                true,
                100
            ) !== ''
            || $articleLayout !== $canary['layout']
            || red_theme_administration_preview_integer(
                $row['SectionPosition'],
                'Login section position',
                0,
                20
            ) !== $canary['sectionPosition']
            || red_theme_administration_preview_integer(
                $row['SectionPositionOrder'],
                'Login section order',
                0,
                1000
            ) !== $canary['sectionPositionOrder']
            || red_theme_administration_preview_integer(
                $row['PagePosition'],
                'Login page position',
                0,
                20
            ) !== $canary['pagePosition']
            || red_theme_administration_preview_integer(
                $row['PagePositionOrder'],
                'Login page order',
                0,
                1000
            ) !== $canary['pagePositionOrder']
            || strlen($summary) !== $canary['summaryBytes']
            || hash('sha256', $summary) !== $canary['summarySha256']
            || red_theme_administration_preview_source_string(
                $row['Link'],
                'Login Article link',
                true,
                500
            ) !== ''
            || red_theme_administration_preview_source_string(
                $row['NewWindow'],
                'Login Article target',
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
                'Login joined Article/Form/Section row does not match the fixed selected-route canary.'
            );
        }
        $template = red_theme_administration_preview_login_template($formTemplate);

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

if (!function_exists('red_theme_login_preview_prepare_rows')) {
    function red_theme_login_preview_prepare_rows(array $rows)
    {
        red_theme_preview_require_exact_keys(
            $rows,
            ['articleFormSection', 'navigation', 'settings'],
            [],
            'Login preview rows'
        );
        $login = red_theme_login_preview_article_form($rows['articleFormSection']);
        $navigation = red_theme_administration_preview_navigation($rows['navigation']);
        $settings = red_theme_administration_preview_settings($rows['settings']);
        $canary = red_theme_login_preview_canary();
        $siteTitle = trim((string) ($settings['Website_Title'] ?? ''));
        $siteTitleSource = 'advanced.Website_Title';
        if ($siteTitle === '') {
            $siteTitle = $login['sectionTitle'];
            $siteTitleSource = 'section.Title';
        }
        $footer = trim((string) ($settings['Website_Footer'] ?? ''));
        $footerSource = 'advanced.Website_Footer';
        if ($footer === '') {
            $footer = $login['sectionTitle'];
            $footerSource = 'section.Title';
        }
        $navigationItems = array_map(static function (array $item) {
            return [
                'label' => $item['label'],
                'url' => $item['url'],
                'current' => false,
            ];
        }, $navigation);

        $fixture = [
            'schemaVersion' => 1,
            'theme' => 'starter-reference',
            'document' => [
                'language' => $canary['documentLanguage'],
                'title' => $login['articleTitle'] . ' — ' . $login['sectionTitle'],
                'description' => 'Read-only selected Login Form preview.',
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
                    'title' => $login['articleTitle'],
                    'summary' => $login['summary'],
                    'action' => [
                        'label' => 'Back to Administration',
                        'url' => $canary['sectionRoute'],
                    ],
                ],
                'footer' => [
                    'copyright' => $footer,
                ],
            ],
            'page' => [
                'layout' => $login['articleLayout'],
                'breadcrumb' => [
                    ['label' => $navigation[0]['label'], 'url' => $navigation[0]['url']],
                    ['label' => $login['sectionTitle'], 'url' => $canary['sectionRoute']],
                    ['label' => $login['articleTitle'], 'url' => ''],
                ],
                'slots' => [
                    '1' => [[
                        'component' => 'Form',
                        'data' => [
                            'title' => $login['formTitle'],
                            'fields' => $login['fields'],
                            'submitLabel' => $login['submitLabel'],
                        ],
                    ]],
                    '2' => [],
                    '3' => [],
                    '4' => [],
                ],
            ],
        ];
        $source = [
            'mode' => 'read-only-login-preview',
            'canary' => [
                'articleRecordId' => $login['articleRecordId'],
                'articleAlias' => $login['articleAlias'],
                'formRecordId' => $login['formRecordId'],
                'formAlias' => $login['formAlias'],
                'sectionRecordId' => $login['sectionRecordId'],
                'section' => $login['sectionAlias'],
                'legacyLanguage' => $canary['legacyLanguage'],
                'route' => $canary['route'],
                'layout' => $login['articleLayout'],
                'pagePosition' => $canary['pagePosition'],
                'pagePositionOrder' => $canary['pagePositionOrder'],
            ],
            'form' => [
                'type' => $login['formType'],
                'templateBytes' => $canary['templateBytes'],
                'templateSha256' => $canary['templateSha256'],
                'fieldCount' => count($login['fields']),
                'fieldNames' => array_column($login['fields'], 'name'),
                'buttonType' => 'button',
                'submissionConnected' => false,
            ],
            'queryIds' => array_keys(red_theme_login_preview_query_inventory()),
            'rowCounts' => [
                'articleFormSection' => count($rows['articleFormSection']),
                'navigation' => count($rows['navigation']),
                'settings' => count($rows['settings']),
            ],
            'fallbacks' => [
                'siteTitle' => $siteTitleSource,
                'footer' => $footerSource,
            ],
        ];
        red_theme_preview_assert_non_executable($fixture, 'Login prepared preview input');
        red_theme_preview_assert_non_executable($source, 'Login preview source metadata');

        return ['fixture' => $fixture, 'source' => $source];
    }
}

if (!function_exists('red_theme_login_preview_render_rows')) {
    function red_theme_login_preview_render_rows(array $rows, $projectRoot = null, $databaseReads = 0)
    {
        if (!in_array($databaseReads, [0, 3], true)) {
            throw new InvalidArgumentException('Login preview database-read count must be zero or three.');
        }
        $validation = red_theme_preview_validate_reference_theme('starter-reference', $projectRoot);
        $prepared = red_theme_login_preview_prepare_rows($rows);
        $contract = red_theme_preview_contract($prepared['fixture'], $validation);
        $result = red_theme_preview_render_prepared_contract(
            $validation,
            $contract,
            'read-only-login-preview',
            red_theme_login_preview_scope($databaseReads)
        );
        $result['source'] = $prepared['source'];

        return $result;
    }
}

if (!function_exists('red_theme_login_preview_render')) {
    function red_theme_login_preview_render($connection, $projectRoot = null)
    {
        $read = red_theme_login_preview_read_rows($connection);
        $result = red_theme_login_preview_render_rows(
            $read['rows'],
            $projectRoot,
            $read['scope']['databaseReads']
        );
        if ($result['scope'] !== $read['scope']) {
            throw new RuntimeException('Login preview side-effect scope changed during rendering.');
        }

        return $result;
    }
}
