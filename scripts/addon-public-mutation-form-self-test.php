<?php
/**
 * Dependency-free checks for the declared public-mutation form decoder.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_public_mutation_form_helpers.php';

$assertions = 0;

function red_addon_public_mutation_form_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_form_test_manifest()
{
    return [
        'id' => 'redcms.form-fixture',
        'routes' => [[
            'id' => 'redcms.form-fixture/cart-intent',
            'scope' => 'public',
            'path' => '/addons/redcms/form-fixture/cart-intent',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => 'redcms.form-fixture/cart-intent',
            'mutation' => 'redcms.form-fixture/add-to-cart',
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 128,
            'requestFields' => [
                [
                    'key' => 'product',
                    'type' => 'identifier',
                    'required' => true,
                    'minLength' => 1,
                    'maxLength' => 120,
                ],
                [
                    'key' => 'quantity',
                    'type' => 'positive-integer',
                    'required' => true,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
                [
                    'key' => 'source',
                    'type' => 'identifier',
                    'required' => false,
                    'minLength' => 1,
                    'maxLength' => 20,
                ],
            ],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => ['RED_Addon_Form_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

try {
    $manifest = red_addon_public_mutation_form_test_manifest();
    $routeId = 'redcms.form-fixture/cart-intent';
    $mutationId = 'redcms.form-fixture/add-to-cart';
    $contract = red_addon_public_mutation_form_contract(
        $manifest,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_form_test_assert(
        is_array($contract)
            && $contract['maxBodyBytes'] === 128
            && array_column($contract['requestFields'], 'key') === [
                'product', 'quantity', 'source',
            ],
        'the decoder resolves only one normalized static POST declaration'
    );

    $decoded = red_addon_public_mutation_form_decode(
        $manifest,
        $routeId,
        $mutationId,
        'product=SKU-42&quantity=2'
    );
    red_addon_public_mutation_form_test_assert(
        $decoded === [
            'valid' => true,
            'fields' => ['product' => 'SKU-42', 'quantity' => 2],
            'reason' => 'parsed',
        ],
        'canonical declared form bytes produce only sorted typed package fields'
    );

    $optional = red_addon_public_mutation_form_decode(
        $manifest,
        $routeId,
        $mutationId,
        'source=homepage&quantity=100&product=SKU_42'
    );
    red_addon_public_mutation_form_test_assert(
        $optional === [
            'valid' => true,
            'fields' => [
                'product' => 'SKU_42',
                'quantity' => 100,
                'source' => 'homepage',
            ],
            'reason' => 'parsed',
        ],
        'field transport order cannot change normalized typed output'
    );

    $encodedTilde = red_addon_public_mutation_form_decode(
        $manifest,
        $routeId,
        $mutationId,
        'product=SKU%7E42&quantity=2'
    );
    red_addon_public_mutation_form_test_assert(
        $encodedTilde === [
            'valid' => true,
            'fields' => ['product' => 'SKU~42', 'quantity' => 2],
            'reason' => 'parsed',
        ],
        'the one browser-canonical escape needed by the identifier alphabet is normalized'
    );

    $invalidBodies = [
        'missing-required' => 'product=SKU-42',
        'unknown-field' => 'product=SKU-42&quantity=2&price=1',
        'duplicate-field' => 'product=SKU-42&quantity=2&quantity=3',
        'nested-field' => 'product%5Bcode%5D=SKU-42&quantity=2',
        'bare-key' => 'product&quantity=2',
        'extra-equals' => 'product=SKU=42&quantity=2',
        'separator-drift' => 'product=SKU-42&&quantity=2',
        'plus-encoding' => 'product=SKU+42&quantity=2',
        'percent-encoding' => 'product=SKU%2D42&quantity=2',
        'lowercase-percent-encoding' => 'product=SKU%7e42&quantity=2',
        'raw-tilde' => 'product=SKU~42&quantity=2',
        'leading-zero-integer' => 'product=SKU-42&quantity=02',
        'integer-out-of-range' => 'product=SKU-42&quantity=101',
        'identifier-drift' => 'product=SKU/42&quantity=2',
    ];
    foreach ($invalidBodies as $name => $body) {
        $invalid = red_addon_public_mutation_form_decode(
            $manifest,
            $routeId,
            $mutationId,
            $body
        );
        red_addon_public_mutation_form_test_assert(
            $invalid === [
                'valid' => false,
                'fields' => [],
                'reason' => 'fields_invalid',
            ],
            'invalid ' . $name . ' bytes expose no partial package field value'
        );
    }

    $oversized = red_addon_public_mutation_form_decode(
        $manifest,
        $routeId,
        $mutationId,
        str_repeat('x', 129)
    );
    red_addon_public_mutation_form_test_assert(
        $oversized === [
            'valid' => false,
            'fields' => [],
            'reason' => 'body_too_large',
        ],
        'over-limit raw bytes are refused before package-field parsing'
    );

    $nonString = red_addon_public_mutation_form_decode(
        $manifest,
        $routeId,
        $mutationId,
        ['product' => 'SKU-42']
    );
    red_addon_public_mutation_form_test_assert(
        $nonString === [
            'valid' => false,
            'fields' => [],
            'reason' => 'fields_invalid',
        ],
        'an already-parsed array cannot bypass canonical raw-body decoding'
    );

    $forged = $manifest;
    $forged['publicMutationContracts'][0]['requestFields'][0]['key'] = 'price';
    $unavailable = red_addon_public_mutation_form_decode(
        $forged,
        $routeId,
        $mutationId,
        'product=SKU-42&quantity=2'
    );
    red_addon_public_mutation_form_test_assert(
        $unavailable === [
            'valid' => false,
            'fields' => [],
            'reason' => 'contract_unavailable',
        ],
        'a forged or undeclared manifest cannot select a package field decoder'
    );

    $wrongIdentity = red_addon_public_mutation_form_decode(
        $manifest,
        'redcms.form-fixture/other',
        $mutationId,
        'product=SKU-42&quantity=2'
    );
    red_addon_public_mutation_form_test_assert(
        $wrongIdentity === $unavailable,
        'an undeclared route or mutation identity has no decoding path'
    );

    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_form_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_form_test_assert(
        is_string($source)
            && is_string($frontController)
            && preg_match(
                '/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|header|http_response_code|setcookie|session_start|session_id|ob_start|ob_end_clean|ob_get_clean|parse_str|urldecode|file_get_contents|file_put_contents)\s*\(/',
                $source
            ) !== 1
            && strpos($source, 'php://') === false
            && strpos(
                $frontController,
                'addon_public_mutation_form_helpers.php'
            ) === false,
        'the decoder has no request-global, database, HTTP-emission, filesystem, or front-controller path'
    );

    $serverBefore = $_SERVER;
    $getBefore = $_GET;
    $postBefore = $_POST;
    $cookieBefore = $_COOKIE;
    $requestBefore = $_REQUEST;
    $headersBefore = headers_list();
    $statusBefore = http_response_code();
    $bufferBefore = ob_get_level();
    red_addon_public_mutation_form_decode(
        $manifest,
        $routeId,
        $mutationId,
        'product=SKU-42&quantity=2'
    );
    red_addon_public_mutation_form_test_assert(
        $_SERVER === $serverBefore
            && $_GET === $getBefore
            && $_POST === $postBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && http_response_code() === $statusBefore
            && ob_get_level() === $bufferBefore,
        'decoding changes no request-global, HTTP, session, or buffer state'
    );

    fwrite(
        STDOUT,
        'Public-mutation form self-test passed ('
            . $assertions . " assertions).\n"
    );
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
