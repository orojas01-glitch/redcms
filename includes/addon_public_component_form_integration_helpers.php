<?php
/**
 * Core-owned integration of one public component mutation presentation.
 *
 * A caller supplies an already returned component view model, its exact
 * placement context, one explicit database connection, and an optional raw
 * subject-cookie value. Core revalidates the model and runtime ownership,
 * bootstraps browser evidence, and returns escaped form markup plus the
 * lifecycle descriptor for a later response owner. This helper invokes no
 * package callback, reads no request global, and emits no output, header, or
 * cookie. It does not create or dispatch a public endpoint.
 */

require_once __DIR__ . '/addon_component_render_helpers.php';
require_once __DIR__ . '/addon_public_mutation_form_bootstrap_helpers.php';

if (!function_exists('red_addon_public_component_form_integration_result')) {
    function red_addon_public_component_form_integration_result(
        $reason = 'integration_invalid'
    ) {
        $allowed = [
            'integration_invalid',
            'component_unavailable',
            'ownership_invalid',
            'bootstrap_unavailable',
            'render_unavailable',
        ];
        $reason = is_string($reason) && in_array($reason, $allowed, true)
            ? $reason
            : 'integration_invalid';
        return [
            'valid' => false,
            'available' => false,
            'component' => '',
            'package' => '',
            'formModel' => [],
            'formHtml' => '',
            'lifecycle' => [],
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_component_form_integration_absent')) {
    function red_addon_public_component_form_integration_absent(
        $componentId,
        $packageId
    ) {
        return [
            'valid' => true,
            'available' => false,
            'component' => $componentId,
            'package' => $packageId,
            'formModel' => [],
            'formHtml' => '',
            'lifecycle' => [],
            'reason' => 'presentation_absent',
        ];
    }
}

if (!function_exists('red_addon_public_component_form_context')) {
    function red_addon_public_component_form_context($context)
    {
        if (!is_array($context)
            || array_keys($context) !== ['component', 'active', 'inputs']
            || !is_string($context['component'])
            || !red_addon_valid_capability($context['component'])
            || ($context['active'] ?? null) !== true
            || !is_array($context['inputs'])
            || array_keys($context['inputs']) !== [
                'recordId', 'layout', 'article', 'position',
            ]
            || !is_int($context['inputs']['recordId'])
            || $context['inputs']['recordId'] < 1
            || !is_int($context['inputs']['position'])
            || $context['inputs']['position'] < 0
            || red_addon_public_component_scalar(
                $context['inputs']['layout'] ?? null,
                160
            ) === null
            || red_addon_public_component_scalar(
                $context['inputs']['article'] ?? null,
                240
            ) === null
        ) {
            return null;
        }
        return $context;
    }
}

if (!function_exists('red_addon_public_component_form_runtime_owner')) {
    function red_addon_public_component_form_runtime_owner(
        $componentId,
        array $presentation
    ) {
        $packageId = red_addon_runtime_owner('components', $componentId);
        if (!is_string($packageId)
            || !red_addon_valid_package_id($packageId)
        ) {
            return null;
        }
        foreach ([
            'routes' => $presentation['route'],
            'publicMutationHandlers' => $presentation['mutation'],
            'publicMutationStateLoaders' => $presentation['mutation'],
        ] as $type => $id) {
            if (!hash_equals(
                $packageId,
                (string) red_addon_runtime_owner($type, $id)
            )) {
                return null;
            }
        }
        $manifest = red_addon_runtime_manifest($packageId);
        if (!is_array($manifest)
            || ($manifest['id'] ?? null) !== $packageId
        ) {
            return null;
        }
        return ['package' => $packageId, 'manifest' => $manifest];
    }
}

if (!function_exists('red_addon_public_component_form_integrate')) {
    function red_addon_public_component_form_integrate(
        $connection,
        $context,
        $viewModel,
        $cookieValue = ''
    ) {
        $context = red_addon_public_component_form_context($context);
        $viewModel = red_addon_public_component_view_model($viewModel);
        if (!is_array($context)
            || !is_array($viewModel)
            || !is_string($cookieValue)
        ) {
            return red_addon_public_component_form_integration_result();
        }
        $componentId = $context['component'];
        $packageId = red_addon_runtime_owner('components', $componentId);
        if (!is_string($packageId)
            || !red_addon_valid_package_id($packageId)
        ) {
            return red_addon_public_component_form_integration_result(
                'component_unavailable'
            );
        }
        if (!array_key_exists('mutationForm', $viewModel)) {
            return red_addon_public_component_form_integration_absent(
                $componentId,
                $packageId
            );
        }
        $runtime = red_addon_public_component_form_runtime_owner(
            $componentId,
            $viewModel['mutationForm']
        );
        if (!is_array($runtime)) {
            return red_addon_public_component_form_integration_result(
                'ownership_invalid'
            );
        }
        if (!$connection instanceof mysqli) {
            return red_addon_public_component_form_integration_result();
        }

        $presentation = $viewModel['mutationForm'];
        $bootstrap = red_addon_public_mutation_form_bootstrap(
            $connection,
            $runtime['manifest'],
            $presentation['route'],
            $presentation['mutation'],
            'component-' . $context['inputs']['recordId'],
            $presentation['submitLabel'],
            $presentation['fields'],
            $cookieValue
        );
        if (!red_addon_public_mutation_form_bootstrap_result_valid($bootstrap)
            || empty($bootstrap['valid'])
        ) {
            return red_addon_public_component_form_integration_result(
                'bootstrap_unavailable'
            );
        }
        $html = red_addon_public_mutation_form_ui_render(
            $bootstrap['formModel']
        );
        if (!is_string($html) || $html === '') {
            return red_addon_public_component_form_integration_result(
                'render_unavailable'
            );
        }
        return [
            'valid' => true,
            'available' => true,
            'component' => $componentId,
            'package' => $runtime['package'],
            'formModel' => $bootstrap['formModel'],
            'formHtml' => $html,
            'lifecycle' => $bootstrap['lifecycle'],
            'reason' => 'component_form_ready',
        ];
    }
}

if (!function_exists('red_addon_public_component_form_integration_valid')) {
    function red_addon_public_component_form_integration_valid($result)
    {
        if (!is_array($result)
            || array_keys($result) !== [
                'valid', 'available', 'component', 'package', 'formModel',
                'formHtml', 'lifecycle', 'reason',
            ]
            || !is_bool($result['valid'])
            || !is_bool($result['available'])
            || !is_string($result['component'])
            || !is_string($result['package'])
            || !is_array($result['formModel'])
            || !is_string($result['formHtml'])
            || !is_array($result['lifecycle'])
            || !is_string($result['reason'])
        ) {
            return false;
        }
        if (!$result['valid']) {
            return $result === red_addon_public_component_form_integration_result(
                $result['reason']
            );
        }
        if (!red_addon_valid_capability($result['component'])
            || !red_addon_valid_package_id($result['package'])
        ) {
            return false;
        }
        if (!$result['available']) {
            return $result['reason'] === 'presentation_absent'
                && $result['formModel'] === []
                && $result['formHtml'] === ''
                && $result['lifecycle'] === [];
        }
        return $result['reason'] === 'component_form_ready'
            && red_addon_public_mutation_form_ui_model_valid(
                $result['formModel']
            )
            && hash_equals(
                red_addon_public_mutation_form_ui_render($result['formModel']),
                $result['formHtml']
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
