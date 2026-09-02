<?php
/**
 * Closed, non-routable server-event ingress contract for P3A.
 *
 * This helper binds fresh registration-only adapter evidence to one exact
 * static POST route and captures only explicit raw transport facts. It does
 * not read request globals, resolve a secret, verify a provider signature,
 * parse JSON, invoke a handler, access a database, emit a response, publish a
 * route, or enable a package.
 */

require_once __DIR__ . '/addon_payment_adapter_registrar_helpers.php';

if (!class_exists('RED_Addon_Payment_Adapter_Server_Event_Request', false)) {
    final class RED_Addon_Payment_Adapter_Server_Event_Request implements JsonSerializable
    {
        private static ?WeakMap $verificationMaterial = null;
        private string $packageId;
        private string $routeId;
        private string $path;
        private string $contentType;
        private int $receivedAtUnix;
        private int $bodyBytes;
        private string $bodySha256;

        private static function materialStore(): WeakMap
        {
            if (!(self::$verificationMaterial instanceof WeakMap)) {
                self::$verificationMaterial = new WeakMap();
            }
            return self::$verificationMaterial;
        }

        public function __construct(
            string $packageId,
            string $routeId,
            string $path,
            string $contentType,
            int $receivedAtUnix,
            string $rawBody,
            string $signatureHeader
        ) {
            $bodySignedProfile = $packageId === 'redcms.store-lite-wompi';
            $signatureHeaderInvalid = $bodySignedProfile
                ? $signatureHeader !== ''
                : (strlen($signatureHeader) < 8
                    || strlen($signatureHeader) > 4096
                    || trim($signatureHeader) !== $signatureHeader
                    || preg_match(
                        '/[\x00-\x1F\x7F]/',
                        $signatureHeader
                    ) === 1);
            if (!red_addon_valid_package_id($packageId)
                || !red_addon_valid_capability($routeId)
                || strpos($routeId, $packageId . '/') !== 0
                || !red_addon_valid_route_path($path)
                || $contentType !== 'application/json'
                || $receivedAtUnix < 1
                || $receivedAtUnix > 4102444800
                || $rawBody === ''
                || strlen($rawBody) > 65536
                || $signatureHeaderInvalid
            ) {
                throw new InvalidArgumentException(
                    'Server-event request material is invalid.'
                );
            }
            $this->packageId = $packageId;
            $this->routeId = $routeId;
            $this->path = $path;
            $this->contentType = $contentType;
            $this->receivedAtUnix = $receivedAtUnix;
            $this->bodyBytes = strlen($rawBody);
            $this->bodySha256 = hash('sha256', $rawBody);
            self::materialStore()[$this] = [
                'rawBody' => $rawBody,
                'signatureHeader' => $signatureHeader,
            ];
        }

        public function packageId(): string
        {
            return $this->packageId;
        }

        public function routeId(): string
        {
            return $this->routeId;
        }

        public function metadata(): array
        {
            return [
                'packageId' => $this->packageId,
                'routeId' => $this->routeId,
                'path' => $this->path,
                'contentType' => $this->contentType,
                'receivedAtUnix' => $this->receivedAtUnix,
                'bodyBytes' => $this->bodyBytes,
                'bodySha256' => $this->bodySha256,
            ];
        }

        /**
         * Supplies unmodified verification inputs only through by-reference
         * outputs. The returned status contains no raw body or header value.
         */
        public function verificationMaterial(
            &$rawBody = null,
            &$signatureHeader = null
        ): array {
            $material = self::materialStore()[$this] ?? null;
            if (!is_array($material)) {
                $rawBody = null;
                $signatureHeader = null;
                return [
                    'valid' => false,
                    'bodyBytes' => 0,
                    'bodySha256' => '',
                    'signaturePresent' => false,
                ];
            }
            $rawBody = $material['rawBody'];
            $signatureHeader = $material['signatureHeader'];
            return [
                'valid' => true,
                'bodyBytes' => $this->bodyBytes,
                'bodySha256' => $this->bodySha256,
                'signaturePresent' => $material['signatureHeader'] !== '',
            ];
        }

        public function jsonSerialize(): array
        {
            return $this->metadata();
        }

        public function __serialize(): array
        {
            throw new LogicException(
                'Server-event request material cannot be serialized.'
            );
        }

        public function __clone(): void
        {
            throw new LogicException(
                'Server-event request material cannot be cloned.'
            );
        }

        public function __debugInfo(): array
        {
            return $this->metadata();
        }
    }
}

