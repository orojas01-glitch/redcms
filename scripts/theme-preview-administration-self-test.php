<?php
/**
 * Dependency-free contract, tamper, determinism, and isolation tests for the
 * fixed read-only Administration landing provider.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_preview_administration_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_administration_preview_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_administration_preview_test_expect(callable $callback, $fragment, $message)
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    red_theme_administration_preview_test_assert(
        $caught instanceof Throwable
            && ($fragment === '' || strpos($caught->getMessage(), $fragment) !== false),
        $message
    );
}

function red_theme_administration_preview_test_rows()
{
    $loginTemplate =
        '#|question=|name=username|type=textfield|required=true|displayname=Username:|initialvalue=;' . "\r\n" .
        '#|question=|name=password|type=password|required=true|displayname=Password:|initialvalue=;' . "\r\n" .
        '#|question=|name=Submit|type=button|displayname=submit';
    $instructionsSummary = '<p>Install and operate the clean RED-CMS 5.1 core: prepare the database, sign in, create pages, place content, manage navigation and settings, and update safely. Store Lite and other optional add-ons are not part of this guide.</p>';

    return [
        'section' => [
            [
                'RecordID' => '25',
                'Sections' => 'administracion',
                'Title' => 'administracion',
                'Layout' => 'index-3',
                'QueryLimit' => '100',
                'Description' => '',
                'Tags' => '',
                'Features' => '',
                'Language' => 'sp',
                'Active' => 'Y',
            ],
        ],
        'composition' => [
            [
                'ArticleRecordID' => '966111194',
                'ArticleAlias' => 'login',
                'ArticleTitle' => 'Login',
                'Component' => 'Form',
                'SectionPosition' => '1',
                'SectionPositionOrder' => '1',
                'ArticleLayout' => 'index-2',
                'PagePosition' => '1',
                'ArticleShortDesc' => 'Login now!',
                'Sections' => 'administracion',
                'Language' => 'sp',
                'Active' => 'Y',
                'FormRecordID' => '884542279',
                'FormRefID' => '966111194',
                'FormAlias' => 'login',
                'FormTitle' => 'Login',
                'FormType' => 'Login',
                'FormTemplate' => $loginTemplate,
                'GalleryRecordID' => null,
                'GalleryRefID' => null,
                'GalleryAlias' => null,
                'GalleryTitle' => null,
                'GalleryType' => null,
                'GalleryCaption' => null,
                'GallerySource' => null,
                'GalleryLink' => null,
                'GalleryNewWindow' => null,
            ],
            [
                'ArticleRecordID' => '89196971',
                'ArticleAlias' => 'instructions',
                'ArticleTitle' => 'Instructions',
                'Component' => 'Article',
                'SectionPosition' => '2',
                'SectionPositionOrder' => '1',
                'ArticleLayout' => 'index-2',
                'PagePosition' => '1',
                'ArticleShortDesc' => $instructionsSummary,
                'Sections' => 'administracion',
                'Language' => 'sp',
                'Active' => 'Y',
                'FormRecordID' => null,
                'FormRefID' => null,
                'FormAlias' => null,
                'FormTitle' => null,
                'FormType' => null,
                'FormTemplate' => null,
                'GalleryRecordID' => null,
                'GalleryRefID' => null,
                'GalleryAlias' => null,
                'GalleryTitle' => null,
                'GalleryType' => null,
                'GalleryCaption' => null,
                'GallerySource' => null,
                'GalleryLink' => null,
                'GalleryNewWindow' => null,
            ],
            [
                'ArticleRecordID' => '880701099',
                'ArticleAlias' => 'admin-video',
                'ArticleTitle' => 'Como agregar contenido',
                'Component' => 'Gallery',
                'SectionPosition' => '2',
                'SectionPositionOrder' => '2',
                'ArticleLayout' => '',
                'PagePosition' => '1',
                'ArticleShortDesc' => '',
                'Sections' => 'administracion',
                'Language' => 'sp',
                'Active' => 'Y',
                'FormRecordID' => null,
                'FormRefID' => null,
                'FormAlias' => null,
                'FormTitle' => null,
                'FormType' => null,
                'FormTemplate' => null,
                'GalleryRecordID' => '1968830051',
                'GalleryRefID' => '880701099',
                'GalleryAlias' => 'admin-video',
                'GalleryTitle' => 'Como agregar contenido',
                'GalleryType' => 'Video',
                'GalleryCaption' => '',
                'GallerySource' => 'https://www.youtube.com/watch?v=pP8VJwjSnqA&feature=youtu.be',
                'GalleryLink' => '',
                'GalleryNewWindow' => '',
            ],
        ],
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

$sessionWasSet = isset($_SESSION);
$previousSession = $sessionWasSet ? $_SESSION : null;
$previousGet = $_GET;
$previousPost = $_POST;

try {
    $queries = red_theme_administration_preview_query_inventory();
    red_theme_administration_preview_test_assert(
        array_keys($queries) === [
            'administration-section',
            'administration-composition',
            'administration-navigation',
            'administration-settings',
        ],
        'query inventory contains exactly the four fixed reads in order'
    );
    red_theme_administration_preview_test_assert(
        red_theme_administration_preview_assert_query_inventory($queries),
        'fixed query inventory passes its safety boundary'
    );
    foreach ($queries as $id => $sql) {
        red_theme_administration_preview_test_assert(
            preg_match('/\ASELECT\s/i', ltrim($sql)) === 1,
            $id . ' is a SELECT'
        );
        red_theme_administration_preview_test_assert(
            preg_match(
                '/\b(?:ALTER|CREATE|DELETE|DROP|INSERT|REPLACE|TRUNCATE|UPDATE)\b/i',
                $sql
            ) !== 1,
            $id . ' contains no write operation'
        );
    }
    $tamperedQueries = $queries;
    $tamperedQueries['fifth-read'] = 'SELECT RecordID FROM RED_Sections LIMIT 1';
    red_theme_administration_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_administration_preview_assert_query_inventory($tamperedQueries);
        },
        'exactly four',
        'a fifth query is rejected'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['administration-section'] =
        "UPDATE RED_Sections SET Active='N' WHERE RecordID=25";
    red_theme_administration_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_administration_preview_assert_query_inventory($tamperedQueries);
        },
        'single fixed SELECT',
        'a write-shaped query is rejected'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['administration-settings'] = 'SELECT Item FROM RED_Admin LIMIT 1';
    red_theme_administration_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_administration_preview_assert_query_inventory($tamperedQueries);
        },
        'unexpected table',
        'a read from an unapproved table is rejected'
    );

    $_GET = ['administration_preview_sentinel' => 'get'];
    $_POST = ['administration_preview_sentinel' => 'post'];
    $_SESSION = ['administration_preview_sentinel' => 'session'];
    $rows = red_theme_administration_preview_test_rows();
    $first = red_theme_administration_preview_render_rows($rows, $repositoryRoot, 0);
    $second = red_theme_administration_preview_render_rows($rows, $repositoryRoot, 0);
    red_theme_administration_preview_test_assert(
        $first['html'] === $second['html'] && $first['sha256'] === $second['sha256'],
        'two Administration fixture-row renders are byte-for-byte deterministic'
    );
    red_theme_administration_preview_test_assert(
        $first['bytes'] === 13685
            && $first['sha256'] === '089e4aac3d7978dc732da6e369c74f2d536626aacc3653ceba37e2368dd0222f',
        'Administration fixture-row output matches the reviewed deterministic artifact'
    );
    red_theme_administration_preview_test_assert(
        $first['bytes'] === strlen($first['html']),
        'Administration byte report matches the complete output'
    );
    red_theme_administration_preview_test_assert(
        $_GET === ['administration_preview_sentinel' => 'get']
            && $_POST === ['administration_preview_sentinel' => 'post']
            && $_SESSION === ['administration_preview_sentinel' => 'session'],
        'Administration rendering does not read or mutate request/session sentinels'
    );
    red_theme_administration_preview_test_assert(
        $first['scope'] === red_theme_administration_preview_scope(0),
        'fixture-row test reports zero database and session side effects'
    );
    red_theme_administration_preview_test_assert(
        $first['layout'] === 'index-3'
            && $first['contract']['document']['language'] === 'es'
            && $first['contract']['document']['title'] === 'administracion — Instructions',
        'real Administration values map into the expected document and layout'
    );
    red_theme_administration_preview_test_assert(
        count($first['contract']['page']['slots'][1]) === 1
            && $first['contract']['page']['slots'][1][0]['component'] === 'Form'
            && array_column($first['contract']['page']['slots'][2], 'component') === ['Article', 'Gallery'],
        'the exact index-3 composition maps Login to slot 1 and Instructions/Video to slot 2'
    );
    $loginFields = $first['contract']['page']['slots'][1][0]['data']['fields'];
    red_theme_administration_preview_test_assert(
        array_column($loginFields, 'name') === ['username', 'password']
            && array_column($loginFields, 'type') === ['text', 'password']
            && array_column($loginFields, 'autocomplete') === ['username', 'current-password']
            && array_column($loginFields, 'required') === [true, true],
        'legacy Login fields map to the exact inert portable credential display'
    );
    red_theme_administration_preview_test_assert(
        strpos($first['html'], 'type="password"') !== false
            && strpos($first['html'], 'value=') === false
            && strpos($first['html'], '<button type="button" aria-disabled="true">submit</button>') !== false
            && preg_match('/<form\b[^>]*\baction\s*=/i', $first['html']) !== 1,
        'Login preview has a password control but no value, target, or submitting button'
    );
    red_theme_administration_preview_test_assert(
        $first['contract']['page']['slots'][2][0]['data']['summary'] ===
            'Install and operate the clean RED-CMS 5.1 core: prepare the database, sign in, create pages, place content, manage navigation and settings, and update safely. Store Lite and other optional add-ons are not part of this guide.'
            && strpos($first['html'], 'Store Lite and other optional add-ons are not part of this guide.') !== false,
        'Instructions rich listing input is reduced to bounded plain text'
    );
    red_theme_administration_preview_test_assert(
        strpos($first['html'], 'data-video-provider="youtube"') !== false
            && strpos($first['html'], 'data-video-id="pP8VJwjSnqA"') !== false
            && strpos($first['html'], 'YouTube video preview') !== false
            && strpos($first['html'], 'External playback is intentionally disabled') !== false,
        'recognized Video source maps only to the proven offline provider/id representation'
    );
    red_theme_administration_preview_test_assert(
        substr_count($first['html'], '<iframe') === 0
            && substr_count($first['html'], '<object') === 0
            && substr_count($first['html'], '<embed') === 0
            && substr_count($first['html'], '<script') === 0
            && strpos($first['html'], 'youtube.com') === false
            && strpos($first['html'], 'youtu.be') === false,
        'Administration output executes no external media or client-side script'
    );
    red_theme_administration_preview_test_assert(
        strpos($first['html'], 'Read-only Administration data preview') !== false
            && strpos($first['html'], 'forms and video remain offline') !== false
            && strpos($first['html'], 'Read-only Contact data preview') === false,
        'Administration output carries its exact visible read-only boundary notice'
    );
    $sourceJson = json_encode($first['source'], JSON_UNESCAPED_SLASHES);
    red_theme_administration_preview_test_assert(
        is_string($sourceJson)
            && strpos($sourceJson, 'FormTemplate') === false
            && strpos($sourceJson, 'GallerySource') === false
            && strpos($sourceJson, 'youtube.com') === false
            && strpos($sourceJson, 'SELECT ') === false
            && $first['source']['login']['fieldCount'] === 2
            && $first['source']['video']['provider'] === 'youtube'
            && $first['source']['video']['id'] === 'pP8VJwjSnqA',
        'source metadata exposes bounded provenance but no raw template, URL, or SQL'
    );
    red_theme_administration_preview_test_assert(
        $first['source']['rowCounts'] === [
            'section' => 1,
            'composition' => 3,
            'navigation' => 2,
            'settings' => 2,
        ]
            && $first['source']['fallbacks'] === [
                'siteTitle' => 'section.Title',
                'description' => 'login.ShortDesc',
                'footer' => 'section.Title',
                'videoCaption' => 'gallery.Title',
            ],
        'source report locks exact row counts and current bounded fallbacks'
    );
    $fixturePreview = red_theme_preview_render('starter-reference', $repositoryRoot);
    red_theme_administration_preview_test_assert(
        $fixturePreview['bytes'] === 19523
            && $fixturePreview['sha256'] === '984e8464dbbd6d0db1edabcae81023f65e3de31b941f3e569e0c3ede13f93e7e',
        'the current deterministic fixture output remains byte-for-byte unchanged'
    );
    $runtime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_administration_preview_test_assert(
        $runtime['themeId'] === 'legacy-bootstrap' && !empty($runtime['resolution']['usedFallback']),
        'live compatibility runtime still refuses standard-theme execution'
    );

    $fourReadResult = red_theme_administration_preview_render_rows($rows, $repositoryRoot, 4);
    red_theme_administration_preview_test_assert(
        $fourReadResult['scope'] === red_theme_administration_preview_scope(4)
            && $fourReadResult['html'] === $first['html'],
        'real-read scope reports exactly four reads without changing rendered output'
    );
    red_theme_administration_preview_test_expect(
        function () use ($rows, $repositoryRoot) {
            red_theme_administration_preview_render_rows($rows, $repositoryRoot, 1);
        },
        'zero or four',
        'an ambiguous database-read count is rejected'
    );
    red_theme_administration_preview_test_expect(
        function () {
            red_theme_preview_scope([
                'databaseReads' => 4,
                'databaseWrites' => 1,
                'sessionReads' => 0,
                'sessionWrites' => 0,
                'liveRuntimeChanges' => 0,
            ]);
        },
        'database reads only',
        'any claimed database write is rejected by the isolated scope'
    );
    $validation = red_theme_preview_validate_reference_theme('starter-reference', $repositoryRoot);
    red_theme_administration_preview_test_expect(
        function () use ($validation, $first) {
            red_theme_preview_render_prepared_contract(
                $validation,
                $first['contract'],
                'read-only-contact-preview',
                red_theme_administration_preview_scope(0)
            );
        },
        'offline fixture contract',
        'Administration Video cannot cross into the fixed Contact preview mode'
    );

    $tampered = $rows;
    $tampered['section'] = [];
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly one',
        'a missing Administration section is rejected'
    );
    $tampered = $rows;
    $tampered['section'][] = $tampered['section'][0];
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly one',
        'multiple Administration sections are rejected'
    );
    $tampered = $rows;
    $tampered['section'][0]['Unexpected'] = 'value';
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'exact selected columns',
        'an extra Section column is rejected'
    );
    foreach ([
        'RecordID' => '24',
        'Sections' => 'other',
        'Layout' => 'index-2',
        'QueryLimit' => '99',
        'Features' => 'slider',
        'Language' => 'en',
        'Active' => 'N',
    ] as $key => $value) {
        $tampered = $rows;
        $tampered['section'][0][$key] = $value;
        red_theme_administration_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
            },
            'fixed active canary',
            'tampered Administration Section ' . $key . ' is rejected'
        );
    }

    $tampered = $rows;
    array_pop($tampered['composition']);
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly three',
        'a missing composition row is rejected'
    );
    $tampered = $rows;
    $tampered['composition'][] = $tampered['composition'][2];
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly three',
        'a duplicate fourth composition row is rejected'
    );
    $tampered = $rows;
    [$tampered['composition'][1], $tampered['composition'][2]] =
        [$tampered['composition'][2], $tampered['composition'][1]];
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'ordered canary',
        'composition order drift is rejected'
    );
    $tampered = $rows;
    $tampered['composition'][0]['Unexpected'] = 'value';
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'exact selected columns',
        'an extra composition column is rejected'
    );

    foreach ([
        'ArticleAlias' => 'other',
        'Component' => 'Article',
        'SectionPosition' => '2',
        'SectionPositionOrder' => '2',
        'ArticleLayout' => 'index-3',
        'PagePosition' => '2',
    ] as $key => $value) {
        $tampered = $rows;
        $tampered['composition'][0][$key] = $value;
        red_theme_administration_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
            },
            'fixed position canary',
            'tampered Login Article ' . $key . ' is rejected'
        );
    }
    foreach ([
        'FormRecordID' => '884542280',
        'FormRefID' => '966111195',
        'FormAlias' => 'other',
        'FormType' => 'Contact',
    ] as $key => $value) {
        $tampered = $rows;
        $tampered['composition'][0][$key] = $value;
        red_theme_administration_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
            },
            'fixed paired canary',
            'tampered Login Form ' . $key . ' is rejected'
        );
    }
    $loginTemplateCases = [
        ['type=password', 'type=textfield', 'unsupported or unsafe', 'password type drift'],
        ['required=true', 'required=false', 'unsupported or unsafe', 'required-state drift'],
        ['initialvalue=;', 'initialvalue=secret;', 'unsupported or unsafe', 'default-value injection'],
        ['|initialvalue=;', '|initialvalue=|endpoint=/bin/login.php;', 'unknown or duplicated', 'endpoint injection'],
    ];
    foreach ($loginTemplateCases as $case) {
        $tampered = $rows;
        $tampered['composition'][0]['FormTemplate'] = preg_replace(
            '/' . preg_quote($case[0], '/') . '/',
            $case[1],
            $tampered['composition'][0]['FormTemplate'],
            1
        );
        red_theme_administration_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
            },
            $case[2],
            'Login template rejects ' . $case[3]
        );
    }
    $tampered = $rows;
    $tampered['composition'][0]['GalleryRecordID'] = '1';
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'unexpected paired child',
        'Login row cannot acquire a Gallery child'
    );

    foreach ([
        'ArticleAlias' => 'other',
        'Component' => 'Other',
        'SectionPositionOrder' => '2',
        'ArticleLayout' => 'index-3',
        'PagePosition' => '2',
    ] as $key => $value) {
        $tampered = $rows;
        $tampered['composition'][1][$key] = $value;
        red_theme_administration_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
            },
            'fixed position canary',
            'tampered Instructions Article ' . $key . ' is rejected'
        );
    }
    $tampered = $rows;
    $tampered['composition'][1]['FormRecordID'] = '1';
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'unexpected paired child',
        'Instructions listing cannot acquire a Form child'
    );
    $plainOnly = $rows;
    $plainOnly['composition'][1]['ArticleShortDesc'] =
        '<p>Safe summary</p><script>not executed</script><img src="https://example.test/tracker.png">';
    $plainOnlyResult = red_theme_administration_preview_render_rows($plainOnly, $repositoryRoot);
    red_theme_administration_preview_test_assert(
        $plainOnlyResult['contract']['page']['slots'][2][0]['data']['summary'] ===
            'Safe summarynot executed'
            && strpos($plainOnlyResult['html'], '<script>') === false
            && strpos($plainOnlyResult['html'], '<img') === false
            && strpos($plainOnlyResult['html'], 'example.test') === false,
        'untrusted Instructions listing markup is reduced to inert text only'
    );

    foreach ([
        'ArticleAlias' => 'other',
        'Component' => 'Article',
        'SectionPositionOrder' => '3',
        'ArticleLayout' => 'index-2',
        'PagePosition' => '2',
    ] as $key => $value) {
        $tampered = $rows;
        $tampered['composition'][2][$key] = $value;
        red_theme_administration_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
            },
            'fixed position canary',
            'tampered Video Article ' . $key . ' is rejected'
        );
    }
    foreach ([
        'GalleryRecordID' => '1968830052',
        'GalleryRefID' => '880701100',
        'GalleryAlias' => 'other',
        'GalleryType' => 'Banner',
        'GalleryLink' => '/administracion/',
        'GalleryNewWindow' => 'Y',
    ] as $key => $value) {
        $tampered = $rows;
        $tampered['composition'][2][$key] = $value;
        red_theme_administration_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
            },
            'fixed paired canary',
            'tampered Video Gallery ' . $key . ' is rejected'
        );
    }
    foreach ([
        'http://www.youtube.com/watch?v=pP8VJwjSnqA',
        'https://www.youtube.com/watch?v=pP8VJwjSnqA&autoplay=1',
        'https://example.test/watch?v=pP8VJwjSnqA',
        '<iframe src="https://www.youtube.com/embed/pP8VJwjSnqA"></iframe>',
        'https://vimeo.com/123456789',
        'javascript:alert(1)',
    ] as $unsafeSource) {
        $tampered = $rows;
        $tampered['composition'][2]['GallerySource'] = $unsafeSource;
        red_theme_administration_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
            },
            '',
            'unsafe or non-canary Video source is rejected'
        );
    }

    $tampered = $rows;
    $tampered['navigation'][1]['Link'] = 'javascript:alert(1)';
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'fragment, root-relative URL, or HTTPS URL',
        'an unsafe navigation URL is rejected'
    );
    $tampered = $rows;
    $tampered['navigation'][] = $tampered['navigation'][1];
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly the active Spanish',
        'an unexpected third root-menu row is rejected'
    );
    $tampered = $rows;
    $tampered['settings'][0]['Item'] = 'Website_Header';
    red_theme_administration_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_administration_preview_render_rows($tampered, $repositoryRoot);
        },
        'outside the fixed allowlist',
        'a raw Website_Header setting is rejected'
    );

    $withContent = $rows;
    $withContent['section'][0]['Description'] = '<p>Administration preview description.</p>';
    $withContent['settings'][0]['Content'] = '<p>Real footer</p>';
    $withContent['settings'][1]['Content'] = '<strong>Real site title</strong>';
    $withContent['composition'][2]['GalleryCaption'] = '<em>Safe video caption</em>';
    $contentResult = red_theme_administration_preview_render_rows($withContent, $repositoryRoot);
    red_theme_administration_preview_test_assert(
        $contentResult['contract']['document']['description'] === 'Administration preview description.'
            && $contentResult['contract']['regions']['header']['siteTitle'] === 'Real site title'
            && $contentResult['contract']['regions']['footer']['copyright'] === 'Real footer'
            && $contentResult['contract']['page']['slots'][2][1]['data']['video']['caption'] ===
                'Safe video caption',
        'allowed legacy values are reduced to bounded plain text'
    );
    red_theme_administration_preview_test_assert(
        $contentResult['source']['fallbacks'] === [
            'siteTitle' => 'advanced.Website_Title',
            'description' => 'section.Description',
            'footer' => 'advanced.Website_Footer',
            'videoCaption' => 'gallery.ShortDesc',
        ],
        'non-empty values record their exact bounded sources'
    );
} finally {
    $_GET = $previousGet;
    $_POST = $previousPost;
    if ($sessionWasSet) {
        $_SESSION = $previousSession;
    } else {
        unset($_SESSION);
    }
}

echo 'Theme Administration preview self-test passed: ' . $assertions . " assertions.\n";
