<?php
/**
 * Dependency-free tests for the read-only activation-readiness report.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_readiness_helpers.php';

$assertions = 0;

function red_theme_readiness_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_readiness_test_area($recordId, $slug, $title, $layout, $features = '')
{
    return [
        'AreaType' => 'section',
        'RecordID' => (string) $recordId,
        'Slug' => $slug,
        'Title' => $title,
        'Layout' => $layout,
        'QueryLimit' => '100',
        'Features' => $features,
        'Description' => '',
        'Tags' => '',
        'Language' => 'sp',
        'Active' => 'Y',
    ];
}

function red_theme_readiness_test_article(array $overrides)
{
    $base = [
        'RecordID' => '1',
        'Title' => 'Fixture',
        'Component' => 'Article',
        'Alias' => 'fixture',
        'Sections' => 'administracion',
        'Categories' => '',
        'SubCategories' => '',
        'Layout' => 'index-2',
        'Article' => '',
        'HomePosition' => '0',
        'HomePositionOrder' => '0',
        'SectionPosition' => '1',
        'SectionPositionOrder' => '1',
        'CategoryPosition' => '0',
        'CategoryPositionOrder' => '0',
        'SubCategoryPosition' => '0',
        'SubCategoryPositionOrder' => '0',
        'PagePosition' => '1',
        'PagePositionOrder' => '0',
        'HomeFeature' => '',
        'HomeFeatures' => '',
        'HomeFeatures_Order' => '0',
        'SectionFeatures' => '',
        'SectionFeatures_Order' => '0',
        'CategoryFeatures' => '',
        'CategoryFeatures_Order' => '0',
        'SubCategoryFeatures' => '',
        'SubCategoryFeatures_Order' => '0',
        'ArticleFeatures' => '',
        'StartDate' => '1970-01-01 00:00:00',
        'ExpDate' => '9999-12-31 23:59:59',
        'ShortDesc' => '',
        'LongDesc' => '',
        'Link' => '',
        'NewWindow' => '',
        'BigPict' => '',
        'SmallPict' => '',
        'SmallPict2' => '',
        'Language' => 'sp',
        'Active' => 'Y',
        'RenderableNow' => '1',
    ];
    return array_replace($base, $overrides);
}

function red_theme_readiness_test_layout($id, $positions)
{
    return [
        'UniqueName' => $id,
        'Positions' => (string) $positions,
        'w_Pos1' => '100',
        'vw_Pos1' => '',
        'vh_Pos1' => '100',
        'w_Pos2' => '100',
        'vw_Pos2' => '',
        'vh_Pos2' => '100',
        'w_Pos3' => '100',
        'vw_Pos3' => '',
        'vh_Pos3' => '100',
        'w_Pos4' => '100',
        'vw_Pos4' => '',
        'vh_Pos4' => '100',
    ];
}

function red_theme_readiness_test_route(array $report, $url)
{
    foreach ($report['routes'] as $route) {
        if ($route['url'] === $url) {
            return $route;
        }
    }
    throw new RuntimeException('Fixture route not found: ' . $url);
}

function red_theme_readiness_test_gap_ids(array $report)
{
    return array_map(static function (array $gap) { return $gap['id']; }, $report['gaps']);
}

$contactDefinition =
    '#|question=|name=name|type=textfield|required=true|displayname=Name|initialvalue=;' . "\r\n" .
    '#|question=|name=email|type=textfield|required=true|displayname=Email|initialvalue=;' . "\r\n" .
    '#|question=|name=Submit|type=button|displayname=submit';
$loginDefinition =
    '#|question=|name=username|type=textfield|required=true|displayname=Username|initialvalue=;' . "\r\n" .
    '#|question=|name=password|type=password|required=true|displayname=Password|initialvalue=;' . "\r\n" .
    '#|question=|name=Submit|type=button|displayname=submit';

$rows = [
    'active-areas' => [
        red_theme_readiness_test_area(25, 'administracion', 'administracion', 'index-3'),
        red_theme_readiness_test_area(24, 'contacto', 'contacto', 'index-1'),
        red_theme_readiness_test_area(13, 'home', 'Home', 'index-1', 'slider'),
    ],
    'active-articles' => [
        red_theme_readiness_test_article([
            'RecordID' => '880701099',
            'Title' => 'Como agregar contenido',
            'Component' => 'Gallery',
            'Alias' => 'admin-video',
            'Layout' => '',
            'SectionPosition' => '2',
            'SectionPositionOrder' => '2',
        ]),
        red_theme_readiness_test_article([
            'RecordID' => '89196971',
            'Title' => 'Instructions',
            'Alias' => 'instructions',
            'SectionPosition' => '2',
            'SectionPositionOrder' => '1',
            'LongDesc' => '<p><img src="../admin/images/red-cms-instructions-manual_files/v51-workspace.jpg"></p>',
        ]),
        red_theme_readiness_test_article([
            'RecordID' => '966111194',
            'Title' => 'Login',
            'Component' => 'Form',
            'Alias' => 'login',
            'SectionPosition' => '1',
            'SectionPositionOrder' => '1',
        ]),
        red_theme_readiness_test_article([
            'RecordID' => '459269660',
            'Title' => 'Contact',
            'Component' => 'Form',
            'Alias' => 'contact',
            'Sections' => 'contacto',
            'Layout' => 'index-1',
            'SectionPosition' => '1',
            'SectionPositionOrder' => '1',
        ]),
        red_theme_readiness_test_article([
            'RecordID' => '1154326271',
            'Title' => 'banner test',
            'Component' => 'Gallery',
            'Alias' => 'banner-test',
            'Sections' => 'home',
            'Layout' => '',
            'HomePosition' => '1',
            'HomePositionOrder' => '1',
            'SectionPosition' => '0',
            'SectionPositionOrder' => '0',
        ]),
    ],
    'active-navigation' => [
        [
            'RecordID' => '1', 'Parent' => '0', 'RootOrder' => '1', 'Title' => 'Top Navigation',
            'Label' => 'Inicio', 'Link' => '/', 'NewWindow' => '', 'MenuOrder' => '1',
            'Language' => 'sp', 'Active' => 'Y',
        ],
        [
            'RecordID' => '67', 'Parent' => '0', 'RootOrder' => '1', 'Title' => 'Top Navigation',
            'Label' => 'Contacto', 'Link' => '/contacto/', 'NewWindow' => '', 'MenuOrder' => '5',
            'Language' => 'sp', 'Active' => 'Y',
        ],
    ],
    'form-components' => [
        [
            'ArticleRecordID' => '459269660', 'ComponentRecordID' => '93039112',
            'RefID' => '459269660', 'Title' => 'Contact', 'Alias' => 'contact',
            'FormType' => 'Contact', 'Definition' => $contactDefinition, 'TableName' => '',
        ],
        [
            'ArticleRecordID' => '966111194', 'ComponentRecordID' => '884542279',
            'RefID' => '966111194', 'Title' => 'Login', 'Alias' => 'login',
            'FormType' => 'Login', 'Definition' => $loginDefinition, 'TableName' => '',
        ],
    ],
    'gallery-components' => [
        [
            'ArticleRecordID' => '880701099', 'ComponentRecordID' => '1968830051',
            'RefID' => '880701099', 'Title' => 'Como agregar contenido', 'Alias' => 'admin-video',
            'GalleryType' => 'Video', 'ShortDesc' => '', 'Link' => '',
            'LongDesc' => 'https://www.youtube.com/watch?v=pP8VJwjSnqA&feature=youtu.be', 'NewWindow' => '',
        ],
        [
            'ArticleRecordID' => '1154326271', 'ComponentRecordID' => '2030445666',
            'RefID' => '1154326271', 'Title' => 'banner test', 'Alias' => 'banner-test',
            'GalleryType' => 'Banner', 'ShortDesc' => '', 'Link' => '/administracion/',
            'LongDesc' => 'layout-02.png', 'NewWindow' => '',
        ],
    ],
    'layout-catalog' => [
        red_theme_readiness_test_layout('index', 3),
        red_theme_readiness_test_layout('index-1', 3),
        red_theme_readiness_test_layout('index-2', 4),
        red_theme_readiness_test_layout('index-3', 2),
    ],
    'custom-layout-catalog' => [],
    'region-settings' => [
        ['RecordID' => '8', 'Item' => 'System_Active_Theme', 'Content' => 'legacy-bootstrap', 'Language' => ''],
        ['RecordID' => '9', 'Item' => 'System_Previous_Theme', 'Content' => 'legacy-bootstrap', 'Language' => ''],
        ['RecordID' => '5', 'Item' => 'Website_Footer', 'Content' => '', 'Language' => 'sp'],
        ['RecordID' => '4', 'Item' => 'Website_Header', 'Content' => '', 'Language' => 'sp'],
        ['RecordID' => '3', 'Item' => 'Website_Logo', 'Content' => '', 'Language' => 'sp'],
        ['RecordID' => '1', 'Item' => 'Website_Title', 'Content' => '', 'Language' => 'sp'],
    ],
];

try {
    $queries = red_theme_readiness_query_inventory();
    red_theme_readiness_test_assert(
        array_keys($queries) === [
            'active-areas', 'active-articles', 'active-navigation', 'form-components',
            'gallery-components', 'layout-catalog', 'custom-layout-catalog', 'region-settings',
        ],
        'readiness owns exactly eight stable query ids'
    );
    red_theme_readiness_test_assert(
        red_theme_readiness_assert_query_inventory($queries),
        'all fixed readiness queries are single allowlisted SELECT statements'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['active-articles'] = "UPDATE RED_Articles SET Active='N'";
    try {
        red_theme_readiness_assert_query_inventory($tamperedQueries);
        red_theme_readiness_test_assert(false, 'write query must fail');
    } catch (InvalidArgumentException $exception) {
        red_theme_readiness_test_assert(
            strpos($exception->getMessage(), 'one fixed SELECT') !== false,
            'write query tampering fails closed'
        );
    }

    $first = red_theme_readiness_report_from_rows($rows, $repositoryRoot, 0);
    $second = red_theme_readiness_report_from_rows($rows, $repositoryRoot, 0);
    red_theme_readiness_test_assert($first === $second, 'fixture report is deterministic');
    red_theme_readiness_test_assert(
        $first['theme'] === [
            'id' => 'starter-reference', 'version' => '1.3.1', 'manifestValid' => true,
        ],
        'report identifies the validated activation-ready starter package'
    );
    red_theme_readiness_test_assert(
        $first['activationReady'],
        'complete production, state, control, runtime, and recovery contracts establish activation readiness: '
            . json_encode([
                'gaps' => $first['gaps'],
                'activation' => $first['activation'],
                'runtime' => $first['runtime'],
            ])
    );
    red_theme_readiness_test_assert(
        $first['manifestIdCoverage']['compatible']
            && $first['manifestIdCoverage']['missingLayouts'] === []
            && $first['manifestIdCoverage']['missingComponents'] === [],
        'fixture proves manifest layout/component id coverage separately'
    );
    red_theme_readiness_test_assert(
        $first['runtime']['resolvedThemeId'] === 'starter-reference'
            && !$first['runtime']['usedFallback']
            && $first['runtime']['standardRuntimeExecution']
            && $first['runtime']['legacyRecovery'],
        'guarded production runtime initializes the standard starter and retains legacy recovery'
    );
    red_theme_readiness_test_assert(
        $first['activation'] === [
            'state' => [
                'activeThemeId' => 'legacy-bootstrap',
                'previousThemeId' => 'legacy-bootstrap',
                'persisted' => true,
            ],
            'statePackagesValid' => true,
            'productionContractValid' => true,
            'productionFileCount' => 17,
            'capabilities' => [
                'persistedState' => true,
                'adminControls' => true,
                'publicSelection' => true,
                'legacyRecovery' => true,
            ],
        ],
        'readiness reports the exact persisted state and four activation boundary capabilities'
    );
    red_theme_readiness_test_assert(
        $first['canariesValid']
            && count($first['canaries']) === 5
            && array_column($first['canaries'], 'valid') === [true, true, true, true, true],
        'five fixed live-route canaries match the audited readiness contract'
    );
    red_theme_readiness_test_assert(
        $first['routeSummary'] === [
            'total' => 9,
            'exactPreviewCovered' => 6,
            'renderableWithoutPreview' => 0,
            'shellOnlyOrUnmatched' => 3,
            'menuExposed' => 2,
            'discoverable' => 4,
        ],
        'route coverage summary distinguishes exact, missing, and fallback routes'
    );

    $home = red_theme_readiness_test_route($first, '/');
    red_theme_readiness_test_assert(
        $home['layout'] === 'index-1'
            && $home['features'] === ['slider']
            && $home['previewCoverage'] === ['status' => 'exact', 'mode' => 'home']
            && $home['components'][0]['subtype'] === 'Banner',
        'Home route retains its exact preview and Banner canary'
    );
    $contact = red_theme_readiness_test_route($first, '/contacto/');
    red_theme_readiness_test_assert(
        $contact['menuExposed']
            && $contact['previewCoverage'] === ['status' => 'exact', 'mode' => 'contact']
            && $contact['components'][0]['subtype'] === 'Contact',
        'Contact route retains its exact menu and preview canary'
    );
    $administration = red_theme_readiness_test_route($first, '/administracion/');
    red_theme_readiness_test_assert(
        !$administration['menuExposed']
            && $administration['discoverable']
            && $administration['previewCoverage'] === ['status' => 'exact', 'mode' => 'administration']
            && array_column($administration['components'], 'subtype') === ['Login', 'Article', 'Video'],
        'Administration route records its exact preview, indirect discovery, and component order'
    );
    $instructions = red_theme_readiness_test_route($first, '/administracion/instructions');
    red_theme_readiness_test_assert(
        $instructions['layout'] === 'index-2'
            && $instructions['discoverable']
            && $instructions['previewCoverage'] === ['status' => 'exact', 'mode' => 'instructions'],
        'Instructions selected route is addressable and covered by its fixed provider'
    );
    $login = red_theme_readiness_test_route($first, '/administracion/login');
    red_theme_readiness_test_assert(
        $login['layout'] === 'index-2'
            && !$login['discoverable']
            && $login['previewCoverage'] === ['status' => 'exact', 'mode' => 'login']
            && $login['components'][0]['subtype'] === 'Login',
        'Login selected route is covered by its fixed display-only provider'
    );
    $selectedContact = red_theme_readiness_test_route($first, '/contacto/contact');
    red_theme_readiness_test_assert(
        $selectedContact['layout'] === 'index-1'
            && !$selectedContact['discoverable']
            && $selectedContact['previewCoverage'] === [
                'status' => 'exact',
                'mode' => 'selected-contact',
            ]
            && $selectedContact['components'][0]['subtype'] === 'Contact',
        'selected Contact route is covered by its fixed display-only provider'
    );
    $instructionsSource = null;
    foreach ($first['source']['articles'] as $article) {
        if ($article['alias'] === 'instructions') {
            $instructionsSource = $article;
            break;
        }
    }
    red_theme_readiness_test_assert(
        is_array($instructionsSource)
            && $instructionsSource['bodyMedia']['count'] === 1
            && $instructionsSource['bodyMedia']['existing'] === 1,
        'trusted Instructions media dependency is resolved and hashed without exposing source HTML'
    );
    $video = null;
    $banner = null;
    foreach ($first['source']['galleries'] as $gallery) {
        if ($gallery['type'] === 'Video') {
            $video = $gallery;
        } elseif ($gallery['type'] === 'Banner') {
            $banner = $gallery;
        }
    }
    red_theme_readiness_test_assert(
        $video['media']['fact']['provider'] === 'youtube'
            && $video['media']['fact']['id'] === 'pP8VJwjSnqA',
        'Video Gallery records its recognized external provider and id'
    );
    red_theme_readiness_test_assert(
        $banner['media']['fact']['exists']
            && $banner['media']['fact']['sha256'] === '24c407995a1f14053866595c4e4ecc88842bf804baa3cf6e87b9a3b9be056458',
        'Banner Gallery records the confined live image digest'
    );

    $formTypes = [];
    foreach ($first['source']['forms'] as $form) {
        $formTypes[$form['type']] = $form;
    }
    red_theme_readiness_test_assert(
        $formTypes['Contact']['operationalEndpoint'] === '/bin/contact.php'
            && count($formTypes['Contact']['fields']) === 2,
        'Contact fixture records fields and its operational endpoint without enabling it'
    );
    red_theme_readiness_test_assert(
        $formTypes['Login']['operationalEndpoint'] === '/bin/login.php'
            && array_column($formTypes['Login']['fields'], 'name') === ['username', 'password'],
        'Login fixture records password field and its separate endpoint contract'
    );
    red_theme_readiness_test_assert(
        !empty($first['source']['operationalAssets']['formSuccessIcon']['exists'])
            && !empty($first['source']['operationalAssets']['formErrorIcon']['exists']),
        'legacy Form response image dependencies exist and are hashed'
    );

    red_theme_readiness_test_assert(
        red_theme_readiness_test_route($first, '/administracion/admin-video')['fallback'] === 'empty-layout-shell'
            && red_theme_readiness_test_route($first, '/banner-test')['fallback'] === 'empty-layout-shell',
        'both active blank-layout aliases remain explicit shell-only routes'
    );
    red_theme_readiness_test_assert(
        red_theme_readiness_test_route($first, '/administracion/test-vimeo')['fallback']
            === 'unmatched-theme-404',
        'known unmatched test-vimeo canary remains an explicit active-theme 404'
    );
    red_theme_readiness_test_assert(
        $first['portableCapabilities']['routeFallback'] === [
            'coreContract' => true,
            'livePublicConnection' => true,
        ],
        'report detects the explicit blank-layout and active-theme 404 connection'
    );
    red_theme_readiness_test_assert(
        $first['portableCapabilities']['regionContext'] === [
            'coreProvider' => true,
            'allCurrentRoutes' => true,
            'inputFree' => true,
            'productionConnected' => false,
        ],
        'report detects the input-free all-route region provider without a production connection'
    );
    red_theme_readiness_test_assert(
        $first['portableCapabilities']['document'] === [
            'contactNoticeExact' => true,
            'homeNoticeExact' => true,
            'administrationNoticeExact' => true,
            'instructionsNoticeExact' => true,
            'loginNoticeExact' => true,
            'selectedContactNoticeExact' => true,
        ],
        'report detects exact mode-specific notices for all six fixed previews'
    );
    red_theme_readiness_test_assert(
        $first['portableCapabilities']['form']['displayFields']
            && !$first['portableCapabilities']['form']['submits']
            && !$first['portableCapabilities']['form']['endpointInput']
            && $first['portableCapabilities']['form']['selectedLoginProvider']
            && $first['portableCapabilities']['form']['selectedContactProvider']
            && $first['portableCapabilities']['form']['coreOperationBoundary']
            && $first['portableCapabilities']['form']['contactOperationAdapter']
            && $first['portableCapabilities']['form']['loginOperationAdapter'],
        'portable Form stays display-only while core exposes both providers, the boundary, and both live adapters'
    );
    red_theme_readiness_test_assert(
        $first['portableCapabilities']['gallery']['imageItems']
            && $first['portableCapabilities']['gallery']['videoContract']
            && !$first['portableCapabilities']['gallery']['videoEmbed']
            && !$first['portableCapabilities']['gallery']['bannerLink'],
        'portable Gallery separates image, offline Video, external embed, and Banner-link behavior'
    );
    red_theme_readiness_test_assert(
        $first['portableCapabilities']['article']['escapedSummary']
            && $first['portableCapabilities']['article']['trustedHtml']
            && $first['portableCapabilities']['article']['selectedInstructionsProvider'],
        'portable Article separates escaped summaries from its fixed trusted Instructions boundary'
    );

    $gapIds = red_theme_readiness_test_gap_ids($first);
    red_theme_readiness_test_assert(
        $gapIds === [],
        'all reviewed route, operation, media, region, notice, and fallback contracts have no remaining readiness gap'
    );
    red_theme_readiness_test_assert(
        !in_array('home-preview-notice-copy', $gapIds, true),
        'exact Home-mode copy removes only its nonblocking preview debt'
    );
    red_theme_readiness_test_assert(
        !in_array('generic-region-settings-provider', $gapIds, true),
        'proven generic region-context provider is no longer reported as missing'
    );
    red_theme_readiness_test_assert(
        !in_array('empty-layout-route-policy', $gapIds, true)
            && !in_array('unmatched-route-policy', $gapIds, true),
        'explicit live fallback contract removes the two route-policy gaps'
    );
    red_theme_readiness_test_assert(
        !in_array('operational-form-boundary', $gapIds, true),
        'dependency-tested CMS-owned operation boundary is no longer reported as missing'
    );
    red_theme_readiness_test_assert(
        !in_array('operational-contact-integration', $gapIds, true),
        'live Contact endpoint integration is no longer reported as missing'
    );
    red_theme_readiness_test_assert(
        !in_array('operational-login-integration', $gapIds, true),
        'live Login endpoint integration is no longer reported as missing'
    );
    red_theme_readiness_test_assert(
        !in_array('selected-form-provider', $gapIds, true),
        'both selected Form providers are proven and no longer reported as missing'
    );
    red_theme_readiness_test_assert(
        !in_array('administration-section-provider', $gapIds, true),
        'proven Administration provider is no longer reported as missing'
    );
    red_theme_readiness_test_assert(
        !in_array('selected-article-provider', $gapIds, true)
            && !in_array('trusted-article-html-policy', $gapIds, true),
        'proven selected Instructions provider and trusted-HTML policy are no longer gaps'
    );
    red_theme_readiness_test_assert(
        !in_array('gallery-video-contract', $gapIds, true),
        'proven Gallery Video contract is no longer reported as missing'
    );
    red_theme_readiness_test_assert(
        $first['source']['regionSettings']['Website_Logo']['policy'] === [
            'id' => 'core-managed-raster-override',
            'managedRoot' => '/images',
            'publicRendering' => false,
            'runtimeConnected' => true,
            'templateFallback' => true,
        ]
            && $first['source']['regionSettings']['Website_Logo']['configured'] === false
            && !isset($first['source']['regionSettings']['Website_Logo']['media']),
        'an empty logo setting keeps the connected raster override contract on template fallback'
    );
    red_theme_readiness_test_assert(
        count($first['recommendedSequence']) === 1
            && strpos($first['recommendedSequence'][0], 'template-fallback logo policy') !== false
            && strpos($first['recommendedSequence'][0], 'Themes controls') !== false
            && strpos($first['recommendedSequence'][0], 'legacy recovery') !== false,
        'recommended sequence uses the theme controls while preserving the connected logo and recovery policies'
    );
    red_theme_readiness_test_assert(
        $first['scope']['databaseReads'] === 0
            && $first['scope']['databaseWrites'] === 0
            && $first['scope']['filesystemWrites'] === 0
            && $first['scope']['sessionReads'] === 0
            && $first['scope']['sessionWrites'] === 0
            && $first['scope']['liveRuntimeChanges'] === 0,
        'fixture report records exact zero-write/session/runtime scope'
    );
    $encoded = json_encode($first, JSON_UNESCAPED_SLASHES);
    red_theme_readiness_test_assert(
        is_string($encoded)
            && strpos($encoded, '#|question=') === false
            && strpos($encoded, $repositoryRoot) === false,
        'report exposes neither raw Form definitions nor absolute repository paths'
    );

    $missingStateRows = $rows;
    $missingStateRows['region-settings'] = array_values(array_filter(
        $missingStateRows['region-settings'],
        static function (array $row) {
            return (string) $row['Language'] !== '';
        }
    ));
    $missingStateReport = red_theme_readiness_report_from_rows(
        $missingStateRows,
        $repositoryRoot,
        0
    );
    red_theme_readiness_test_assert(
        !$missingStateReport['activationReady']
            && !$missingStateReport['activation']['state']['persisted']
            && in_array('active-theme-state', red_theme_readiness_test_gap_ids($missingStateReport), true),
        'a pre-migration database remains safely blocked even when package and runtime contracts are complete'
    );

    $badRows = $rows;
    $badRows['active-areas'][0]['Unexpected'] = 'value';
    try {
        red_theme_readiness_report_from_rows($badRows, $repositoryRoot, 0);
        red_theme_readiness_test_assert(false, 'extra selected column must fail');
    } catch (InvalidArgumentException $exception) {
        red_theme_readiness_test_assert(
            strpos($exception->getMessage(), 'fixed selected columns') !== false,
            'schema drift fails closed'
        );
    }
    $badGroups = $rows;
    $badGroups['extra-query'] = [];
    try {
        red_theme_readiness_report_from_rows($badGroups, $repositoryRoot, 0);
        red_theme_readiness_test_assert(false, 'extra query group must fail');
    } catch (InvalidArgumentException $exception) {
        red_theme_readiness_test_assert(
            strpos($exception->getMessage(), 'eight fixed query ids') !== false,
            'query-group drift fails closed'
        );
    }
    $badRelationship = $rows;
    $badRelationship['form-components'][0]['RefID'] = '966111194';
    try {
        red_theme_readiness_report_from_rows($badRelationship, $repositoryRoot, 0);
        red_theme_readiness_test_assert(false, 'invalid child relationship must fail');
    } catch (InvalidArgumentException $exception) {
        red_theme_readiness_test_assert(
            strpos($exception->getMessage(), 'relationship') !== false,
            'invalid child relationship fails closed'
        );
    }

    echo 'Theme activation-readiness self-test passed (' . $assertions . " assertions).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Theme activation-readiness self-test failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);
