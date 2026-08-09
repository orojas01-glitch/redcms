<?php
/**
 * Core-owned evidence bootstrap for a future public-mutation form.
 *
 * The caller supplies one explicit database connection, trusted manifest
 * declaration, bounded presentation model, and raw subject-cookie value. This
 * helper validates the complete presentation before issuing a subject, CSRF
 * token, or idempotency key, then composes the existing pure form model. It
 * reads no request/session/cookie globals, loads no package code, renders no
 * HTML, emits no header, and does not link a public route.
 */

require_once __DIR__ . '/addon_public_mutation_form_ui_helpers.php';
require_once __DIR__ .
    '/addon_public_mutation_subject_cookie_lifecycle_helpers.php';
require_once __DIR__ . '/addon_public_mutation_idempotency_helpers.php';

if (!function_exists('red_addon_public_mutation_form_bootstrap_result')) {
    function red_addon_public_mutation_form_bootstrap_result(
        $reason = 'bootstrap_invalid'
    ) {
        $allowed = [
            'bootstrap_invalid',
            'presentation_invalid',
            'subject_unavailable',
            'csrf_unavailable',
            'idempotency_unavailable',
            'form_unavailable',
            'cleanup_failed',
        ];
        $reason = is_string($reason) && in_array($reason, $allowed, true)
            ? $reason
            : 'bootstrap_invalid';
        return [
            'valid' => false,
            'formModel' => [],
            'lifecycle' => [],
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_form_bootstrap_presentation')) {
    /**
     * Validates every pure input before any browser evidence is issued.
     */
    function red_addon_public_mutation_form_bootstrap_presentation(
        $manifest,
        $routeId,
        $mutationId,
        $instanceId,
        $submitLabel,
        $presentedFields
    ) {
        if (!is_array($manifest)
            || !is_string($routeId)
            || !is_string($mutationId)
            || !is_string($instanceId)
            || preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $instanceId) !== 1
            || red_addon_public_mutation_form_ui_text($submitLabel, 80) === ''
        ) {
            return null;
        }
        $plan = red_addon_public_mutation_declaration_preflight(
            $manifest,
            $routeId,
            $mutationId
        );
        if (!red_addon_public_mutation_declaration_preflight_is_valid($plan)) {
            return null;
        }
        $contract = red_addon_public_mutation_form_contract(
            $manifest,
            $routeId,
            $mutationId
        );
        if (!is_array($contract)
            || !red_addon_valid_route_path($contract['path'] ?? null)
            || !is_array(red_addon_public_mutation_form_ui_fields(
                $contract,
                $presentedFields
            ))
        ) {
            return null;
        }
        return $plan;
    }
}

if (!function_exists('red_addon_public_mutation_form_bootstrap_cleanup')) {
    /**
     * Compensates only evidence created by the current failed bootstrap.
     */
    function red_addon_public_mutation_form_bootstrap_cleanup(
        $connection,
        array $lifecycle,
        array $csrf,
        array $idempotency
    ) {
        if (!red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
            $lifecycle
        )
            || empty($lifecycle['valid'])
            || !in_array($lifecycle['state'], ['issued', 'resolved'], true)
            || red_addon_public_mutation_subject_cookie_lifecycle_transaction_state(
                $connection
            ) !== false
        ) {
            return false;
        }
        $subjectRecordId = (int) $lifecycle['subjectRecordId'];
        $deleteSubject = $lifecycle['state'] === 'issued';
        if ($deleteSubject) {
            $cookieName = preg_quote(
                red_addon_public_mutation_subject_cookie_name(),
                '/'
            );
            if (preg_match(
                '/\A' . $cookieName . '=([a-f0-9]{64});/',
                $lifecycle['setCookieValue'],
                $cookieMatches
            ) !== 1
                || red_addon_public_mutation_subject_record_id(
                    red_addon_public_mutation_subject_resolve(
                        $connection,
                        $cookieMatches[1]
                    )
                ) !== $subjectRecordId
            ) {
                return false;
            }
        } elseif (!red_addon_public_mutation_subject_is_active(
            $connection,
            $subjectRecordId
        )) {
            return false;
        }
        $deleteCsrf = !$deleteSubject
            && !empty($csrf['valid'])
            && !empty($csrf['issued'])
            && ($csrf['subjectRecordId'] ?? 0) === $subjectRecordId
            && red_addon_valid_sha256($csrf['scopeSha256'] ?? null)
            && red_addon_public_mutation_valid_opaque_token(
                $csrf['token'] ?? null
            );
        $deleteIdempotency = !$deleteSubject
            && !empty($idempotency['valid'])
            && !empty($idempotency['issued'])
            && is_int($idempotency['idempotencyRecordId'] ?? null)
            && $idempotency['idempotencyRecordId'] > 0
            && ($idempotency['subjectRecordId'] ?? 0) === $subjectRecordId
            && red_addon_valid_sha256($idempotency['scopeSha256'] ?? null)
            && red_addon_public_mutation_valid_opaque_token(
                $idempotency['key'] ?? null
            );
        if (!$deleteSubject && !$deleteCsrf && !$deleteIdempotency) {
            return true;
        }

        $started = false;
        try {
            if (!mysqli_begin_transaction($connection)) {
                return false;
            }
            $started = true;
            if ($deleteSubject) {
                $statement = mysqli_prepare(
                    $connection,
                    'DELETE FROM RED_Addon_Public_Mutation_Subjects
                     WHERE RecordID=? LIMIT 1'
                );
                if (!$statement) {
                    throw new RuntimeException('cleanup_failed');
                }
                mysqli_stmt_bind_param($statement, 'i', $subjectRecordId);
                $executed = mysqli_stmt_execute($statement);
                $affected = mysqli_stmt_affected_rows($statement);
                mysqli_stmt_close($statement);
                if (!$executed || $affected !== 1) {
                    throw new RuntimeException('cleanup_failed');
                }
            } else {
                if ($deleteIdempotency) {
                    $recordId = $idempotency['idempotencyRecordId'];
                    $scope = $idempotency['scopeSha256'];
                    $keySha256 = red_addon_public_mutation_opaque_token_sha256(
                        $idempotency['key']
                    );
                    $statement = mysqli_prepare(
                        $connection,
                        'DELETE FROM RED_Addon_Public_Mutation_Idempotency_Keys
                         WHERE RecordID=? AND SubjectRecordID=?
                           AND BINARY ScopeSHA256=BINARY ?
                           AND BINARY KeySHA256=BINARY ?
                         LIMIT 1'
                    );
                    if (!$statement) {
                        throw new RuntimeException('cleanup_failed');
                    }
                    mysqli_stmt_bind_param(
                        $statement,
                        'iiss',
                        $recordId,
                        $subjectRecordId,
                        $scope,
                        $keySha256
                    );
                    $executed = mysqli_stmt_execute($statement);
                    $affected = mysqli_stmt_affected_rows($statement);
                    mysqli_stmt_close($statement);
                    if (!$executed || $affected !== 1) {
                        throw new RuntimeException('cleanup_failed');
                    }
                }
                if ($deleteCsrf) {
                    $scope = $csrf['scopeSha256'];
                    $tokenSha256 =
                        red_addon_public_mutation_opaque_token_sha256(
                            $csrf['token']
                        );
                    $statement = mysqli_prepare(
                        $connection,
                        'DELETE FROM RED_Addon_Public_Mutation_CSRF_Tokens
                         WHERE SubjectRecordID=?
                           AND BINARY ScopeSHA256=BINARY ?
                           AND BINARY TokenSHA256=BINARY ?
                         LIMIT 1'
                    );
                    if (!$statement) {
                        throw new RuntimeException('cleanup_failed');
                    }
                    mysqli_stmt_bind_param(
                        $statement,
                        'iss',
                        $subjectRecordId,
                        $scope,
                        $tokenSha256
                    );
                    $executed = mysqli_stmt_execute($statement);
                    $affected = mysqli_stmt_affected_rows($statement);
                    mysqli_stmt_close($statement);
                    if (!$executed || $affected !== 1) {
                        throw new RuntimeException('cleanup_failed');
                    }
                }
            }
            if (!mysqli_commit($connection)) {
                throw new RuntimeException('cleanup_failed');
            }
            $started = false;
            return true;
        } catch (Throwable $throwable) {
            if ($started) {
                mysqli_rollback($connection);
            }
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_form_bootstrap')) {
    function red_addon_public_mutation_form_bootstrap(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $instanceId,
        $submitLabel,
        $presentedFields,
        $cookieValue = ''
    ) {
        $result = red_addon_public_mutation_form_bootstrap_result();
        if (!is_string($cookieValue)
            || strlen($cookieValue) > 256
            || preg_match('/[\x00-\x1F\x7F]/', $cookieValue) === 1
        ) {
            return $result;
        }
        $plan = red_addon_public_mutation_form_bootstrap_presentation(
            $manifest,
            $routeId,
            $mutationId,
            $instanceId,
            $submitLabel,
            $presentedFields
        );
        if (!is_array($plan)) {
            return red_addon_public_mutation_form_bootstrap_result(
                'presentation_invalid'
            );
        }

        $lifecycle = red_addon_public_mutation_subject_cookie_lifecycle(
            $connection,
            'ensure',
            $cookieValue
        );
        if (!red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
            $lifecycle
        )
            || empty($lifecycle['valid'])
            || !in_array($lifecycle['state'], ['issued', 'resolved'], true)
        ) {
            return red_addon_public_mutation_form_bootstrap_result(
                'subject_unavailable'
            );
        }
        $subject = [
            'valid' => true,
            'subjectRecordId' => $lifecycle['subjectRecordId'],
        ];
        $csrf = red_addon_public_mutation_csrf_issue(
            $connection,
            $subject,
            $plan
        );
        if (empty($csrf['valid'])) {
            $clean = red_addon_public_mutation_form_bootstrap_cleanup(
                $connection,
                $lifecycle,
                $csrf,
                []
            );
            return red_addon_public_mutation_form_bootstrap_result(
                $clean ? 'csrf_unavailable' : 'cleanup_failed'
            );
        }
        $idempotency = red_addon_public_mutation_idempotency_issue(
            $connection,
            $subject,
            $plan
        );
        if (empty($idempotency['valid'])) {
            $clean = red_addon_public_mutation_form_bootstrap_cleanup(
                $connection,
                $lifecycle,
                $csrf,
                $idempotency
            );
            return red_addon_public_mutation_form_bootstrap_result(
                $clean ? 'idempotency_unavailable' : 'cleanup_failed'
            );
        }
        $formModel = red_addon_public_mutation_form_ui_compose(
            $manifest,
            $routeId,
            $mutationId,
            $instanceId,
            $submitLabel,
            $presentedFields,
            $csrf,
            $idempotency
        );
        if (!red_addon_public_mutation_form_ui_model_valid($formModel)) {
            $clean = red_addon_public_mutation_form_bootstrap_cleanup(
                $connection,
                $lifecycle,
                $csrf,
                $idempotency
            );
            return red_addon_public_mutation_form_bootstrap_result(
                $clean ? 'form_unavailable' : 'cleanup_failed'
            );
        }
        return [
            'valid' => true,
            'formModel' => $formModel,
            'lifecycle' => $lifecycle,
            'reason' => 'bootstrap_ready',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_form_bootstrap_result_valid')) {
    function red_addon_public_mutation_form_bootstrap_result_valid($result)
    {
        if (!is_array($result)
            || array_keys($result) !== [
                'valid', 'formModel', 'lifecycle', 'reason',
            ]
            || !is_bool($result['valid'])
            || !is_array($result['formModel'])
            || !is_array($result['lifecycle'])
            || !is_string($result['reason'])
        ) {
            return false;
        }
        if (!$result['valid']) {
            return $result === red_addon_public_mutation_form_bootstrap_result(
                $result['reason']
            );
        }
        return $result['reason'] === 'bootstrap_ready'
            && red_addon_public_mutation_form_ui_model_valid(
                $result['formModel']
            )
            && red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
                $result['lifecycle']
            )
            && $result['lifecycle']['valid'] === true
            && in_array(
                $result['lifecycle']['state'],
                ['issued', 'resolved'],
                true
            );
    }
}

?>
