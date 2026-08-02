<?php

require_once dirname(__DIR__) . '/includes/addon_service_helpers.php';

$assertions = 0;
$calls = 0;
function red_addon_service_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$manifest = [
    'id' => 'redcms.service-fixture',
    'provides' => [
        'components' => [],
        'services' => ['fixture.catalog'],
        'adminTools' => [],
        'adapters' => [],
    ],
    'componentEditors' => [],
    'routes' => [],
];

try {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $missing = red_addon_service_invoke(
        'fixture.catalog',
        'list',
        ['limit' => 10]
    );
    red_addon_service_test_assert(
        empty($missing['invoked'])
            && $missing['reason'] === 'service_unavailable',
        'a service cannot run without enabled request-local ownership'
    );

    $registry = new RED_Addon_Runtime_Registry(
        'redcms.service-fixture',
        $manifest
    );
    $registry->registerService(
        'fixture.catalog',
        static function (RED_Addon_Service_Request $request) use (&$calls) {
            $calls++;
            return RED_Addon_Service_Result::success([
                'operation' => $request->operation(),
                'service' => $request->service(),
                'items' => [[
                    'id' => $request->input()['productId'],
                    'available' => true,
                ]],
            ]);
        }
    );
    $registry->assertComplete();
    $context = new RED_Addon_Runtime_Context(
        ['redcms.service-fixture'],
        ['redcms.service-fixture' => $registry]
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = $context;

    $invalidInputs = [
        ['service' => 'Fixture.catalog', 'operation' => 'read', 'input' => []],
        ['service' => 'fixture.catalog', 'operation' => 'Read', 'input' => []],
        ['service' => 'fixture.catalog', 'operation' => 'read', 'input' => ['bad-key' => 1]],
        ['service' => 'fixture.catalog', 'operation' => 'read', 'input' => ['price' => 1.5]],
        ['service' => 'fixture.catalog', 'operation' => 'read', 'input' => ['object' => new stdClass()]],
        ['service' => 'fixture.catalog', 'operation' => 'read', 'input' => ['deep' => [[[[[]]]]]]],
        ['service' => 'fixture.catalog', 'operation' => 'read', 'input' => ['text' => str_repeat('x', 4097)]],
    ];
    foreach ($invalidInputs as $invalid) {
        $refused = red_addon_service_invoke(
            $invalid['service'],
            $invalid['operation'],
            $invalid['input']
        );
        red_addon_service_test_assert(
            empty($refused['invoked'])
                && $refused['reason'] === 'invalid_request'
                && $calls === 0,
            'invalid service input is refused before handler invocation'
        );
    }

    $completed = red_addon_service_invoke(
        'fixture.catalog',
        'product.read',
        ['productId' => 42, 'includeDraft' => false]
    );
    red_addon_service_test_assert(
        $completed === [
            'invoked' => true,
            'success' => true,
            'service' => 'fixture.catalog',
            'package' => 'redcms.service-fixture',
            'operation' => 'product.read',
            'data' => [
                'operation' => 'product.read',
                'service' => 'fixture.catalog',
                'items' => [[
                    'id' => 42,
                    'available' => true,
                ]],
            ],
            'error' => '',
            'reason' => 'completed',
        ] && $calls === 1,
        'the exact owner receives one typed request and returns bounded typed data'
    );

    $modes = [
        'failure' => static fn () => RED_Addon_Service_Result::failure('not_found'),
        'malformed' => static fn () => ['ok' => true],
        'output' => static function () {
            echo 'forbidden';
            return RED_Addon_Service_Result::success();
        },
        'exception' => static function () {
            throw new RuntimeException('contained');
        },
        'buffer' => static function () {
            ob_end_clean();
            return RED_Addon_Service_Result::success();
        },
    ];
    foreach ($modes as $mode => $handler) {
        $modeRegistry = new RED_Addon_Runtime_Registry(
            'redcms.service-fixture',
            $manifest
        );
        $modeRegistry->registerService('fixture.catalog', $handler);
        $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
            ['redcms.service-fixture'],
            ['redcms.service-fixture' => $modeRegistry]
        );
        $contained = red_addon_service_invoke(
            'fixture.catalog',
            'product.read',
            []
        );
        $expectedReason = [
            'failure' => 'service_error',
            'malformed' => 'invalid_result',
            'output' => 'service_output',
            'exception' => 'service_failed',
            'buffer' => 'service_failed',
        ][$mode];
        red_addon_service_test_assert(
            $contained['invoked'] === true
                && $contained['reason'] === $expectedReason
                && ($mode === 'failure'
                    ? $contained['error'] === 'not_found'
                        && empty($contained['success'])
                    : $contained['data'] === []
                        && empty($contained['success'])),
            'service ' . $mode . ' behavior is contained by the typed boundary'
        );
    }

    red_addon_service_test_assert(
        ob_get_level() === 0,
        'all service invocation paths restore the output buffer stack'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
}

fwrite(
    STDOUT,
    'Add-on typed service invocation self-test passed ('
        . $assertions . " assertions).\n"
);

?>
