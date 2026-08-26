<?php
/** Internal Stripe subscription webhook handler composition. */

require_once __DIR__ . '/addon_webhook_request_helpers.php';
require_once __DIR__
    . '/addon_subscription_event_delivery_coordinator_helpers.php';

if (!function_exists('red_addon_subscription_event_webhook_data')) {
    function red_addon_subscription_event_webhook_data($delivery)
    {
        if (!is_array($delivery)
            || !in_array(
                $delivery['status'] ?? '',
                ['applied', 'replayed', 'refused'],
                true
            )
            || !in_array($delivery['providerEventType'] ?? '', [
                'checkout.session.completed', 'invoice.paid',
                'invoice.payment_failed',
                'customer.subscription.deleted',
                'checkout.session.expired',
            ], true)
            || !red_addon_valid_sha256(
                $delivery['eventRefSha256'] ?? null
            )
            || !red_addon_valid_sha256(
                $delivery['eventEvidenceSha256'] ?? null
            )
            || !red_addon_valid_sha256(
                $delivery['lifecycleResultSha256'] ?? null
            )
            || ($delivery['journalCompleted'] ?? false) !== true
            || ($delivery['restartable'] ?? true) !== false
            || ($delivery['rawBodyIncluded'] ?? true) !== false
            || ($delivery['signatureHeaderIncluded'] ?? true) !== false
            || ($delivery['secretIncluded'] ?? true) !== false
            || ($delivery['customerDataIncluded'] ?? true) !== false
            || ($delivery['paymentMethodDataIncluded'] ?? true) !== false
            || ($delivery['addressDataIncluded'] ?? true) !== false
            || ($delivery['routeExposure'] ?? true) !== false
            || ($delivery['networkAccess'] ?? true) !== false
            || ($delivery['providerContact'] ?? true) !== false
            || ($delivery['payment'] ?? true) !== false
            || ($delivery['browserNavigation'] ?? true) !== false
            || ($delivery['deployment'] ?? true) !== false
        ) {
            return null;
        }
        $status = $delivery['status'];
        $refused = $status === 'refused';
        if (($refused && ($delivery['valid'] ?? true) !== false)
            || (!$refused && ($delivery['valid'] ?? false) !== true)
            || !is_bool($delivery['lifecycleApplied'] ?? null)
            || !is_bool($delivery['lifecycleReplayed'] ?? null)
            || !is_bool($delivery['replayed'] ?? null)
            || ($status === 'replayed'
                && ($delivery['replayed'] ?? false) !== true)
            || ($status === 'applied'
                && !($delivery['lifecycleApplied']
                    || $delivery['lifecycleReplayed']))
        ) {
            return null;
        }
        return [
            'acknowledged' => true,
            'outcome' => $refused
                ? 'subscription_event_refused'
                : ($status === 'replayed'
                    ? 'subscription_event_replayed'
                    : 'subscription_event_applied'),
            'status' => $status,
            'providerEventType' => $delivery['providerEventType'],
            'eventRefSha256' => $delivery['eventRefSha256'],
            'eventEvidenceSha256' =>
                $delivery['eventEvidenceSha256'],
            'lifecycleResultSha256' =>
                $delivery['lifecycleResultSha256'],
            'applied' => !$refused
                && ($delivery['lifecycleApplied'] ?? false),
            'recovered' => !$refused
                && ($delivery['lifecycleReplayed'] ?? false),
            'replayed' => ($delivery['replayed'] ?? false),
            'rawBodyIncluded' => false,
            'signatureHeaderIncluded' => false,
            'secretIncluded' => false,
            'customerDataIncluded' => false,
            'paymentMethodDataIncluded' => false,
            'addressDataIncluded' => false,
            'routeExposure' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'payment' => false,
            'browserNavigation' => false,
            'deployment' => false,
        ];
    }
}

if (!function_exists('red_addon_subscription_event_webhook_handle')) {
    function red_addon_subscription_event_webhook_handle(
        $request,
        $binding,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $completedAtEpoch
    ) {
        if (!$request instanceof RED_Addon_Webhook_Request
            || $request->route()
                !== 'redcms.store-lite-stripe-checkout/provider-events'
            || !red_addon_subscription_event_binding_valid($binding)
            || !is_callable($signatureVerifier)
            || !is_callable($eventProjector)
            || !is_callable($journalInvoker)
            || !is_callable($serviceInvoker)
            || !is_callable($adapterInvoker)
            || !is_int($completedAtEpoch)
            || $completedAtEpoch < $request->receivedAt()
            || $completedAtEpoch > 4102444800
        ) {
            return RED_Addon_Webhook_Result::refused(
                'subscription_webhook_handler_refused',
                500
            );
        }
        $endpointSecret = null;
        $resolution = $request->secret(
            'stripe.webhook-secret',
            $endpointSecret
        );
        if (($resolution['resolved'] ?? false) !== true
            || ($resolution['valid'] ?? false) !== true
            || ($resolution['reason'] ?? '') !== 'resolved'
            || !is_string($endpointSecret)
            || !str_starts_with($endpointSecret, 'whsec_')
            || strlen($endpointSecret) < 16
            || strlen($endpointSecret) > 255
            || preg_match('/[^\x21-\x7E]/', $endpointSecret) === 1
        ) {
            $endpointSecret = null;
            return RED_Addon_Webhook_Result::refused(
                'subscription_webhook_secret_unavailable',
                500
            );
        }
        try {
            $verify = static function (
                string $rawBody,
                string $signatureHeader,
                int $receivedAt
            ) use (&$endpointSecret, $signatureVerifier): array {
                if (!is_string($endpointSecret)) {
                    return [];
                }
                return $signatureVerifier(
                    $rawBody,
                    $signatureHeader,
                    $endpointSecret,
                    $receivedAt
                );
            };
            $delivery = red_addon_subscription_event_delivery_coordinate(
                $binding,
                [
                    'rawBody' => $request->rawBody(),
                    'signatureHeader' => $request->signatureHeader(),
                    'receivedAt' => $request->receivedAt(),
                ],
                $verify,
                $eventProjector,
                $journalInvoker,
                $serviceInvoker,
                $adapterInvoker,
                $completedAtEpoch
            );
        } catch (Throwable $throwable) {
            $delivery = null;
        } finally {
            $endpointSecret = null;
        }
        if (!is_array($delivery)) {
            return RED_Addon_Webhook_Result::refused(
                'subscription_webhook_delivery_failed',
                500
            );
        }
        $data = red_addon_subscription_event_webhook_data($delivery);
        if (is_array($data)) {
            return RED_Addon_Webhook_Result::accepted($data);
        }
        if (($delivery['stage'] ?? '') === 'signature_verification') {
            return RED_Addon_Webhook_Result::refused(
                'subscription_webhook_signature_refused',
                400
            );
        }
        return RED_Addon_Webhook_Result::refused(
            'subscription_webhook_retry_required',
            500
        );
    }
}

?>
