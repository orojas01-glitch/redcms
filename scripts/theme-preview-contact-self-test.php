<?php
/**
 * Dependency-free contract, tamper, determinism, and isolation tests for the
 * read-only Contact preview-input provider.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_preview_contact_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_contact_preview_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_contact_preview_test_expect(callable $callback, $fragment, $message)
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    red_theme_contact_preview_test_assert(
        $caught instanceof Throwable
            && ($fragment === '' || strpos($caught->getMessage(), $fragment) !== false),
        $message
    );
}

function red_theme_contact_preview_test_rows()
{
    $formTemplate =
        '#|question=|name=name|type=textfield|required=true|displayname=Enter your Full Name:|initialvalue=;' . "\r\n" .
        '#|question=|name=title|type=textfield|required=false|displayname=Enter your Title:|initialvalue=;' . "\r\n" .
        '#|question=|name=email|type=textfield|required=true|displayname=Enter your e-mail:|initialvalue=;' . "\r\n" .
        '#|question=|name=telephone|type=textfield|required=true|displayname=Enter your Telephone:|initialvalue=;' . "\r\n" .
        '#|question=|name=fax|type=textfield|required=false|displayname=Enter your Fax:|initialvalue=;' . "\r\n" .
        '#|question=|name=message|type=textarea|required=false|displayname=Enter your Message:|readonly=false|initialvalue=|cols=45|rows=5;' . "\r\n" .
        '#|question=|name=Submit|type=button|displayname=submit';

    return [
        'section' => [
            [
                'RecordID' => '24',
                'Sections' => 'contacto',
                'Title' => 'contacto',
                'Layout' => 'index-1',
                'Description' => '',
                'Tags' => '',
                'Language' => 'sp',
                'Active' => 'Y',
            ],
        ],
        'form' => [
            [
                'ArticleRecordID' => '459269660',
                'ArticleAlias' => 'contact',
                'ArticleTitle' => 'Contact',
                'Component' => 'Form',
                'SectionPosition' => '1',
                'SectionPositionOrder' => '1',
                'Sections' => 'contacto',
                'Language' => 'sp',
                'Active' => 'Y',
                'FormRecordID' => '93039112',
                'RefID' => '459269660',
                'FormAlias' => 'contact',
                'FormTitle' => 'Contact',
                'FormType' => 'Contact',
                'FormTemplate' => $formTemplate,
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
    $queries = red_theme_contact_preview_query_inventory();
    red_theme_contact_preview_test_assert(count($queries) === 4, 'query inventory contains exactly four reads');
    red_theme_contact_preview_test_assert(
        red_theme_contact_preview_assert_query_inventory($queries),
        'fixed query inventory passes its safety boundary'
    );
    foreach ($queries as $id => $sql) {
        red_theme_contact_preview_test_assert(
            preg_match('/\ASELECT\s/i', ltrim($sql)) === 1,
            $id . ' is a SELECT'
        );
        red_theme_contact_preview_test_assert(
            preg_match(
                '/\b(?:ALTER|CREATE|DELETE|DROP|INSERT|REPLACE|TRUNCATE|UPDATE)\b/i',
                $sql
            ) !== 1,
            $id . ' contains no write operation'
        );
    }
    $tamperedQueries = $queries;
    $tamperedQueries['fifth-read'] = 'SELECT RecordID FROM RED_Sections LIMIT 1';
    red_theme_contact_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_contact_preview_assert_query_inventory($tamperedQueries);
        },
        'exactly four',
        'a fifth query is rejected'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['contact-section'] = 'UPDATE RED_Sections SET Active=\'N\' WHERE RecordID=24';
    red_theme_contact_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_contact_preview_assert_query_inventory($tamperedQueries);
        },
        'single fixed SELECT',
        'a write-shaped query is rejected'
    );

    $_GET = ['contact_preview_sentinel' => 'get'];
    $_POST = ['contact_preview_sentinel' => 'post'];
    $_SESSION = ['contact_preview_sentinel' => 'session'];
    $rows = red_theme_contact_preview_test_rows();
    $first = red_theme_contact_preview_render_rows($rows, $repositoryRoot, 0);
    $second = red_theme_contact_preview_render_rows($rows, $repositoryRoot, 0);
    red_theme_contact_preview_test_assert(
        $first['html'] === $second['html'],
        'two Contact fixture-row renders are byte-for-byte deterministic'
    );
    red_theme_contact_preview_test_assert(
        $first['sha256'] === $second['sha256'],
        'two Contact fixture-row renders have the same digest'
    );
    red_theme_contact_preview_test_assert(
        $first['sha256'] === '5122860f249594cacbdfe353903f604729eeee106dae286e2f832fb084552b83',
        'Contact fixture-row digest matches reviewed real-content output'
    );
    red_theme_contact_preview_test_assert(
        $first['bytes'] === strlen($first['html']) && $first['bytes'] === 13964,
        'Contact byte report matches the complete output'
    );
    red_theme_contact_preview_test_assert(
        $_GET === ['contact_preview_sentinel' => 'get']
            && $_POST === ['contact_preview_sentinel' => 'post']
            && $_SESSION === ['contact_preview_sentinel' => 'session'],
        'Contact preview rendering does not read or mutate request/session sentinels'
    );
    red_theme_contact_preview_test_assert(
        $first['scope'] === red_theme_contact_preview_scope(0),
        'fixture-row test reports zero database and session side effects'
    );
    red_theme_contact_preview_test_assert(
        $first['layout'] === 'index-1'
            && $first['contract']['document']['language'] === 'es'
            && $first['contract']['document']['title'] === 'Contact — contacto',
        'real Contact values map into the expected document and layout'
    );
    red_theme_contact_preview_test_assert(
        count($first['contract']['page']['slots'][1]) === 1
            && $first['contract']['page']['slots'][1][0]['component'] === 'Form'
            && $first['contract']['page']['slots'][2] === []
            && $first['contract']['page']['slots'][3] === []
            && $first['contract']['page']['slots'][4] === [],
        'the paired Form maps only into real SectionPosition 1'
    );
    $portableFields = $first['contract']['page']['slots'][1][0]['data']['fields'];
    red_theme_contact_preview_test_assert(
        array_column($portableFields, 'name') === ['name', 'title', 'email', 'telephone', 'fax', 'message'],
        'all six expected Contact field names map in source order'
    );
    red_theme_contact_preview_test_assert(
        array_column($portableFields, 'type') === ['text', 'text', 'email', 'tel', 'tel', 'textarea'],
        'legacy Contact field types map to the fixed portable allowlist'
    );
    red_theme_contact_preview_test_assert(
        strpos($first['html'], 'Read-only Contact data preview') !== false
            && strpos($first['html'], 'Isolated fixture preview') === false
            && substr_count($first['html'], '<form') === 1
            && substr_count($first['html'], '<script') === 0
            && strpos($first['html'], '<button type="button" aria-disabled="true">submit</button>') !== false,
        'Contact output is visibly isolated, scriptless, and non-submitting'
    );
    $sourceJson = json_encode($first['source'], JSON_UNESCAPED_SLASHES);
    red_theme_contact_preview_test_assert(
        is_string($sourceJson)
            && strpos($sourceJson, 'FormTemplate') === false
            && strpos($sourceJson, 'SELECT ') === false
            && $first['source']['form']['fieldCount'] === 6,
        'source metadata reports provenance without raw templates or SQL'
    );
    red_theme_contact_preview_test_assert(
        $first['source']['fallbacks'] === [
            'siteTitle' => 'section.Title',
            'description' => 'form.Title',
            'footer' => 'section.Title',
        ],
        'empty live settings use explicit real Contact fallbacks'
    );
    $fixturePreview = red_theme_preview_render('starter-reference', $repositoryRoot);
    red_theme_contact_preview_test_assert(
        $fixturePreview['sha256'] === '106c984a77643cb0a8b4f0154a59e0558b1d082ff90267d5cbd7e785bbd02a7d',
        'the original deterministic fixture output remains byte-for-byte unchanged'
    );
    $runtime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_contact_preview_test_assert(
        $runtime['themeId'] === 'legacy-bootstrap' && !empty($runtime['resolution']['usedFallback']),
        'live compatibility runtime still refuses standard-theme execution'
    );

    $fourReadResult = red_theme_contact_preview_render_rows($rows, $repositoryRoot, 4);
    red_theme_contact_preview_test_assert(
        $fourReadResult['scope'] === red_theme_contact_preview_scope(4)
            && $fourReadResult['html'] === $first['html'],
        'real-read scope reports exactly four reads without changing rendered output'
    );
    red_theme_contact_preview_test_expect(
        function () use ($rows, $repositoryRoot) {
            red_theme_contact_preview_render_rows($rows, $repositoryRoot, 1);
        },
        'zero or four',
        'an ambiguous database-read count is rejected'
    );
    red_theme_contact_preview_test_expect(
        function () {
            red_theme_preview_scope(
                [
                    'databaseReads' => 4,
                    'databaseWrites' => 1,
                    'sessionReads' => 0,
                    'sessionWrites' => 0,
                    'liveRuntimeChanges' => 0,
                ]
            );
        },
        'database reads only',
        'any claimed database write is rejected by the isolated scope'
    );
    $validation = red_theme_preview_validate_reference_theme('starter-reference', $repositoryRoot);
    red_theme_contact_preview_test_expect(
        function () use ($validation, $first) {
            red_theme_preview_render_prepared_contract(
                $validation,
                $first['contract'],
                'admin-preview',
                red_theme_contact_preview_scope(0)
            );
        },
        'unsupported',
        'a session/admin preview mode cannot enter this renderer'
    );

    $tampered = $rows;
    $tampered['section'] = [];
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly one',
        'a missing Contact section is rejected'
    );
    $tampered = $rows;
    $tampered['section'][] = $tampered['section'][0];
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly one',
        'multiple Contact sections are rejected'
    );
    $tampered = $rows;
    $tampered['section'][0]['Unexpected'] = 'value';
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'exact selected columns',
        'an extra Section column is rejected'
    );
    foreach ([
        'RecordID' => '25',
        'Sections' => 'other',
        'Layout' => 'index-2',
        'Language' => 'en',
        'Active' => 'N',
    ] as $key => $value) {
        $tampered = $rows;
        $tampered['section'][0][$key] = $value;
        red_theme_contact_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
            },
            'fixed active canary',
            'tampered Contact Section ' . $key . ' is rejected'
        );
    }

    $tampered = $rows;
    $tampered['form'][] = $tampered['form'][0];
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly one',
        'multiple paired Contact Forms are rejected'
    );
    foreach ([
        'RefID' => '459269661',
        'ArticleAlias' => 'other',
        'Component' => 'Article',
        'SectionPosition' => '2',
        'SectionPositionOrder' => '2',
        'FormAlias' => 'other',
        'FormType' => 'Login',
    ] as $key => $value) {
        $tampered = $rows;
        $tampered['form'][0][$key] = $value;
        red_theme_contact_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
            },
            'fixed paired canary',
            'tampered paired Form ' . $key . ' is rejected'
        );
    }
    $templateTamperCases = [
        [
            'search' => '|required=true',
            'replace' => '|required',
            'fragment' => 'malformed property',
            'message' => 'a malformed legacy Form property is rejected',
        ],
        [
            'search' => 'type=textfield',
            'replace' => 'type=password',
            'fragment' => 'unsupported or unsafe',
            'message' => 'an unsupported legacy Form field type is rejected',
        ],
        [
            'search' => 'initialvalue=;',
            'replace' => 'initialvalue=injected;',
            'fragment' => 'unsupported or unsafe',
            'message' => 'a legacy Form default value is rejected',
        ],
        [
            'search' => '|initialvalue=;',
            'replace' => '|initialvalue=|endpoint=/send;',
            'fragment' => 'unknown or duplicated',
            'message' => 'a legacy Form endpoint property is rejected',
        ],
        [
            'search' => 'readonly=false',
            'replace' => 'readonly=true',
            'fragment' => 'unsupported or unsafe',
            'message' => 'a read-only legacy Form field cannot silently change behavior',
        ],
    ];
    foreach ($templateTamperCases as $case) {
        $tampered = $rows;
        $tampered['form'][0]['FormTemplate'] = preg_replace(
            '/' . preg_quote($case['search'], '/') . '/',
            $case['replace'],
            $tampered['form'][0]['FormTemplate'],
            1
        );
        red_theme_contact_preview_test_expect(
            function () use ($tampered, $repositoryRoot) {
                red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
            },
            $case['fragment'],
            $case['message']
        );
    }

    $tampered = $rows;
    $tampered['navigation'][1]['Link'] = 'javascript:alert(1)';
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'fragment, root-relative URL, or HTTPS URL',
        'an unsafe navigation URL is rejected'
    );
    $tampered = $rows;
    $tampered['navigation'][1]['MenuOrder'] = '1';
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'fixed root-menu contract',
        'non-ascending navigation order is rejected'
    );
    $tampered = $rows;
    $tampered['navigation'][1]['RecordID'] = '1';
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'unique',
        'duplicated navigation RecordIDs are rejected'
    );
    $tampered = $rows;
    $tampered['navigation'][1]['Link'] = '/other/';
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'Home/Contact canary',
        'a missing current Contact route is rejected'
    );
    $tampered = $rows;
    $tampered['navigation'][] = $tampered['navigation'][1];
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'exactly the active Spanish',
        'an unexpected third root-menu row is rejected'
    );

    $tampered = $rows;
    $tampered['settings'][0]['Item'] = 'Website_Header';
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'outside the fixed allowlist',
        'a raw Website_Header setting is rejected'
    );
    $tampered = $rows;
    $tampered['settings'][1]['Item'] = 'Website_Footer';
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'duplicated',
        'a duplicated allowed setting is rejected'
    );
    $tampered = $rows;
    $tampered['settings'][0]['Unexpected'] = 'value';
    red_theme_contact_preview_test_expect(
        function () use ($tampered, $repositoryRoot) {
            red_theme_contact_preview_render_rows($tampered, $repositoryRoot);
        },
        'exact selected columns',
        'an extra setting column is rejected'
    );

    $withContent = $rows;
    $withContent['section'][0]['Description'] = '<p>Reach the Contact team.</p>';
    $withContent['settings'][0]['Content'] = '<p>Real footer</p>';
    $withContent['settings'][1]['Content'] = '<strong>Real site title</strong>';
    $contentResult = red_theme_contact_preview_render_rows($withContent, $repositoryRoot);
    red_theme_contact_preview_test_assert(
        $contentResult['contract']['document']['description'] === 'Reach the Contact team.'
            && $contentResult['contract']['regions']['header']['siteTitle'] === 'Real site title'
            && $contentResult['contract']['regions']['footer']['copyright'] === 'Real footer',
        'allowed rich legacy values are reduced to bounded plain text'
    );
    red_theme_contact_preview_test_assert(
        $contentResult['source']['fallbacks'] === [
            'siteTitle' => 'advanced.Website_Title',
            'description' => 'section.Description',
            'footer' => 'advanced.Website_Footer',
        ],
        'non-empty allowed values record their exact source instead of fallbacks'
    );
    red_theme_contact_preview_test_assert(
        strpos($contentResult['html'], '<strong>Real site title</strong>') === false
            && strpos($contentResult['html'], '<p>Real footer</p>') === false
            && strpos($contentResult['html'], 'Real site title') !== false,
        'legacy setting markup cannot cross the portable view-data boundary'
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

echo 'Theme Contact preview self-test passed: ' . $assertions . " assertions.\n";
