<?php
/**
 * Dependency-free tests for the generic region-context boundary.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_region_context_helpers.php';

$assertions = 0;

function red_theme_region_context_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_region_context_test_throws(callable $callback, $needle, $message)
{
    try {
        $callback();
    } catch (Throwable $exception) {
        red_theme_region_context_test_assert(
            strpos($exception->getMessage(), $needle) !== false,
            $message
        );
        return;
    }

    red_theme_region_context_test_assert(false, $message);
}

function red_theme_region_context_test_rows()
{
    return [
        'region-settings' => [
            ['RecordID' => '5', 'Item' => 'Website_Footer', 'Content' => '', 'Language' => 'sp'],
            ['RecordID' => '4', 'Item' => 'Website_Header', 'Content' => '', 'Language' => 'sp'],
            ['RecordID' => '3', 'Item' => 'Website_Logo', 'Content' => '', 'Language' => 'sp'],
            ['RecordID' => '1', 'Item' => 'Website_Title', 'Content' => '', 'Language' => 'sp'],
        ],
        'navigation' => [
            [
                'RecordID' => '1', 'Parent' => '0', 'RootOrder' => '1',
                'Title' => 'Top Navigation', 'Label' => 'Inicio', 'Link' => '/',
                'NewWindow' => '', 'MenuOrder' => '1', 'Language' => 'sp', 'Active' => 'Y',
            ],
            [
                'RecordID' => '67', 'Parent' => '0', 'RootOrder' => '1',
                'Title' => 'Top Navigation', 'Label' => 'Contacto', 'Link' => '/contacto/',
                'NewWindow' => '', 'MenuOrder' => '5', 'Language' => 'sp', 'Active' => 'Y',
            ],
        ],
        'hero-areas' => [
            [
                'AreaType' => 'section', 'RecordID' => '25', 'Slug' => 'administracion',
                'Title' => 'administracion', 'Features' => '', 'Language' => 'sp', 'Active' => 'Y',
            ],
            [
                'AreaType' => 'section', 'RecordID' => '24', 'Slug' => 'contacto',
                'Title' => 'contacto', 'Features' => '', 'Language' => 'sp', 'Active' => 'Y',
            ],
            [
                'AreaType' => 'section', 'RecordID' => '13', 'Slug' => 'home',
                'Title' => 'Home', 'Features' => 'slider', 'Language' => 'sp', 'Active' => 'Y',
            ],
        ],
        'hero-articles' => [],
    ];
}

function red_theme_region_context_test_article(array $overrides = [])
{
    return array_replace([
        'RecordID' => '100',
        'Title' => 'Hero Article',
        'Alias' => 'hero-article',
        'Sections' => 'Home',
        'Categories' => '',
        'SubCategories' => '',
        'LongDesc' => '<p>Body marker</p>',
        'SliderDesc' => 'A safe slider summary.',
        'Link' => '',
        'NewWindow' => '',
        'BigPict' => 'hero.png',
        'ExpDate' => '9999-12-31 23:59:59',
        'HomeFeatures' => 'slider',
        'HomeFeatures_Order' => '2',
        'SectionFeatures' => '',
        'SectionFeatures_Order' => '0',
        'CategoryFeatures' => '',
        'CategoryFeatures_Order' => '0',
        'SubCategoryFeatures' => '',
        'SubCategoryFeatures_Order' => '0',
        'Language' => 'sp',
        'Active' => 'Y',
    ], $overrides);
}

final class RedThemeRegionContextTestResult
{
    private $rows;
    private $index = 0;
    public $freed = false;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetch_assoc()
    {
        if (!array_key_exists($this->index, $this->rows)) {
            return null;
        }

        return $this->rows[$this->index++];
    }

    public function free()
    {
        $this->freed = true;
    }
}

final class RedThemeRegionContextTestConnection
{
    public $events = [];
    public $commits = 0;
    public $rollbacks = 0;
    private $rows;
    private $queries;
    private $failId;

    public function __construct(array $rows, $failId = null)
    {
        $this->rows = $rows;
        $this->queries = red_theme_region_context_query_inventory();
        $this->failId = $failId;
    }

    public function query($sql)
    {
        if ($sql === 'START TRANSACTION READ ONLY') {
            $this->events[] = 'start-read-only';
            return true;
        }
        $id = array_search($sql, $this->queries, true);
        if ($id === false) {
            $this->events[] = 'unexpected-query';
            return false;
        }
        $this->events[] = 'read:' . $id;
        if ($id === $this->failId) {
            return false;
        }

        return new RedThemeRegionContextTestResult($this->rows[$id]);
    }

    public function commit()
    {
        $this->commits++;
        $this->events[] = 'commit';
        return true;
    }

    public function rollback()
    {
        $this->rollbacks++;
        $this->events[] = 'rollback';
        return true;
    }
}

try {
    $queries = red_theme_region_context_query_inventory();
    red_theme_region_context_test_assert(
        array_keys($queries) === ['region-settings', 'navigation', 'hero-areas', 'hero-articles'],
        'provider owns exactly four fixed query ids'
    );
    red_theme_region_context_test_assert(
        red_theme_region_context_assert_query_inventory($queries),
        'all provider queries are single allowlisted SELECT statements'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['hero-articles'] = "UPDATE RED_Articles SET Active='N'";
    red_theme_region_context_test_throws(
        static function () use ($tamperedQueries) {
            red_theme_region_context_assert_query_inventory($tamperedQueries);
        },
        'one fixed SELECT',
        'write-query tampering fails closed'
    );
    $extraQueries = $queries;
    $extraQueries['caller-query'] = 'SELECT RecordID FROM RED_Articles';
    red_theme_region_context_test_throws(
        static function () use ($extraQueries) {
            red_theme_region_context_assert_query_inventory($extraQueries);
        },
        'four fixed query ids',
        'extra query input fails closed'
    );

    $rows = red_theme_region_context_test_rows();
    $first = red_theme_region_context_report_from_rows($rows, 0);
    $second = red_theme_region_context_report_from_rows($rows, 0);
    red_theme_region_context_test_assert($first === $second, 'prepared provider report is deterministic');
    red_theme_region_context_test_assert(
        $first['schemaVersion'] === 1 && $first['mode'] === 'read-only-generic-region-context',
        'report exposes the fixed schema and read-only mode'
    );
    red_theme_region_context_test_assert(
        $first['contract']['acceptedCallerInputs'] === []
            && $first['contract']['routeCount'] === 9
            && $first['contract']['routeScope'] === 'all-current-public-routes',
        'one input-free contract covers all nine current routes'
    );
    red_theme_region_context_test_assert(
        !$first['contract']['themeRendering'] && !$first['contract']['productionConnection'],
        'provider neither renders nor connects a production theme'
    );
    red_theme_region_context_test_assert(
        array_keys($first['document']['settings']) === [
            'site.title', 'branding.logo', 'header.custom-html', 'footer.custom-html',
        ],
        'document exposes only the four manifest-mapped settings'
    );
    red_theme_region_context_test_assert(
        $first['document']['settingCanaryValid'],
        'current setting RecordIDs, sizes, and hashes match the exact live canary'
    );
    red_theme_region_context_test_assert(
        $first['document']['settings']['branding.logo']['value'] === ''
            && !$first['document']['settings']['branding.logo']['configured']
            && $first['document']['settings']['branding.logo']['resolution'] === 'deferred',
        'an empty logo setting remains deliberately unresolved for template fallback'
    );
    red_theme_region_context_test_assert(
        $first['document']['settings']['header.custom-html']['valueKind'] === 'trusted-html'
            && $first['document']['settings']['header.custom-html']['execution'] === 'data-only'
            && $first['document']['settings']['footer.custom-html']['valueKind'] === 'trusted-html',
        'legacy custom regions are classified as non-executable data'
    );
    red_theme_region_context_test_assert(
        array_column($first['navigation']['items'], 'recordId') === [1, 67]
            && array_column($first['navigation']['items'], 'label') === ['Inicio', 'Contacto'],
        'navigation preserves the exact ordered roots'
    );
    red_theme_region_context_test_assert(
        $first['navigation']['currentRecordIdByRoute']['/'] === 1
            && $first['navigation']['currentRecordIdByRoute']['/contacto/'] === 67
            && $first['navigation']['currentRecordIdByRoute']['/contacto/contact'] === 67
            && $first['navigation']['currentRecordIdByRoute']['/administracion/'] === null,
        'fixed route map derives current navigation without caller route input'
    );
    red_theme_region_context_test_assert(
        array_keys($first['hero']['contexts']) === [
            'section:administracion', 'section:contacto', 'section:home', 'non-area',
        ],
        'hero data is grouped by every current area plus one non-area fallback'
    );
    red_theme_region_context_test_assert(
        $first['hero']['contexts']['section:home']['enabled']
            && $first['hero']['contexts']['section:home']['candidates'] === []
            && !$first['hero']['contexts']['section:contacto']['enabled']
            && !$first['hero']['contexts']['non-area']['enabled'],
        'current Home slider enablement and zero-candidate state remain exact'
    );
    red_theme_region_context_test_assert(
        $first['hero']['expirationEvaluation'] === 'deferred-to-core-clock',
        'time-sensitive hero expiration remains a later core decision rather than caller input'
    );
    red_theme_region_context_test_assert(
        array_column($first['routes'], 'url') === array_keys(red_theme_region_context_route_inventory())
            && array_column($first['routes'], 'heroContextKey') === array_values(
                red_theme_region_context_route_inventory()
            ),
        'route coverage retains all six renderable and three fallback paths'
    );
    red_theme_region_context_test_assert(
        $first['source']['rowCounts'] === [
            'region-settings' => 4, 'navigation' => 2, 'hero-areas' => 3, 'hero-articles' => 0,
        ],
        'source report records exact current fixture row counts'
    );
    red_theme_region_context_test_assert(
        $first['source']['settingItems'] === [
            'Website_Footer', 'Website_Header', 'Website_Logo', 'Website_Title',
        ]
            && $first['source']['navigationRecordIds'] === [1, 67]
            && $first['source']['heroArticleRecordIds'] === [],
        'source inventory records only bounded ids and setting names'
    );
    red_theme_region_context_test_assert(
        $first['scope'] === red_theme_region_context_scope(0)
            && array_sum($first['scope']) === 0,
        'fixture preparation has zero external side effects'
    );
    $encoded = json_encode($first, JSON_UNESCAPED_SLASHES);
    red_theme_region_context_test_assert(
        is_string($encoded)
            && strpos($encoded, 'SELECT ') === false
            && strpos($encoded, $repositoryRoot) === false,
        'report exposes neither SQL text nor an absolute repository path'
    );

    $heroRows = $rows;
    $heroRows['hero-articles'] = [red_theme_region_context_test_article()];
    $heroReport = red_theme_region_context_report_from_rows($heroRows, 0);
    $homeCandidates = $heroReport['hero']['contexts']['section:home']['candidates'];
    red_theme_region_context_test_assert(
        count($homeCandidates) === 1
            && $homeCandidates[0]['recordId'] === 100
            && $homeCandidates[0]['url'] === '/hero-article'
            && $homeCandidates[0]['image'] === 'hero.png'
            && $homeCandidates[0]['featureOrder'] === 2,
        'generic hero input reconstructs the existing core link/media/order shape'
    );
    red_theme_region_context_test_assert(
        $heroReport['hero']['candidateGroups']['section'] === []
            && $heroReport['hero']['candidateGroups']['category'] === []
            && $heroReport['hero']['candidateGroups']['subcategory'] === [],
        'feature candidates remain isolated by legacy area scope'
    );
    $externalRows = $rows;
    $externalRows['hero-articles'] = [red_theme_region_context_test_article([
        'Link' => 'https://example.com/path', 'NewWindow' => 'Y',
    ])];
    $externalReport = red_theme_region_context_report_from_rows($externalRows, 0);
    red_theme_region_context_test_assert(
        $externalReport['hero']['candidateGroups']['home'][0]['url'] === 'https://example.com/path'
            && $externalReport['hero']['candidateGroups']['home'][0]['target'] === '_blank',
        'stored HTTPS link and new-window semantics remain explicit data'
    );

    $nestedRows = $rows;
    $nestedRows['navigation'][] = [
        'RecordID' => '68', 'Parent' => '67', 'RootOrder' => '2',
        'Title' => 'Top Navigation', 'Label' => 'Contact child', 'Link' => '/contacto/contact',
        'NewWindow' => '', 'MenuOrder' => '6', 'Language' => 'sp', 'Active' => 'Y',
    ];
    $nestedReport = red_theme_region_context_report_from_rows($nestedRows, 0);
    red_theme_region_context_test_assert(
        $nestedReport['navigation']['items'][1]['children'][0]['recordId'] === 68,
        'bounded navigation hierarchy is prepared without a renderer callback'
    );

    $badRows = $rows;
    $badRows['region-settings'][0]['Unexpected'] = 'value';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'exact selected columns',
        'extra selected setting column fails closed'
    );
    $badRows = $rows;
    array_pop($badRows['region-settings']);
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'exactly four',
        'missing setting row fails closed'
    );
    $badRows = $rows;
    [$badRows['region-settings'][0], $badRows['region-settings'][1]] = [
        $badRows['region-settings'][1], $badRows['region-settings'][0],
    ];
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'reordered',
        'reordered setting rows fail closed'
    );
    $badRows = $rows;
    $badRows['region-settings'][2]['Content'] = '../logo.png';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'bounded filename',
        'caller-controlled logo path fails closed without resolving media'
    );
    $badRows = $rows;
    $badRows['region-settings'][1]['Content'] = '<?php echo 1;';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'non-executable',
        'executable custom-region content fails closed'
    );
    $badRows = $rows;
    $badRows['navigation'][1]['Link'] = 'javascript:alert(1)';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'internal path or HTTPS',
        'unsafe navigation scheme fails closed'
    );
    $badRows = $rows;
    $badRows['navigation'][1]['Parent'] = '999';
    $badRows['navigation'][1]['RootOrder'] = '2';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'orphaned',
        'orphaned navigation fails closed'
    );
    $badRows = $nestedRows;
    $badRows['navigation'][1]['Parent'] = '68';
    $badRows['navigation'][1]['RootOrder'] = '2';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'unreachable cycle',
        'unreachable navigation cycle fails closed'
    );
    $badRows = $rows;
    $badRows['navigation'][1]['MenuOrder'] = '0';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'reordered',
        'navigation order tampering fails closed'
    );
    $badRows = $rows;
    $badRows['hero-areas'][0]['Slug'] = '../administracion';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'slug is unsafe',
        'unsafe area slug fails closed'
    );
    $badRows = $rows;
    $badRows['hero-areas'][1]['Slug'] = 'administracion';
    $badRows['hero-areas'][1]['RecordID'] = '26';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'keys must be unique',
        'duplicate hero area fails closed'
    );
    $badRows = $rows;
    array_pop($badRows['hero-areas']);
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'missing a fixed current-route hero area',
        'missing current route area fails closed'
    );
    $badRows = $heroRows;
    $badRows['hero-articles'][0]['Unexpected'] = 'value';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'exact selected columns',
        'extra hero Article column fails closed'
    );
    $badRows = $heroRows;
    $badRows['hero-articles'][0]['HomeFeatures'] = 'flex';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'without the exact slider feature',
        'query/content feature mismatch fails closed'
    );
    $badRows = $heroRows;
    $badRows['hero-articles'][0]['BigPict'] = '../hero.png';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'confined relative media path',
        'unsafe hero media path fails closed'
    );
    $badRows = $heroRows;
    $badRows['hero-articles'][0]['ExpDate'] = 'tomorrow';
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'expiration is malformed',
        'malformed hero expiration fails closed'
    );
    $badRows = $rows;
    $badRows['caller-route'] = [];
    red_theme_region_context_test_throws(
        static function () use ($badRows) {
            red_theme_region_context_report_from_rows($badRows, 0);
        },
        'four fixed query ids',
        'caller route row group fails closed'
    );
    red_theme_region_context_test_throws(
        static function () {
            red_theme_region_context_scope(3);
        },
        'zero or four',
        'unexpected database-read scope fails closed'
    );

    $connection = new RedThemeRegionContextTestConnection($rows);
    $read = red_theme_region_context_read_rows($connection);
    red_theme_region_context_test_assert(
        $connection->events === [
            'start-read-only', 'read:region-settings', 'read:navigation',
            'read:hero-areas', 'read:hero-articles', 'commit',
        ]
            && $connection->commits === 1
            && $connection->rollbacks === 0,
        'live reader opens one read-only transaction and performs only four ordered reads'
    );
    red_theme_region_context_test_assert(
        $read['rows'] === $rows && $read['scope'] === red_theme_region_context_scope(4),
        'live reader returns exact rows with four-read zero-write scope'
    );
    $liveConnection = new RedThemeRegionContextTestConnection($rows);
    $live = red_theme_region_context_live_report($liveConnection);
    red_theme_region_context_test_assert(
        $live['scope']['databaseReads'] === 4
            && $live['source']['rowCounts']['region-settings'] === 4
            && $live['contract']['routeCount'] === 9,
        'live report preserves the prepared contract and exact scope'
    );
    $failingConnection = new RedThemeRegionContextTestConnection($rows, 'hero-areas');
    red_theme_region_context_test_throws(
        static function () use ($failingConnection) {
            red_theme_region_context_read_rows($failingConnection);
        },
        'hero-areas',
        'failed fixed read reports its stable query id'
    );
    red_theme_region_context_test_assert(
        $failingConnection->commits === 0 && $failingConnection->rollbacks === 1,
        'failed read rolls back the read-only transaction'
    );
    $overflowRows = $rows;
    $overflowRows['region-settings'] = array_fill(0, 6, $rows['region-settings'][0]);
    $overflowConnection = new RedThemeRegionContextTestConnection($overflowRows);
    red_theme_region_context_test_throws(
        static function () use ($overflowConnection) {
            red_theme_region_context_read_rows($overflowConnection);
        },
        'exceeded its row boundary',
        'database row overflow fails before report preparation'
    );
    red_theme_region_context_test_assert(
        $overflowConnection->rollbacks === 1,
        'row overflow rolls back the read-only transaction'
    );
    red_theme_region_context_test_throws(
        static function () {
            red_theme_region_context_read_rows(new stdClass());
        },
        'valid mysqli connection',
        'invalid database dependency fails closed'
    );

    $source = file_get_contents($repositoryRoot . '/includes/theme_region_context_helpers.php');
    red_theme_region_context_test_assert(
        is_string($source)
            && strpos($source, '$_GET') === false
            && strpos($source, '$_POST') === false
            && strpos($source, '$_REQUEST') === false
            && strpos($source, '$_SESSION') === false
            && strpos($source, 'theme_runtime.php') === false
            && strpos($source, 'themes/starter-reference') === false,
        'provider source has no request/session/theme-runtime/package dependency'
    );
    red_theme_region_context_test_assert(
        strpos($source, 'START TRANSACTION READ ONLY') !== false
            && strpos($source, "'acceptedCallerInputs' => []") !== false
            && strpos($source, "'productionConnection' => false") !== false,
        'provider source anchors the read-only input-free non-production boundary'
    );

    echo 'Generic region-context self-test passed (' . $assertions . " assertions).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Generic region-context self-test failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);
