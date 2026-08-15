<?php
/**
 * Dependency-free query, content, media, tamper, determinism, and isolation
 * tests for the fixed selected Instructions Article preview provider.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_preview_instructions_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_instructions_preview_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_instructions_preview_test_expect(callable $callback, $fragment, $message)
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    red_theme_instructions_preview_test_assert(
        $caught instanceof Throwable
            && ($fragment === '' || strpos($caught->getMessage(), $fragment) !== false),
        $message
    );
}

function red_theme_instructions_preview_test_decode_escape($character)
{
    $map = [
        '0' => "\0",
        'b' => "\x08",
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'Z' => "\x1A",
        '\\' => '\\',
        "'" => "'",
        '"' => '"',
    ];

    return array_key_exists($character, $map) ? $map[$character] : $character;
}

function red_theme_instructions_preview_test_sql_literal($sql, &$offset)
{
    $length = strlen($sql);
    while ($offset < $length && ctype_space($sql[$offset])) {
        $offset++;
    }
    if ($offset >= $length || $sql[$offset] !== "'") {
        throw new RuntimeException('Expected a quoted Instructions seed value.');
    }
    $offset++;
    $value = '';
    while ($offset < $length) {
        $character = $sql[$offset++];
        if ($character === '\\') {
            if ($offset >= $length) {
                throw new RuntimeException('The Instructions seed ends in an escape.');
            }
            $value .= red_theme_instructions_preview_test_decode_escape($sql[$offset++]);
            continue;
        }
        if ($character === "'") {
            return $value;
        }
        $value .= $character;
    }

    throw new RuntimeException('The Instructions seed contains an unclosed string.');
}

function red_theme_instructions_preview_test_seed_values($repositoryRoot)
{
    $sql = file_get_contents($repositoryRoot . '/db-structure.sql');
    if (!is_string($sql)) {
        throw new RuntimeException('Could not read the clean-install Instructions seed.');
    }
    $marker = '-- RED-CMS 5.1 clean-core Instructions Article';
    $start = strpos($sql, $marker);
    if ($start === false) {
        throw new RuntimeException('Could not find the clean-core Instructions seed update.');
    }
    $summaryStart = strpos($sql, '`ShortDesc` = ', $start);
    $bodyStart = strpos($sql, '`LongDesc` = ', $summaryStart === false ? $start : $summaryStart);
    if ($summaryStart === false || $bodyStart === false) {
        throw new RuntimeException('Could not isolate the clean-core Instructions source values.');
    }

    $summaryOffset = $summaryStart + strlen('`ShortDesc` = ');
    $bodyOffset = $bodyStart + strlen('`LongDesc` = ');
    $summary = red_theme_instructions_preview_test_sql_literal($sql, $summaryOffset);
    $body = red_theme_instructions_preview_test_sql_literal($sql, $bodyOffset);
    if (strpos($sql, 'WHERE `RecordID` = 89196971', $bodyOffset) === false) {
        throw new RuntimeException('The clean-core Instructions update has no fixed Article target.');
    }

    return ['summary' => $summary, 'body' => $body];
}

function red_theme_instructions_preview_test_rows($repositoryRoot)
{
    $seed = red_theme_instructions_preview_test_seed_values($repositoryRoot);

    return [
        'articleSection' => [[
            'ArticleRecordID' => '89196971',
            'ArticleTitle' => 'Instructions',
            'ArticleAlias' => 'instructions',
            'Component' => 'Article',
            'ArticleSection' => 'administracion',
            'Categories' => '',
            'SubCategories' => '',
            'ArticleLayout' => 'index-2',
            'SectionPosition' => '2',
            'SectionPositionOrder' => '1',
            'PagePosition' => '1',
            'PagePositionOrder' => '0',
            'ShortDesc' => $seed['summary'],
            'LongDesc' => $seed['body'],
            'Link' => '',
            'NewWindow' => '',
            'ArticleLanguage' => 'sp',
            'ArticleActive' => 'Y',
            'StartDate' => '1970-01-01 00:00:00',
            'ExpDate' => '9999-12-31 23:59:59',
            'SectionRecordID' => '25',
            'SectionAlias' => 'administracion',
            'SectionTitle' => 'administracion',
            'SectionLayout' => 'index-3',
            'QueryLimit' => '100',
            'SectionDescription' => '',
            'SectionTags' => '',
            'SectionFeatures' => '',
            'SectionLanguage' => 'sp',
            'SectionActive' => 'Y',
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

function red_theme_instructions_preview_test_remove_tree($path)
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $entries = scandir($path);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                red_theme_instructions_preview_test_remove_tree($path . '/' . $entry);
            }
        }
    }
    @rmdir($path);
}

function red_theme_instructions_preview_test_media_root($repositoryRoot)
{
    $temporaryRoot = sys_get_temp_dir() . '/redcms-theme-instructions-test-' .
        getmypid() . '-' . bin2hex(random_bytes(4));
    $relative = '/admin/images/red-cms-instructions-manual_files';
    if (!mkdir($temporaryRoot . $relative, 0700, true)) {
        throw new RuntimeException('Could not create the temporary Instructions media root.');
    }
    $canary = red_theme_instructions_preview_canary();
    foreach ($canary['mediaFiles'] as $filename) {
        if (!copy(
            $repositoryRoot . $relative . '/' . $filename,
            $temporaryRoot . $relative . '/' . $filename
        )) {
            red_theme_instructions_preview_test_remove_tree($temporaryRoot);
            throw new RuntimeException('Could not copy a fixed Instructions test image.');
        }
    }

    return $temporaryRoot;
}

class RedThemeInstructionsPreviewTestResult
{
    private $rows;
    private $offset = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetch_assoc()
    {
        if (!array_key_exists($this->offset, $this->rows)) {
            return null;
        }

        return $this->rows[$this->offset++];
    }

    public function free()
    {
    }
}

class RedThemeInstructionsPreviewTestConnection
{
    public $queries = [];
    public $commits = 0;
    public $rollbacks = 0;
    public $failId = '';
    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function query($sql)
    {
        $this->queries[] = $sql;
        if ($sql === 'START TRANSACTION READ ONLY') {
            return true;
        }
        $inventory = red_theme_instructions_preview_query_inventory();
        $mapping = [
            'instructions-article-section' => 'articleSection',
            'instructions-navigation' => 'navigation',
            'instructions-settings' => 'settings',
        ];
        foreach ($inventory as $id => $expectedSql) {
            if ($sql === $expectedSql) {
                if ($this->failId === $id) {
                    return false;
                }
                return new RedThemeInstructionsPreviewTestResult($this->rows[$mapping[$id]]);
            }
        }

        return false;
    }

    public function commit()
    {
        $this->commits++;
        return true;
    }

    public function rollback()
    {
        $this->rollbacks++;
        return true;
    }
}

$sessionWasSet = isset($_SESSION);
$previousSession = $sessionWasSet ? $_SESSION : null;
$previousGet = $_GET;
$previousPost = $_POST;
$temporaryRoot = null;

try {
    $queries = red_theme_instructions_preview_query_inventory();
    red_theme_instructions_preview_test_assert(
        array_keys($queries) === [
            'instructions-article-section',
            'instructions-navigation',
            'instructions-settings',
        ],
        'query inventory contains exactly the three fixed reads in order'
    );
    red_theme_instructions_preview_test_assert(
        red_theme_instructions_preview_assert_query_inventory($queries),
        'fixed query inventory passes its exact safety boundary'
    );
    foreach ($queries as $id => $sql) {
        red_theme_instructions_preview_test_assert(
            preg_match('/\ASELECT\s/i', ltrim($sql)) === 1,
            $id . ' is a SELECT'
        );
        red_theme_instructions_preview_test_assert(
            preg_match(
                '/\b(?:ALTER|CREATE|DELETE|DROP|INSERT|REPLACE|TRUNCATE|UPDATE)\b/i',
                $sql
            ) !== 1,
            $id . ' contains no write operation'
        );
    }
    $tamperedQueries = $queries;
    $tamperedQueries['fourth-read'] = 'SELECT RecordID FROM RED_Articles LIMIT 1';
    red_theme_instructions_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_instructions_preview_assert_query_inventory($tamperedQueries);
        },
        'exactly three',
        'a fourth query is rejected'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['instructions-article-section'] =
        "UPDATE RED_Articles SET Active='N' WHERE RecordID=89196971";
    red_theme_instructions_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_instructions_preview_assert_query_inventory($tamperedQueries);
        },
        'single fixed SELECT',
        'a write-shaped query is rejected'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['instructions-settings'] = 'SELECT Item FROM RED_Admin LIMIT 1';
    red_theme_instructions_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_instructions_preview_assert_query_inventory($tamperedQueries);
        },
        'fixed inventory',
        'a read from an unapproved table cannot enter the fixed inventory'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['instructions-navigation'] = str_replace(
        'ORDER BY MenuOrder ASC, RecordID ASC',
        'ORDER BY RecordID DESC',
        $tamperedQueries['instructions-navigation']
    );
    red_theme_instructions_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_instructions_preview_assert_query_inventory($tamperedQueries);
        },
        'fixed inventory',
        'query ordering drift is rejected'
    );

    $rows = red_theme_instructions_preview_test_rows($repositoryRoot);
    $canary = red_theme_instructions_preview_canary();
    red_theme_instructions_preview_test_assert(
        strlen($rows['articleSection'][0]['ShortDesc']) === $canary['summaryBytes']
            && hash('sha256', $rows['articleSection'][0]['ShortDesc']) === $canary['summarySha256'],
        'clean-install Instructions summary reconstructs the exact live canary'
    );
    red_theme_instructions_preview_test_assert(
        strlen($rows['articleSection'][0]['LongDesc']) === $canary['bodyBytes']
            && hash('sha256', $rows['articleSection'][0]['LongDesc']) === $canary['bodySha256'],
        'clean-install Instructions body reconstructs the exact live canary (' .
            strlen($rows['articleSection'][0]['LongDesc']) . ' bytes, ' .
            hash('sha256', $rows['articleSection'][0]['LongDesc']) . ')'
    );

    $connection = new RedThemeInstructionsPreviewTestConnection($rows);
    $read = red_theme_instructions_preview_read_rows($connection);
    red_theme_instructions_preview_test_assert(
        $connection->queries === array_merge(
            ['START TRANSACTION READ ONLY'],
            array_values($queries)
        ),
        'provider opens one read-only transaction and executes only the three fixed reads in order'
    );
    red_theme_instructions_preview_test_assert(
        $connection->commits === 1 && $connection->rollbacks === 0,
        'successful fixed reads close with one commit and no rollback'
    );
    red_theme_instructions_preview_test_assert(
        $read['rows'] === $rows && $read['scope'] === red_theme_instructions_preview_scope(3),
        'read boundary returns only fixed rows plus exact three-read scope'
    );
    $failingConnection = new RedThemeInstructionsPreviewTestConnection($rows);
    $failingConnection->failId = 'instructions-navigation';
    red_theme_instructions_preview_test_expect(
        function () use ($failingConnection) {
            red_theme_instructions_preview_read_rows($failingConnection);
        },
        'fixed read',
        'a failed fixed query aborts the provider'
    );
    red_theme_instructions_preview_test_assert(
        $failingConnection->commits === 0 && $failingConnection->rollbacks === 1,
        'a failed fixed query rolls back the read-only transaction'
    );

    $_GET = ['instructions_preview_sentinel' => 'get'];
    $_POST = ['instructions_preview_sentinel' => 'post'];
    $_SESSION = ['instructions_preview_sentinel' => 'session'];
    $first = red_theme_instructions_preview_render_rows($rows, $repositoryRoot, 0);
    $second = red_theme_instructions_preview_render_rows($rows, $repositoryRoot, 0);
    red_theme_instructions_preview_test_assert(
        $first['html'] === $second['html'] && $first['sha256'] === $second['sha256'],
        'two selected Instructions renders are byte-for-byte deterministic'
    );
    red_theme_instructions_preview_test_assert(
        $first['bytes'] === 509066
            && $first['sha256'] === 'd731c02698cc8197f36fb8d2f63586fda4fdb093c005905db82cb3e02ebcba3a',
        'reviewed selected Instructions output locks its exact bytes and hash'
    );
    red_theme_instructions_preview_test_assert(
        $first['theme'] === 'starter-reference'
            && $first['layout'] === 'index-2'
            && $first['scope'] === red_theme_instructions_preview_scope(0),
        'render stays in the non-active starter, fixed index-2, and zero-read synthetic scope'
    );
    red_theme_instructions_preview_test_assert(
        $_GET === ['instructions_preview_sentinel' => 'get']
            && $_POST === ['instructions_preview_sentinel' => 'post']
            && $_SESSION === ['instructions_preview_sentinel' => 'session'],
        'render does not read or mutate request or session state'
    );
    red_theme_instructions_preview_test_assert(
        substr_count($first['html'], 'data:image/') === 4
            && substr_count($first['html'], 'loading="lazy"') === 4
            && substr_count($first['html'], 'decoding="async"') === 4,
        'render embeds exactly four bounded lazy-decoded local screenshots'
    );
    red_theme_instructions_preview_test_assert(
        strpos($first['html'], 'id="instructions-manual"') !== false
            && strpos($first['html'], 'Trusted Article component') !== false
            && strpos($first['html'], 'Read-only Instructions data preview') !== false,
        'render uses the dedicated trusted Article branch and exact read-only notice'
    );
    red_theme_instructions_preview_test_assert(
        preg_match('/<(?:script|iframe|object|embed|form|input|button)\b/i', $first['html']) !== 1
            && preg_match('/\b(?:src|href)="(?:https?:)?\/\//i', $first['html']) !== 1,
        'render contains no executable element or external source/link'
    );
    red_theme_instructions_preview_test_assert(
        $first['source']['content'] === [
            'sourceBytes' => 7506,
            'sourceSha256' => 'ac05d87a2a7821e13083d66067b1af9e1f4ff131d3133658ba07b2416afd3c36',
            'sanitizedBytes' => 497598,
            'sanitizedSha256' => '913fab88e6440a1c4e84fa612d8be80f52397f6f77de95c176c42a618f716455',
            'localLinkCount' => 7,
            'duplicateTargetsRemoved' => 0,
        ],
        'report locks exact source/sanitized content facts without exposing HTML'
    );
    red_theme_instructions_preview_test_assert(
        $first['source']['media'] === [
            'count' => 4,
            'bytes' => 367642,
            'manifestSha256' => '330c2c4ca83b78522e9d3484430d85fc79e3c11363089fdd23d27efcf0157994',
            'embedded' => 4,
            'dimensionCorrections' => 0,
            'externalResources' => 0,
        ],
        'report locks the exact confined media manifest and normalization facts'
    );
    $sourceJson = json_encode($first['source'], JSON_UNESCAPED_SLASHES);
    red_theme_instructions_preview_test_assert(
        is_string($sourceJson)
            && strpos($sourceJson, '<h') === false
            && strpos($sourceJson, 'data:image') === false
            && strpos($sourceJson, 'v51-workspace') === false
            && strpos($sourceJson, 'SELECT ') === false
            && strpos($sourceJson, $repositoryRoot) === false,
        'source report redacts body HTML, media filenames, SQL, and filesystem paths'
    );
    $threeRead = red_theme_instructions_preview_render_rows($rows, $repositoryRoot, 3);
    red_theme_instructions_preview_test_assert(
        $threeRead['html'] === $first['html']
            && $threeRead['scope'] === red_theme_instructions_preview_scope(3),
        'real-read scope records exactly three reads without changing output'
    );
    red_theme_instructions_preview_test_expect(
        function () use ($rows, $repositoryRoot) {
            red_theme_instructions_preview_render_rows($rows, $repositoryRoot, 1);
        },
        'zero or three',
        'an ambiguous database-read count is rejected'
    );
    red_theme_instructions_preview_test_expect(
        function () {
            red_theme_preview_scope([
                'databaseReads' => 3,
                'databaseWrites' => 1,
                'sessionReads' => 0,
                'sessionWrites' => 0,
                'liveRuntimeChanges' => 0,
            ]);
        },
        'database reads only',
        'any claimed database write is rejected by the isolated scope'
    );

    $trustedData = $first['contract']['page']['slots'][1][0]['data'];
    $trustedContext = red_theme_preview_component_view_context(
        'Article',
        $trustedData,
        $repositoryRoot . '/themes/starter-reference',
        null,
        'read-only-instructions-preview'
    );
    red_theme_instructions_preview_test_assert(
        $trustedContext === $trustedData,
        'trusted Article data survives one final exact reconstruction in its fixed mode'
    );
    red_theme_instructions_preview_test_expect(
        function () use ($trustedData, $repositoryRoot) {
            red_theme_preview_component_view_context(
                'Article',
                $trustedData,
                $repositoryRoot . '/themes/starter-reference',
                null,
                'fixture-preview'
            );
        },
        'confined',
        'trusted Article HTML cannot cross into the generic fixture mode'
    );
    $extraTrustedData = $trustedData;
    $extraTrustedData['url'] = '/administracion/instructions';
    red_theme_instructions_preview_test_expect(
        function () use ($extraTrustedData) {
            red_theme_preview_component_data('Article', $extraTrustedData);
        },
        'unexpected key',
        'trusted Article data rejects a caller URL or extra field'
    );

    $sanitized = $trustedData['bodyHtml'];
    red_theme_instructions_preview_test_expect(
        function () use ($sanitized) {
            red_theme_preview_trusted_article_html(
                str_replace('#guide-install', 'https://example.test/manual', $sanitized)
            );
        },
        'local fragment',
        'trusted output rejects an external link'
    );
    red_theme_instructions_preview_test_expect(
        function () use ($sanitized) {
            red_theme_preview_trusted_article_html(
                $sanitized . '<p id="guide-install">duplicate</p>'
            );
        },
        'unique',
        'trusted output rejects a duplicate fragment target'
    );
    red_theme_instructions_preview_test_expect(
        function () use ($sanitized) {
            red_theme_preview_trusted_article_html(
                str_replace(
                    'display:block;max-width:100%;height:auto;margin:1rem auto;',
                    'display:block',
                    $sanitized
                )
            );
        },
        'responsive policy',
        'trusted output rejects caller-controlled image style behavior'
    );
    red_theme_instructions_preview_test_expect(
        function () use ($sanitized) {
            $corrupt = preg_replace(
                '/(data:image\/(?:png|jpeg);base64,)[A-Za-z0-9+\/]/',
                '$1!',
                $sanitized,
                1
            );
            red_theme_preview_trusted_article_html($corrupt);
        },
        'embedded PNG or JPEG',
        'trusted output rejects corrupt embedded image data'
    );

    $tampered = $rows;
    $tampered['articleSection'] = [];
    red_theme_instructions_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_instructions_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly one',
        'a missing joined Article/Section row is rejected'
    );
    $tampered = $rows;
    $tampered['articleSection'][] = $tampered['articleSection'][0];
    red_theme_instructions_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_instructions_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly one',
        'multiple joined Article/Section rows are rejected'
    );
    $tampered = $rows;
    $tampered['articleSection'][0]['Unexpected'] = 'value';
    red_theme_instructions_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_instructions_preview_render_rows($tampered, $repositoryRoot);
        },
        'exact selected columns',
        'an extra selected column is rejected'
    );
    foreach ([
        'ArticleRecordID' => '89196972',
        'ArticleTitle' => 'Other',
        'ArticleAlias' => 'other',
        'Component' => 'Form',
        'ArticleSection' => 'contacto',
        'Categories' => 'category',
        'SubCategories' => 'subcategory',
        'ArticleLayout' => 'index-1',
        'SectionPosition' => '1',
        'SectionPositionOrder' => '2',
        'PagePosition' => '2',
        'PagePositionOrder' => '1',
        'Link' => '/other',
        'NewWindow' => '_blank',
        'ArticleLanguage' => 'en',
        'ArticleActive' => 'N',
        'StartDate' => '1970-01-02 00:00:00',
        'ExpDate' => '2030-01-01 00:00:00',
        'SectionRecordID' => '24',
        'SectionAlias' => 'other',
        'SectionTitle' => 'other',
        'SectionLayout' => 'index-2',
        'QueryLimit' => '99',
        'SectionDescription' => 'description',
        'SectionTags' => 'tag',
        'SectionFeatures' => 'feature',
        'SectionLanguage' => 'en',
        'SectionActive' => 'N',
    ] as $column => $value) {
        $tampered = $rows;
        $tampered['articleSection'][0][$column] = $value;
        red_theme_instructions_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_instructions_preview_render_rows($tampered, $repositoryRoot);
            },
            'canary',
            $column . ' relationship drift is rejected'
        );
    }
    $tampered = $rows;
    $tampered['articleSection'][0]['ShortDesc'] .= ' ';
    red_theme_instructions_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_instructions_preview_render_rows($tampered, $repositoryRoot);
        },
        'canary',
        'Instructions summary byte drift is rejected'
    );
    $tampered = $rows;
    $tampered['articleSection'][0]['LongDesc'] .= ' ';
    red_theme_instructions_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_instructions_preview_render_rows($tampered, $repositoryRoot);
        },
        'canary',
        'benign Instructions body byte drift is rejected'
    );
    foreach ([
        '<script>alert(1)</script>' => 'executable',
        '<style>body{display:none}</style>' => 'executable',
        '<iframe src="https://example.test"></iframe>' => 'external',
        '<p onclick="alert(1)">unsafe</p>' => 'executable',
        '<img src="data:image/png;base64,AAAA" alt="">' => 'external',
    ] as $injection => $label) {
        red_theme_instructions_preview_test_expect(
            function () use ($rows, $repositoryRoot, $injection) {
                red_theme_instructions_preview_sanitize_body(
                    $injection . $rows['articleSection'][0]['LongDesc'],
                    $repositoryRoot
                );
            },
            'executable or external-resource',
            $label . ' rich-body injection is rejected before canary acceptance'
        );
    }

    $temporaryRoot = red_theme_instructions_preview_test_media_root($repositoryRoot);
    $temporarySanitized = red_theme_instructions_preview_sanitize_body(
        $rows['articleSection'][0]['LongDesc'],
        $temporaryRoot
    );
    red_theme_instructions_preview_test_assert(
        $temporarySanitized['htmlSha256'] === $first['source']['content']['sanitizedSha256']
            && $temporarySanitized['mediaManifestSha256'] === $canary['mediaManifestSha256'],
        'a confined copy of the exact four-screenshot inventory renders identically'
    );
    $firstImage = $temporaryRoot .
        '/admin/images/red-cms-instructions-manual_files/' . $canary['mediaFiles'][0];
    file_put_contents($firstImage, 'tamper', FILE_APPEND);
    red_theme_instructions_preview_test_expect(
        function () use ($rows, $temporaryRoot) {
            red_theme_instructions_preview_sanitize_body(
                $rows['articleSection'][0]['LongDesc'],
                $temporaryRoot
            );
        },
        'manifest',
        'local image byte tampering changes and rejects the fixed manifest'
    );
    red_theme_instructions_preview_test_remove_tree($temporaryRoot);
    $temporaryRoot = red_theme_instructions_preview_test_media_root($repositoryRoot);
    unlink(
        $temporaryRoot . '/admin/images/red-cms-instructions-manual_files/' .
        $canary['mediaFiles'][count($canary['mediaFiles']) - 1]
    );
    red_theme_instructions_preview_test_expect(
        function () use ($rows, $temporaryRoot) {
            red_theme_instructions_preview_sanitize_body(
                $rows['articleSection'][0]['LongDesc'],
                $temporaryRoot
            );
        },
        'does not resolve',
        'a missing local manual image is rejected'
    );

    $runtime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_instructions_preview_test_assert(
        $runtime['themeId'] === 'legacy-bootstrap'
            && !empty($runtime['resolution']['usedFallback']),
        'live runtime still refuses standard execution and hard-falls back to legacy-bootstrap'
    );
    $manifest = json_decode(
        file_get_contents($repositoryRoot . '/themes/starter-reference/theme.json'),
        true
    );
    red_theme_instructions_preview_test_assert(
        is_array($manifest)
            && $manifest['version'] === '1.3.1'
            && strpos($manifest['description'], 'Activatable portable reference package') !== false,
        'five-layout starter retains the selected Instructions preview contract at 1.3.1'
    );
} finally {
    if ($temporaryRoot !== null) {
        red_theme_instructions_preview_test_remove_tree($temporaryRoot);
    }
    $_GET = $previousGet;
    $_POST = $previousPost;
    if ($sessionWasSet) {
        $_SESSION = $previousSession;
    } else {
        unset($_SESSION);
    }
}

echo 'Theme Instructions preview self-test passed: ' . $assertions . " assertions.\n";