if (!function_exists('red_addon_payment_adapter_ingress_plan_result')) {
    function red_addon_payment_adapter_ingress_plan_result($packageId = '')
    {
        $packageId = is_string($packageId) ? $packageId : '';
        $wompiProfile = $packageId === 'redcms.store-lite-wompi';
        $paypalProfile = $packageId === 'redcms.store-lite-paypal';
        return [
            'valid' => false,
            'profileId' => $paypalProfile
                ? 'store_lite_paypal_adapter_v1'
                : ($wompiProfile
                    ? 'store_lite_wompi_adapter_v1'
                    : 'store_lite_stripe_checkout_adapter_v1'),
            'ingressContractReady' => false,
            'enableReady' => false,
            'activationSupported' => false,
            'stateMutation' => false,
            'runtimePublication' => false,
            'requestRead' => false,
            'handlerInvocation' => false,
            'secretResolution' => false,
            'signatureVerification' => false,
            'jsonParsing' => false,
            'databaseAccess' => false,
            'networkAccess' => false,
            'routeExposure' => false,
            'packageId' => $packageId,
            'version' => '',
            'serverEventRoute' => '',
            'serverEventPath' => '',
            'method' => 'POST',
            'contentType' => 'application/json',
            'requiredHeaders' => $paypalProfile
                ? [
                    'Content-Type',
                    'Content-Length',
                    'PayPal-Auth-Algo',
                    'PayPal-Cert-Url',
                    'PayPal-Transmission-Id',
                    'PayPal-Transmission-Sig',
                    'PayPal-Transmission-Time',
                ]
                : ($wompiProfile
                    ? ['Content-Type', 'Content-Length']
                    : [
                        'Content-Type',
                        'Content-Length',
                        'Stripe-Signature',
                    ]),
            'maximumBodyBytes' => 65536,
            'contractSha256' => '',
            'registrarPlanSha256' => '',
            'registrationSha256' => '',
            'manifestSha256' => '',
            'inventorySha256' => '',
            'ingressContractSha256' => '',
            'gates' => [
                'adapterContract' => 'not_checked',
                'registrarValidation' => 'not_checked',
                'serverEventIngress' => 'not_checked',
                'atomicEnablement' => 'not_implemented',
            ],
            'blockers' => [],
            'planSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_payment_adapter_ingress_plan_fingerprint')) {
    function red_addon_payment_adapter_ingress_plan_fingerprint(array $plan)
    {
        $material = $plan;
        unset($material['valid'], $material['planSha256']);
        $encoded = json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_payment_adapter_ingress_contract_fingerprint')) {
    function red_addon_payment_adapter_ingress_contract_fingerprint(array $plan)
    {
        $encoded = json_encode(
            [
                'schema' => 1,
                'profileId' => $plan['profileId'],
                'packageId' => $plan['packageId'],
                'version' => $plan['version'],
                'serverEventRoute' => $plan['serverEventRoute'],
                'serverEventPath' => $plan['serverEventPath'],
                'method' => $plan['method'],
                'contentType' => $plan['contentType'],
                'requiredHeaders' => $plan['requiredHeaders'],
                'maximumBodyBytes' => $plan['maximumBodyBytes'],
                'contractSha256' => $plan['contractSha256'],
                'registrarPlanSha256' => $plan['registrarPlanSha256'],
                'registrationSha256' => $plan['registrationSha256'],
                'manifestSha256' => $plan['manifestSha256'],
                'inventorySha256' => $plan['inventorySha256'],
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_payment_adapter_server_event_ingress_plan')) {
    function red_addon_payment_adapter_server_event_ingress_plan(
        array $package,
        array $registrarPlan
    ) {
        $packageId = is_string($package['id'] ?? null) ? $package['id'] : '';
        $result = red_addon_payment_adapter_ingress_plan_result($packageId);
        if (!red_addon_payment_adapter_registrar_preflight_is_valid(
            $registrarPlan
        )) {
            $result['errors'][] = 'payment_adapter_registrar_evidence_invalid';
            return $result;
        }
        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $profile = red_addon_payment_adapter_profile($manifest);
        $snapshot = red_addon_registry_snapshot($package);
        if (!red_addon_payment_adapter_profile_is_valid($profile)
            || !is_array($snapshot)
            || ($snapshot['id'] ?? null) !== $packageId
            || ($registrarPlan['packageId'] ?? null) !== $packageId
            || ($registrarPlan['version'] ?? null)
                !== ($snapshot['version'] ?? null)
            || !hash_equals(
                (string) ($registrarPlan['contractSha256'] ?? ''),
                $profile['contractSha256']
            )
            || !hash_equals(
                (string) ($registrarPlan['manifestSha256'] ?? ''),
                (string) ($snapshot['manifestSha256'] ?? '')
            )
            || !hash_equals(
                (string) ($registrarPlan['inventorySha256'] ?? ''),
                (string) ($snapshot['inventorySha256'] ?? '')
            )
            || ($registrarPlan['serverEventRoute'] ?? null)
                !== $profile['serverEventRoute']
        ) {
            $result['errors'][] = 'payment_adapter_ingress_identity_mismatch';
            return $result;
        }

        $result['version'] = $snapshot['version'];
        $result['profileId'] = $profile['profileId'];
        $result['serverEventRoute'] = $profile['serverEventRoute'];
        $result['serverEventPath'] = $profile['serverEventPath'];
        $result['contractSha256'] = $profile['contractSha256'];
        $result['registrarPlanSha256'] = $registrarPlan['planSha256'];
        $result['registrationSha256'] =
            $registrarPlan['registrationSha256'];
        $result['manifestSha256'] = $snapshot['manifestSha256'];
        $result['inventorySha256'] = $snapshot['inventorySha256'];
        $result['gates']['adapterContract'] = 'passed';
        $result['gates']['registrarValidation'] = 'passed';
        $result['gates']['serverEventIngress'] = 'passed';
        $result['blockers'] = [[
            'code' => 'atomic_payment_adapter_enablement_required',
        ]];
        $result['ingressContractSha256'] =
            red_addon_payment_adapter_ingress_contract_fingerprint($result);
        if (!red_addon_valid_sha256($result['ingressContractSha256'])) {
            $result['errors'][] = 'payment_adapter_ingress_encoding_failed';
            $result['ingressContractSha256'] = '';
            return $result;
        }
        $result['ingressContractReady'] = true;
        $result['planSha256'] =
            red_addon_payment_adapter_ingress_plan_fingerprint($result);
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['errors'][] = 'plan_encoding_failed';
            $result['planSha256'] = '';
            $result['ingressContractReady'] = false;
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_payment_adapter_server_event_ingress_plan_is_valid')) {
    function red_addon_payment_adapter_server_event_ingress_plan_is_valid($plan)
    {
        $planData = is_array($plan) ? $plan : [];
        $packageId = is_string($planData['packageId'] ?? null)
            ? $planData['packageId']
            : '';
        $expectedResult = red_addon_payment_adapter_ingress_plan_result(
            $packageId
        );
        if (!is_array($plan)
            || array_keys($plan) !== array_keys(
                red_addon_payment_adapter_ingress_plan_result('')
            )
            || empty($plan['valid'])
            || ($plan['profileId'] ?? null)
                !== $expectedResult['profileId']
            || empty($plan['ingressContractReady'])
            || ($plan['enableReady'] ?? null) !== false
            || ($plan['activationSupported'] ?? null) !== false
            || ($plan['stateMutation'] ?? null) !== false
            || ($plan['runtimePublication'] ?? null) !== false
            || ($plan['requestRead'] ?? null) !== false
            || ($plan['handlerInvocation'] ?? null) !== false
            || ($plan['secretResolution'] ?? null) !== false
            || ($plan['signatureVerification'] ?? null) !== false
            || ($plan['jsonParsing'] ?? null) !== false
            || ($plan['databaseAccess'] ?? null) !== false
            || ($plan['networkAccess'] ?? null) !== false
            || ($plan['routeExposure'] ?? null) !== false
            || !red_addon_valid_package_id($plan['packageId'] ?? null)
            || !red_addon_valid_semantic_version($plan['version'] ?? null)
            || !red_addon_valid_capability($plan['serverEventRoute'] ?? null)
            || strpos(
                $plan['serverEventRoute'],
                $plan['packageId'] . '/'
            ) !== 0
            || !red_addon_valid_route_path($plan['serverEventPath'] ?? null)
            || ($plan['method'] ?? null) !== 'POST'
            || ($plan['contentType'] ?? null) !== 'application/json'
            || ($plan['requiredHeaders'] ?? null)
                !== $expectedResult['requiredHeaders']
            || ($plan['maximumBodyBytes'] ?? null) !== 65536
            || !red_addon_valid_sha256($plan['contractSha256'] ?? null)
            || !red_addon_valid_sha256($plan['registrarPlanSha256'] ?? null)
            || !red_addon_valid_sha256($plan['registrationSha256'] ?? null)
            || !red_addon_valid_sha256($plan['manifestSha256'] ?? null)
            || !red_addon_valid_sha256($plan['inventorySha256'] ?? null)
            || !red_addon_valid_sha256(
                $plan['ingressContractSha256'] ?? null
            )
            || ($plan['gates'] ?? null) !== [
                'adapterContract' => 'passed',
                'registrarValidation' => 'passed',
                'serverEventIngress' => 'passed',
                'atomicEnablement' => 'not_implemented',
            ]
            || ($plan['blockers'] ?? null) !== [[
                'code' => 'atomic_payment_adapter_enablement_required',
            ]]
            || ($plan['errors'] ?? null) !== []
            || !red_addon_valid_sha256($plan['planSha256'] ?? null)
            || !hash_equals(
                $plan['ingressContractSha256'],
                red_addon_payment_adapter_ingress_contract_fingerprint($plan)
            )
            || !hash_equals(
                $plan['planSha256'],
                red_addon_payment_adapter_ingress_plan_fingerprint($plan)
            )
        ) {
            return false;
        }
        return true;
    }
}

if (!function_exists('red_addon_payment_adapter_server_event_headers')) {
    /**
     * Requires an upstream-complete canonical capture for the selected closed
     * provider profile. Extra, missing, reordered, or duplicated lines fail
     * closed.
     */
    function red_addon_payment_adapter_server_event_headers(
        $capture,
        $profileId = 'store_lite_stripe_checkout_adapter_v1'
    )
    {
        $bodySignedProfile = $profileId === 'store_lite_wompi_adapter_v1';
        $paypalProfile = $profileId === 'store_lite_paypal_adapter_v1';
        if (!$bodySignedProfile
            && !$paypalProfile
            && $profileId !== 'store_lite_stripe_checkout_adapter_v1'
        ) {
            return null;
        }
        $expectedNames = $paypalProfile
            ? [
                'Content-Type',
                'Content-Length',
                'PayPal-Auth-Algo',
                'PayPal-Cert-Url',
                'PayPal-Transmission-Id',
                'PayPal-Transmission-Sig',
                'PayPal-Transmission-Time',
            ]
            : ($bodySignedProfile
                ? ['Content-Type', 'Content-Length']
                : ['Content-Type', 'Content-Length', 'Stripe-Signature']);
        if (!is_array($capture)
            || array_keys($capture) !== ['complete', 'headers']
            || $capture['complete'] !== true
            || !is_array($capture['headers'])
            || !array_is_list($capture['headers'])
            || count($capture['headers']) !== count($expectedNames)
        ) {
            return null;
        }
        $values = [];
        foreach ($capture['headers'] as $index => $header) {
            if (!is_array($header)
                || array_keys($header) !== ['name', 'value']
                || ($header['name'] ?? null) !== $expectedNames[$index]
                || !is_string($header['value'] ?? null)
            ) {
                return null;
            }
            $values[$header['name']] = $header['value'];
        }
        $contentLength = $values['Content-Length'];
        if ($bodySignedProfile) {
            $signature = '';
        } elseif ($paypalProfile) {
            $verificationHeaders = [];
            foreach (array_slice($expectedNames, 2) as $headerName) {
                $headerValue = $values[$headerName];
                if ($headerValue === ''
                    || strlen($headerValue) > 2048
                    || trim($headerValue) !== $headerValue
                    || preg_match('/[\x00-\x1F\x7F]/', $headerValue) === 1
                ) {
                    return null;
                }
                $verificationHeaders[$headerName] = $headerValue;
            }
            $signature = json_encode(
                $verificationHeaders,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            if (!is_string($signature)) {
                return null;
            }
        } else {
            $signature = $values['Stripe-Signature'];
        }
        if ($values['Content-Type'] !== 'application/json'
            || preg_match('/\A[1-9][0-9]{0,4}\z/D', $contentLength) !== 1
            || (int) $contentLength > 65536
            || (!$bodySignedProfile
                && (strlen($signature) < 8
                    || strlen($signature) > 4096
                    || trim($signature) !== $signature
                    || preg_match('/[\x00-\x1F\x7F]/', $signature) === 1))
        ) {
            return null;
        }
        return [
            'contentType' => 'application/json',
            'contentLength' => (int) $contentLength,
            'signatureHeader' => $signature,
        ];
    }
}

if (!function_exists('red_addon_payment_adapter_server_event_capture_result')) {
    function red_addon_payment_adapter_server_event_capture_result(
        $reason = 'ingress_unavailable'
    ) {
        $allowed = [
            'ingress_unavailable',
            'method_invalid',
            'target_invalid',
            'headers_invalid',
            'body_invalid',
            'receipt_time_invalid',
            'captured',
        ];
        $reason = is_string($reason) && in_array($reason, $allowed, true)
            ? $reason
            : 'ingress_unavailable';
        return [
            'available' => false,
            'packageId' => '',
            'routeId' => '',
            'path' => '',
            'contentType' => '',
            'receivedAtUnix' => 0,
            'bodyBytes' => 0,
            'bodySha256' => '',
            'captureSha256' => '',
            'request' => null,
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_payment_adapter_server_event_capture')) {
    function red_addon_payment_adapter_server_event_capture(
        array $package,
        array $registrarPlan,
        $method,
        $requestTarget,
        $headerCapture,
        $rawBody,
        $receivedAtUnix
    ) {
        $plan = red_addon_payment_adapter_server_event_ingress_plan(
            $package,
            $registrarPlan
        );
        if (!red_addon_payment_adapter_server_event_ingress_plan_is_valid(
            $plan
        )) {
            return red_addon_payment_adapter_server_event_capture_result(
                'ingress_unavailable'
            );
        }
        if ($method !== 'POST') {
            return red_addon_payment_adapter_server_event_capture_result(
                'method_invalid'
            );
        }
        if (!is_string($requestTarget)
            || $requestTarget !== $plan['serverEventPath']
        ) {
            return red_addon_payment_adapter_server_event_capture_result(
                'target_invalid'
            );
        }
        $headers = red_addon_payment_adapter_server_event_headers(
            $headerCapture,
            $plan['profileId']
        );
        if (!is_array($headers)) {
            return red_addon_payment_adapter_server_event_capture_result(
                'headers_invalid'
            );
        }
        if (!is_string($rawBody)
            || $rawBody === ''
            || strlen($rawBody) !== $headers['contentLength']
            || strlen($rawBody) > $plan['maximumBodyBytes']
        ) {
            return red_addon_payment_adapter_server_event_capture_result(
                'body_invalid'
            );
        }
        if (!is_int($receivedAtUnix)
            || $receivedAtUnix < 1
            || $receivedAtUnix > 4102444800
        ) {
            return red_addon_payment_adapter_server_event_capture_result(
                'receipt_time_invalid'
            );
        }

        try {
            $request = new RED_Addon_Payment_Adapter_Server_Event_Request(
                $plan['packageId'],
                $plan['serverEventRoute'],
                $plan['serverEventPath'],
                $headers['contentType'],
                $receivedAtUnix,
                $rawBody,
                $headers['signatureHeader']
            );
        } catch (Throwable $throwable) {
            return red_addon_payment_adapter_server_event_capture_result(
                'ingress_unavailable'
            );
        }
        $metadata = $request->metadata();
        $encoded = json_encode(
            [
                'schema' => 1,
                'ingressContractSha256' =>
                    $plan['ingressContractSha256'],
                'request' => $metadata,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $captureSha256 = is_string($encoded)
            ? hash('sha256', $encoded)
            : '';
        if (!red_addon_valid_sha256($captureSha256)) {
            return red_addon_payment_adapter_server_event_capture_result(
                'ingress_unavailable'
            );
        }
        return [
            'available' => true,
            'packageId' => $metadata['packageId'],
            'routeId' => $metadata['routeId'],
            'path' => $metadata['path'],
            'contentType' => $metadata['contentType'],
            'receivedAtUnix' => $metadata['receivedAtUnix'],
            'bodyBytes' => $metadata['bodyBytes'],
            'bodySha256' => $metadata['bodySha256'],
            'captureSha256' => $captureSha256,
            'request' => $request,
            'reason' => 'captured',
        ];
    }
}

?>
