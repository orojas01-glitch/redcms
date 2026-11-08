<?php
/** Scoped runtime assembly for the Wompi Web Checkout webhook endpoint. */

require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_service_helpers.php';
require_once __DIR__ . '/addon_wompi_webhook_endpoint_helpers.php';

if (!function_exists('red_addon_wompi_webhook_runtime_versions')) {
    /** Exact package pair this endpoint was verified against. */
    function red_addon_wompi_webhook_runtime_versions()
    {
        return [
            'redcms.store-lite' => ['0.1.53'],
            'redcms.store-lite-wompi' => ['0.4.0'],
        ];
    }
}

if (!function_exists('red_addon_wompi_webhook_runtime_result')) {
    function red_addon_wompi_webhook_runtime_result($reason)
    {
        return [
            'invoked' => false,
            'success' => false,
            'route' => '',
            'package' => '',
            'statusCode' => 0,
            'data' => [],
            'error' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_wompi_webhook_runtime_run')) {
    function red_addon_wompi_webhook_runtime_run(
        $connection,
        $projectRoot,
        $request
    ) {
        $unavailable = red_addon_wompi_webhook_runtime_result(
            'runtime_unavailable'
        );
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || !is_dir($projectRoot)
            || !is_array($request)
            || array_keys($request) !== [
                'rawBody', 'signatureHeader', 'receivedAt',
            ]
            || !is_string($request['rawBody'])
            || !is_string($request['signatureHeader'])
            || !is_int($request['receivedAt'])
            || red_addon_runtime_current_context() !== null
        ) {
            return $unavailable;
        }
        $storeId = 'redcms.store-lite';
        $adapterId = 'redcms.store-lite-wompi';
        $route = $adapterId . '/provider-events';
        $versions = red_addon_wompi_webhook_runtime_versions();
        try {
            $catalog = red_addon_discover($projectRoot, [
                'cmsVersion' => '5.1.0',
                'phpVersion' => PHP_VERSION,
            ]);
            $store = $catalog['packages'][$storeId] ?? null;
            $adapter = $catalog['packages'][$adapterId] ?? null;
            if (($catalog['valid'] ?? false) !== true
                || !is_array($store)
                || !is_array($adapter)
                || !in_array(
                    $store['manifest']['version'] ?? '',
                    $versions[$storeId],
                    true
                )
                || !in_array(
                    $adapter['manifest']['version'] ?? '',
                    $versions[$adapterId],
                    true
                )
            ) {
                return $unavailable;
            }
            $errors = [];
            $order = red_addon_runtime_load_order(
                $catalog,
                [$storeId, $adapterId],
                $errors
            );
            if ($order !== [$storeId, $adapterId]
                || $errors !== []
                || red_addon_runtime_namespace_errors(
                    $catalog,
                    [$storeId, $adapterId]
                ) !== []
            ) {
                return $unavailable;
            }
            foreach ([$store, $adapter] as $package) {
                $report = red_addon_registry_package_report(
                    $connection,
                    $package
                );
                if (($report['status'] ?? '') !== 'enabled_current'
                    || ($report['errors'] ?? null) !== []
                ) {
                    return $unavailable;
                }
            }
            // Only the events secret enters this request. The private and
            // integrity keys stay outside the webhook context.
            $secret = red_addon_runtime_secret_access_for_package(
                $connection,
                $adapter,
                true,
                ['wompi.event-secret']
            );
            if (($secret['valid'] ?? false) !== true
                || ($secret['resolvedCount'] ?? 0) !== 1
                || ($secret['settingCount'] ?? 0) !== 1
                || !$secret['access'] instanceof
                    RED_Addon_Runtime_Secret_Access
                || $secret['access']->packageId() !== $adapterId
                || $secret['access']->settingCount() !== 1
            ) {
                return $unavailable;
            }
            $context = new RED_Addon_Runtime_Context(
                $order,
                [
                    $storeId => red_addon_runtime_register_package($store),
                    $adapterId =>
                        red_addon_runtime_register_package($adapter),
                ],
                [$adapterId => $secret['access']]
            );
            red_addon_runtime_set_request_context($context);
            if (red_addon_runtime_owner('services', 'commerce.orders')
                    !== $storeId
                || red_addon_runtime_owner(
                    'adapters',
                    $adapterId . '/checkout'
                ) !== $adapterId
                || red_addon_runtime_owner('routes', $route) !== $adapterId
                || !is_callable(red_addon_runtime_handler('routes', $route))
                || !class_exists(
                    'RED_CMS_Store_Lite_Wompi_Event_Ingress_Coordinator',
                    false
                )
                || !class_exists(
                    'RED_CMS_Store_Lite_Wompi_Transaction_Lookup_Transport',
                    false
                )
            ) {
                return $unavailable;
            }
            $manifest = red_addon_runtime_manifest($adapterId);
            $access = red_addon_runtime_secret_access($adapterId);
            if (!is_array($manifest)
                || !in_array(
                    'sandbox.wompi.co',
                    $manifest['outboundHosts'] ?? [],
                    true
                )
                || !$access instanceof RED_Addon_Runtime_Secret_Access
                || $access->settingCount() !== 1
            ) {
                return $unavailable;
            }
            $handler = static fn (
                RED_Addon_Webhook_Request $webhookRequest
            ): RED_Addon_Webhook_Result =>
                red_addon_wompi_webhook_handle(
                    $webhookRequest,
                    [
                        'RED_CMS_Store_Lite_Wompi_Event_Ingress_Coordinator',
                        'handle',
                    ],
                    [
                        'RED_CMS_Store_Lite_Wompi_Transaction_Lookup_Transport',
                        'get',
                    ],
                    static fn ($service, $operation, $input) =>
                        red_addon_service_invoke(
                            $service,
                            $operation,
                            $input
                        ),
                    max(time(), $request['receivedAt'])
                );
            return red_addon_webhook_invoke_registered(
                $route,
                $request['rawBody'],
                $request['signatureHeader'],
                $request['receivedAt'],
                $adapterId,
                $handler,
                $manifest,
                $access
            );
        } catch (Throwable $throwable) {
            return $unavailable;
        } finally {
            unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
        }
    }
}

if (!function_exists('red_addon_wompi_webhook_dispatch_current')) {
    function red_addon_wompi_webhook_dispatch_current(
        $connection,
        $projectRoot
    ) {
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        $target = $_SERVER['REQUEST_URI'] ?? null;
        if (!red_addon_wompi_webhook_candidate($target)
            || !red_addon_wompi_webhook_endpoint_enabled()
        ) {
            return red_addon_subscription_webhook_endpoint_result();
        }
        $capture = $method === 'POST'
            ? red_addon_wompi_webhook_capture_current()
            : red_addon_wompi_webhook_capture_result();
        return red_addon_wompi_webhook_dispatch(
            $method,
            $target,
            $capture,
            true,
            static fn (array $request): array =>
                red_addon_wompi_webhook_runtime_run(
                    $connection,
                    $projectRoot,
                    $request
                )
        );
    }
}

?>
