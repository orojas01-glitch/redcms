<?php
/**
 * Core-only selection of one declared public-mutation route from an already
 * initialized add-on runtime context.
 *
 * This is deliberately a prerequisite for a later HTTP dispatcher. It maps a
 * bounded raw request target to one current, registrar-bound mutation
 * declaration, but it does not read PHP request globals, open a database,
 * execute a package callback, issue browser evidence, emit a response, or
 * claim a front-controller route. A later core dispatcher must still supply
 * an explicit server-owned request adapter, call the HTTP-envelope normalizer
 * and declared-form decoder, then use the transaction runner and fixed
 * response emitter.
 */

require_once __DIR__ . '/addon_public_mutation_http_request_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';

if (!function_exists('red_addon_public_mutation_route_selection_result')) {
    function red_addon_public_mutation_route_selection_result(
        $reason = 'not_matched'
    ) {
        $allowed = [
            'not_matched',
            'request_invalid',
            'runtime_unavailable',
            'route_unavailable',
            'route_ambiguous',
            'route_selected',
        ];
        $reason = is_string($reason) && in_array($reason, $allowed, true)
            ? $reason
            : 'runtime_unavailable';
        return [
            'claimed' => false,
            'ready' => false,
            'packageId' => '',
            'route' => '',
            'mutation' => '',
            'path' => '',
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_route_target_path')) {
    /**
     * Preserves query-bearing targets for a later normalizer while using only
     * their raw, un-decoded path portion to locate a static declaration.
     */
    function red_addon_public_mutation_route_target_path($requestTarget)
    {
        if (!is_string($requestTarget)
            || $requestTarget === ''
            || strlen($requestTarget) > 2048
            || preg_match('/[\x00-\x20\x7F]/', $requestTarget) === 1
            || str_contains($requestTarget, '#')
        ) {
            return '';
        }
        $queryAt = strpos($requestTarget, '?');
        $path = $queryAt === false
            ? $requestTarget
            : substr($requestTarget, 0, $queryAt);
        if (!is_string($path)
            || $path === ''
            || str_contains($path, '%')
            || str_contains($path, '{')
            || str_contains($path, '}')
            || !red_addon_valid_route_path($path)
        ) {
            return '';
        }
        return $path;
    }
}

if (!function_exists('red_addon_public_mutation_route_candidates')) {
    /**
     * Returns only complete, trusted declaration candidates for one path.
     * A malformed runtime context yields null rather than partial selection.
     */
    function red_addon_public_mutation_route_candidates($context, $path)
    {
        if (!$context instanceof RED_Addon_Runtime_Context
            || !is_string($path)
            || $path === ''
        ) {
            return null;
        }
        try {
            $order = $context->order();
            if (!is_array($order) || !array_is_list($order)) {
                return null;
            }
            $candidates = [];
            foreach ($order as $packageId) {
                if (!is_string($packageId)
                    || !red_addon_valid_package_id($packageId)
                ) {
                    return null;
                }
                $manifest = $context->manifest($packageId);
                if (!is_array($manifest)
                    || ($manifest['id'] ?? null) !== $packageId
                ) {
                    return null;
                }
                $contracts = $manifest['publicMutationContracts'] ?? [];
                if (!is_array($contracts) || !array_is_list($contracts)) {
                    return null;
                }
                foreach ($contracts as $declared) {
                    if (!is_array($declared)
                        || !is_string($declared['route'] ?? null)
                        || !is_string($declared['mutation'] ?? null)
                    ) {
                        return null;
                    }
                    $routeId = $declared['route'];
                    $mutationId = $declared['mutation'];
                    $contract = red_addon_public_mutation_http_request_contract(
                        $manifest,
                        $routeId,
                        $mutationId
                    );
                    if (!is_array($contract)
                        || ($contract['route'] ?? null) !== $routeId
                        || ($contract['mutation'] ?? null) !== $mutationId
                    ) {
                        return null;
                    }
                    if (hash_equals($contract['path'], $path)) {
                        $candidates[] = [
                            'packageId' => $packageId,
                            'route' => $routeId,
                            'mutation' => $mutationId,
                            'path' => $path,
                        ];
                    }
                }
            }
            return $candidates;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_public_mutation_route_selection_valid')) {
    function red_addon_public_mutation_route_selection_valid($selection)
    {
        if (!is_array($selection)
            || array_keys($selection) !== [
                'claimed',
                'ready',
                'packageId',
                'route',
                'mutation',
                'path',
                'reason',
            ]
            || !is_bool($selection['claimed'])
            || !is_bool($selection['ready'])
            || !is_string($selection['packageId'])
            || !is_string($selection['route'])
            || !is_string($selection['mutation'])
            || !is_string($selection['path'])
            || !is_string($selection['reason'])
        ) {
            return false;
        }
        if ($selection['ready'] === true) {
            return $selection['claimed'] === true
                && red_addon_valid_package_id($selection['packageId'])
                && red_addon_valid_capability($selection['route'])
                && red_addon_valid_capability($selection['mutation'])
                && red_addon_valid_route_path($selection['path'])
                && !str_contains($selection['path'], '%')
                && $selection['reason'] === 'route_selected';
        }
        if ($selection['packageId'] !== ''
            || $selection['route'] !== ''
            || $selection['mutation'] !== ''
            || $selection['path'] !== ''
        ) {
            return false;
        }
        if ($selection['claimed'] === true) {
            return in_array(
                $selection['reason'],
                ['route_unavailable', 'route_ambiguous'],
                true
            );
        }
        return in_array(
            $selection['reason'],
            ['not_matched', 'request_invalid', 'runtime_unavailable'],
            true
        );
    }
}

if (!function_exists('red_addon_public_mutation_route_select')) {
    /**
     * Selects one exact static mutation route without dispatching it.
     */
    function red_addon_public_mutation_route_select($requestTarget)
    {
        $path = red_addon_public_mutation_route_target_path($requestTarget);
        if ($path === '') {
            return red_addon_public_mutation_route_selection_result(
                'request_invalid'
            );
        }
        $context = red_addon_runtime_current_context();
        if (!$context instanceof RED_Addon_Runtime_Context) {
            return red_addon_public_mutation_route_selection_result(
                'runtime_unavailable'
            );
        }
        $candidates = red_addon_public_mutation_route_candidates($context, $path);
        if (!is_array($candidates)) {
            return red_addon_public_mutation_route_selection_result(
                'runtime_unavailable'
            );
        }
        if ($candidates === []) {
            return red_addon_public_mutation_route_selection_result('not_matched');
        }
        if (count($candidates) !== 1) {
            $result = red_addon_public_mutation_route_selection_result(
                'route_ambiguous'
            );
            $result['claimed'] = true;
            return $result;
        }
        $candidate = $candidates[0];
        try {
            $routeOwner = $context->owner('routes', $candidate['route']);
            $mutationOwner = $context->owner(
                'publicMutationHandlers',
                $candidate['mutation']
            );
            $stateLoaderOwner = $context->owner(
                'publicMutationStateLoaders',
                $candidate['mutation']
            );
            $bindingReady = $routeOwner === $candidate['packageId']
                && $mutationOwner === $candidate['packageId']
                && $stateLoaderOwner === $candidate['packageId']
                && is_callable(
                    $context->handler('routes', $candidate['route'])
                )
                && is_callable(
                    $context->handler(
                        'publicMutationHandlers',
                        $candidate['mutation']
                    )
                )
                && is_callable(
                    $context->handler(
                        'publicMutationStateLoaders',
                        $candidate['mutation']
                    )
                );
        } catch (Throwable $throwable) {
            $bindingReady = false;
        }
        if (!$bindingReady) {
            $result = red_addon_public_mutation_route_selection_result(
                'route_unavailable'
            );
            $result['claimed'] = true;
            return $result;
        }
        return [
            'claimed' => true,
            'ready' => true,
            'packageId' => $candidate['packageId'],
            'route' => $candidate['route'],
            'mutation' => $candidate['mutation'],
            'path' => $candidate['path'],
            'reason' => 'route_selected',
        ];
    }
}

?>
