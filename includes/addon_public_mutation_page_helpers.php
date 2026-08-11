<?php
/**
 * Request-local coordination for core-rendered public-mutation forms.
 *
 * The front controller begins one explicit page context. The first valid form
 * integration may issue one anonymous subject; later forms reuse that subject.
 * Core retains the first lifecycle descriptor for the response cookie owner
 * and delivers the browser controller only when at least one form was accepted.
 */

require_once __DIR__ .
    '/addon_public_component_form_integration_helpers.php';
require_once __DIR__ .
    '/addon_public_mutation_subject_cookie_emitter_helpers.php';

if (!function_exists('red_addon_public_mutation_page_context_result')) {
    function red_addon_public_mutation_page_context_result(
        $reason = 'page_disabled'
    ) {
        return [
            'enabled' => false,
            'subjectToken' => '',
            'subjectRecordId' => 0,
            'lifecycle' => [],
            'formCount' => 0,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_page_context_set')) {
    function red_addon_public_mutation_page_context_set(array $context)
    {
        if (!red_addon_public_mutation_page_context_valid($context)) {
            $context = red_addon_public_mutation_page_context_result(
                'page_context_invalid'
            );
        }
        $GLOBALS['RED_ADDON_PUBLIC_MUTATION_PAGE_CONTEXT'] = $context;
        return $context;
    }
}

if (!function_exists('red_addon_public_mutation_page_context_current')) {
    function red_addon_public_mutation_page_context_current()
    {
        $context = $GLOBALS['RED_ADDON_PUBLIC_MUTATION_PAGE_CONTEXT'] ?? null;
        return red_addon_public_mutation_page_context_valid($context)
            ? $context
            : red_addon_public_mutation_page_context_result(
                'page_context_invalid'
            );
    }
}

if (!function_exists('red_addon_public_mutation_page_begin')) {
    /**
     * Accepts raw Cookie bytes so duplicate or malformed subject cookies close
     * the form path instead of relying on PHP's lossy parsed cookie map.
     */
    function red_addon_public_mutation_page_begin($enabled, $cookieHeader)
    {
        $context = red_addon_public_mutation_page_context_result();
        if ($enabled !== true) {
            return red_addon_public_mutation_page_context_set($context);
        }
        if (!is_string($cookieHeader)
            || strlen($cookieHeader) > 16384
            || preg_match('/[\x00-\x1F\x7F]/', $cookieHeader) === 1
        ) {
            $context['reason'] = 'cookie_invalid';
            return red_addon_public_mutation_page_context_set($context);
        }
        $subjectToken = $cookieHeader === ''
            ? ''
            : red_addon_public_mutation_http_request_subject_token(
                $cookieHeader
            );
        if (!is_string($subjectToken)) {
            $context['reason'] = 'cookie_invalid';
            return red_addon_public_mutation_page_context_set($context);
        }
        $context['enabled'] = true;
        $context['subjectToken'] = $subjectToken;
        $context['reason'] = 'page_ready';
        return red_addon_public_mutation_page_context_set($context);
    }
}

if (!function_exists('red_addon_public_mutation_page_issued_token')) {
    function red_addon_public_mutation_page_issued_token($lifecycle)
    {
        if (!red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
            $lifecycle
        )
            || empty($lifecycle['valid'])
            || $lifecycle['state'] !== 'issued'
        ) {
            return '';
        }
        $prefix = red_addon_public_mutation_subject_cookie_name() . '=';
        $value = $lifecycle['setCookieValue'];
        if (!str_starts_with($value, $prefix)) {
            return '';
        }
        $token = substr($value, strlen($prefix), 64);
        return red_addon_public_mutation_valid_opaque_token($token)
            ? $token
            : '';
    }
}

if (!function_exists('red_addon_public_mutation_page_context_valid')) {
    function red_addon_public_mutation_page_context_valid($context)
    {
        if (!is_array($context)
            || array_keys($context) !== [
                'enabled',
                'subjectToken',
                'subjectRecordId',
                'lifecycle',
                'formCount',
                'reason',
            ]
            || !is_bool($context['enabled'])
            || !is_string($context['subjectToken'])
            || !is_int($context['subjectRecordId'])
            || !is_array($context['lifecycle'])
            || !is_int($context['formCount'])
            || !is_string($context['reason'])
        ) {
            return false;
        }
        if (!$context['enabled']) {
            return $context === red_addon_public_mutation_page_context_result(
                $context['reason']
            );
        }
        if ($context['formCount'] < 0 || $context['formCount'] > 128) {
            return false;
        }
        if ($context['subjectToken'] !== ''
            && !red_addon_public_mutation_valid_opaque_token(
                $context['subjectToken']
            )
        ) {
            return false;
        }
        if ($context['formCount'] === 0) {
            return $context['subjectRecordId'] === 0
                && $context['lifecycle'] === []
                && $context['reason'] === 'page_ready';
        }
        if ($context['subjectRecordId'] < 1
            || $context['reason'] !== 'forms_ready'
            || !red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
                $context['lifecycle']
            )
            || empty($context['lifecycle']['valid'])
            || !in_array(
                $context['lifecycle']['state'],
                ['issued', 'resolved'],
                true
            )
            || $context['lifecycle']['subjectRecordId']
                !== $context['subjectRecordId']
        ) {
            return false;
        }
        if ($context['lifecycle']['state'] === 'issued') {
            return hash_equals(
                $context['subjectToken'],
                red_addon_public_mutation_page_issued_token(
                    $context['lifecycle']
                )
            );
        }
        return red_addon_public_mutation_valid_opaque_token(
            $context['subjectToken']
        );
    }
}

