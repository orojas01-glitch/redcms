<?php
/**
 * Dependency-free query, relationship, template, tamper, determinism, and
 * isolation tests for the fixed display-only selected Login preview provider.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_preview_login_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_login_preview_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_login_preview_test_expect(callable $callback, $fragment, $message)
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    red_theme_login_preview_test_assert(
        $caught instanceof Throwable
            && ($fragment === '' || strpos($caught->getMessage(), $fragment) !== false),
        $message
    );
}

function red_theme_login_preview_test_rows()
{
    $template =
        "#|question=|name=username|type=textfield|required=true|displayname=Username:|initialvalue=;\r\n" .
        "#|question=|name=password|type=password|required=true|displayname=Password:|initialvalue=;\r\n" .
        '#|question=|name=Submit|type=button|displayname=submit';

    return [
        'articleFormSection' => [[
            'ArticleRecordID' => '966111194',
            'ArticleTitle' => 'Login',
            'ArticleAlias' => 'login',
            'Component' => 'Form',
            'ArticleSection' => 'administracion',
            'Categories' => '',
            'SubCategories' => '',
            'ArticleLayout' => 'index-2',
            'SectionPosition' => '1',
            'SectionPositionOrder' => '1',
            'PagePosition' => '1',
            'PagePositionOrder' => '0',
            'ShortDesc' => 'Login now!',
            'Link' => '',
            'NewWindow' => '',
            'ArticleLanguage' => 'sp',
            'ArticleActive' => 'Y',
            'StartDate' => '2012-06-01 00:00:00',
            'ExpDate' => '0000-00-00 00:00:00',
            'FormRecordID' => '884542279',
            'FormRefID' => '966111194',
            'FormTitle' => 'Login',
            'FormAlias' => 'login',
            'FormType' => 'Login',
            'FormTemplate' => $template,
            'SectionRecordID' => '25',
            'SectionAlias' => 'administracion',
            'SectionTitle' => 'administracion',
            'SectionLayout' => 'index-3',
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

function red_theme_login_preview_test_contains_key($value, array $forbidden)
{
    if (!is_array($value)) {
        return false;
    }
    foreach ($value as $key => $child) {
        if (is_string($key) && isset($forbidden[strtolower($key)])) {
            return true;
        }
        if (red_theme_login_preview_test_contains_key($child, $forbidden)) {
            return true;
        }
    }

    return false;
}

class RedThemeLoginPreviewTestResult
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

class RedThemeLoginPreviewTestConnection
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
        $inventory = red_theme_login_preview_query_inventory();
        $mapping = [
            'login-article-form-section' => 'articleFormSection',
            'login-navigation' => 'navigation',
            'login-settings' => 'settings',
        ];
        foreach ($inventory as $id => $expectedSql) {
            if ($sql === $expectedSql) {
                if ($this->failId === $id) {
                    return false;
                }
                return new RedThemeLoginPreviewTestResult($this->rows[$mapping[$id]]);
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
    $queries = red_theme_login_preview_query_inventory();
    red_theme_login_preview_test_assert(
        array_keys($queries) === [
            'login-article-form-section',
            'login-navigation',
            'login-settings',
        ],
        'query inventory contains exactly the three fixed reads in order'
    );
    red_theme_login_preview_test_assert(
        red_theme_login_preview_assert_query_inventory($queries),
        'fixed query inventory passes its exact safety boundary'
    );
    foreach ($queries as $id => $sql) {
        red_theme_login_preview_test_assert(
            preg_match('/\ASELECT\s/i', ltrim($sql)) === 1,
            $id . ' is a SELECT'
        );
        red_theme_login_preview_test_assert(
            preg_match('/\b(?:ALTER|CREATE|DELETE|DROP|INSERT|REPLACE|TRUNCATE|UPDATE)\b/i', $sql) !== 1,
            $id . ' contains no write operation'
        );
    }
    red_theme_login_preview_test_assert(
        strpos($queries['login-article-form-section'], 'f.Subject') === false
            && strpos($queries['login-article-form-section'], 'f.Submitter') === false
            && strpos($queries['login-article-form-section'], 'f.Destinatary') === false
            && strpos($queries['login-article-form-section'], 'f.Response') === false
            && strpos($queries['login-article-form-section'], 'f.TableName') === false,
        'joined provider query excludes every operational Form column'
    );

    $tamperedQueries = $queries;
    $tamperedQueries['fourth-read'] = 'SELECT RecordID FROM RED_Articles LIMIT 1';
    red_theme_login_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_login_preview_assert_query_inventory($tamperedQueries);
        },
        'exactly three',
        'a fourth query is rejected'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['login-article-form-section'] =
        "UPDATE RED_Articles SET Active='N' WHERE RecordID=966111194";
    red_theme_login_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_login_preview_assert_query_inventory($tamperedQueries);
        },
        'fixed SELECT',
        'a write-shaped query is rejected'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['login-settings'] = 'SELECT Item FROM RED_Admin LIMIT 1';
    red_theme_login_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_login_preview_assert_query_inventory($tamperedQueries);
        },
        'fixed SELECT',
        'an unapproved table cannot replace a fixed read'
    );
    $tamperedQueries = $queries;
    $tamperedQueries['login-navigation'] = str_replace(
        'ORDER BY MenuOrder ASC, RecordID ASC',
        'ORDER BY RecordID DESC',
        $tamperedQueries['login-navigation']
    );
    red_theme_login_preview_test_expect(
        function () use ($tamperedQueries) {
            red_theme_login_preview_assert_query_inventory($tamperedQueries);
        },
        'fixed SELECT',
        'query ordering drift is rejected'
    );

    $rows = red_theme_login_preview_test_rows();
    $canary = red_theme_login_preview_canary();
    red_theme_login_preview_test_assert(
        strlen($rows['articleFormSection'][0]['ShortDesc']) === $canary['summaryBytes']
            && hash('sha256', $rows['articleFormSection'][0]['ShortDesc']) === $canary['summarySha256'],
        'synthetic Login summary matches the exact live canary'
    );
    red_theme_login_preview_test_assert(
        strlen($rows['articleFormSection'][0]['FormTemplate']) === $canary['templateBytes']
            && hash('sha256', $rows['articleFormSection'][0]['FormTemplate']) === $canary['templateSha256'],
        'synthetic Login template matches the exact live canary'
    );

    $connection = new RedThemeLoginPreviewTestConnection($rows);
    $read = red_theme_login_preview_read_rows($connection);
    red_theme_login_preview_test_assert(
        $connection->queries === array_merge(['START TRANSACTION READ ONLY'], array_values($queries)),
        'provider opens one read-only transaction and executes only the three fixed reads in order'
    );
    red_theme_login_preview_test_assert(
        $connection->commits === 1 && $connection->rollbacks === 0,
        'successful fixed reads close with one commit and no rollback'
    );
    red_theme_login_preview_test_assert(
        $read['rows'] === $rows && $read['scope'] === red_theme_login_preview_scope(3),
        'read boundary returns only fixed rows plus exact three-read scope'
    );
    $failingConnection = new RedThemeLoginPreviewTestConnection($rows);
    $failingConnection->failId = 'login-navigation';
    red_theme_login_preview_test_expect(
        function () use ($failingConnection) {
            red_theme_login_preview_read_rows($failingConnection);
        },
        'fixed read',
        'a failed fixed query aborts the provider'
    );
    red_theme_login_preview_test_assert(
        $failingConnection->commits === 0 && $failingConnection->rollbacks === 1,
        'a failed fixed query rolls back the read-only transaction'
    );

    $_GET = ['login_preview_sentinel' => 'get'];
    $_POST = ['login_preview_sentinel' => 'post'];
    $_SESSION = ['login_preview_sentinel' => 'session'];
    $prepared = red_theme_login_preview_prepare_rows($rows);
    $first = red_theme_login_preview_render_rows($rows, $repositoryRoot, 0);
    $second = red_theme_login_preview_render_rows($rows, $repositoryRoot, 0);
    red_theme_login_preview_test_assert(
        $first['html'] === $second['html'] && $first['sha256'] === $second['sha256'],
        'two selected Login renders are byte-for-byte deterministic'
    );
    red_theme_login_preview_test_assert(
        $first['bytes'] === 12408
            && $first['bytes'] === strlen($first['html'])
            && $first['sha256'] === '56601b59af8b7eff070beeac1c05a17f0fb60c7737f338b694fe3defc05758fb'
            && $first['sha256'] === hash('sha256', $first['html']),
        'render locks the exact selected Login byte count and digest'
    );
    red_theme_login_preview_test_assert(
        $first['layout'] === 'index-2'
            && $first['scope'] === red_theme_login_preview_scope(0)
            && $first['source']['mode'] === 'read-only-login-preview',
        'render stays in the fixed selected Login mode and zero-read synthetic scope'
    );
    red_theme_login_preview_test_assert(
        $prepared['source']['queryIds'] === array_keys($queries)
            && $prepared['source']['rowCounts'] === [
                'articleFormSection' => 1,
                'navigation' => 2,
                'settings' => 2,
            ],
        'source metadata exposes only fixed query ids and row counts'
    );
    red_theme_login_preview_test_assert(
        $prepared['source']['form'] === [
            'type' => 'Login',
            'templateBytes' => 239,
            'templateSha256' => '2609b17e4e14419ac0c2117cfb699db242b193089e409d1aa0f6391da19049b5',
            'fieldCount' => 2,
            'fieldNames' => ['username', 'password'],
            'buttonType' => 'button',
            'submissionConnected' => false,
        ],
        'source metadata locks the display-only Login shape without the raw template'
    );
    red_theme_login_preview_test_assert(
        $prepared['fixture']['page']['slots']['1'][0]['data']['fields'] === [
            [
                'name' => 'username',
                'label' => 'Username:',
                'type' => 'text',
                'autocomplete' => 'username',
                'required' => true,
            ],
            [
                'name' => 'password',
                'label' => 'Password:',
                'type' => 'password',
                'autocomplete' => 'current-password',
                'required' => true,
            ],
        ],
        'portable contract reconstructs only the two required empty Login fields'
    );
    red_theme_login_preview_test_assert(
        $prepared['fixture']['page']['slots']['1'][0]['data']['submitLabel'] === 'submit'
            && $prepared['fixture']['page']['slots']['2'] === []
            && $prepared['fixture']['page']['slots']['3'] === []
            && $prepared['fixture']['page']['slots']['4'] === [],
        'portable selected layout contains only the inert final button Form in position one'
    );
    red_theme_login_preview_test_assert(
        !red_theme_login_preview_test_contains_key(
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
    red_theme_login_preview_test_assert(
        strpos(json_encode($prepared['source']), '#|question=') === false
            && strpos(json_encode($prepared['source']), '/bin/login.php') === false,
        'source report redacts the raw template and legacy endpoint'
    );
    red_theme_login_preview_test_assert(
        substr_count($first['html'], '<form') === 1
            && strpos($first['html'], '<form aria-describedby="preview-form-note">') !== false
            && strpos($first['html'], '<form action=') === false
            && strpos($first['html'], '<form method=') === false,
        'rendered Form has no action or method'
    );
    red_theme_login_preview_test_assert(
        strpos($first['html'], 'name="username"') !== false
            && strpos($first['html'], 'type="text"') !== false
            && strpos($first['html'], 'autocomplete="username"') !== false
            && strpos($first['html'], 'name="password"') !== false
            && strpos($first['html'], 'type="password"') !== false
            && strpos($first['html'], 'autocomplete="current-password"') !== false
            && substr_count($first['html'], 'required') === 2,
        'rendered Login fields retain exact types, autocomplete hints, and required state'
    );
    red_theme_login_preview_test_assert(
        strpos($first['html'], '<button type="button" aria-disabled="true">submit</button>') !== false
            && strpos($first['html'], 'type="submit"') === false
            && strpos($first['html'], '/bin/login.php') === false
            && strpos($first['html'], '<script') === false
            && strpos($first['html'], 'onsubmit=') === false,
        'rendered final button is inert and no endpoint or executable submit behavior exists'
    );
    red_theme_login_preview_test_assert(
        preg_match('/<input\b[^>]*\bvalue\s*=/i', $first['html']) !== 1
            && preg_match('/<textarea\b[^>]*>[^<]+<\/textarea>/i', $first['html']) !== 1,
        'rendered fields carry no default or prefilled value'
    );
    red_theme_login_preview_test_assert(
        strpos(
            $first['html'],
            'Read-only Login data preview — three fixed database reads; the form remains display-only; no session, activation, or live website change.'
        ) !== false,
        'document renders the exact Login read-only notice'
    );
    red_theme_login_preview_test_assert(
        $_GET === ['login_preview_sentinel' => 'get']
            && $_POST === ['login_preview_sentinel' => 'post']
            && $_SESSION === ['login_preview_sentinel' => 'session'],
        'rendering neither reads nor mutates request/session sentinels'
    );

    red_theme_login_preview_test_expect(
        function () use ($rows, $repositoryRoot) {
            red_theme_login_preview_render_rows($rows, $repositoryRoot, 2);
        },
        'zero or three',
        'an unapproved database-read count is rejected'
    );

    $tamperCases = [
        'extra selected column' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['Endpoint'] = '/bin/login.php';
        },
        'missing joined row' => static function (&$candidate) {
            $candidate['articleFormSection'] = [];
        },
        'Article RecordID drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ArticleRecordID'] = '1';
        },
        'Article alias drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ArticleAlias'] = 'signin';
        },
        'Article component drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['Component'] = 'Article';
        },
        'Article layout drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ArticleLayout'] = 'index-1';
        },
        'Article page position drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['PagePosition'] = '2';
        },
        'Article summary drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['ShortDesc'] = 'Login now?';
        },
        'Article start date drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['StartDate'] = '2012-06-02 00:00:00';
        },
        'Form relationship drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormRefID'] = '1';
        },
        'Form RecordID drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormRecordID'] = '1';
        },
        'Form type drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormType'] = 'Contact';
        },
        'parent Section drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['SectionRecordID'] = '24';
        },
        'parent Section layout drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['SectionLayout'] = 'index-2';
        },
        'username required drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormTemplate'] = str_replace(
                'name=username|type=textfield|required=true',
                'name=username|type=textfield|required=false',
                $candidate['articleFormSection'][0]['FormTemplate']
            );
        },
        'password type drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormTemplate'] = str_replace(
                'name=password|type=password',
                'name=password|type=textfield',
                $candidate['articleFormSection'][0]['FormTemplate']
            );
        },
        'prefilled username drift' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormTemplate'] = str_replace(
                'name=username|type=textfield|required=true|displayname=Username:|initialvalue=',
                'name=username|type=textfield|required=true|displayname=Username:|initialvalue=admin',
                $candidate['articleFormSection'][0]['FormTemplate']
            );
        },
        'template endpoint property' => static function (&$candidate) {
            $candidate['articleFormSection'][0]['FormTemplate'] = str_replace(
                'displayname=submit',
                'displayname=submit|endpoint=/bin/login.php',
                $candidate['articleFormSection'][0]['FormTemplate']
            );
        },
        'template fourth record' => static function (&$candidate) {
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
        red_theme_login_preview_test_expect(
            function () use ($tampered) {
                red_theme_login_preview_prepare_rows($tampered);
            },
            '',
            $label . ' fails closed'
        );
    }

    $fixturePreview = red_theme_preview_render('starter-reference', $repositoryRoot);
    red_theme_login_preview_test_assert(
        $fixturePreview['bytes'] === 19507
            && $fixturePreview['sha256'] === '106c984a77643cb0a8b4f0154a59e0558b1d082ff90267d5cbd7e785bbd02a7d',
        'existing isolated image fixture remains byte-identical'
    );
    $runtime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_login_preview_test_assert(
        $runtime['themeId'] === 'legacy-bootstrap'
            && !empty($runtime['resolution']['usedFallback']),
        'live runtime still hard-falls back from the standard starter'
    );
    $manifest = json_decode(
        file_get_contents($repositoryRoot . '/themes/starter-reference/theme.json'),
        true
    );
    red_theme_login_preview_test_assert(
        is_array($manifest)
            && $manifest['id'] === 'starter-reference'
            && $manifest['version'] === '1.2.0',
        'activation-ready manifest retains the selected Login preview provider'
    );

    echo 'Theme selected Login preview self-test passed (' . $assertions . " assertions).\n";
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
