<?php
/**
 * Dependency-free query, relationship, template, tamper, determinism, and
 * isolation tests for the fixed display-only selected Contact preview.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_preview_selected_contact_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_selected_contact_preview_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_selected_contact_preview_test_expect(callable $callback, $fragment, $message)
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    red_theme_selected_contact_preview_test_assert(
        $caught instanceof Throwable
            && ($fragment === '' || strpos($caught->getMessage(), $fragment) !== false),
        $message
    );
}

function red_theme_selected_contact_preview_test_template()
{
    return
        '#|question=|name=name|type=textfield|required=true|displayname=Enter your Full Name:|initialvalue=;' . "\r\n" .
        '#|question=|name=title|type=textfield|required=false|displayname=Enter your Title:|initialvalue=;' . "\r\n" .
        '#|question=|name=email|type=textfield|required=true|displayname=Enter your e-mail:|initialvalue=;' . "\r\n" .
        '#|question=|name=telephone|type=textfield|required=true|displayname=Enter your Telephone:|initialvalue=;' . "\r\n" .
        '#|question=|name=fax|type=textfield|required=false|displayname=Enter your Fax:|initialvalue=;' . "\r\n" .
        '#|question=|name=message|type=textarea|required=false|displayname=Enter your Message:|readonly=false|initialvalue=|cols=45|rows=5;' . "\r\n" .
        '#|question=|name=Submit|type=button|displayname=submit';
}

function red_theme_selected_contact_preview_test_rows()
{
    return [
        'articleFormSection' => [[
            'ArticleRecordID' => '459269660',
            'ArticleTitle' => 'Contact',
            'ArticleAlias' => 'contact',
            'Component' => 'Form',
            'ArticleSection' => 'contacto',
            'Categories' => '',
            'SubCategories' => '',
            'ArticleLayout' => 'index-1',
            'SectionPosition' => '1',
            'SectionPositionOrder' => '1',
            'PagePosition' => '1',
            'PagePositionOrder' => '0',
            'ShortDesc' => '',
            'Link' => '',
            'NewWindow' => '',
            'ArticleLanguage' => 'sp',
            'ArticleActive' => 'Y',
            'StartDate' => '1970-01-01 00:00:00',
            'ExpDate' => '9999-12-31 23:59:59',
            'FormRecordID' => '93039112',
            'FormRefID' => '459269660',
            'FormTitle' => 'Contact',
            'FormAlias' => 'contact',
            'FormType' => 'Contact',
            'FormTemplate' => red_theme_selected_contact_preview_test_template(),
            'SectionRecordID' => '24',
            'SectionAlias' => 'contacto',
            'SectionTitle' => 'contacto',
            'SectionLayout' => 'index-1',
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

function red_theme_selected_contact_preview_test_section_rows()
{
    $selected = red_theme_selected_contact_preview_test_rows();
    $joined = $selected['articleFormSection'][0];

    return [
        'section' => [[
            'RecordID' => '24',
            'Sections' => 'contacto',
            'Title' => 'contacto',
            'Layout' => 'index-1',
            'Description' => '',
            'Tags' => '',
            'Language' => 'sp',
            'Active' => 'Y',
        ]],
        'form' => [[
            'ArticleRecordID' => $joined['ArticleRecordID'],
            'ArticleAlias' => $joined['ArticleAlias'],
            'ArticleTitle' => $joined['ArticleTitle'],
            'Component' => $joined['Component'],
            'SectionPosition' => $joined['SectionPosition'],
            'SectionPositionOrder' => $joined['SectionPositionOrder'],
            'Sections' => $joined['ArticleSection'],
            'Language' => $joined['ArticleLanguage'],
            'Active' => $joined['ArticleActive'],
            'FormRecordID' => $joined['FormRecordID'],
            'RefID' => $joined['FormRefID'],
            'FormAlias' => $joined['FormAlias'],
            'FormTitle' => $joined['FormTitle'],
            'FormType' => $joined['FormType'],
            'FormTemplate' => $joined['FormTemplate'],
        ]],
        'navigation' => $selected['navigation'],
        'settings' => $selected['settings'],
    ];
}

function red_theme_selected_contact_preview_test_contains_key($value, array $forbidden)
{
    if (!is_array($value)) {
        return false;
    }
    foreach ($value as $key => $child) {
        if (is_string($key) && isset($forbidden[strtolower($key)])) {
            return true;
        }
        if (red_theme_selected_contact_preview_test_contains_key($child, $forbidden)) {
            return true;
        }
    }

    return false;
}

class RedThemeSelectedContactPreviewTestResult
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

class RedThemeSelectedContactPreviewTestConnection
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
        $inventory = red_theme_selected_contact_preview_query_inventory();
        $mapping = [
            'selected-contact-article-form-section' => 'articleFormSection',
            'selected-contact-navigation' => 'navigation',
            'selected-contact-settings' => 'settings',
        ];
        foreach ($inventory as $id => $expectedSql) {
            if ($sql === $expectedSql) {
                if ($this->failId === $id) {
                    return false;
                }
                return new RedThemeSelectedContactPreviewTestResult($this->rows[$mapping[$id]]);
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

try {
    $queries = red_theme_selected_contact_preview_query_inventory();
    red_theme_selected_contact_preview_test_assert(
        array_keys($queries) === [
            'selected-contact-article-form-section',
            'selected-contact-navigation',
            'selected-contact-settings',
        ],
        'query inventory contains exactly the three fixed reads in order'
    );
    red_theme_selected_contact_preview_test_assert(
        red_theme_selected_contact_preview_assert_query_inventory($queries),
        'fixed query inventory passes its exact safety boundary'
    );
    foreach ($queries as $id => $sql) {
        red_theme_selected_contact_preview_test_assert(
            preg_match('/\ASELECT\s/i', ltrim($sql)) === 1
                && preg_match(
                    '/\b(?:ALTER|CREATE|DELETE|DROP|INSERT|REPLACE|TRUNCATE|UPDATE)\b/i',
                    $sql
                ) !== 1,
            $id . ' is a read-only SELECT'
        );
    }
    red_theme_selected_contact_preview_test_assert(
        strpos($queries['selected-contact-article-form-section'], 'f.Subject') === false
            && strpos($queries['selected-contact-article-form-section'], 'f.Submitter') === false
            && strpos($queries['selected-contact-article-form-section'], 'f.Destinatary') === false
            && strpos($queries['selected-contact-article-form-section'], 'f.Response') === false
            && strpos($queries['selected-contact-article-form-section'], 'f.TableName') === false,
        'joined provider query excludes every operational Form column'
    );

    $tamperedQueries = $queries;
    $tamperedQueries['fourth-read'] = 'SELECT RecordID FROM RED_Articles LIMIT 1';
    red_theme_selected_contact_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_selected_contact_preview_assert_query_inventory($tamperedQueries);
        },
        'exactly three',
        'a fourth query is rejected'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['selected-contact-article-form-section'] =
        "UPDATE RED_Articles SET Active='N' WHERE RecordID=459269660";
    red_theme_selected_contact_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_selected_contact_preview_assert_query_inventory($tamperedQueries);
        },
        'fixed SELECT',
        'a write-shaped query is rejected'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['selected-contact-settings'] = 'SELECT Item FROM RED_Admin LIMIT 1';
    red_theme_selected_contact_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_selected_contact_preview_assert_query_inventory($tamperedQueries);
        },
        'fixed SELECT',
        'an unapproved table cannot replace a fixed read'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['selected-contact-navigation'] = str_replace(
        'ORDER BY MenuOrder ASC, RecordID ASC',
        'ORDER BY RecordID DESC',
        $tamperedQueries['selected-contact-navigation']
    );
    red_theme_selected_contact_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_selected_contact_preview_assert_query_inventory($tamperedQueries);
        },
        'fixed SELECT',
        'query ordering drift is rejected'
    );

    $rows = red_theme_selected_contact_preview_test_rows();
    $canary = red_theme_selected_contact_preview_canary();
    red_theme_selected_contact_preview_test_assert(
        strlen($rows['articleFormSection'][0]['ShortDesc']) === $canary['summaryBytes']
            && hash('sha256', $rows['articleFormSection'][0]['ShortDesc']) === $canary['summarySha256'],
        'synthetic selected Contact summary matches the exact live canary'
    );
    red_theme_selected_contact_preview_test_assert(
        strlen($rows['articleFormSection'][0]['FormTemplate']) === $canary['templateBytes']
            && hash('sha256', $rows['articleFormSection'][0]['FormTemplate']) === $canary['templateSha256'],
        'synthetic selected Contact template matches the exact live canary'
    );

    $connection = new RedThemeSelectedContactPreviewTestConnection($rows);
    $read = red_theme_selected_contact_preview_read_rows($connection);
    red_theme_selected_contact_preview_test_assert(
        $connection->queries === array_merge(['START TRANSACTION READ ONLY'], array_values($queries)),
        'provider opens one read-only transaction and executes only the three fixed reads in order'
    );
    red_theme_selected_contact_preview_test_assert(
        $connection->commits === 1 && $connection->rollbacks === 0,
        'successful fixed reads close with one commit and no rollback'
    );
    red_theme_selected_contact_preview_test_assert(
        $read['rows'] === $rows && $read['scope'] === red_theme_selected_contact_preview_scope(3),
        'read boundary returns only fixed rows plus exact three-read scope'
    );
    $failingConnection = new RedThemeSelectedContactPreviewTestConnection($rows);
    $failingConnection->failId = 'selected-contact-navigation';
    red_theme_selected_contact_preview_test_expect(
        function () use ($failingConnection) {
            red_theme_selected_contact_preview_read_rows($failingConnection);
        },
        'fixed read',
        'a failed fixed query aborts the provider'
    );
    red_theme_selected_contact_preview_test_assert(
        $failingConnection->commits === 0 && $failingConnection->rollbacks === 1,
        'a failed fixed query rolls back the read-only transaction'
    );

    $_GET = ['selected_contact_preview_sentinel' => 'get'];
    $_POST = ['selected_contact_preview_sentinel' => 'post'];
    $_SESSION = ['selected_contact_preview_sentinel' => 'session'];
    $prepared = red_theme_selected_contact_preview_prepare_rows($rows);
    $first = red_theme_selected_contact_preview_render_rows($rows, $repositoryRoot, 0);
    $second = red_theme_selected_contact_preview_render_rows($rows, $repositoryRoot, 0);
    red_theme_selected_contact_preview_test_assert(
        $first['html'] === $second['html'] && $first['sha256'] === $second['sha256'],
        'two selected Contact renders are byte-for-byte deterministic'
    );
    red_theme_selected_contact_preview_test_assert(
        $first['bytes'] === 14153
            && $first['bytes'] === strlen($first['html'])
            && $first['sha256'] === 'f022c36609523c4e59c4594634e13bb25b33f185f2bd031ddf71949d341cc0a0'
            && $first['sha256'] === hash('sha256', $first['html']),
        'render locks the exact selected Contact byte count and digest'
    );
    red_theme_selected_contact_preview_test_assert(
        $first['layout'] === 'index-1'
            && $first['scope'] === red_theme_selected_contact_preview_scope(0)
            && $first['source']['mode'] === 'read-only-selected-contact-preview',
        'render stays in the fixed selected Contact mode and zero-read synthetic scope'
    );
    red_theme_selected_contact_preview_test_assert(
        $prepared['source']['queryIds'] === array_keys($queries)
            && $prepared['source']['rowCounts'] === [
                'articleFormSection' => 1,
                'navigation' => 2,
                'settings' => 2,
            ],
        'source metadata exposes only fixed query ids and row counts'
    );
    red_theme_selected_contact_preview_test_assert(
        $prepared['source']['form'] === [
            'type' => 'Contact',
            'templateBytes' => 686,
            'templateSha256' => '5f84ca1244b3c9a66884783469ef6ee2bed4d469f2a75d73a337acc72c43d1a1',
            'fieldCount' => 6,
            'fieldNames' => ['name', 'title', 'email', 'telephone', 'fax', 'message'],
            'buttonType' => 'button',
            'submissionConnected' => false,
        ],
        'source metadata locks the display-only Contact shape without the raw template'
    );
    $fields = $prepared['fixture']['page']['slots']['1'][0]['data']['fields'];
    red_theme_selected_contact_preview_test_assert(
        array_column($fields, 'name') === ['name', 'title', 'email', 'telephone', 'fax', 'message']
            && array_column($fields, 'type') === ['text', 'text', 'email', 'tel', 'tel', 'textarea']
            && array_column($fields, 'required') === [true, false, true, true, false, false],
        'portable contract reconstructs exactly the six empty Contact fields in source order'
    );
    red_theme_selected_contact_preview_test_assert(
        $prepared['fixture']['page']['slots']['1'][0]['data']['submitLabel'] === 'submit'
            && $prepared['fixture']['page']['slots']['2'] === []
            && $prepared['fixture']['page']['slots']['3'] === []
            && $prepared['fixture']['page']['slots']['4'] === [],
        'portable selected layout contains only the inert final-button Form in position one'
    );
    red_theme_selected_contact_preview_test_assert(
        $prepared['fixture']['page']['breadcrumb'] === [
            ['label' => 'Inicio', 'url' => '/'],
            ['label' => 'contacto', 'url' => '/contacto/'],
            ['label' => 'Contact', 'url' => ''],
        ]
            && array_column($prepared['fixture']['regions']['navigation']['items'], 'current') === [false, true],
        'selected route reconstructs its exact parent breadcrumb and current root navigation state'
    );
    red_theme_selected_contact_preview_test_assert(
        !red_theme_selected_contact_preview_test_contains_key(
            [
                'formData' => $prepared['fixture']['page']['slots']['1'][0]['data'],
                'formSource' => $prepared['source']['form'],
            ],
            array_fill_keys([
                'action', 'endpoint', 'payload', 'validation', 'response', 'request', 'session',
                'method', 'defaultvalue', 'initialvalue', 'tablename', 'destinatary', 'submitter',
            ], true)
        ),
        'prepared input contains no operational, request, session, or default-value key'
    );
    red_theme_selected_contact_preview_test_assert(
        strpos(json_encode($prepared['source']), '#|question=') === false
            && strpos(json_encode($prepared['source']), '/bin/contact.php') === false,
        'source report redacts the raw template and legacy endpoint'
    );
    red_theme_selected_contact_preview_test_assert(
        substr_count($first['html'], '<form') === 1
            && strpos($first['html'], '<form aria-describedby="preview-form-note">') !== false
            && strpos($first['html'], '<form action=') === false
            && strpos($first['html'], '<form method=') === false,
        'rendered Form has no action or method'
    );
    red_theme_selected_contact_preview_test_assert(
        strpos($first['html'], 'name="name"') !== false
            && strpos($first['html'], 'autocomplete="name"') !== false
            && strpos($first['html'], 'name="email"') !== false
            && strpos($first['html'], 'type="email"') !== false
            && strpos($first['html'], 'name="telephone"') !== false
            && strpos($first['html'], 'type="tel"') !== false
            && strpos($first['html'], 'name="message"') !== false
            && substr_count($first['html'], 'required') === 3,
        'rendered Contact fields retain exact types, autocomplete hints, and required state'
    );
    red_theme_selected_contact_preview_test_assert(
        strpos($first['html'], '<button type="button" aria-disabled="true">submit</button>') !== false
            && strpos($first['html'], 'type="submit"') === false
            && strpos($first['html'], '/bin/contact.php') === false
            && strpos($first['html'], '<script') === false
            && strpos($first['html'], 'onsubmit=') === false,
        'rendered final button is inert and no endpoint or executable submit behavior exists'
    );
    red_theme_selected_contact_preview_test_assert(
        preg_match('/<input\b[^>]*\bvalue\s*=/i', $first['html']) !== 1
            && preg_match('/<textarea\b[^>]*>[^<]+<\/textarea>/i', $first['html']) !== 1,
        'rendered fields carry no default or prefilled value'
    );
    red_theme_selected_contact_preview_test_assert(
        strpos(
            $first['html'],
            'Read-only selected Contact data preview — three fixed database reads; the form remains display-only; no session, activation, or live website change.'
        ) !== false,
        'document renders the exact selected Contact read-only notice'
    );
    red_theme_selected_contact_preview_test_assert(
        $_GET === ['selected_contact_preview_sentinel' => 'get']
            && $_POST === ['selected_contact_preview_sentinel' => 'post']
            && $_SESSION === ['selected_contact_preview_sentinel' => 'session'],
        'rendering neither reads nor mutates request/session sentinels'
    );

    red_theme_selected_contact_preview_test_expect(
        function () use ($rows, $repositoryRoot) {
            red_theme_selected_contact_preview_render_rows($rows, $repositoryRoot, 2);
        },
        'zero or three',
        'an unapproved database-read count is rejected'
    );

    $tamperCases = [
        'extra selected column' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['Endpoint'] = '/bin/contact.php';
        },
        'missing joined row' => static function (&$candidate) {
            $candidate['articleFormSection'] = [];
        },
        'Article RecordID drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ArticleRecordID'] = '1';
        },
        'Article alias drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ArticleAlias'] = 'contact-us';
        },
        'Article component drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['Component'] = 'Article';
        },
        'Article section drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ArticleSection'] = 'administracion';
        },
        'Article layout drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ArticleLayout'] = 'index-2';
        },
        'Article page position drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['PagePosition'] = '2';
        },
        'Article section order drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['SectionPositionOrder'] = '2';
        },
        'Article summary drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ShortDesc'] = 'Contact us';
        },
        'Article link drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['Link'] = '/contacto/';
        },
        'Article expiry drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ExpDate'] = '2030-01-01 00:00:00';
        },
        'Form relationship drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormRefID'] = '1';
        },
        'Form RecordID drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormRecordID'] = '1';
        },
        'Form type drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormType'] = 'Login';
        },
        'parent Section drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['SectionRecordID'] = '25';
        },
        'parent Section layout drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['SectionLayout'] = 'index-3';
        },
        'name required drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormTemplate'] = str_replace(
                'name=name|type=textfield|required=true',
                'name=name|type=textfield|required=false',
                $candidate['articleFormSection'][0]['FormTemplate']
            );
        },
        'email type drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormTemplate'] = str_replace(
                'name=email|type=textfield',
                'name=email|type=password',
                $candidate['articleFormSection'][0]['FormTemplate']
            );
        },
        'prefilled telephone drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormTemplate'] = str_replace(
                'name=telephone|type=textfield|required=true|displayname=Enter your Telephone:|initialvalue=',
                'name=telephone|type=textfield|required=true|displayname=Enter your Telephone:|initialvalue=555',
                $candidate['articleFormSection'][0]['FormTemplate']
            );
        },
        'template endpoint property' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormTemplate'] = str_replace(
                'displayname=submit',
                'displayname=submit|endpoint=/bin/contact.php',
                $candidate['articleFormSection'][0]['FormTemplate']
            );
        },
        'template eighth record' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormTemplate'] .=
                ';#|question=|name=extra|type=textfield|required=false|displayname=Extra:|initialvalue=';
        },
        'navigation order drift' => static function (&$candidate) {
            $candidate['navigation'] = array_reverse($candidate['navigation']);
        },
        'setting duplication' => static function (&$candidate) {
            $candidate['settings'][1]['Item'] = 'Website_Footer';
        },
    ];
    foreach ($tamperCases as $label => $mutator) {
        $tampered = $rows;
        $mutator($tampered);
        red_theme_selected_contact_preview_test_expect(
            function () use ($tampered) {
                red_theme_selected_contact_preview_prepare_rows($tampered);
            },
            '',
            $label . ' fails closed'
        );
    }

    $fixturePreview = red_theme_preview_render('starter-reference', $repositoryRoot);
    red_theme_selected_contact_preview_test_assert(
        $fixturePreview['bytes'] === 19507
            && $fixturePreview['sha256'] === '106c984a77643cb0a8b4f0154a59e0558b1d082ff90267d5cbd7e785bbd02a7d',
        'existing isolated image fixture remains byte-identical'
    );
    $sectionContact = red_theme_contact_preview_render_rows(
        red_theme_selected_contact_preview_test_section_rows(),
        $repositoryRoot,
        0
    );
    red_theme_selected_contact_preview_test_assert(
        $sectionContact['bytes'] === 13964
            && $sectionContact['sha256'] === '5122860f249594cacbdfe353903f604729eeee106dae286e2f832fb084552b83',
        'existing Contact Section provider remains byte-identical'
    );
    $runtime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_selected_contact_preview_test_assert(
        $runtime['themeId'] === 'legacy-bootstrap'
            && !empty($runtime['resolution']['usedFallback']),
        'live runtime still hard-falls back from the standard starter'
    );
    $manifest = json_decode(
        file_get_contents($repositoryRoot . '/themes/starter-reference/theme.json'),
        true
    );
    red_theme_selected_contact_preview_test_assert(
        is_array($manifest)
            && $manifest['id'] === 'starter-reference'
            && $manifest['version'] === '1.2.0',
        'activation-ready manifest retains the selected Contact preview provider'
    );

    echo 'Theme selected Contact preview self-test passed (' . $assertions . " assertions).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $_GET = $previousGet;
    $_POST = $previousPost;
    if ($sessionWasSet) {
        $_SESSION = $previousSession;
    } else {
        unset($_SESSION);
    }
}
