<?php

require_once dirname(__DIR__) . '/includes/addon_adapter_helpers.php';

$assertions = 0;
$calls = 0;

function red_addon_adapter_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$packageId = 'redcms.adapter-fixture';
$adapterId = 'redcms.adapter-fixture/checkout';
$manifest = [
    'id' => $packageId,
    'provides' => [
        'components' => [],
        'services' => [],
        'adminTools' => [],
        'adapters' => [$adapterId],
    ],
    'componentEditors' => [],
    'routes' => [],
];

try {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $missing = red_addon_adapter_invoke(
        $adapterId,
        'checkout.prepare',
        ['orderId' => 'ord_fixture']
    );
    red_addon_adapter_test_assert(
        empty($missing['invoked'])
            && $missing['reason'] === 'adapter_unavailable',
        'an adapter cannot run without enabled request-local ownership'
    );

    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $registry->registerAdapter(
        $adapterId,
        static function (RED_Addon_Adapter_Request $request) use (&$calls) {
            $calls++;
            return RED_Addon_Adapter_Result::success([
                'operation' => $request->operation(),
                'adapter' => $request->adapter(),
                'orderId' => $request->input()['orderId'],
                'ready' => true,
            ]);
        }
    );
    $registry->assertComplete();
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $registry]
    );

    $invalidInputs = [
        ['adapter' => 'Redcms.adapter', 'operation' => 'read', 'input' => []],
        ['adapter' => $adapterId, 'operation' => 'Read', 'input' => []],
        ['adapter' => $adapterId, 'operation' => 'read', 'input' => ['bad-key' => 1]],
        ['adapter' => $adapterId, 'operation' => 'read', 'input' => ['price' => 1.5]],
        ['adapter' => $adapterId, 'operation' => 'read', 'input' => ['object' => new stdClass()]],
        ['adapter' => $adapterId, 'operation' => 'read', 'input' => ['deep' => [[[[[]]]]]]],
        ['adapter' => $adapterId, 'operation' => 'read', 'input' => ['text' => str_repeat('x', 4097)]],
    ];
    foreach ($invalidInputs as $invalid) {
        $refused = red_addon_adapter_invoke(
            $invalid['adapter'],
            $invalid['operation'],
            $invalid['input']
        );
        red_addon_adapter_test_assert(
            empty($refused['invoked'])
                && $refused['reason'] === 'invalid_request'
                && $calls === 0,
            'invalid adapter input is refused before handler invocation'
        );
    }

    $completed = red_addon_adapter_invoke(
        $adapterId,
        'checkout.prepare',
        ['orderId' => 'ord_fixture', 'amountMinor' => 5897]
    );
    red_addon_adapter_test_assert(
        $completed === [
            'invoked' => true,
            'success' => true,
            'adapter' => $adapterId,
            'package' => $packageId,
            'operation' => 'checkout.prepare',
            'data' => [
                'operation' => 'checkout.prepare',
                'adapter' => $adapterId,
                'orderId' => 'ord_fixture',
                'ready' => true,
            ],
            'error' => '',
            'reason' => 'completed',
        ] && $calls === 1,
        'the exact owner receives one typed request and returns bounded data'
    );

    $modes = [
        'failure' => static fn () =>
            RED_Addon_Adapter_Result::failure('checkout_unavailable'),
        'malformed' => static fn () => ['ok' => true],
        'output' => static function () {
            echo 'forbidden';
            return RED_Addon_Adapter_Result::success();
        },
        'exception' => static function () {
            throw new RuntimeException('contained');
        },
        'buffer' => static function () {
            ob_end_clean();
            return RED_Addon_Adapter_Result::success();
        },
    ];
    foreach ($modes as $mode => $handler) {
        $modeRegistry = new RED_Addon_Runtime_Registry(
            $packageId,
            $manifest
        );
        $modeRegistry->registerAdapter($adapterId, $handler);
        $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
            [$packageId],
            [$packageId => $modeRegistry]
        );
        $contained = red_addon_adapter_invoke(
            $adapterId,
            'checkout.prepare',
            []
        );
        $expectedReason = [
            'failure' => 'adapter_error',
            'malformed' => 'invalid_result',
            'output' => 'adapter_output',
            'exception' => 'adapter_failed',
            'buffer' => 'adapter_failed',
        ][$mode];
        red_addon_adapter_test_assert(
            $contained['invoked'] === true
                && $contained['reason'] === $expectedReason
                && ($mode === 'failure'
                    ? $contained['error'] === 'checkout_unavailable'
                        && empty($contained['success'])
                    : $contained['data'] === []
                        && empty($contained['success'])),
            'adapter ' . $mode . ' behavior is contained by the typed boundary'
        );
    }

    red_addon_adapter_test_assert(
        ob_get_level() === 0,
        'all adapter invocation paths restore the output buffer stack'
    );

    $secret = 'runtime_adapter_secret';
    $access = new RED_Addon_Runtime_Secret_Access(
        $packageId,
        ['provider.api-key' => $secret]
    );
    $secretRegistry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $secretRegistry->registerAdapter(
        $adapterId,
        static function (RED_Addon_Adapter_Request $request) {
            $value = null;
            $resolved = $request->secret('provider.api-key', $value);
            if (empty($resolved['resolved']) || !is_string($value)) {
                return RED_Addon_Adapter_Result::failure(
                    'secret_unavailable'
                );
            }
            return RED_Addon_Adapter_Result::success([
                'configured' => true,
                'secretLength' => strlen($value),
            ]);
        }
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $secretRegistry],
        [$packageId => $access]
    );
    $safe = red_addon_adapter_invoke(
        $adapterId,
        'configuration.check',
        []
    );
    red_addon_adapter_test_assert(
        !empty($safe['success'])
            && $safe['reason'] === 'completed'
            && ($safe['data']['secretLength'] ?? 0) === strlen($secret)
            && !str_contains(json_encode($safe), $secret),
        'an adapter may consume its own secret without returning the bytes'
    );

    $foreignRegistry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $foreignRegistry->registerAdapter(
        $adapterId,
        static function (RED_Addon_Adapter_Request $request) {
            $value = null;
            $resolved = $request->secret('provider.other-key', $value);
            return RED_Addon_Adapter_Result::success([
                'resolved' => !empty($resolved['resolved']),
                'valueReturned' => $value !== null,
            ]);
        }
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $foreignRegistry],
        [$packageId => $access]
    );
    $foreign = red_addon_adapter_invoke(
        $adapterId,
        'configuration.check',
        []
    );
    red_addon_adapter_test_assert(
        !empty($foreign['success'])
            && ($foreign['data']['resolved'] ?? null) === false
            && ($foreign['data']['valueReturned'] ?? null) === false,
        'an adapter cannot resolve a setting outside its package-bound map'
    );

    $disclosureRegistry = new RED_Addon_Runtime_Registry(
        $packageId,
        $manifest
    );
    $disclosureRegistry->registerAdapter(
        $adapterId,
        static function (RED_Addon_Adapter_Request $request) {
            $value = null;
            $request->secret('provider.api-key', $value);
            return RED_Addon_Adapter_Result::success(['secret' => $value]);
        }
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $disclosureRegistry],
        [$packageId => $access]
    );
    $disclosure = red_addon_adapter_invoke(
        $adapterId,
        'configuration.check',
        []
    );
    red_addon_adapter_test_assert(
        empty($disclosure['success'])
            && $disclosure['reason'] === 'secret_disclosure'
            && $disclosure['data'] === [],
        'adapter data containing secret bytes is rejected before return'
    );

    $errorDisclosureRegistry = new RED_Addon_Runtime_Registry(
        $packageId,
        $manifest
    );
    $errorDisclosureRegistry->registerAdapter(
        $adapterId,
        static function (RED_Addon_Adapter_Request $request) {
            $value = null;
            $request->secret('provider.api-key', $value);
            return RED_Addon_Adapter_Result::failure($value);
        }
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $errorDisclosureRegistry],
        [$packageId => $access]
    );
    $errorDisclosure = red_addon_adapter_invoke(
        $adapterId,
        'configuration.check',
        []
    );
    red_addon_adapter_test_assert(
        empty($errorDisclosure['success'])
            && $errorDisclosure['reason'] === 'secret_disclosure'
            && $errorDisclosure['error'] === '',
        'adapter error text containing secret bytes is rejected before return'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
}

fwrite(
    STDOUT,
    'Add-on typed adapter invocation self-test passed ('
        . $assertions . " assertions).\n"
);

?>
