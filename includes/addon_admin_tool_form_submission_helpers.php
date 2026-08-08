<?php
/**
 * Validation-only JSON adapter for one operational administrator tool form.
 *
 * The protected core endpoint authenticates the current administrator and
 * verifies the header CSRF token before opening the request-body stream. This
 * helper then
 * accepts only exact transport facts and canonical bounded JSON, reloads the
 * current package values through the permission-scoped read-only loader,
 * refuses stale state, validates the complete submitted graph, and returns
 * opaque preparation evidence. It invokes no writer and mutates no state.
 */

require_once __DIR__ . '/addon_admin_tool_form_value_helpers.php';

if (!function_exists('red_addon_admin_tool_form_submission_transport_result')) {
    function red_addon_admin_tool_form_submission_transport_result(
        $reason = 'invalid_request'
    ) {
        $allowed = [
            'valid',
            'invalid_request',
            'content_type_invalid',
            'content_length_invalid',
            'body_too_large',
        ];
        return [
            'valid' => $reason === 'valid',
            'rawBody' => '',
            'reason' => is_string($reason) && in_array($reason, $allowed, true)
                ? $reason
                : 'invalid_request',
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_submission_read_body')) {
    function red_addon_admin_tool_form_submission_read_body(
        $contentType,
        $contentLength,
        $stream
    ) {
        if (!is_string($contentType)
            || !hash_equals('application/json', $contentType)
        ) {
            return red_addon_admin_tool_form_submission_transport_result(
                'content_type_invalid'
            );
        }
        if (!is_string($contentLength)
            || preg_match('/\A[1-9][0-9]{0,5}\z/D', $contentLength) !== 1
        ) {
            return red_addon_admin_tool_form_submission_transport_result(
                'content_length_invalid'
            );
        }
        $length = (int) $contentLength;
        if ((string) $length !== $contentLength) {
            return red_addon_admin_tool_form_submission_transport_result(
                'content_length_invalid'
            );
        }
        if ($length > 262144) {
            return red_addon_admin_tool_form_submission_transport_result(
                'body_too_large'
            );
        }
        if (!is_resource($stream)
            || get_resource_type($stream) !== 'stream'
        ) {
            return red_addon_admin_tool_form_submission_transport_result();
        }
        try {
            $rawBody = stream_get_contents($stream, $length + 1);
        } catch (Throwable $throwable) {
            $rawBody = false;
        }
        if (!is_string($rawBody) || strlen($rawBody) !== $length) {
            return red_addon_admin_tool_form_submission_transport_result(
                'content_length_invalid'
            );
        }
        $result = red_addon_admin_tool_form_submission_transport_result('valid');
        $result['rawBody'] = $rawBody;
        return $result;
    }
}

if (!function_exists('red_addon_admin_tool_form_submission_decode')) {
    function red_addon_admin_tool_form_submission_decode($rawBody)
    {
        if (!is_string($rawBody)
            || $rawBody === ''
            || strlen($rawBody) > 262144
            || $rawBody[0] !== '{'
        ) {
            return null;
        }
        try {
            $decoded = json_decode(
                $rawBody,
                true,
                12,
                JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR
            );
            $canonical = json_encode(
                $decoded,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return null;
        }
        if (!is_array($decoded)
            || array_is_list($decoded)
            || !is_string($canonical)
            || !hash_equals($canonical, $rawBody)
        ) {
            return null;
        }
        $keys = array_keys($decoded);
        sort($keys, SORT_STRING);
        if ($keys !== [
            'currentStateSha256',
            'form',
            'targetRecordId',
            'tool',
            'values',
        ]
            || !is_string($decoded['tool'] ?? null)
            || !red_addon_valid_capability($decoded['tool'])
            || !is_string($decoded['form'] ?? null)
            || !red_addon_valid_capability($decoded['form'])
            || !is_int($decoded['targetRecordId'] ?? null)
            || $decoded['targetRecordId'] < 1
            || $decoded['targetRecordId'] > 2147483647
            || !is_string($decoded['currentStateSha256'] ?? null)
            || !red_addon_valid_sha256($decoded['currentStateSha256'])
            || !is_array($decoded['values'] ?? null)
            || (array_is_list($decoded['values'])
                && $decoded['values'] !== [])
        ) {
            return null;
        }
        return [
            'tool' => $decoded['tool'],
            'form' => $decoded['form'],
            'targetRecordId' => $decoded['targetRecordId'],
            'currentStateSha256' => $decoded['currentStateSha256'],
            'values' => $decoded['values'],
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_submission_result')) {
    function red_addon_admin_tool_form_submission_result(
        $request,
        $actorRecordId,
        $reason = 'invalid_request'
    ) {
        $toolId = is_array($request) ? ($request['tool'] ?? null) : null;
        $formId = is_array($request) ? ($request['form'] ?? null) : null;
        $targetRecordId = is_array($request)
            ? ($request['targetRecordId'] ?? null)
            : null;
        return [
            'authorized' => false,
            'invoked' => false,
            'prepared' => false,
            'tool' => is_string($toolId) && red_addon_valid_capability($toolId)
                ? $toolId
                : '',
            'form' => is_string($formId) && red_addon_valid_capability($formId)
                ? $formId
                : '',
            'package' => '',
            'targetRecordId' => is_int($targetRecordId)
                && $targetRecordId >= 1
                && $targetRecordId <= 2147483647
                    ? $targetRecordId
                    : 0,
            'actorRecordId' => red_addon_admin_tool_form_actor_record_id(
                $actorRecordId
            ),
            'permission' => '',
            'contractSha256' => '',
            'runtimeSettingsSha256' => '',
            'currentStateSha256' => '',
            'submittedValuesSha256' => '',
            'planSha256' => '',
            'values' => [],
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_submission_values_hash')) {
    function red_addon_admin_tool_form_submission_values_hash(array $values)
    {
        try {
            $encoded = json_encode(
                $values,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return '';
        }
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_admin_tool_form_submission_plan_hash')) {
    function red_addon_admin_tool_form_submission_plan_hash(array $result)
    {
        try {
            $encoded = json_encode(
                [
                    'schema' => 2,
                    'package' => $result['package'] ?? null,
                    'tool' => $result['tool'] ?? null,
                    'form' => $result['form'] ?? null,
                    'targetRecordId' => $result['targetRecordId'] ?? null,
                    'actorRecordId' => $result['actorRecordId'] ?? null,
                    'permission' => $result['permission'] ?? null,
                    'contractSha256' => $result['contractSha256'] ?? null,
                    'runtimeSettingsSha256' =>
                        $result['runtimeSettingsSha256'] ?? null,
                    'currentStateSha256' =>
                        $result['currentStateSha256'] ?? null,
                    'submittedValuesSha256' =>
                        $result['submittedValuesSha256'] ?? null,
                ],
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return '';
        }
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_admin_tool_form_submission_prepare')) {
    function red_addon_admin_tool_form_submission_prepare(
        $connection,
        $rawBody,
        $actorRecordId
    ) {
        $request = red_addon_admin_tool_form_submission_decode($rawBody);
        $result = red_addon_admin_tool_form_submission_result(
            $request,
            $actorRecordId
        );
        if (!$connection
            || !is_array($request)
            || $result['actorRecordId'] < 1
        ) {
            return $result;
        }
        $preflight = red_addon_admin_tool_form_preflight(
            $connection,
            $result['tool'],
            $result['form'],
            $result['actorRecordId']
        );
        $result['authorized'] = ($preflight['authorized'] ?? false) === true;
        $result['package'] = is_string($preflight['package'] ?? null)
            ? $preflight['package']
            : '';
        $result['permission'] = is_string($preflight['permission'] ?? null)
            ? $preflight['permission']
            : '';
        $result['contractSha256'] =
            is_string($preflight['contractSha256'] ?? null)
                ? $preflight['contractSha256']
                : '';
        if (($preflight['ready'] ?? false) !== true) {
            $result['reason'] = (string) (
                $preflight['reason'] ?? 'form_unavailable'
            );
            return $result;
        }
        if (!is_int($preflight['maxBodyBytes'] ?? null)
            || strlen($rawBody) > $preflight['maxBodyBytes']
        ) {
            $result['reason'] = 'body_too_large';
            return $result;
        }

        $loaded = red_addon_admin_tool_form_load_values(
            $connection,
            $result['tool'],
            $result['form'],
            $result['targetRecordId'],
            $result['actorRecordId']
        );
        $result['invoked'] = ($loaded['invoked'] ?? false) === true;
        if (($loaded['loaded'] ?? false) !== true) {
            $result['authorized'] = ($loaded['authorized'] ?? false) === true;
            $result['reason'] = (string) (
                $loaded['reason'] ?? 'form_unavailable'
            );
            return $result;
        }
        $result['currentStateSha256'] = is_string(
            $loaded['stateSha256'] ?? null
        ) ? $loaded['stateSha256'] : '';
        $result['runtimeSettingsSha256'] = is_string(
            $loaded['runtimeSettingsSha256'] ?? null
        ) ? $loaded['runtimeSettingsSha256'] : '';
        if (!red_addon_valid_sha256($result['currentStateSha256'])
            || !red_addon_valid_sha256(
                $result['runtimeSettingsSha256']
            )
            || !hash_equals(
                $result['currentStateSha256'],
                $request['currentStateSha256']
            )
        ) {
            $result['reason'] = 'state_conflict';
            return $result;
        }

        $manifest = red_addon_runtime_manifest($result['package']);
        $contract = is_array($manifest)
            ? red_addon_admin_tool_form_contract(
                $manifest,
                $result['tool'],
                $result['form']
            )
            : null;
        $validated = is_array($contract)
            ? red_addon_admin_tool_form_validate_values(
                $contract,
                $request['values']
            )
            : ['valid' => false];
        if (($validated['valid'] ?? false) !== true
            || !is_array($validated['values'] ?? null)
        ) {
            $result['reason'] = 'invalid_values';
            return $result;
        }
        $result['submittedValuesSha256'] =
            red_addon_admin_tool_form_submission_values_hash(
                $validated['values']
            );
        if (!red_addon_valid_sha256($result['submittedValuesSha256'])) {
            $result['reason'] = 'invalid_values';
            return $result;
        }
        $result['values'] = $validated['values'];
        $result['planSha256'] =
            red_addon_admin_tool_form_submission_plan_hash($result);
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['values'] = [];
            $result['reason'] = 'form_unavailable';
            return $result;
        }
        $result['prepared'] = true;
        $result['reason'] = 'prepared';
        return $result;
    }
}

if (!function_exists('red_addon_admin_tool_form_submission_public_result')) {
    function red_addon_admin_tool_form_submission_public_result(array $result)
    {
        if (($result['prepared'] ?? false) === true) {
            return [
                'httpStatus' => 200,
                'body' => ['ok' => true, 'status' => 'validated'],
            ];
        }
        $reason = (string) ($result['reason'] ?? 'form_unavailable');
        $mapping = [
            'invalid_request' => [400, 'invalid_request'],
            'permission_denied' => [403, 'permission_denied'],
            'body_too_large' => [413, 'body_too_large'],
            'state_conflict' => [409, 'state_conflict'],
            'invalid_values' => [422, 'invalid_values'],
        ];
        [$httpStatus, $publicReason] = $mapping[$reason]
            ?? [422, 'form_unavailable'];
        return [
            'httpStatus' => $httpStatus,
            'body' => ['ok' => false, 'reason' => $publicReason],
        ];
    }
}

?>
