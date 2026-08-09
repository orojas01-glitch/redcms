<?php
/** Pure checks for the typed package-runtime secret access boundary. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/addon_service_helpers.php';

$assertions = 0;

function red_addon_runtime_secret_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$access = new RED_Addon_Runtime_Secret_Access(
    'redcms.runtime-secret-fixture',
    ['payment.api-key' => 'runtime-fixture-secret']
);

try {
    $value = null;
    $resolved = $access->resolve('payment.api-key', $value);
    red_addon_runtime_secret_test_assert(
        !empty($resolved['valid'])
            && !empty($resolved['resolved'])
            && $resolved['reason'] === 'resolved'
            && $value === 'runtime-fixture-secret'
            && !array_key_exists('value', $resolved),
        'the service access object resolves only through a by-reference value'
    );

    $missing = $access->resolve('payment.missing', $value);
    red_addon_runtime_secret_test_assert(
        empty($missing['valid'])
            && $missing['reason'] === 'secret_unavailable'
            && $value === null,
        'a service cannot resolve a setting outside its own secret map'
    );

    try {
        serialize($access);
        red_addon_runtime_secret_test_assert(
            false,
            'runtime secret access must not be serializable'
        );
    } catch (LogicException $exception) {
        red_addon_runtime_secret_test_assert(
            str_contains($exception->getMessage(), 'serialized'),
            'serialization refusal is explicit and value-free'
        );
    }
    $debug = print_r($access, true);
    red_addon_runtime_secret_test_assert(
        !str_contains($debug, 'runtime-fixture-secret'),
        'debug output does not disclose the secret value'
    );

    $manifest = [
        'id' => 'redcms.runtime-secret-fixture',
        'provides' => [
            'components' => [],
            'services' => ['runtime.secret'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'settings' => [],
    ];
    $registry = new RED_Addon_Runtime_Registry(
        'redcms.runtime-secret-fixture',
        $manifest
    );
    $registry->registerService(
        'runtime.secret',
        static function (RED_Addon_Service_Request $request) {
            $secret = null;
            $result = $request->secret('payment.api-key', $secret);
            if (empty($result['resolved']) || $secret === null) {
                return RED_Addon_Service_Result::failure(
                    'secret_unavailable'
                );
            }
            return RED_Addon_Service_Result::success([
                'configured' => true,
                'secretLength' => strlen($secret),
            ]);
        }
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
        ['redcms.runtime-secret-fixture'],
        ['redcms.runtime-secret-fixture' => $registry],
        ['redcms.runtime-secret-fixture' => $access]
    );
    $safe = red_addon_service_invoke(
        'runtime.secret',
        'health.check',
        []
    );
    red_addon_runtime_secret_test_assert(
        !empty($safe['success'])
            && $safe['reason'] === 'completed'
            && ($safe['data']['secretLength'] ?? 0) === strlen(
                'runtime-fixture-secret'
            ),
        'a service may consume its own secret without returning the bytes'
    );

    $disclosureRegistry = new RED_Addon_Runtime_Registry(
        'redcms.runtime-secret-fixture',
        $manifest
    );
    $disclosureRegistry->registerService(
        'runtime.secret',
        static function (RED_Addon_Service_Request $request) {
            $secret = null;
            $request->secret('payment.api-key', $secret);
            return RED_Addon_Service_Result::success(['secret' => $secret]);
        }
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
        ['redcms.runtime-secret-fixture'],
        ['redcms.runtime-secret-fixture' => $disclosureRegistry],
        ['redcms.runtime-secret-fixture' => $access]
    );
    $disclosure = red_addon_service_invoke(
        'runtime.secret',
        'health.check',
        []
    );
    red_addon_runtime_secret_test_assert(
        empty($disclosure['success'])
            && $disclosure['reason'] === 'secret_disclosure'
            && $disclosure['data'] === [],
        'a service result containing secret bytes is rejected before dispatch returns'
    );

    $errorDisclosureRegistry = new RED_Addon_Runtime_Registry(
        'redcms.runtime-secret-fixture',
        $manifest
    );
    $errorDisclosureRegistry->registerService(
        'runtime.secret',
        static function (RED_Addon_Service_Request $request) {
            $secret = null;
            $request->secret('payment.api-key', $secret);
            return RED_Addon_Service_Result::failure($secret);
        }
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = new RED_Addon_Runtime_Context(
        ['redcms.runtime-secret-fixture'],
        ['redcms.runtime-secret-fixture' => $errorDisclosureRegistry],
        ['redcms.runtime-secret-fixture' => $access]
    );
    $errorDisclosure = red_addon_service_invoke(
        'runtime.secret',
        'health.check',
        []
    );
    red_addon_runtime_secret_test_assert(
        empty($errorDisclosure['success'])
            && $errorDisclosure['reason'] === 'secret_disclosure'
            && $errorDisclosure['error'] === '',
        'a service error code containing secret bytes is rejected'
    );

    $nodes = 0;
    red_addon_runtime_secret_test_assert(
        red_addon_runtime_secret_data_is_safe(
            ['ok' => true, 'nested' => ['count' => 2]],
            $access,
            0,
            $nodes
        )
            && !red_addon_runtime_secret_data_is_safe(
                ['nested' => ['value' => 'runtime-fixture-secret']],
                $access,
                0,
                $nodes
            ),
        'nested service response scanning remains value-free and bounded'
    );

    echo 'Add-on runtime secret self-test passed (' . $assertions
        . " assertions).\n";
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
}

?>
