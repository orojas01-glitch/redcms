<?php
/** Non-routable PayPal webhook-ingress contract acceptance. */

define('RED_PAYPAL_REGISTRAR_FIXTURE_ONLY', true);
require_once __DIR__
    . '/addon-payment-adapter-paypal-registrar-self-test.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_server_event_ingress_helpers.php';

$assertions = 0;
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-paypal-ingress-' . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$fixturePackage = $fixtureProject . '/addons/redcms/store-lite-paypal';
$packageId = 'redcms.store-lite-paypal';
$externalPackageRoot = '/Users/oscarrojas/Documents/'
    . 'redcms-store-lite-paypal/package';

function red_paypal_ingress_assert($condition, $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_paypal_ingress_headers(int $bodyBytes): array
{
    return [
        'complete' => true,
        'headers' => [[
            'name' => 'Content-Type',
            'value' => 'application/json',
        ], [
            'name' => 'Content-Length',
            'value' => (string) $bodyBytes,
        ], [
            'name' => 'PayPal-Auth-Algo',
            'value' => 'SHA256withRSA',
        ], [
            'name' => 'PayPal-Cert-Url',
            'value' => 'https://api-m.sandbox.paypal.com/certs/test',
        ], [
            'name' => 'PayPal-Transmission-Id',
            'value' => '7f6c9e20-1234-4abc-9def-0123456789ab',
        ], [
            'name' => 'PayPal-Transmission-Sig',
            'value' => str_repeat('S', 96),
        ], [
            'name' => 'PayPal-Transmission-Time',
            'value' => '2026-09-02T14:30:00Z',
        ]],
    ];
}

try {
    red_paypal_registrar_copy($externalPackageRoot, $fixturePackage);
    $package = red_addon_validate_manifest(
        $packageId,
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $databasePlan = red_paypal_registrar_database_plan($package);
    $registrarPlan = red_addon_payment_adapter_validate_registrar(
        $package,
        $databasePlan
    );
    red_paypal_ingress_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid($registrarPlan),
        'exact registration-only PayPal evidence is available'
    );

    $plan = red_addon_payment_adapter_server_event_ingress_plan(
        $package,
        $registrarPlan
    );
    red_paypal_ingress_assert(
        red_addon_payment_adapter_server_event_ingress_plan_is_valid($plan)
            && $plan['profileId'] === 'store_lite_paypal_adapter_v1'
            && $plan['ingressContractReady'],
        'exact PayPal package produces one valid non-routable ingress plan'
    );
    red_paypal_ingress_assert(
        $plan['serverEventRoute']
            === 'redcms.store-lite-paypal/provider-events'
            && $plan['serverEventPath']
                === '/addons/redcms/store-lite-paypal/provider-events'
            && $plan['method'] === 'POST'
            && $plan['contentType'] === 'application/json'
            && $plan['requiredHeaders'] === [
                'Content-Type',
                'Content-Length',
                'PayPal-Auth-Algo',
                'PayPal-Cert-Url',
                'PayPal-Transmission-Id',
                'PayPal-Transmission-Sig',
                'PayPal-Transmission-Time',
            ]
            && $plan['maximumBodyBytes'] === 65536,
        'ingress binds exact route and PayPal verification headers'
    );
    red_paypal_ingress_assert(
        !$plan['enableReady']
            && !$plan['activationSupported']
            && !$plan['stateMutation']
            && !$plan['runtimePublication']
            && !$plan['requestRead']
            && !$plan['handlerInvocation']
            && !$plan['secretResolution']
            && !$plan['signatureVerification']
            && !$plan['jsonParsing']
            && !$plan['databaseAccess']
            && !$plan['networkAccess']
            && !$plan['routeExposure'],
        'ingress planning has no request, verification, or runtime effect'
    );
    red_paypal_ingress_assert(
        $plan['blockers'] === [[
            'code' => 'atomic_payment_adapter_enablement_required',
        ]],
        'only atomic enablement remains after ingress planning'
    );

    $rawBody = json_encode([
        'id' => 'WH-TEST-PAYPAL-INGRESS-0001',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
    ], JSON_UNESCAPED_SLASHES);
    $headers = red_paypal_ingress_headers(strlen($rawBody));
    $capture = red_addon_payment_adapter_server_event_capture(
        $package,
        $registrarPlan,
        'POST',
        '/addons/redcms/store-lite-paypal/provider-events',
        $headers,
        $rawBody,
        1788359400
    );
    red_paypal_ingress_assert(
        $capture['available']
            && $capture['reason'] === 'captured'
            && $capture['bodyBytes'] === strlen($rawBody)
            && hash_equals($capture['bodySha256'], hash('sha256', $rawBody))
            && $capture['request']
                instanceof RED_Addon_Payment_Adapter_Server_Event_Request,
        'exact raw body and canonical PayPal headers are captured transiently'
    );
    $capturedBody = null;
    $verificationHeaders = null;
    $material = $capture['request']->verificationMaterial(
        $capturedBody,
        $verificationHeaders
    );
    $decodedHeaders = json_decode(
        (string) $verificationHeaders,
        true,
        16,
        JSON_THROW_ON_ERROR
    );
    red_paypal_ingress_assert(
        $material['valid']
            && $material['signaturePresent']
            && $capturedBody === $rawBody
            && $decodedHeaders === [
                'PayPal-Auth-Algo' => 'SHA256withRSA',
                'PayPal-Cert-Url' =>
                    'https://api-m.sandbox.paypal.com/certs/test',
                'PayPal-Transmission-Id' =>
                    '7f6c9e20-1234-4abc-9def-0123456789ab',
                'PayPal-Transmission-Sig' => str_repeat('S', 96),
                'PayPal-Transmission-Time' => '2026-09-02T14:30:00Z',
            ],
        'raw verification material is available only through explicit outputs'
    );
    $encodedRequest = json_encode($capture['request']);
    red_paypal_ingress_assert(
        is_string($encodedRequest)
            && !str_contains($encodedRequest, $rawBody)
            && !str_contains($encodedRequest, 'PayPal-Transmission-Sig')
            && !str_contains($encodedRequest, str_repeat('S', 20)),
        'ordinary serialization contains neither body nor verification headers'
    );

    foreach ([
        'missing' => static function (array $value): array {
            array_pop($value['headers']);
            return $value;
        },
        'extra' => static function (array $value): array {
            $value['headers'][] = ['name' => 'Other', 'value' => 'x'];
            return $value;
        },
        'reordered' => static function (array $value): array {
            [$value['headers'][2], $value['headers'][3]] = [
                $value['headers'][3], $value['headers'][2],
            ];
            return $value;
        },
        'empty signature' => static function (array $value): array {
            $value['headers'][5]['value'] = '';
            return $value;
        },
    ] as $name => $mutate) {
        $refused = red_addon_payment_adapter_server_event_capture(
            $package,
            $registrarPlan,
            'POST',
            '/addons/redcms/store-lite-paypal/provider-events',
            $mutate($headers),
            $rawBody,
            1788359400
        );
        red_paypal_ingress_assert(
            !$refused['available'] && $refused['reason'] === 'headers_invalid',
            $name . ' PayPal header capture fails closed'
        );
    }

    $wrongTarget = red_addon_payment_adapter_server_event_capture(
        $package,
        $registrarPlan,
        'POST',
        '/bin/paypal_response.php',
        $headers,
        $rawBody,
        1788359400
    );
    red_paypal_ingress_assert(
        !$wrongTarget['available']
            && $wrongTarget['reason'] === 'target_invalid',
        'legacy PayPal PDT route cannot satisfy Store Lite ingress'
    );
} finally {
    red_paypal_registrar_remove($temporaryRoot);
}

echo 'PayPal payment-adapter ingress self-test passed: '
    . $assertions . " assertions.\n";
echo "No signature verification, handler, secret, network, PayPal, payment, webhook response, database, route, or Store Lite effect occurred.\n";

?>
