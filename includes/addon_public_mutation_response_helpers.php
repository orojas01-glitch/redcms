<?php
/**
 * Pure core-owned public-mutation response contract.
 *
 * This helper turns only bounded core outcomes into an internal JSON response
 * envelope for a future dispatcher. It does not accept an HTTP request, read
 * request/cookie/session globals, access a database, load package code, emit
 * headers/cookies/body bytes, or change lifecycle, package, or Store Lite
 * state. A later core-owned dispatcher must validate its request completely
 * before it selects one of these fixed envelopes.
 */

if (!function_exists('red_addon_public_mutation_response_outcome_valid')) {
    function red_addon_public_mutation_response_outcome_valid($outcome)
    {
        return is_string($outcome)
            && in_array($outcome, ['accepted', 'unchanged'], true);
    }
}

if (!function_exists('red_addon_public_mutation_response_refusal')) {
    function red_addon_public_mutation_response_refusal($reason)
    {
        $reason = is_string($reason) ? $reason : '';
        $httpStatus = 503;
        $publicReason = 'temporarily_unavailable';

        if (in_array(
            $reason,
            [
                'invalid_request',
                'subject_invalid',
                'csrf_invalid',
                'idempotency_invalid',
                'command_invalid',
                'origin_invalid',
                'content_type_invalid',
                'content_length_invalid',
                'body_too_large',
                'fields_invalid',
            ],
            true
        )) {
            $httpStatus = 400;
            $publicReason = 'invalid_request';
        } elseif ($reason === 'method_not_allowed') {
            $httpStatus = 405;
            $publicReason = 'method_not_allowed';
        } elseif (in_array(
            $reason,
            ['idempotency_conflict', 'request_conflict'],
            true
        )) {
            $httpStatus = 409;
            $publicReason = 'request_conflict';
        } elseif ($reason === 'rate_limited') {
            $httpStatus = 429;
            $publicReason = 'rate_limited';
        }

        return red_addon_public_mutation_response_build(
            $httpStatus,
            false,
            '',
            $publicReason
        );
    }
}

if (!function_exists('red_addon_public_mutation_response_build')) {
    /**
     * Builds only values already selected by the closed success/refusal maps.
     */
    function red_addon_public_mutation_response_build(
        $httpStatus,
        $ok,
        $outcome,
        $reason
    ) {
        $body = '';
        if ($httpStatus === 200
            && $ok === true
            && $outcome === 'accepted'
            && $reason === ''
        ) {
            $body = '{"ok":true,"outcome":"accepted"}';
        } elseif ($httpStatus === 200
            && $ok === true
            && $outcome === 'unchanged'
            && $reason === ''
        ) {
            $body = '{"ok":true,"outcome":"unchanged"}';
        } elseif ($httpStatus === 400
            && $ok === false
            && $outcome === ''
            && $reason === 'invalid_request'
        ) {
            $body = '{"ok":false,"reason":"invalid_request"}';
        } elseif ($httpStatus === 405
            && $ok === false
            && $outcome === ''
            && $reason === 'method_not_allowed'
        ) {
            $body = '{"ok":false,"reason":"method_not_allowed"}';
        } elseif ($httpStatus === 409
            && $ok === false
            && $outcome === ''
            && $reason === 'request_conflict'
        ) {
            $body = '{"ok":false,"reason":"request_conflict"}';
        } elseif ($httpStatus === 429
            && $ok === false
            && $outcome === ''
            && $reason === 'rate_limited'
        ) {
            $body = '{"ok":false,"reason":"rate_limited"}';
        } elseif ($httpStatus === 503
            && $ok === false
            && $outcome === ''
            && $reason === 'temporarily_unavailable'
        ) {
            $body = '{"ok":false,"reason":"temporarily_unavailable"}';
        } else {
            $httpStatus = 503;
            $ok = false;
            $outcome = '';
            $reason = 'temporarily_unavailable';
            $body = '{"ok":false,"reason":"temporarily_unavailable"}';
        }

        $headers = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) strlen($body),
        ];
        if ($httpStatus === 405) {
            $headers['Allow'] = 'POST';
        }

        return [
            'httpStatus' => $httpStatus,
            'headers' => $headers,
            'body' => $body,
            'ok' => $ok,
            'outcome' => $outcome,
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_response_success')) {
    function red_addon_public_mutation_response_success($outcome)
    {
        if (!red_addon_public_mutation_response_outcome_valid($outcome)) {
            return red_addon_public_mutation_response_refusal(
                'response_invalid'
            );
        }
        return red_addon_public_mutation_response_build(
            200,
            true,
            $outcome,
            ''
        );
    }
}

if (!function_exists('red_addon_public_mutation_response_from_execution')) {
    /**
     * Redacts the internal runner result into the fixed public vocabulary.
     */
    function red_addon_public_mutation_response_from_execution($execution)
    {
        if (!is_array($execution)) {
            return red_addon_public_mutation_response_refusal(
                'response_invalid'
            );
        }
        $completed = $execution['completed'] ?? null;
        $replayed = $execution['replayed'] ?? null;
        $outcome = $execution['outcome'] ?? null;
        $reason = $execution['reason'] ?? null;

        if ($completed === true
            && $replayed === false
            && $reason === 'completed'
            && red_addon_public_mutation_response_outcome_valid($outcome)
        ) {
            return red_addon_public_mutation_response_success($outcome);
        }
        if ($completed === false
            && $replayed === true
            && $reason === 'replayed'
            && red_addon_public_mutation_response_outcome_valid($outcome)
        ) {
            return red_addon_public_mutation_response_success($outcome);
        }
        if ($completed === false
            && $replayed === false
            && $outcome === ''
            && is_string($reason)
        ) {
            return red_addon_public_mutation_response_refusal($reason);
        }
        return red_addon_public_mutation_response_refusal('response_invalid');
    }
}

if (!function_exists('red_addon_public_mutation_response_valid')) {
    function red_addon_public_mutation_response_valid($response)
    {
        if (!is_array($response)
            || array_keys($response) !== [
                'httpStatus', 'headers', 'body', 'ok', 'outcome', 'reason',
            ]
        ) {
            return false;
        }
        if (($response['ok'] ?? null) === true
            && is_string($response['outcome'] ?? null)
            && ($response['reason'] ?? null) === ''
        ) {
            return $response === red_addon_public_mutation_response_success(
                $response['outcome']
            );
        }
        if (($response['ok'] ?? null) === false
            && ($response['outcome'] ?? null) === ''
            && is_string($response['reason'] ?? null)
        ) {
            return $response === red_addon_public_mutation_response_refusal(
                $response['reason']
            );
        }
        return false;
    }
}

?>