if (!function_exists('red_addon_public_mutation_page_accept_integration')) {
    function red_addon_public_mutation_page_accept_integration(
        array $page,
        $integration
    ) {
        if (!red_addon_public_component_form_integration_valid($integration)
            || empty($integration['valid'])
            || empty($integration['available'])
        ) {
            return $integration;
        }

        $lifecycle = $integration['lifecycle'];
        $recordId = (int) ($lifecycle['subjectRecordId'] ?? 0);
        if ($recordId < 1
            || !in_array($lifecycle['state'], ['issued', 'resolved'], true)
        ) {
            return red_addon_public_component_form_integration_result(
                'bootstrap_unavailable'
            );
        }
        if ($page['formCount'] === 0) {
            if ($lifecycle['state'] === 'issued') {
                $issuedToken =
                    red_addon_public_mutation_page_issued_token($lifecycle);
                if ($issuedToken === '') {
                    return red_addon_public_component_form_integration_result(
                        'bootstrap_unavailable'
                    );
                }
                $page['subjectToken'] = $issuedToken;
            } elseif ($page['subjectToken'] === '') {
                return red_addon_public_component_form_integration_result(
                    'bootstrap_unavailable'
                );
            }
            $page['subjectRecordId'] = $recordId;
            $page['lifecycle'] = $lifecycle;
        } elseif ($lifecycle['state'] !== 'resolved'
            || $recordId !== $page['subjectRecordId']
        ) {
            return red_addon_public_component_form_integration_result(
                'bootstrap_unavailable'
            );
        }
        $page['formCount']++;
        $page['reason'] = 'forms_ready';
        red_addon_public_mutation_page_context_set($page);
        return $integration;
    }
}

if (!function_exists('red_addon_public_mutation_page_integrate')) {
    function red_addon_public_mutation_page_integrate(
        $connection,
        $componentContext,
        $viewModel
    ) {
        $page = red_addon_public_mutation_page_context_current();
        if (($page['enabled'] ?? null) !== true) {
            return red_addon_public_component_form_integration_result(
                'integration_invalid'
            );
        }
        if (($page['formCount'] ?? 0) >= 128) {
            return red_addon_public_component_form_integration_result(
                'bootstrap_unavailable'
            );
        }
        return red_addon_public_mutation_page_accept_integration(
            $page,
            red_addon_public_component_form_integrate(
                $connection,
                $componentContext,
                $viewModel,
                $page['subjectToken']
            )
        );
    }
}

if (!function_exists('red_addon_public_mutation_page_integrate_collection_form')) {
    function red_addon_public_mutation_page_integrate_collection_form(
        $connection,
        $componentContext,
        $viewModel,
        $rowIndex,
        $formIndex
    ) {
        $page = red_addon_public_mutation_page_context_current();
        if (($page['enabled'] ?? null) !== true) {
            return red_addon_public_component_form_integration_result(
                'integration_invalid'
            );
        }
        if (($page['formCount'] ?? 0) >= 128) {
            return red_addon_public_component_form_integration_result(
                'bootstrap_unavailable'
            );
        }
        return red_addon_public_mutation_page_accept_integration(
            $page,
            red_addon_public_component_collection_form_integrate(
                $connection,
                $componentContext,
                $viewModel,
                $rowIndex,
                $formIndex,
                $page['subjectToken']
            )
        );
    }
}

