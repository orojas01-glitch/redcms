<?php
/**
 * Dependency-free contract and tamper tests for the fixed Home preview input.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_preview_home_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_home_preview_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_home_preview_test_expect(callable $callback, $fragment, $message)
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    red_theme_home_preview_test_assert(
        $caught instanceof Throwable
            && ($fragment === '' || strpos($caught->getMessage(), $fragment) !== false),
        $message
    );
}

function red_theme_home_preview_test_rows()
{
    return [
        'section' => [[
            'RecordID' => '13',
            'Sections' => 'home',
            'Title' => 'Home',
            'Layout' => 'index-1',
            'QueryLimit' => '100',
            'Description' => '',
            'Tags' => '',
            'Features' => 'slider',
            'Language' => 'sp',
            'Active' => 'Y',
        ]],
        'navigation' => [
            [
                'RecordID' => '1',
                'RootOrder' => '1',
                'Label' => 'Inicio',
                'Link' => '/',
                'NewWindow' => '',
                'MenuOrder' => '1',
                'Active' => 'Y',
                'Language' => 'sp',
            ],
            [
                'RecordID' => '67',
                'RootOrder' => '1',
                'Label' => 'Contacto',
                'Link' => '/contacto/',
                'NewWindow' => '',
                'MenuOrder' => '5',
                'Active' => 'Y',
                'Language' => 'sp',
            ],
        ],
        'hero' => [],
        'gallery' => [[
            'ArticleRecordID' => '1154326271',
            'ArticleAlias' => 'banner-test',
            'ArticleTitle' => 'banner test',
            'Component' => 'Gallery',
            'Sections' => 'home',
            'HomePosition' => '1',
            'HomePositionOrder' => '1',
            'HomeFeature' => '',
            'HomeFeatures' => '',
            'StartDate' => '1970-01-01 00:00:00',
            'ExpDate' => '9999-12-31 23:59:59',
            'Language' => 'sp',
            'Active' => 'Y',
            'GalleryRecordID' => '2030445666',
            'RefID' => '1154326271',
            'GalleryAlias' => 'banner-test',
            'GalleryTitle' => 'banner test',
            'GalleryType' => 'Banner',
            'ShortDesc' => '',
            'LongDesc' => 'layout-02.png',
            'Link' => '/administracion/',
            'NewWindow' => '',
        ]],
        'settings' => [
            [
                'RecordID' => '5',
                'Item' => 'Website_Footer',
                'Content' => '',
                'Language' => 'sp',
            ],
            [
                'RecordID' => '1',
                'Item' => 'Website_Title',
                'Content' => '',
                'Language' => 'sp',
            ],
        ],
    ];
}

final class RedThemeHomePreviewTestResult
{
    private $rows;
    private $index = 0;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function fetch_assoc()
    {
        if (!isset($this->rows[$this->index])) {
            return null;
        }

        return $this->rows[$this->index++];
    }

    public function free()
    {
    }
}

final class RedThemeHomePreviewTestConnection
{
    public $queries = [];
    public $committed = false;
    public $rolledBack = false;
    private $resultRows;

    public function __construct(array $resultRows)
    {
        $this->resultRows = array_values($resultRows);
    }

    public function query($sql)
    {
        $this->queries[] = $sql;
        if ($sql === 'START TRANSACTION READ ONLY') {
            return true;
        }
        if ($this->resultRows === []) {
            return false;
        }

        return new RedThemeHomePreviewTestResult(array_shift($this->resultRows));
    }

    public function commit()
    {
        $this->committed = true;
        return true;
    }

    public function rollback()
    {
        $this->rolledBack = true;
        return true;
    }
}

try {
    $rows = red_theme_home_preview_test_rows();
    $queries = red_theme_home_preview_query_inventory();
    red_theme_home_preview_test_assert(
        count($queries) === 5
            && array_keys($queries) === [
                'home-section',
                'home-navigation',
                'home-hero',
                'home-gallery',
                'home-settings',
            ]
            && red_theme_home_preview_assert_query_inventory($queries),
        'Home provider owns exactly five safe fixed SELECT reads'
    );
    foreach ($queries as $sql) {
        red_theme_home_preview_test_assert(
            preg_match('/\ASELECT\s/i', ltrim($sql)) === 1
                && strpos($sql, ';') === false,
            'each Home query remains one semicolon-free SELECT'
        );
    }
    $badQueries = $queries;
    $badQueries['home-gallery'] = 'UPDATE RED_Articles SET Active=\'N\'';
    red_theme_home_preview_test_expect(
        function () use ($badQueries) {
            red_theme_home_preview_assert_query_inventory($badQueries);
        },
        'single fixed SELECT',
        'a write-shaped query replacement is rejected'
    );
    $extraQueries = $queries;
    $extraQueries['home-other'] = 'SELECT RecordID FROM RED_C_Other';
    red_theme_home_preview_test_expect(
        function () use ($extraQueries) {
            red_theme_home_preview_assert_query_inventory($extraQueries);
        },
        'exactly five fixed reads',
        'an extra query is rejected before database access'
    );

    $fake = new RedThemeHomePreviewTestConnection([
        $rows['section'],
        $rows['navigation'],
        $rows['hero'],
        $rows['gallery'],
        $rows['settings'],
    ]);
    $read = red_theme_home_preview_read_rows($fake);
    red_theme_home_preview_test_assert(
        $read['rows'] === $rows
            && $read['scope'] === red_theme_home_preview_scope(5),
        'read-only provider returns only the five exact row groups and five-read scope'
    );
    red_theme_home_preview_test_assert(
        $fake->queries === array_merge(['START TRANSACTION READ ONLY'], array_values($queries))
            && $fake->committed
            && !$fake->rolledBack,
        'provider opens one read-only transaction, executes fixed queries in order, and commits'
    );

    $prepared = red_theme_home_preview_prepare_rows($rows);
    red_theme_home_preview_test_assert(
        $prepared['fixture']['theme'] === 'starter-reference'
            && $prepared['fixture']['document']['language'] === 'es'
            && $prepared['fixture']['document']['title'] === 'Home — banner test',
        'Home document data is reconstructed from the fixed live section and Gallery titles'
    );
    red_theme_home_preview_test_assert(
        $prepared['fixture']['regions']['navigation']['items'] === [
            ['label' => 'Inicio', 'url' => '/', 'current' => true],
            ['label' => 'Contacto', 'url' => '/contacto/', 'current' => false],
        ],
        'Home navigation contains only the exact two root menu items with Home current'
    );
    red_theme_home_preview_test_assert(
        $prepared['fixture']['regions']['hero'] === [
            'title' => 'Home',
            'summary' => 'banner test',
            'action' => ['label' => 'View banner test', 'url' => '#preview-gallery'],
        ]
            && $prepared['source']['hero'] === [
                'enabled' => true,
                'rowCount' => 0,
                'source' => 'section/gallery fallback',
            ],
        'zero live slider rows produce the explicit section/Gallery hero fallback'
    );
    red_theme_home_preview_test_assert(
        $prepared['fixture']['page']['layout'] === 'index-1'
            && array_keys($prepared['fixture']['page']['slots']) === [1, 2, 3, 4]
            && $prepared['fixture']['page']['slots']['1'][0]['component'] === 'Gallery'
            && $prepared['fixture']['page']['slots']['1'][0]['data']['items'][0]['image'] === 'layout-02.png'
            && $prepared['fixture']['page']['slots']['2'] === []
            && $prepared['fixture']['page']['slots']['3'] === []
            && $prepared['fixture']['page']['slots']['4'] === [],
        'Home maps only its fixed position-1 Gallery into the exact index-1 portable slots'
    );
    red_theme_home_preview_test_assert(
        $prepared['source']['canary']['sectionRecordId'] === 13
            && $prepared['source']['component']['articleRecordId'] === 1154326271
            && $prepared['source']['gallery']['recordId'] === 2030445666
            && $prepared['source']['rowCounts'] === [
                'section' => 1,
                'navigation' => 2,
                'hero' => 0,
                'gallery' => 1,
                'settings' => 2,
            ],
        'source report exposes only fixed provenance ids and row counts'
    );

    $first = red_theme_home_preview_render_rows($rows, $repositoryRoot, 0);
    $second = red_theme_home_preview_render_rows($rows, $repositoryRoot, 0);
    red_theme_home_preview_test_assert(
        $first['html'] === $second['html']
            && $first['sha256'] === $second['sha256']
            && $first['bytes'] === strlen($first['html'])
            && $first['scope'] === red_theme_home_preview_scope(0),
        'fixed Home rows and media render deterministically without database reads in fixture mode'
    );
    red_theme_home_preview_test_assert(
        substr_count(
            $first['html'],
            'Read-only Home data preview — five fixed database reads; no session, activation, or live website change.'
        ) === 1
            && strpos($first['html'], 'Isolated fixture preview') === false
            && strpos($first['html'], 'Read-only Contact data preview') === false,
        'Home output exposes exactly its own five-read notice and never falls through to other preview copy'
    );
    red_theme_home_preview_test_assert(
        $first['bytes'] === 558241
            && $first['sha256'] === 'f790b6eedf0e93c2a726ddbcea6a11d3109e1fead7ba479badd4c2c3bd56d223',
        'Home output matches the reviewed exact notice-copy checkpoint'
    );
    red_theme_home_preview_test_assert(
        strpos($first['html'], 'data:image/png;base64,') !== false
            && strpos($first['html'], '/images/gallery/') === false
            && strpos($first['html'], '<form') === false
            && strpos($first['html'], '<script') === false,
        'Home media is embedded from the confined root with no live path, form, or script'
    );
    red_theme_home_preview_test_assert(
        $first['source']['gallery']['mediaBytes'] === 410147
            && $first['source']['gallery']['mediaSha256'] === '24c407995a1f14053866595c4e4ecc88842bf804baa3cf6e87b9a3b9be056458'
            && strpos(json_encode($first['source']), $repositoryRoot) === false,
        'current Home media is fingerprinted without exposing an absolute filesystem path'
    );
    red_theme_home_preview_test_expect(
        function () use ($rows, $repositoryRoot) {
            red_theme_home_preview_render_rows($rows, $repositoryRoot, 4);
        },
        'zero or five',
        'Home rendering rejects a fabricated database-read count'
    );

    $validation = red_theme_preview_validate_reference_theme('starter-reference', $repositoryRoot);
    $contract = red_theme_preview_contract($prepared['fixture'], $validation);
    red_theme_home_preview_test_expect(
        function () use ($validation, $contract, $repositoryRoot) {
            red_theme_preview_render_prepared_contract(
                $validation,
                $contract,
                'fixture-preview',
                red_theme_preview_scope(),
                $repositoryRoot . '/images/gallery'
            );
        },
        'Only Home preview',
        'fixture and Contact modes cannot opt into the external Gallery media boundary'
    );
    red_theme_home_preview_test_expect(
        function () use ($validation, $contract) {
            red_theme_preview_render_prepared_contract(
                $validation,
                $contract,
                'read-only-home-preview',
                red_theme_home_preview_scope(0),
                null
            );
        },
        'fixed local Gallery media root',
        'Home mode fails closed without its explicit media root'
    );

    $tamperCases = [
        ['section record', function ($value) { $value['section'][0]['RecordID'] = '14'; return $value; }, 'route/layout canary'],
        ['section layout', function ($value) { $value['section'][0]['Layout'] = 'index'; return $value; }, 'route/layout canary'],
        ['section features', function ($value) { $value['section'][0]['Features'] = ''; return $value; }, 'route/layout canary'],
        ['navigation record', function ($value) { $value['navigation'][1]['RecordID'] = '68'; return $value; }, 'unexpected root'],
        ['navigation URL', function ($value) { $value['navigation'][1]['Link'] = 'https://example.com'; return $value; }, 'local absolute-path'],
        ['navigation order', function ($value) { $value['navigation'][1]['MenuOrder'] = '4'; return $value; }, 'root-menu canary'],
        ['gallery article', function ($value) { $value['gallery'][0]['ArticleRecordID'] = '1'; return $value; }, 'relationship canary'],
        ['gallery RefID', function ($value) { $value['gallery'][0]['RefID'] = '1'; return $value; }, 'relationship canary'],
        ['gallery component', function ($value) { $value['gallery'][0]['Component'] = 'Article'; return $value; }, 'relationship canary'],
        ['gallery position', function ($value) { $value['gallery'][0]['HomePosition'] = '2'; return $value; }, 'relationship canary'],
        ['gallery traversal', function ($value) { $value['gallery'][0]['LongDesc'] = '../layout-02.png'; return $value; }, 'safe local raster'],
        ['gallery external link', function ($value) { $value['gallery'][0]['Link'] = 'https://example.com'; return $value; }, 'local absolute-path'],
        ['gallery new window', function ($value) { $value['gallery'][0]['NewWindow'] = 'Y'; return $value; }, 'new browsing context'],
        ['setting removal', function ($value) { array_pop($value['settings']); return $value; }, 'exactly two'],
    ];
    foreach ($tamperCases as $case) {
        red_theme_home_preview_test_expect(
            function () use ($rows, $case) {
                red_theme_home_preview_prepare_rows($case[1]($rows));
            },
            $case[2],
            $case[0] . ' tampering is rejected'
        );
    }
    $extraColumn = $rows;
    $extraColumn['gallery'][0]['Path'] = '/tmp';
    red_theme_home_preview_test_expect(
        function () use ($extraColumn) {
            red_theme_home_preview_prepare_rows($extraColumn);
        },
        'exact selected columns',
        'an extra database column is rejected before mapping'
    );
    $tooManyHeroRows = $rows;
    $heroRow = [
        'RecordID' => '100',
        'Title' => 'Slide',
        'Alias' => 'slide',
        'Sections' => 'home',
        'Categories' => '',
        'SubCategories' => '',
        'LongDesc' => '',
        'SliderDesc' => 'Summary',
        'Link' => '/',
        'NewWindow' => '',
        'BigPict' => '',
        'ExpDate' => '9999-12-31 23:59:59',
        'HomeFeatures' => 'slider',
        'HomeFeatures_Order' => '1',
    ];
    $tooManyHeroRows['hero'] = array_fill(0, 6, $heroRow);
    red_theme_home_preview_test_expect(
        function () use ($tooManyHeroRows) {
            red_theme_home_preview_prepare_rows($tooManyHeroRows);
        },
        'slider boundary',
        'more than five live hero rows are rejected'
    );

    $runtime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_home_preview_test_assert(
        $runtime['themeId'] === 'legacy-bootstrap'
            && !empty($runtime['resolution']['usedFallback']),
        'live runtime still refuses standard-theme execution and falls back to legacy-bootstrap'
    );
    $fixtureHash = hash_file('sha256', $repositoryRoot . '/themes/starter-reference/fixtures/preview.json');
    red_theme_home_preview_test_assert(
        $fixtureHash === '31125dbea805f7192edbdf6ea10585e1b38e0bc35db9feef7edd663b9aff9d80',
        'the original deterministic fixture remains unchanged'
    );

    echo 'Home theme preview self-test passed (' . $assertions . " assertions).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Home theme preview self-test failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);
