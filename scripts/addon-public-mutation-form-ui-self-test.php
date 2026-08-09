<?php
/**
 * Dependency-free checks for core-owned public-mutation form composition.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_public_mutation_form_ui_helpers.php';

$assertions = 0;

function red_addon_public_mutation_form_ui_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_form_ui_test_manifest()
{
    return [
        'id' => 'redcms.form-ui-fixture',
        'routes' => [[
            'id' => 'redcms.form-ui-fixture/cart-intent',
            'scope' => 'public',
            'path' => '/addons/redcms/form-ui-fixture/cart-intent',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => 'redcms.form-ui-fixture/cart-intent',
            'mutation' => 'redcms.form-ui-fixture/add-to-cart',
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
            'tables' => ['RED_Addon_Form_UI_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_public_mutation_form_ui_test_csrf($subjectRecordId = 42)
{
    return [
        'valid' => true,
        'issued' => true,
        'subjectRecordId' => $subjectRecordId,
        'scopeSha256' => str_repeat('a', 64),
        'token' => str_repeat('b', 64),
        'maxAgeSeconds' => 600,
        'reason' => 'csrf_issued',
    ];
}

function red_addon_public_mutation_form_ui_test_idempotency(
    $subjectRecordId = 42
) {
    return [
        'valid' => true,
        'issued' => true,
        'idempotencyRecordId' => 84,
        'subjectRecordId' => $subjectRecordId,
        'scopeSha256' => str_repeat('c', 64),
        'key' => str_repeat('d', 64),
        'maxAgeSeconds' => 600,
        'reason' => 'idempotency_issued',
    ];
}

function red_addon_public_mutation_form_ui_test_simple_fields()
{
    return [[
        'key' => 'product',
        'control' => 'hidden',
        'value' => 'banana-bunch',
    ], [
        'key' => 'quantity',
        'control' => 'number',
        'label' => 'Quantity',
        'value' => 1,
    ]];
}

function red_addon_public_mutation_form_ui_test_variable_fields()
{
    return [[
        'key' => 'product',
        'control' => 'hidden',
        'value' => 'studio-shirt',
    ], [
        'key' => 'quantity',
        'control' => 'number',
        'label' => 'Quantity <items>',
        'value' => 2,
    ], [
        'key' => 'variant',
        'control' => 'select',
        'label' => 'Size & fit',
        'value' => 'shirt-medium',
        'options' => [[
            'value' => 'shirt-small',
            'label' => 'Small',
        ], [
            'value' => 'shirt-medium',
            'label' => 'Medium & regular',
        ]],
    ]];
}

try {
    $manifest = red_addon_public_mutation_form_ui_test_manifest();
    $routeId = 'redcms.form-ui-fixture/cart-intent';
    $mutationId = 'redcms.form-ui-fixture/add-to-cart';
    $csrf = red_addon_public_mutation_form_ui_test_csrf();
    $idempotency =
        red_addon_public_mutation_form_ui_test_idempotency();

    $simple = red_addon_public_mutation_form_ui_compose(
        $manifest,
        $routeId,
        $mutationId,
        'product-42',
        'Add to cart',
        red_addon_public_mutation_form_ui_test_simple_fields(),
        $csrf,
        $idempotency
    );
    red_addon_public_mutation_form_ui_test_assert(
        $simple['valid'] === true
            && $simple['action']
                === '/addons/redcms/form-ui-fixture/cart-intent'
            && $simple['method'] === 'POST'
            && $simple['csrfHeaderName'] === 'X-RED-CMS-CSRF'
            && $simple['idempotencyHeaderName'] === 'Idempotency-Key'
            && array_column($simple['fields'], 'key')
                === ['product', 'quantity']
            && $simple['fields'][1]['minimum'] === 1
            && $simple['fields'][1]['maximum'] === 100,
        'core derives one simple-product form only from the closed declaration'
    );

    $variable = red_addon_public_mutation_form_ui_compose(
        $manifest,
        $routeId,
        $mutationId,
        'product-43',
        'Add <shirt> to cart',
        red_addon_public_mutation_form_ui_test_variable_fields(),
        $csrf,
        $idempotency
    );
    red_addon_public_mutation_form_ui_test_assert(
        $variable['valid'] === true
            && count($variable['fields']) === 3
            && $variable['fields'][2]['control'] === 'select'
            && $variable['fields'][2]['value'] === 'shirt-medium'
            && count($variable['fields'][2]['options']) === 2,
        'a declared optional identifier can become one bounded required select'
    );

    $html = red_addon_public_mutation_form_ui_render($variable);
    red_addon_public_mutation_form_ui_test_assert(
        str_contains(
            $html,
            'id="red-public-mutation-product-43"'
        )
            && str_contains(
                $html,
                'action="/addons/redcms/form-ui-fixture/cart-intent"'
            )
            && str_contains($html, 'method="post"')
            && str_contains(
                $html,
                'enctype="application/x-www-form-urlencoded"'
            )
            && str_contains($html, 'data-red-addon-public-mutation-form')
            && str_contains($html, 'data-red-csrf-header="X-RED-CMS-CSRF"')
            && str_contains($html, 'data-red-idempotency-header="Idempotency-Key"')
            && str_contains($html, 'data-red-csrf-token="' . str_repeat('b', 64) . '"')
            && str_contains($html, 'data-red-idempotency-key="' . str_repeat('d', 64) . '"'),
        'the escaped form exposes only the exact endpoint and fetch header evidence'
    );
    red_addon_public_mutation_form_ui_test_assert(
        substr_count($html, 'name="product"') === 1
            && substr_count($html, 'name="quantity"') === 1
            && substr_count($html, 'name="variant"') === 1
            && !str_contains($html, 'name="csrf"')
            && !str_contains($html, 'name="idempotency"')
            && !str_contains($html, 'name="price"')
            && !str_contains($html, 'name="currency"'),
        'the form body contains package fields only and no security or commerce authority'
    );
    red_addon_public_mutation_form_ui_test_assert(
        str_contains($html, 'Quantity &lt;items&gt;')
            && str_contains($html, 'Size &amp; fit')
            && str_contains($html, 'Medium &amp; regular')
            && str_contains($html, 'Add &lt;shirt&gt; to cart')
            && !str_contains($html, '<shirt>')
            && !str_contains($html, '<script'),
        'all package labels are escaped and no package HTML is executable'
    );
    red_addon_public_mutation_form_ui_test_assert(
        str_contains($html, 'type="number"')
            && str_contains($html, 'min="1" max="100" step="1"')
            && str_contains($html, '<select id="red-public-mutation-product-43-variant"')
            && substr_count($html, ' selected') === 1
            && str_contains($html, 'aria-describedby="red-public-mutation-product-43-status"')
            && str_contains($html, 'role="status"')
            && str_contains($html, 'aria-live="polite"')
            && str_contains($html, '<noscript><p>This action requires JavaScript.</p></noscript>'),
        'quantity, variant, status, and no-script behavior use accessible core markup'
    );

    $invalidCases = [];
    $missingRequired = red_addon_public_mutation_form_ui_test_simple_fields();
    array_shift($missingRequired);
    $invalidCases['missing required product'] = $missingRequired;
    $unknown = red_addon_public_mutation_form_ui_test_simple_fields();
    $unknown[] = ['key' => 'price', 'control' => 'hidden', 'value' => '1'];
    $invalidCases['undeclared price'] = $unknown;
    $badQuantity = red_addon_public_mutation_form_ui_test_simple_fields();
    $badQuantity[1]['value'] = 101;
    $invalidCases['out-of-range quantity'] = $badQuantity;
    $textProduct = red_addon_public_mutation_form_ui_test_simple_fields();
    $textProduct[0]['control'] = 'text';
    $invalidCases['undeclared control'] = $textProduct;
    $badIdentifier = red_addon_public_mutation_form_ui_test_simple_fields();
    $badIdentifier[0]['value'] = 'banana/bunch';
    $invalidCases['invalid product identifier'] = $badIdentifier;
    $missingSelection = red_addon_public_mutation_form_ui_test_variable_fields();
    $missingSelection[2]['value'] = 'shirt-large';
    $invalidCases['unlisted selected variant'] = $missingSelection;
    $duplicateOptions = red_addon_public_mutation_form_ui_test_variable_fields();
    $duplicateOptions[2]['options'][1]['value'] = 'shirt-small';
    $invalidCases['duplicate variant option'] = $duplicateOptions;
    foreach ($invalidCases as $name => $fields) {
        $invalid = red_addon_public_mutation_form_ui_compose(
            $manifest,
            $routeId,
            $mutationId,
            'product-44',
            'Add to cart',
            $fields,
            $csrf,
            $idempotency
        );
        red_addon_public_mutation_form_ui_test_assert(
            $invalid === red_addon_public_mutation_form_ui_result(
                'fields_invalid'
            ),
            $name . ' fails closed without partial values or browser evidence'
        );
    }

    $wrongSubject = red_addon_public_mutation_form_ui_test_idempotency(43);
    $invalidEvidence = red_addon_public_mutation_form_ui_compose(
        $manifest,
        $routeId,
        $mutationId,
        'product-45',
        'Add to cart',
        red_addon_public_mutation_form_ui_test_simple_fields(),
        $csrf,
        $wrongSubject
    );
    red_addon_public_mutation_form_ui_test_assert(
        $invalidEvidence === red_addon_public_mutation_form_ui_result(
            'evidence_invalid'
        ),
        'cross-subject browser evidence is refused without token disclosure'
    );

    $invalidIdentity = red_addon_public_mutation_form_ui_compose(
        $manifest,
        'redcms.form-ui-fixture/other',
        $mutationId,
        'product-46',
        'Add to cart',
        red_addon_public_mutation_form_ui_test_simple_fields(),
        $csrf,
        $idempotency
    );
    $invalidInstance = red_addon_public_mutation_form_ui_compose(
        $manifest,
        $routeId,
        $mutationId,
        'Product_46',
        'Add to cart',
        red_addon_public_mutation_form_ui_test_simple_fields(),
        $csrf,
        $idempotency
    );
    $invalidLabel = red_addon_public_mutation_form_ui_compose(
        $manifest,
        $routeId,
        $mutationId,
        'product-46',
        ' Add to cart',
        red_addon_public_mutation_form_ui_test_simple_fields(),
        $csrf,
        $idempotency
    );
    $controlLabelFields =
        red_addon_public_mutation_form_ui_test_simple_fields();
    $controlLabelFields[1]['label'] = "Quantity\nitems";
    $invalidControlLabel = red_addon_public_mutation_form_ui_compose(
        $manifest,
        $routeId,
        $mutationId,
        'product-46',
        'Add to cart',
        $controlLabelFields,
        $csrf,
        $idempotency
    );
    red_addon_public_mutation_form_ui_test_assert(
        $invalidIdentity === red_addon_public_mutation_form_ui_result(
            'contract_unavailable'
        )
            && $invalidInstance === red_addon_public_mutation_form_ui_result(
                'instance_invalid'
            )
            && $invalidLabel === red_addon_public_mutation_form_ui_result(
                'label_invalid'
            )
            && $invalidControlLabel
                === red_addon_public_mutation_form_ui_result(
                    'fields_invalid'
            ),
        'undeclared identities and malformed core presentation facts have no form path'
    );

    $tampered = $variable;
    $tampered['fields'][1]['maximum'] = 0;
    $tokenAsField = $variable;
    $tokenAsField['fields'][] = [
        'key' => 'csrf',
        'control' => 'hidden',
        'value' => str_repeat('b', 64),
    ];
    red_addon_public_mutation_form_ui_test_assert(
        red_addon_public_mutation_form_ui_render(
            red_addon_public_mutation_form_ui_result()
        ) === ''
            && red_addon_public_mutation_form_ui_render($tampered) === ''
            && red_addon_public_mutation_form_ui_render($tokenAsField) === '',
        'malformed or post-composition-tampered models render no partial form'
    );

    $source = file_get_contents(
        $projectRoot .
        '/includes/addon_public_mutation_form_ui_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_form_ui_test_assert(
        is_string($source)
            && is_string($frontController)
            && preg_match(
                '/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|header|http_response_code|setcookie|'
                    . 'session_start|session_id|ob_start|ob_end_clean|'
                    . 'ob_get_clean|file_get_contents|file_put_contents)\s*\(/',
                $source
            ) !== 1
            && strpos($source, 'php://') === false
            && strpos(
                $frontController,
                'addon_public_mutation_form_ui_helpers.php'
            ) === false,
        'the form helper has no request, database, emission, package-loading, or front-controller path'
    );

    $serverBefore = $_SERVER;
    $getBefore = $_GET;
    $postBefore = $_POST;
    $cookieBefore = $_COOKIE;
    $requestBefore = $_REQUEST;
    $headersBefore = headers_list();
    $statusBefore = http_response_code();
    $bufferBefore = ob_get_level();
    $repeat = red_addon_public_mutation_form_ui_compose(
        $manifest,
        $routeId,
        $mutationId,
        'product-47',
        'Add to cart',
        red_addon_public_mutation_form_ui_test_simple_fields(),
        $csrf,
        $idempotency
    );
    $repeatHtml = red_addon_public_mutation_form_ui_render($repeat);
    red_addon_public_mutation_form_ui_test_assert(
        $repeat['valid'] === true
            && $repeatHtml !== ''
            && $_SERVER === $serverBefore
            && $_GET === $getBefore
            && $_POST === $postBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && http_response_code() === $statusBefore
            && ob_get_level() === $bufferBefore,
        'composition and rendering change no request-global, HTTP, session, or buffer state'
    );

    fwrite(
        STDOUT,
        'Public-mutation form UI self-test passed ('
            . $assertions . " assertions).\n"
    );
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