if (!function_exists('red_addon_public_mutation_page_subject_context')) {
    /**
     * Resolves the current anonymous browser subject for a package-owned
     * read model. It never issues evidence, changes lifecycle state, or
     * exposes the opaque cookie value to package code.
     */
    function red_addon_public_mutation_page_subject_context($connection)
    {
        $absent = [
            'valid' => false,
            'subjectRecordId' => 0,
            'reason' => 'subject_unavailable',
        ];
        $page = red_addon_public_mutation_page_context_current();
        if (!$connection instanceof mysqli
            || ($page['enabled'] ?? null) !== true
            || !red_addon_public_mutation_valid_opaque_token(
                $page['subjectToken'] ?? null
            )
        ) {
            return $absent;
        }
        $resolved = red_addon_public_mutation_subject_resolve(
            $connection,
            $page['subjectToken']
        );
        $recordId = red_addon_public_mutation_subject_record_id($resolved);
        if ($recordId < 1) {
            return $absent;
        }
        return [
            'valid' => true,
            'subjectRecordId' => $recordId,
            'reason' => 'subject_resolved',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_page_delivery')) {
    function red_addon_public_mutation_page_delivery()
    {
        $page = red_addon_public_mutation_page_context_current();
        if (($page['enabled'] ?? null) !== true
            || ($page['formCount'] ?? 0) < 1
            || !red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
                $page['lifecycle'] ?? null
            )
            || empty($page['lifecycle']['valid'])
        ) {
            return [
                'active' => false,
                'formCount' => 0,
                'controllerSrc' => '',
                'lifecycle' => [],
                'reason' => 'delivery_inactive',
            ];
        }
        return [
            'active' => true,
            'formCount' => $page['formCount'],
            'controllerSrc' => '/js/public-addon-mutation.js',
            'lifecycle' => $page['lifecycle'],
            'reason' => 'delivery_ready',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_page_delivery_valid')) {
    function red_addon_public_mutation_page_delivery_valid($delivery)
    {
        if (!is_array($delivery)
            || array_keys($delivery) !== [
                'active',
                'formCount',
                'controllerSrc',
                'lifecycle',
                'reason',
            ]
            || !is_bool($delivery['active'])
            || !is_int($delivery['formCount'])
            || !is_string($delivery['controllerSrc'])
            || !is_array($delivery['lifecycle'])
            || !is_string($delivery['reason'])
        ) {
            return false;
        }
        if (!$delivery['active']) {
            return $delivery === [
                'active' => false,
                'formCount' => 0,
                'controllerSrc' => '',
                'lifecycle' => [],
                'reason' => 'delivery_inactive',
            ];
        }
        return $delivery['formCount'] > 0
            && $delivery['formCount'] <= 128
            && $delivery['controllerSrc']
                === '/js/public-addon-mutation.js'
            && $delivery['reason'] === 'delivery_ready'
            && red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
                $delivery['lifecycle']
            )
            && $delivery['lifecycle']['valid'] === true
            && in_array(
                $delivery['lifecycle']['state'],
                ['issued', 'resolved'],
                true
            );
    }
}

if (!function_exists('red_addon_public_mutation_page_controller_tag')) {
    function red_addon_public_mutation_page_controller_tag($delivery)
    {
        if (!red_addon_public_mutation_page_delivery_valid($delivery)
            || empty($delivery['active'])
        ) {
            return '';
        }
        return '<script src="/js/public-addon-mutation.js" defer></script>';
    }
}

if (!function_exists('red_addon_public_mutation_page_emit_cookie')) {
    function red_addon_public_mutation_page_emit_cookie($delivery)
    {
        if (!red_addon_public_mutation_page_delivery_valid($delivery)
            || empty($delivery['active'])
        ) {
            throw new InvalidArgumentException(
                'Public-mutation page delivery is invalid.'
            );
        }
        red_addon_public_mutation_subject_cookie_emit(
            $delivery['lifecycle']
        );
    }
}

?>
