<?php
/**
 * Disposable checks for core-owned public-mutation form evidence bootstrap.
 * No endpoint or package fixture is created.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot .
    '/includes/addon_public_mutation_form_bootstrap_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_public_mutation_bootstrap)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Form-bootstrap self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$subjectIds = [];
$idempotencyRenamed = false;
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_public_mutation_bootstrap_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_bootstrap_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_public_mutation_bootstrap_test_counts($connection)
{
    return red_addon_public_mutation_bootstrap_test_scalar(
        $connection,
        'SELECT CONCAT_WS(CHAR(58),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys)
         )'
    );
}

function red_addon_public_mutation_bootstrap_test_manifest()
{
    return [
        'id' => 'redcms.form-bootstrap-fixture',
        'routes' => [[
            'id' => 'redcms.form-bootstrap-fixture/cart-intent',
            'scope' => 'public',
            'path' => '/addons/redcms/form-bootstrap-fixture/cart-intent',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => 'redcms.form-bootstrap-fixture/cart-intent',
            'mutation' => 'redcms.form-bootstrap-fixture/add-to-cart',
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 512,
            'requestFields' => [[
                'key' => 'product',
                'type' => 'identifier',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 64,
            ], [
                'key' => 'quantity',
                'type' => 'positive-integer',
                'required' => true,
                'minimum' => 1,
                'maximum' => 100,
            ], [
                'key' => 'variant',
                'type' => 'identifier',
                'required' => false,
                'minLength' => 1,
                'maxLength' => 64,
            ]],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => ['RED_Addon_Form_Bootstrap_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_public_mutation_bootstrap_test_fields($variable = false)
{
    $fields = [[
        'key' => 'product',
        'control' => 'hidden',
        'value' => $variable ? 'studio-shirt' : 'banana-bunch',
    ], [
        'key' => 'quantity',
        'control' => 'number',
        'label' => 'Quantity',
        'value' => 1,
    ]];
    if ($variable) {
        $fields[] = [
            'key' => 'variant',
            'control' => 'select',
            'label' => 'Options',
            'value' => 'shirt-medium-blue',
            'options' => [[
                'value' => 'shirt-small-red',
                'label' => 'Size: Small · Color: Red',
            ], [
                'value' => 'shirt-medium-blue',
                'label' => 'Size: Medium · Color: Blue',
            ]],
        ];
    }
    return $fields;
}

try {
    $manifest = red_addon_public_mutation_bootstrap_test_manifest();
    $routeId = 'redcms.form-bootstrap-fixture/cart-intent';
    $mutationId = 'redcms.form-bootstrap-fixture/add-to-cart';
    red_addon_public_mutation_bootstrap_test_assert(
        red_addon_public_mutation_bootstrap_test_counts($connection) === '0:0:0',
        'bootstrap fixture starts with empty subject, CSRF, and idempotency storage'
    );

    $invalidFields = red_addon_public_mutation_bootstrap_test_fields();
    $invalidFields[] = [
        'key' => 'price',
        'control' => 'hidden',
        'value' => '399',
    ];
    $invalid = red_addon_public_mutation_form_bootstrap(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        'product-invalid',
        'Add to cart',
        $invalidFields,
        ''
    );
    red_addon_public_mutation_bootstrap_test_assert(
        red_addon_public_mutation_form_bootstrap_result_valid($invalid)
            && $invalid['valid'] === false
            && $invalid['reason'] === 'presentation_invalid'
            && red_addon_public_mutation_bootstrap_test_counts($connection)
                === '0:0:0',
        'undeclared commercial fields fail before any browser evidence is issued'
    );

    $issued = red_addon_public_mutation_form_bootstrap(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        'product-banana',
        'Add to cart',
        red_addon_public_mutation_bootstrap_test_fields(),
        ''
    );
    $subjectIds[] = $issued['lifecycle']['subjectRecordId'] ?? 0;
    red_addon_public_mutation_bootstrap_test_assert(
        red_addon_public_mutation_form_bootstrap_result_valid($issued)
            && $issued['lifecycle']['state'] === 'issued'
            && $issued['formModel']['action']
                === '/addons/redcms/form-bootstrap-fixture/cart-intent'
            && array_column($issued['formModel']['fields'], 'key')
                === ['product', 'quantity']
            && red_addon_public_mutation_bootstrap_test_counts($connection)
                === '1:1:1',
        'an absent cookie receives one subject, scoped CSRF/key pair, and simple form model'
    );
    red_addon_public_mutation_bootstrap_test_assert(
        preg_match(
            '/\Aredcms_public_mutation_subject=([a-f0-9]{64});/',
            $issued['lifecycle']['setCookieValue'],
            $matches
        ) === 1,
        'bootstrap returns the existing fixed host-only subject-cookie descriptor'
    );
    $subjectToken = $matches[1];
    $forgedLifecycle = $issued['lifecycle'];
    $forgedLifecycle['setCookieValue'] = preg_replace(
        '/=([a-f0-9]{64});/',
        '=' . str_repeat('f', 64) . ';',
        $forgedLifecycle['setCookieValue'],
        1
    );
    red_addon_public_mutation_bootstrap_test_assert(
        red_addon_public_mutation_form_bootstrap_cleanup(
            $connection,
            $forgedLifecycle,
            [],
            []
        ) === false
            && red_addon_public_mutation_bootstrap_test_counts($connection)
                === '1:1:1',
        'a forged issuance descriptor cannot delete an active subject'
    );
    $issuedHtml = red_addon_public_mutation_form_ui_render(
        $issued['formModel']
    );
    red_addon_public_mutation_bootstrap_test_assert(
        $issuedHtml !== ''
            && strpos($issuedHtml, $subjectToken) === false
            && substr_count($issuedHtml, 'data-red-csrf-token=') === 1
            && substr_count($issuedHtml, 'data-red-idempotency-key=') === 1,
        'the subject cookie never enters form markup while core evidence remains fetch-only'
    );

    $resolved = red_addon_public_mutation_form_bootstrap(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        'product-shirt',
        'Add to cart',
        red_addon_public_mutation_bootstrap_test_fields(true),
        $subjectToken
    );
    red_addon_public_mutation_bootstrap_test_assert(
        red_addon_public_mutation_form_bootstrap_result_valid($resolved)
            && $resolved['lifecycle']['state'] === 'resolved'
            && $resolved['lifecycle']['setCookieValue'] === ''
            && $resolved['lifecycle']['subjectRecordId']
                === $issued['lifecycle']['subjectRecordId']
            && count($resolved['formModel']['fields'][2]['options']) === 2
            && red_addon_public_mutation_bootstrap_test_counts($connection)
                === '1:2:2',
        'a valid cookie reuses one subject while issuing a fresh variable-form evidence pair'
    );

    $plan = red_addon_public_mutation_declaration_preflight(
        $manifest,
        $routeId,
        $mutationId
    );
    $subject = [
        'valid' => true,
        'subjectRecordId' => $issued['lifecycle']['subjectRecordId'],
    ];
    red_addon_public_mutation_bootstrap_test_assert(
        !empty(red_addon_public_mutation_csrf_verify(
            $connection,
            $subject,
            $plan,
            $issued['formModel']['csrfToken']
        )['valid'])
            && !empty(red_addon_public_mutation_idempotency_resolve(
                $connection,
                $subject,
                $plan,
                $issued['formModel']['idempotencyKey']
            )['valid']),
        'the composed form evidence resolves only through the same declaration and subject'
    );

    $countsBeforeFailure = red_addon_public_mutation_bootstrap_test_counts(
        $connection
    );
    if (!mysqli_query(
        $connection,
        'RENAME TABLE RED_Addon_Public_Mutation_Idempotency_Keys
         TO RED_Addon_Public_Mutation_Idempotency_Keys_Bootstrap_Fixture'
    )) {
        throw new RuntimeException('Could not stage idempotency-storage refusal.');
    }
    $idempotencyRenamed = true;
    $resolvedFailure = red_addon_public_mutation_form_bootstrap(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        'product-resolved-failure',
        'Add to cart',
        red_addon_public_mutation_bootstrap_test_fields(),
        $subjectToken
    );
    $issuedFailure = red_addon_public_mutation_form_bootstrap(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        'product-issued-failure',
        'Add to cart',
        red_addon_public_mutation_bootstrap_test_fields(),
        ''
    );
    $renamedCounts = red_addon_public_mutation_bootstrap_test_scalar(
        $connection,
        'SELECT CONCAT_WS(CHAR(58),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens),
            (SELECT COUNT(*)
             FROM RED_Addon_Public_Mutation_Idempotency_Keys_Bootstrap_Fixture)
         )'
    );
    red_addon_public_mutation_bootstrap_test_assert(
        $resolvedFailure['reason'] === 'idempotency_unavailable'
            && $issuedFailure['reason'] === 'idempotency_unavailable'
            && $resolvedFailure['formModel'] === []
            && $issuedFailure['lifecycle'] === []
            && $renamedCounts === $countsBeforeFailure,
        'partial CSRF and new-subject issuance are compensated when idempotency storage fails'
    );
    if (!mysqli_query(
        $connection,
        'RENAME TABLE RED_Addon_Public_Mutation_Idempotency_Keys_Bootstrap_Fixture
         TO RED_Addon_Public_Mutation_Idempotency_Keys'
    )) {
        throw new RuntimeException('Could not restore idempotency storage.');
    }
    $idempotencyRenamed = false;

    mysqli_begin_transaction($connection);
    $transactionRefusal = red_addon_public_mutation_form_bootstrap(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        'product-transaction',
        'Add to cart',
        red_addon_public_mutation_bootstrap_test_fields(),
        $subjectToken
    );
    mysqli_rollback($connection);
    red_addon_public_mutation_bootstrap_test_assert(
        $transactionRefusal['reason'] === 'subject_unavailable'
            && red_addon_public_mutation_bootstrap_test_counts($connection)
                === $countsBeforeFailure,
        'caller-owned transactions are refused without issuing evidence'
    );

    $source = file_get_contents(
        $projectRoot .
            '/includes/addon_public_mutation_form_bootstrap_helpers.php'
    );
    red_addon_public_mutation_bootstrap_test_assert(
        is_string($source)
            && strpos($source, '$_GET') === false
            && strpos($source, '$_POST') === false
            && strpos($source, '$_COOKIE') === false
            && strpos($source, '$_SESSION') === false
            && strpos($source, 'header(') === false
            && strpos($source, 'setcookie(') === false
            && strpos($source, 'addon.php') === false,
        'bootstrap reads no request/session/cookie globals and has no emission or package path'
    );
} finally {
    if ($idempotencyRenamed) {
        mysqli_query(
            $connection,
            'RENAME TABLE RED_Addon_Public_Mutation_Idempotency_Keys_Bootstrap_Fixture
             TO RED_Addon_Public_Mutation_Idempotency_Keys'
        );
    }
    $ids = array_values(array_filter(
        array_unique(array_map('intval', $subjectIds)),
        static function ($recordId) {
            return $recordId > 0;
        }
    ));
    if ($ids !== []) {
        mysqli_query(
            $connection,
            'DELETE FROM RED_Addon_Public_Mutation_Subjects WHERE RecordID IN (' .
                implode(',', $ids) . ')'
        );
    }
}

red_addon_public_mutation_bootstrap_test_assert(
    red_addon_public_mutation_bootstrap_test_counts($connection) === '0:0:0',
    'bootstrap fixture leaves no subject, CSRF, or idempotency evidence'
);

echo 'Public-mutation form bootstrap self-test passed (' .
    $assertions . " assertions).\n";

?>
