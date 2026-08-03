<?php
/**
 * Non-executing public-mutation declaration preflight.
 *
 * Core turns one already-validated manifest declaration into deterministic,
 * value-free readiness evidence. It does not read package files, load a
 * runtime registrar, inspect a database, read request/cookie/session state,
 * issue CSRF or idempotency material, start a transaction, invoke a handler,
 * serve a route, or change package lifecycle.
 */

require_once __DIR__ . '/addon_manifest_helpers.php';

if (!function_exists('red_addon_public_mutation_declaration_preflight_result')) {
    function red_addon_public_mutation_declaration_preflight_result(
        $packageId,
        $routeId,
        $mutationId,
        $reason = 'invalid_request'
    ) {
        return [
            'valid' => false,
            'ready' => false,
            'invoked' => false,
            'packageId' => is_string($packageId)
                && red_addon_valid_package_id($packageId)
                    ? $packageId
                    : '',
            'route' => is_string($routeId)
                && red_addon_valid_capability($routeId)
                    ? $routeId
                    : '',
            'mutation' => is_string($mutationId)
                && red_addon_valid_capability($mutationId)
                    ? $mutationId
                    : '',
            'path' => '',
            'method' => '',
            'csrf' => '',
            'encoding' => '',
            'maxBodyBytes' => 0,
            'requestFieldCount' => 0,
            'subject' => '',
            'idempotency' => '',
            'privacy' => '',
            'rateLimit' => '',
            'tableCount' => 0,
            'postcondition' => '',
            'outcomes' => [],
            'contractSha256' => '',
            'planSha256' => '',
            'reason' => (string) $reason,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_public_mutation_declaration_preflight_error')) {
    function red_addon_public_mutation_declaration_preflight_error(
        array &$result,
        $code
    ) {
        $code = is_string($code)
            && preg_match('/\A[a-z][a-z0-9_]{0,79}\z/', $code) === 1
                ? $code
                : 'declaration_invalid';
        if (!in_array($code, $result['errors'], true)) {
            $result['errors'][] = $code;
        }
    }
}

if (!function_exists('red_addon_public_mutation_contract_fingerprint')) {
    function red_addon_public_mutation_contract_fingerprint(array $contract)
    {
        $encoded = json_encode(
            [
                'route' => $contract['route'] ?? null,
                'mutation' => $contract['mutation'] ?? null,
                'path' => $contract['path'] ?? null,
                'scope' => $contract['scope'] ?? null,
                'authentication' => $contract['authentication'] ?? null,
                'method' => $contract['method'] ?? null,
                'csrf' => $contract['csrf'] ?? null,
                'encoding' => $contract['encoding'] ?? null,
                'maxBodyBytes' => $contract['maxBodyBytes'] ?? null,
                'requestFields' => $contract['requestFields'] ?? null,
                'subject' => $contract['subject'] ?? null,
                'idempotency' => $contract['idempotency'] ?? null,
                'privacy' => $contract['privacy'] ?? null,
                'rateLimit' => $contract['rateLimit'] ?? null,
                'tables' => $contract['tables'] ?? null,
                'postcondition' => $contract['postcondition'] ?? null,
                'audit' => $contract['audit'] ?? null,
                'outcomes' => $contract['outcomes'] ?? null,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_public_mutation_declaration_preflight_fingerprint')) {
    function red_addon_public_mutation_declaration_preflight_fingerprint(
        array $plan
    ) {
        $encoded = json_encode(
            [
                'version' => 1,
                'packageId' => $plan['packageId'] ?? null,
                'route' => $plan['route'] ?? null,
                'mutation' => $plan['mutation'] ?? null,
                'path' => $plan['path'] ?? null,
                'method' => $plan['method'] ?? null,
                'csrf' => $plan['csrf'] ?? null,
                'encoding' => $plan['encoding'] ?? null,
                'maxBodyBytes' => $plan['maxBodyBytes'] ?? null,
                'requestFieldCount' => $plan['requestFieldCount'] ?? null,
                'subject' => $plan['subject'] ?? null,
                'idempotency' => $plan['idempotency'] ?? null,
                'privacy' => $plan['privacy'] ?? null,
                'rateLimit' => $plan['rateLimit'] ?? null,
                'tableCount' => $plan['tableCount'] ?? null,
                'postcondition' => $plan['postcondition'] ?? null,
                'outcomes' => $plan['outcomes'] ?? null,
                'contractSha256' => $plan['contractSha256'] ?? null,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_public_mutation_declaration_preflight')) {
    function red_addon_public_mutation_declaration_preflight(
        array $manifest,
        $routeId,
        $mutationId
    ) {
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        $result = red_addon_public_mutation_declaration_preflight_result(
            $packageId,
            $routeId,
            $mutationId
        );
        if ($result['packageId'] === '') {
            red_addon_public_mutation_declaration_preflight_error(
                $result,
                'package_invalid'
            );
            return $result;
        }
        if ($result['route'] === '' || $result['mutation'] === '') {
            red_addon_public_mutation_declaration_preflight_error(
                $result,
                'identity_invalid'
            );
            return $result;
        }
        $contract = red_addon_public_mutation_contract(
            $manifest,
            $result['route'],
            $result['mutation']
        );
        if (!is_array($contract)) {
            red_addon_public_mutation_declaration_preflight_error(
                $result,
                'declaration_unavailable'
            );
            return $result;
        }
        $contractSha256 = red_addon_public_mutation_contract_fingerprint($contract);
        if (!red_addon_valid_sha256($contractSha256)) {
            red_addon_public_mutation_declaration_preflight_error(
                $result,
                'contract_invalid'
            );
            return $result;
        }

        $result['path'] = $contract['path'];
        $result['method'] = $contract['method'];
        $result['csrf'] = $contract['csrf'];
        $result['encoding'] = $contract['encoding'];
        $result['maxBodyBytes'] = $contract['maxBodyBytes'];
        $result['requestFieldCount'] = count($contract['requestFields']);
        $result['subject'] = $contract['subject'];
        $result['idempotency'] = $contract['idempotency'];
        $result['privacy'] = $contract['privacy'];
        $result['rateLimit'] = $contract['rateLimit'];
        $result['tableCount'] = count($contract['tables']);
        $result['postcondition'] = $contract['postcondition'];
        $result['outcomes'] = $contract['outcomes'];
        $result['contractSha256'] = $contractSha256;
        $planSha256 = red_addon_public_mutation_declaration_preflight_fingerprint(
            $result
        );
        if (!red_addon_valid_sha256($planSha256)) {
            red_addon_public_mutation_declaration_preflight_error(
                $result,
                'plan_unavailable'
            );
            return $result;
        }
        $result['valid'] = true;
        $result['ready'] = true;
        $result['planSha256'] = $planSha256;
        $result['reason'] = 'preflight_ready';
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_declaration_preflight_is_valid')) {
    function red_addon_public_mutation_declaration_preflight_is_valid($plan)
    {
        $expectedKeys = [
            'contractSha256',
            'csrf',
            'encoding',
            'errors',
            'idempotency',
            'invoked',
            'maxBodyBytes',
            'method',
            'mutation',
            'outcomes',
            'packageId',
            'path',
            'planSha256',
            'postcondition',
            'privacy',
            'rateLimit',
            'ready',
            'reason',
            'requestFieldCount',
            'route',
            'subject',
            'tableCount',
            'valid',
        ];
        if (!is_array($plan)) {
            return false;
        }
        $packageParts = red_addon_package_parts($plan['packageId'] ?? null);
        $publicPrefix = is_array($packageParts)
            ? '/addons/' . $packageParts[0] . '/' . $packageParts[1] . '/'
            : '';
        $keys = array_keys($plan);
        sort($keys, SORT_STRING);
        if ($keys !== $expectedKeys
            || empty($plan['valid'])
            || empty($plan['ready'])
            || ($plan['invoked'] ?? null) !== false
            || !red_addon_valid_package_id($plan['packageId'] ?? null)
            || !red_addon_valid_capability($plan['route'] ?? null)
            || !red_addon_valid_capability($plan['mutation'] ?? null)
            || strpos($plan['route'], $plan['packageId'] . '/') !== 0
            || strpos($plan['mutation'], $plan['packageId'] . '/') !== 0
            || !red_addon_valid_route_path($plan['path'] ?? null)
            || strpbrk((string) $plan['path'], '{}') !== false
            || $publicPrefix === ''
            || strpos($plan['path'], $publicPrefix) !== 0
            || ($plan['method'] ?? null) !== 'POST'
            || ($plan['csrf'] ?? null) !== 'required'
            || ($plan['encoding'] ?? null) !== 'application/x-www-form-urlencoded'
            || !is_int($plan['maxBodyBytes'] ?? null)
            || $plan['maxBodyBytes'] < 128
            || $plan['maxBodyBytes'] > 8192
            || !is_int($plan['requestFieldCount'] ?? null)
            || $plan['requestFieldCount'] < 1
            || $plan['requestFieldCount'] > 8
            || ($plan['subject'] ?? null) !== 'anonymous'
            || ($plan['idempotency'] ?? null) !== 'core-issued-key'
            || ($plan['privacy'] ?? null) !== 'no-store'
            || ($plan['rateLimit'] ?? null) !== 'required'
            || !is_int($plan['tableCount'] ?? null)
            || $plan['tableCount'] < 1
            || $plan['tableCount'] > 8
            || ($plan['postcondition'] ?? null) !== 'server-derived-state'
            || ($plan['outcomes'] ?? null) !== ['accepted', 'unchanged']
            || !red_addon_valid_sha256($plan['contractSha256'] ?? null)
            || !red_addon_valid_sha256($plan['planSha256'] ?? null)
            || ($plan['reason'] ?? null) !== 'preflight_ready'
            || ($plan['errors'] ?? null) !== []
        ) {
            return false;
        }
        return hash_equals(
            $plan['planSha256'],
            red_addon_public_mutation_declaration_preflight_fingerprint($plan)
        );
    }
}

?>
