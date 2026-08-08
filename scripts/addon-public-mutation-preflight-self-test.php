<?php
/**
 * Dependency-free public-mutation declaration-preflight acceptance fixture.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) .
    '/includes/addon_public_mutation_preflight_helpers.php';

$assertions = 0;

function red_addon_public_mutation_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_test_manifest()
{
    return [
        'id' => 'redcms.store-lite',
        'routes' => [[
            'id' => 'redcms.store-lite/cart-intent',
            'scope' => 'public',
            'path' => '/addons/redcms/store-lite/cart-intent',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => 'redcms.store-lite/cart-intent',
            'mutation' => 'redcms.store-lite/add-to-cart',
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 1024,
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
                    'key' => 'variant',
                    'type' => 'identifier',
                    'required' => false,
                    'minLength' => 1,
                    'maxLength' => 120,
                ],
            ],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => [
                'RED_Addon_StoreLite_Carts',
                'RED_Addon_StoreLite_Cart_Items',
            ],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

try {
    $manifest = red_addon_public_mutation_test_manifest();
    $contract = red_addon_public_mutation_contract(
        $manifest,
        'redcms.store-lite/cart-intent',
        'redcms.store-lite/add-to-cart'
    );
    red_addon_public_mutation_test_assert(
        is_array($contract)
            && $contract['path'] === '/addons/redcms/store-lite/cart-intent'
            && array_column($contract['requestFields'], 'key') === [
                'product',
                'quantity',
                'variant',
            ]
            && $contract['tables'] === [
                'RED_Addon_StoreLite_Cart_Items',
                'RED_Addon_StoreLite_Carts',
            ]
            && !red_addon_valid_public_mutation_table('RED_Addon_Settings'),
        'the closed declaration binds one static route and canonicalizes field and table order'
    );

    $plan = red_addon_public_mutation_declaration_preflight(
        $manifest,
        'redcms.store-lite/cart-intent',
        'redcms.store-lite/add-to-cart'
    );
    red_addon_public_mutation_test_assert(
        !empty($plan['valid'])
            && !empty($plan['ready'])
            && $plan['invoked'] === false
            && $plan['packageId'] === 'redcms.store-lite'
            && $plan['method'] === 'POST'
            && $plan['csrf'] === 'required'
            && $plan['encoding'] === 'application/x-www-form-urlencoded'
            && $plan['requestFieldCount'] === 3
            && $plan['tableCount'] === 2
            && $plan['outcomes'] === ['accepted', 'unchanged']
            && red_addon_public_mutation_declaration_preflight_is_valid($plan),
        'one valid declaration produces value-free deterministic readiness evidence without invocation'
    );
    red_addon_public_mutation_test_assert(
        red_addon_valid_sha256($plan['contractSha256'])
            && red_addon_valid_sha256($plan['planSha256'])
            && !array_key_exists('requestFields', $plan)
            && !array_key_exists('tables', $plan),
        'preflight exposes only counts and fingerprints rather than mutable field or table declarations'
    );

    $reordered = $manifest;
    $reordered['publicMutationContracts'][0]['requestFields'] = array_reverse(
        $reordered['publicMutationContracts'][0]['requestFields']
    );
    $reordered['publicMutationContracts'][0]['tables'] = array_reverse(
        $reordered['publicMutationContracts'][0]['tables']
    );
    $reorderedPlan = red_addon_public_mutation_declaration_preflight(
        $reordered,
        'redcms.store-lite/cart-intent',
        'redcms.store-lite/add-to-cart'
    );
    red_addon_public_mutation_test_assert(
        $reorderedPlan === $plan,
        'declaration ordering cannot change preflight readiness evidence'
    );

    $invalidCases = [];
    $missing = $manifest;
    unset($missing['publicMutationContracts']);
    $invalidCases['missing'] = $missing;
    $unsafeMethod = $manifest;
    $unsafeMethod['routes'][0]['methods'] = ['GET'];
    $invalidCases['route-method'] = $unsafeMethod;
    $placeholder = $manifest;
    $placeholder['routes'][0]['path'] = '/addons/redcms/store-lite/cart/{item}';
    $invalidCases['route-placeholder'] = $placeholder;
    $executable = $manifest;
    $executable['publicMutationContracts'][0]['callback'] = 'dangerous';
    $invalidCases['executable'] = $executable;
    $reservedField = $manifest;
    $reservedField['publicMutationContracts'][0]['requestFields'][0]['key'] = 'price';
    $invalidCases['reserved-field'] = $reservedField;
    $badPolicy = $manifest;
    $badPolicy['publicMutationContracts'][0]['idempotency'] = 'optional';
    $invalidCases['weak-policy'] = $badPolicy;
    $foreignMutation = $manifest;
    $foreignMutation['publicMutationContracts'][0]['mutation'] =
        'other.package/add-to-cart';
    $invalidCases['foreign-mutation'] = $foreignMutation;
    $coreTable = $manifest;
    $coreTable['publicMutationContracts'][0]['tables'] = [
        'RED_Addon_Public_Mutation_Executions',
    ];
    $invalidCases['core-table'] = $coreTable;

    foreach ($invalidCases as $name => $invalidManifest) {
        $invalid = red_addon_public_mutation_declaration_preflight(
            $invalidManifest,
            'redcms.store-lite/cart-intent',
            'redcms.store-lite/add-to-cart'
        );
        red_addon_public_mutation_test_assert(
            empty($invalid['valid'])
                && empty($invalid['ready'])
                && $invalid['invoked'] === false
                && $invalid['contractSha256'] === ''
                && $invalid['planSha256'] === ''
                && in_array('declaration_unavailable', $invalid['errors'], true),
            'invalid ' . $name . ' declarations fail closed before any route or mutation can run'
        );
    }

    $invalidIdentity = red_addon_public_mutation_declaration_preflight(
        $manifest,
        '../cart-intent',
        'redcms.store-lite/add-to-cart'
    );
    red_addon_public_mutation_test_assert(
        empty($invalidIdentity['valid'])
            && $invalidIdentity['route'] === ''
            && in_array('identity_invalid', $invalidIdentity['errors'], true),
        'malformed route or mutation identity has no declaration lookup path'
    );

    $forged = $plan;
    $forged['tableCount'] = 3;
    red_addon_public_mutation_test_assert(
        !red_addon_public_mutation_declaration_preflight_is_valid($forged),
        'forged value-free readiness evidence cannot validate'
    );
    $forgedHash = $plan;
    $forgedHash['planSha256'] = str_repeat('0', 64);
    red_addon_public_mutation_test_assert(
        !red_addon_public_mutation_declaration_preflight_is_valid($forgedHash),
        'stale or substituted plan fingerprints cannot validate'
    );
    $forgedNamespace = $plan;
    $forgedNamespace['route'] = 'other.package/cart-intent';
    $forgedNamespace['planSha256'] =
        red_addon_public_mutation_declaration_preflight_fingerprint(
            $forgedNamespace
        );
    red_addon_public_mutation_test_assert(
        !red_addon_public_mutation_declaration_preflight_is_valid($forgedNamespace),
        'even a recomputed forged plan cannot escape its package namespace'
    );

    $source = file_get_contents(
        dirname(__DIR__) . '/includes/addon_public_mutation_preflight_helpers.php'
    );
    red_addon_public_mutation_test_assert(
        is_string($source)
            && strpos($source, 'mysqli_') === false
            && strpos($source, '$_') === false
            && strpos($source, 'include ') === false
            && strpos($source, 'require ') === false
            && strpos($source, 'file_get_contents') === false,
        'declaration preflight has no database, request-global, package-execution, or filesystem-read path'
    );

    fwrite(
        STDOUT,
        'Add-on public-mutation preflight self-test passed (' . $assertions .
            " assertions).\n"
    );
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
