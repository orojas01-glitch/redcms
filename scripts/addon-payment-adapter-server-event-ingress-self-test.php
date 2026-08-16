<?php
/**
 * Dependency-free checks for the closed P3A-4 server-event ingress contract.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_PAYMENT_ADAPTER_REGISTRAR_FIXTURE_ONLY', true);
require_once __DIR__ . '/addon-payment-adapter-registrar-self-test.php';
require_once $projectRoot .
    '/includes/addon_payment_adapter_server_event_ingress_helpers.php';

$assertions = 0;

function red_addon_payment_adapter_ingress_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_payment_adapter_ingress_test_headers(
    $body,
    $signature = null
) {
    return [
        'complete' => true,
        'headers' => [[
            'name' => 'Content-Type',
            'value' => 'application/json',
        ], [
            'name' => 'Content-Length',
            'value' => (string) strlen($body),
        ], [
            'name' => 'Stripe-Signature',
            'value' => $signature ??
                ('t=1720000000,v1=' . str_repeat('a', 64)),
        ]],
    ];
}

try {
    $migration =
        "CREATE TABLE RED_Addon_Stripe_Registrar_Fixture_Attempts (\n" .
        "  RecordID bigint unsigned NOT NULL AUTO_INCREMENT,\n" .
        "  PRIMARY KEY (RecordID)\n" .
        ") ENGINE=InnoDB;\n";
    $entrypoint = "<?php\nreturn static function (\$registry): void {\n" .
        "    \$registry->registerAdapter(" .
        var_export($packageId . '/checkout', true) .
        ", static function (): void { file_put_contents(" .
        var_export($adapterHandlerMarker, true) . ", 'invoked'); });\n" .
        "    \$registry->registerRoute(" .
        var_export($packageId . '/provider-events', true) .
        ", static function (): void { file_put_contents(" .
        var_export($routeHandlerMarker, true) . ", 'invoked'); });\n" .
        "};\n";
    red_addon_payment_adapter_registrar_test_write_package(
        $packageDirectory,
        $packageId,
        $entrypoint,
        $migration
    );
    $package = red_addon_payment_adapter_registrar_test_package(
        $packageId,
        $fixtureProject
    );
    $databasePlan =
        red_addon_payment_adapter_registrar_test_database_plan($package);
    $registrarPlan = red_addon_payment_adapter_validate_registrar(
        $package,
        $databasePlan
    );
    red_addon_payment_adapter_ingress_test_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid(
            $registrarPlan
        )
            && !file_exists($adapterHandlerMarker)
            && !file_exists($routeHandlerMarker),
        'fixture supplies registration-only evidence without invoking handlers'
    );

    $plan = red_addon_payment_adapter_server_event_ingress_plan(
        $package,
        $registrarPlan
    );
    red_addon_payment_adapter_ingress_test_assert(
        red_addon_payment_adapter_server_event_ingress_plan_is_valid($plan)
            && $plan['ingressContractReady']
            && !$plan['enableReady']
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
        'readiness proves only the closed ingress contract'
    );
    red_addon_payment_adapter_ingress_test_assert(
        $plan['method'] === 'POST'
            && $plan['contentType'] === 'application/json'
            && $plan['requiredHeaders'] === [
                'Content-Type',
                'Content-Length',
                'Stripe-Signature',
            ]
            && $plan['maximumBodyBytes'] === 65536
            && array_column($plan['blockers'], 'code') === [
                'atomic_payment_adapter_enablement_required',
            ],
        'ingress uses one bounded exact transport shape and leaves enablement blocked'
    );
    $repeatPlan = red_addon_payment_adapter_server_event_ingress_plan(
        $package,
        $registrarPlan
    );
    red_addon_payment_adapter_ingress_test_assert(
        hash_equals($plan['planSha256'], $repeatPlan['planSha256'])
            && hash_equals(
                $plan['ingressContractSha256'],
                $repeatPlan['ingressContractSha256']
            ),
        'unchanged ingress readiness evidence is deterministic'
    );

    $rawBody =
        '{"id":"evt_test_1042","type":"checkout.session.completed"}';
    $signature = 't=1720000000,v1=' . str_repeat('a', 64);
    $headers = red_addon_payment_adapter_ingress_test_headers(
        $rawBody,
        $signature
    );
    $capture = red_addon_payment_adapter_server_event_capture(
        $package,
        $registrarPlan,
        'POST',
        $plan['serverEventPath'],
        $headers,
        $rawBody,
        1720000001
    );
    red_addon_payment_adapter_ingress_test_assert(
        $capture['available']
            && $capture['reason'] === 'captured'
            && $capture['packageId'] === $packageId
            && $capture['routeId'] === $packageId . '/provider-events'
            && $capture['path'] === $plan['serverEventPath']
            && $capture['bodyBytes'] === strlen($rawBody)
            && hash_equals($capture['bodySha256'], hash('sha256', $rawBody))
            && red_addon_valid_sha256($capture['captureSha256'])
            && $capture['request'] instanceof
                RED_Addon_Payment_Adapter_Server_Event_Request,
        'exact request facts produce value-free capture evidence'
    );
    $verifiedBody = null;
    $verifiedSignature = null;
    $material = $capture['request']->verificationMaterial(
        $verifiedBody,
        $verifiedSignature
    );
    red_addon_payment_adapter_ingress_test_assert(
        $material === [
            'valid' => true,
            'bodyBytes' => strlen($rawBody),
            'bodySha256' => hash('sha256', $rawBody),
            'signaturePresent' => true,
        ]
            && $verifiedBody === $rawBody
            && $verifiedSignature === $signature,
        'verification receives the exact unmodified raw body and complete header'
    );

    ob_start();
    var_dump($capture['request']);
    $debugOutput = (string) ob_get_clean();
    $encodedCapture = json_encode(
        $capture,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    $castOutput = var_export((array) $capture['request'], true);
    red_addon_payment_adapter_ingress_test_assert(
        is_string($encodedCapture)
            && !str_contains($encodedCapture, $rawBody)
            && !str_contains($encodedCapture, $signature)
            && !str_contains($debugOutput, $rawBody)
            && !str_contains($debugOutput, $signature)
            && !str_contains($castOutput, $rawBody)
            && !str_contains($castOutput, $signature),
        'JSON, debug output, and object casts cannot disclose verification bytes'
    );
    $serializationRefused = false;
    try {
        serialize($capture['request']);
    } catch (LogicException $exception) {
        $serializationRefused = true;
    }
    $cloneRefused = false;
    try {
        clone $capture['request'];
    } catch (LogicException $exception) {
        $cloneRefused = true;
    }
    red_addon_payment_adapter_ingress_test_assert(
        $serializationRefused && $cloneRefused,
        'verification material cannot be serialized or cloned'
    );

    $opaqueBody = '{not-json';
    $opaqueCapture = red_addon_payment_adapter_server_event_capture(
        $package,
        $registrarPlan,
        'POST',
        $plan['serverEventPath'],
        red_addon_payment_adapter_ingress_test_headers(
            $opaqueBody,
            'opaque-signature-value'
        ),
        $opaqueBody,
        1720000001
    );
    red_addon_payment_adapter_ingress_test_assert(
        $opaqueCapture['available'],
        'core preserves opaque body and signature bytes without parsing or verification'
    );

    $tamperedPlan = $plan;
    $tamperedPlan['maximumBodyBytes'] = 1;
    red_addon_payment_adapter_ingress_test_assert(
        !red_addon_payment_adapter_server_event_ingress_plan_is_valid(
            $tamperedPlan
        ),
        'tampered ingress readiness evidence is refused'
    );
    $tamperedRegistrar = $registrarPlan;
    $tamperedRegistrar['registrationCount'] = 3;
    $unavailable = red_addon_payment_adapter_server_event_capture(
        $package,
        $tamperedRegistrar,
        'POST',
        $plan['serverEventPath'],
        $headers,
        $rawBody,
        1720000001
    );
    red_addon_payment_adapter_ingress_test_assert(
        !$unavailable['available']
            && $unavailable['reason'] === 'ingress_unavailable',
        'tampered registrar evidence fails before request capture'
    );

    $requestRefusals = [
        'wrong method' => ['GET', $plan['serverEventPath'], $headers,
            $rawBody, 1720000001, 'method_invalid'],
        'query target' => ['POST', $plan['serverEventPath'] . '?x=1',
            $headers, $rawBody, 1720000001, 'target_invalid'],
        'empty body' => ['POST', $plan['serverEventPath'],
            red_addon_payment_adapter_ingress_test_headers(''), '',
            1720000001, 'headers_invalid'],
        'length mismatch' => ['POST', $plan['serverEventPath'], $headers,
            $rawBody . 'x', 1720000001, 'body_invalid'],
        'oversized body' => ['POST', $plan['serverEventPath'],
            red_addon_payment_adapter_ingress_test_headers(
                str_repeat('x', 65536)
            ), str_repeat('x', 65537), 1720000001, 'body_invalid'],
        'invalid receipt time' => ['POST', $plan['serverEventPath'],
            $headers, $rawBody, 0, 'receipt_time_invalid'],
    ];
    foreach ($requestRefusals as $label => $arguments) {
        [$method, $target, $capturedHeaders, $body, $receivedAt, $reason] =
            $arguments;
        $refused = red_addon_payment_adapter_server_event_capture(
            $package,
            $registrarPlan,
            $method,
            $target,
            $capturedHeaders,
            $body,
            $receivedAt
        );
        red_addon_payment_adapter_ingress_test_assert(
            !$refused['available'] && $refused['reason'] === $reason,
            $label . ' fails closed'
        );
    }

    $headerRefusals = [];
    $incomplete = $headers;
    $incomplete['complete'] = false;
    $headerRefusals['incomplete capture'] = $incomplete;
    $missing = $headers;
    array_pop($missing['headers']);
    $headerRefusals['missing header'] = $missing;
    $reordered = $headers;
    $reordered['headers'] = array_reverse($reordered['headers']);
    $headerRefusals['reordered headers'] = $reordered;
    $extra = $headers;
    $extra['headers'][] = ['name' => 'X-Extra', 'value' => '1'];
    $headerRefusals['extra header'] = $extra;
    $wrongType = $headers;
    $wrongType['headers'][0]['value'] = 'application/json; charset=utf-8';
    $headerRefusals['non-exact content type'] = $wrongType;
    $badLength = $headers;
    $badLength['headers'][1]['value'] = '00057';
    $headerRefusals['non-canonical length'] = $badLength;
    $badSignature = $headers;
    $badSignature['headers'][2]['value'] = "signature\nvalue";
    $headerRefusals['control in signature'] = $badSignature;
    foreach ($headerRefusals as $label => $capturedHeaders) {
        $refused = red_addon_payment_adapter_server_event_capture(
            $package,
            $registrarPlan,
            'POST',
            $plan['serverEventPath'],
            $capturedHeaders,
            $rawBody,
            1720000001
        );
        red_addon_payment_adapter_ingress_test_assert(
            !$refused['available']
                && $refused['reason'] === 'headers_invalid',
            $label . ' fails closed'
        );
    }

    red_addon_payment_adapter_ingress_test_assert(
        !file_exists($adapterHandlerMarker)
            && !file_exists($routeHandlerMarker),
        'all ingress operations leave adapter and route handlers uninvoked'
    );
    $helperSource = (string) file_get_contents(
        $projectRoot .
            '/includes/addon_payment_adapter_server_event_ingress_helpers.php'
    );
    red_addon_payment_adapter_ingress_test_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|php:\/\/input|\bjson_decode\s*\(|\bmysqli_|\bPDO\b|\bgetenv\s*\(|\bcurl_|\bfsockopen\s*\(|\bstream_socket_client\s*\(|\bfile_(?:get|put)_contents\s*\(|\bfopen\s*\(|\bheader\s*\(|\bhttp_response_code\s*\(|red_addon_(?:runtime_)?secret|->handler\s*\()/i',
            $helperSource
        ) !== 1,
        'ingress helper has no global request, parsing, file, database, secret, network, response, or handler path'
    );

    red_addon_payment_adapter_registrar_test_remove_tree($temporaryRoot);
    echo 'Payment adapter P3A-4 server-event ingress self-test passed: ' .
        $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_addon_payment_adapter_registrar_test_remove_tree($temporaryRoot);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
