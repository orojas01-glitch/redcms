<?php
/** Pure P3E-9D4B adapter-identity and authorization-envelope checks. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_sandbox_checkout_real_mutation_helpers.php';

$assertions = 0;
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-p3e9d4b-evidence-' . bin2hex(random_bytes(8));
$packageDirectory = $temporaryRoot
    . '/addons/redcms/store-lite-stripe-checkout';

function red_checkout_p3e9d4b_evidence_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_checkout_p3e9d4b_evidence_delete($path)
{
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            red_checkout_p3e9d4b_evidence_delete($path . '/' . $entry);
        }
    }
    rmdir($path);
}

function red_checkout_p3e9d4b_evidence_input()
{
    return [
        'contactTarget' => 'synthetic-checkout-package',
        'checkout' => [
            'orderId' => 'ord_0123456789abcdef0123456789abcdef',
            'orderSnapshotSha256' => str_repeat('a', 64),
            'paymentMethod' => 'stripe_checkout',
            'amountMinor' => 5897,
            'currency' => 'USD',
            'idempotencySha256' => str_repeat('b', 64),
            'lineItems' => [[
                'name' => 'Dog scarf - Small / Red',
                'quantity' => 2,
                'unitAmountMinor' => 1999,
                'lineTotalMinor' => 3998,
            ], [
                'name' => 'Delivery fee',
                'quantity' => 1,
                'unitAmountMinor' => 1899,
                'lineTotalMinor' => 1899,
            ]],
        ],
        'policy' => [
            'apiVersion' => '2024-09-30.acacia',
            'successUrl' =>
                'https://shop.example.test/checkout/stripe-complete',
            'cancelUrl' => 'https://shop.example.test/checkout',
            'createdAtEpoch' => 1787025600,
            'expiresAtEpoch' => 1787027400,
        ],
        'profile' => [
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'contractVersion' => 'p3e9a-v1',
            'operation' => 'checkout.create-sandbox',
            'contactTarget' => 'stripe-sandbox',
            'credentialMode' => 'restricted_test_write',
            'providerContact' => true,
            'providerMutation' => true,
            'checkoutCreation' => true,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'clientDeployment' => false,
            'oneAttempt' => true,
            'automaticRetry' => false,
        ],
        'contractSha256' => str_repeat('c', 64),
    ];
}

function red_checkout_p3e9d4b_evidence_synthetic_plan(array $input)
{
    return [
        'valid' => true,
        'ready' => true,
        'status' => 'ready',
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'packageVersion' => '0.1.5',
        'adapterId' => 'redcms.store-lite-stripe-checkout/checkout',
        'operation' => 'checkout.create-sandbox-synthetic',
        'manifestSha256' => str_repeat('d', 64),
        'inventorySha256' => str_repeat('e', 64),
        'inputSha256' => red_addon_checkout_synthetic_hash($input),
        'planSha256' => str_repeat('f', 64),
        'adapterInvoked' => false,
        'boundedOutcome' => null,
        'outcomeSha256' => '',
        'executionPerformed' => false,
        'networkAccess' => false,
        'providerContact' => false,
        'providerMutation' => false,
        'checkoutCreation' => false,
        'payment' => false,
        'webhook' => false,
        'browserNavigation' => false,
        'orderMutation' => false,
        'retryAuthorized' => false,
        'clientDeployment' => false,
        'errors' => [],
    ];
}

function red_checkout_p3e9d4b_evidence_package($directory)
{
    mkdir($directory, 0700, true);
    $entrypoint = "<?php\nreturn static function (\$registry): void {};\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    $packageId = 'redcms.store-lite-stripe-checkout';
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'P3E-9D4B evidence fixture',
        'description' => 'Pure adapter identity fixture.',
        'version' => '0.1.8',
        'type' => 'adapter',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [],
            'adapters' => [$packageId . '/checkout'],
        ],
        'dependencies' => [
            'required' => [[
                'id' => 'redcms.store-lite',
                'version' => '>=0.1.35 <1.0',
            ]],
            'optional' => [],
        ],
        'permissions' => [],
        'settings' => [],
        'migrations' => [],
        'routes' => [],
        'publicMutationContracts' => [],
        'jobs' => [],
        'outboundHosts' => ['api.stripe.com'],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => hash('sha256', $entrypoint),
            ]],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
    file_put_contents(
        $directory . '/addon.json',
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
        ) . "\n"
    );
    return [
        'valid' => true,
        'path' => $directory,
        'manifest' => $manifest,
        'integrity' => ['inventoryComplete' => true],
    ];
}

function red_checkout_p3e9d4b_evidence_outcome(
    array $input,
    array $preflight
) {
    return [
        'valid' => true,
        'adopted' => true,
        'status' => 'request_contract_adopted',
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'packageVersion' => '0.1.8',
        'sourcePackageVersion' => '0.1.7',
        'operation' => 'checkout.create-sandbox-real-post-preflight',
        'providerOperation' => 'checkout.create-sandbox-real-post',
        'request' => red_addon_checkout_real_operation_typed_request(
            $preflight
        ),
        'inputSha256' => $preflight['inputSha256'],
        'syntheticPlanSha256' => $preflight['syntheticPlanSha256'],
        'contractSha256' => $input['contractSha256'],
        'requestSha256' => $preflight['requestSha256'],
        'restrictedTestWriteKeyRequired' => true,
        'credentialValueIncluded' => false,
        'authorizationHeaderIncluded' => false,
        'executionReady' => false,
        'networkAccess' => false,
        'providerContact' => false,
        'providerMutation' => false,
        'checkoutCreation' => false,
        'payment' => false,
        'webhook' => false,
        'browserNavigation' => false,
        'storeLiteMutation' => false,
        'retryAuthorized' => false,
        'liveMode' => false,
        'clientDeployment' => false,
        'executionPerformed' => false,
        'errors' => [],
    ];
}

if (defined('RED_ADDON_CHECKOUT_REAL_MUTATION_EVIDENCE_FIXTURE_ONLY')
    && RED_ADDON_CHECKOUT_REAL_MUTATION_EVIDENCE_FIXTURE_ONLY
) {
    return;
}

red_checkout_p3e9d4b_evidence_delete($temporaryRoot);

try {
    $package = red_checkout_p3e9d4b_evidence_package($packageDirectory);
    $input = red_checkout_p3e9d4b_evidence_input();
    $syntheticPlan = red_checkout_p3e9d4b_evidence_synthetic_plan($input);
    $preflight = red_addon_checkout_real_post_preflight(
        $syntheticPlan,
        $input
    );
    $outcome = red_checkout_p3e9d4b_evidence_outcome($input, $preflight);
    $evidence = red_addon_checkout_real_mutation_preflight_evidence(
        $package,
        $syntheticPlan,
        $input,
        $preflight,
        $outcome
    );
    red_checkout_p3e9d4b_evidence_assert(
        !empty($evidence['valid'])
            && ($evidence['packageVersion'] ?? '') === '0.1.8'
            && ($evidence['providerOperation'] ?? '')
                === 'checkout.create-sandbox-real-post',
        'exact adapter 0.1.8 preflight evidence is accepted'
    );
    foreach (['planSha256', 'startIdentitySha256',
        'resultIdentitySha256'] as $key
    ) {
        red_checkout_p3e9d4b_evidence_assert(
            red_addon_provider_contact_sha256($evidence[$key] ?? ''),
            $key . ' is a bounded D2 identity'
        );
    }

    $changedOutcome = $outcome;
    $changedOutcome['packageVersion'] = '0.1.7';
    red_checkout_p3e9d4b_evidence_assert(
        empty(red_addon_checkout_real_mutation_preflight_evidence(
            $package,
            $syntheticPlan,
            $input,
            $preflight,
            $changedOutcome
        )['valid']),
        'pre-D4 package outcome cannot be widened into D4B evidence'
    );

    $prepared = red_addon_checkout_real_mutation_prepare(
        $evidence,
        str_repeat('1', 64),
        str_repeat('2', 64),
        $input['checkout']['orderSnapshotSha256'],
        str_repeat('3', 64),
        str_repeat('4', 64),
        '2026-08-22T12:00:00Z',
        '2026-08-22T12:15:00Z'
    );
    red_checkout_p3e9d4b_evidence_assert(
        !empty($prepared['prepared'])
            && red_addon_provider_contact_sha256(
                $prepared['authorizationSha256'] ?? ''
            )
            && ($prepared['authorization']['maximumAttempts'] ?? 0) === 1
            && empty($prepared['authorization']['retryAuthorized'])
            && empty($prepared['authorization']['paymentAuthorized'])
            && empty($prepared['authorization']['webhookAuthorized'])
            && empty($prepared['authorization']['storeLiteMutationAuthorized'])
            && empty($prepared['authorization']['clientDeploymentAuthorized']),
        'fresh D4 authorization binds exact identities and false effects'
    );
    red_checkout_p3e9d4b_evidence_assert(
        red_addon_checkout_real_mutation_prepared_valid(
            $prepared,
            $evidence,
            str_repeat('1', 64),
            str_repeat('2', 64),
            $input['checkout']['orderSnapshotSha256'],
            str_repeat('3', 64),
            '2026-08-22T12:05:00Z'
        ),
        'prepared D4 authorization revalidates at an active instant'
    );
    red_checkout_p3e9d4b_evidence_assert(
        empty(red_addon_checkout_real_mutation_prepare(
            $evidence,
            str_repeat('1', 64),
            str_repeat('2', 64),
            $input['checkout']['orderSnapshotSha256'],
            str_repeat('3', 64),
            str_repeat('4', 64),
            '2026-08-22T12:00:00Z',
            '2026-08-22T12:15:01Z'
        )['prepared']),
        'authorization windows longer than fifteen minutes are refused'
    );

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_sandbox_checkout_real_mutation_helpers.php'
    );
    foreach (['curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'Authorization:', 'php://input', '$_POST', '$_SERVER', 'shell_exec(',
        'sleep(', 'usleep('] as $forbidden
    ) {
        red_checkout_p3e9d4b_evidence_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from the D4B core helper'
        );
    }

    red_checkout_p3e9d4b_evidence_delete($temporaryRoot);
    red_checkout_p3e9d4b_evidence_assert(
        !file_exists($temporaryRoot),
        'temporary evidence package is removed exactly'
    );
    echo 'Sandbox Checkout P3E-9D4B evidence self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_checkout_p3e9d4b_evidence_delete($temporaryRoot);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
