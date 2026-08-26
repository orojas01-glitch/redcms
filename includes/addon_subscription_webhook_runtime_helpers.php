<?php
/** Scoped runtime assembly for the Stripe subscription webhook endpoint. */

require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_subscription_webhook_endpoint_helpers.php';

if (!function_exists('red_addon_subscription_webhook_runtime_result')) {
    function red_addon_subscription_webhook_runtime_result($reason)
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

if (!function_exists('red_addon_subscription_webhook_runtime_run')) {
    function red_addon_subscription_webhook_runtime_run(
        $connection,
        $projectRoot,
        $request
    ) {
        $unavailable = red_addon_subscription_webhook_runtime_result(
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
        $adapterId = 'redcms.store-lite-stripe-checkout';
        $route = $adapterId . '/provider-events';
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
                || ($store['manifest']['version'] ?? '') !== '0.1.50'
                || ($adapter['manifest']['version'] ?? '') !== '0.1.14'
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
            $secret = red_addon_runtime_secret_access_for_package(
                $connection,
                $adapter,
                true,
                ['stripe.webhook-secret']
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
            if (red_addon_runtime_owner(
                'services',
                'commerce.subscriptions'
            ) !== $storeId
                || red_addon_runtime_owner(
                    'adapters',
                    $adapterId . '/checkout'
                ) !== $adapterId
                || red_addon_runtime_owner('routes', $route) !== $adapterId
                || !is_callable(red_addon_runtime_handler('routes', $route))
            ) {
                return $unavailable;
            }
            $manifest = red_addon_runtime_manifest($adapterId);
            $access = red_addon_runtime_secret_access($adapterId);
            if (!is_array($manifest)
                || !$access instanceof RED_Addon_Runtime_Secret_Access
                || $access->settingCount() !== 1
            ) {
                return $unavailable;
            }
            $signatureVerifier = static fn (
                string $rawBody,
                string $signatureHeader,
                string $endpointSecret,
                int $receivedAt
            ): array =>
                RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
                    verify(
                        $rawBody,
                        $signatureHeader,
                        $endpointSecret,
                        $receivedAt
                    );
            $eventProjector = static function (
                array $envelope,
                string $rawBody
            ): array {
                $decoded =
                    RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode(
                        $rawBody
                    );
                return
                    RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::
                        project($envelope, $decoded['value'] ?? []);
            };
            $journal = static fn (string $operation, array $evidence): array =>
                red_addon_subscription_event_journal(
                    $connection,
                    $operation,
                    $evidence
                );
            $handler = static fn (
                RED_Addon_Webhook_Request $webhookRequest
            ): RED_Addon_Webhook_Result =>
                red_addon_subscription_event_webhook_handle(
                    $webhookRequest,
                    [
                        'storePackageId' => $storeId,
                        'storePackageVersion' => '0.1.50',
                        'storeService' => 'commerce.subscriptions',
                        'stripePackageId' => $adapterId,
                        'stripePackageVersion' => '0.1.14',
                        'stripeAdapter' => $adapterId . '/checkout',
                    ],
                    $signatureVerifier,
                    $eventProjector,
                    $journal,
                    static fn ($service, $operation, $input) =>
                        red_addon_service_invoke(
                            $service,
                            $operation,
                            $input
                        ),
                    static fn ($adapterName, $operation, $input) =>
                        red_addon_adapter_invoke(
                            $adapterName,
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

if (!function_exists('red_addon_subscription_webhook_dispatch_current')) {
    function red_addon_subscription_webhook_dispatch_current(
        $connection,
        $projectRoot
    ) {
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        $target = $_SERVER['REQUEST_URI'] ?? null;
        if (!red_addon_subscription_webhook_candidate($target)
            || !red_addon_subscription_webhook_endpoint_enabled()
        ) {
            return red_addon_subscription_webhook_endpoint_result();
        }
        $capture = $method === 'POST'
            ? red_addon_subscription_webhook_capture_current()
            : red_addon_subscription_webhook_capture_result();
        return red_addon_subscription_webhook_dispatch(
            $method,
            $target,
            $capture,
            true,
            static fn (array $request): array =>
                red_addon_subscription_webhook_runtime_run(
                    $connection,
                    $projectRoot,
                    $request
                )
        );
    }
}

?>
