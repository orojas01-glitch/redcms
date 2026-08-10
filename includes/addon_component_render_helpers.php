<?php
/**
 * Safe public dispatch for enabled, manifest-declared add-on components.
 *
 * Add-on PHP is trusted operator-reviewed code, not a sandbox. This boundary
 * nevertheless keeps the value it receives and the markup it can influence
 * deliberately small: page placement scalars in, a bounded data-only view
 * model out. Core may render an optional closed list of label/value facts and
 * retain one validated public-mutation form presentation for a later
 * core-owned controller, but package HTML and browser authority remain
 * forbidden.
 * Core owns the default renderer and never selects a class, template, or
 * callback from a page row.
 */

require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_public_mutation_form_ui_helpers.php';

if (!function_exists('red_addon_public_component_available')) {
    function red_addon_public_component_available($componentId)
    {
        return is_string($componentId)
            && red_addon_valid_capability($componentId)
            && is_string(red_addon_runtime_owner('components', $componentId))
            && is_callable(red_addon_runtime_handler('components', $componentId));
    }
}

if (!function_exists('red_addon_public_component_scalar')) {
    function red_addon_public_component_scalar($value, $maximumLength)
    {
        if (!is_scalar($value) && $value !== null) {
            return null;
        }
        $value = trim((string) $value);
        if (strlen($value) > $maximumLength || strpos($value, "\0") !== false) {
            return null;
        }

        return $value;
    }
}

if (!function_exists('red_addon_public_component_context')) {
    function red_addon_public_component_context(
        $componentId,
        $recordId,
        $layout,
        $article,
        $position,
        $active
    ) {
        if (!is_string($componentId)
            || !red_addon_valid_capability($componentId)
            || !is_bool($active)
        ) {
            return null;
        }
        $owner = red_addon_runtime_owner('components', $componentId);
        if (!is_string($owner) || !red_addon_valid_package_id($owner)) {
            return null;
        }

        $recordId = filter_var(
            $recordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
        );
        $position = filter_var(
            $position,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
        );
        $layout = red_addon_public_component_scalar($layout, 160);
        $article = red_addon_public_component_scalar($article, 240);
        if ($recordId === false || $position === false
            || $layout === null || $article === null
        ) {
            return null;
        }

        return [
            'component' => $componentId,
            'active' => $active,
            'inputs' => [
                'recordId' => (int) $recordId,
                'layout' => $layout,
                'article' => $article,
                'position' => (int) $position,
            ],
        ];
    }
}

if (!function_exists('red_addon_public_component_mutation_form_field')) {
    function red_addon_public_component_mutation_form_field($field)
    {
        if (!is_array($field)
            || !is_string($field['key'] ?? null)
            || !red_addon_valid_component_field_key($field['key'])
            || in_array(
                $field['key'],
                red_addon_public_mutation_reserved_field_keys(),
                true
            )
            || !is_string($field['control'] ?? null)
        ) {
            return null;
        }

        $control = $field['control'];
        if ($control === 'hidden') {
            if (array_keys($field) !== ['key', 'control', 'value']
                || !red_addon_public_mutation_form_identifier_valid(
                    $field['value'] ?? null,
                    1,
                    160
                )
            ) {
                return null;
            }
            return $field;
        }
        if ($control === 'number') {
            if (array_keys($field) !== [
                'key', 'control', 'label', 'value',
            ]
                || red_addon_public_mutation_form_ui_text(
                    $field['label'] ?? null,
                    80
                ) === ''
                || !is_int($field['value'] ?? null)
                || $field['value'] < 1
                || $field['value'] > 2147483647
            ) {
                return null;
            }
            return $field;
        }
        if ($control !== 'select'
            || array_keys($field) !== [
                'key', 'control', 'label', 'value', 'options',
            ]
            || red_addon_public_mutation_form_ui_text(
                $field['label'] ?? null,
                80
            ) === ''
            || !red_addon_public_mutation_form_identifier_valid(
                $field['value'] ?? null,
                1,
                160
            )
            || !is_array($field['options'] ?? null)
            || !array_is_list($field['options'])
            || count($field['options']) < 1
            || count($field['options']) > 128
        ) {
            return null;
        }
        $seen = [];
        foreach ($field['options'] as $option) {
            if (!is_array($option)
                || array_keys($option) !== ['value', 'label']
                || !red_addon_public_mutation_form_identifier_valid(
                    $option['value'] ?? null,
                    1,
                    160
                )
                || red_addon_public_mutation_form_ui_text(
                    $option['label'] ?? null,
                    120
                ) === ''
                || isset($seen[$option['value']])
            ) {
                return null;
            }
            $seen[$option['value']] = true;
        }
        return isset($seen[$field['value']]) ? $field : null;
    }
}

if (!function_exists('red_addon_public_component_mutation_form_presentation')) {
    function red_addon_public_component_mutation_form_presentation(
        $presentation
    ) {
        if (!is_array($presentation)
            || array_keys($presentation) !== [
                'route', 'mutation', 'submitLabel', 'fields',
            ]
            || !red_addon_valid_capability($presentation['route'] ?? null)
            || !red_addon_valid_capability($presentation['mutation'] ?? null)
            || red_addon_public_mutation_form_ui_text(
                $presentation['submitLabel'] ?? null,
                80
            ) === ''
            || !is_array($presentation['fields'] ?? null)
            || !array_is_list($presentation['fields'])
            || count($presentation['fields']) < 1
            || count($presentation['fields']) > 8
        ) {
            return null;
        }
        $fields = [];
        $seen = [];
        foreach ($presentation['fields'] as $field) {
            $normalized =
                red_addon_public_component_mutation_form_field($field);
            $key = is_array($normalized) ? $normalized['key'] : '';
            if ($key === '' || isset($seen[$key])) {
                return null;
            }
            $seen[$key] = true;
            $fields[] = $normalized;
        }
        return [
            'route' => $presentation['route'],
            'mutation' => $presentation['mutation'],
            'submitLabel' => $presentation['submitLabel'],
            'fields' => $fields,
        ];
    }
}

if (!function_exists('red_addon_public_component_collection_presentation')) {
    function red_addon_public_component_collection_presentation($collection)
    {
        if (!is_array($collection)
            || array_keys($collection) !== ['label', 'items']
            || !is_string($collection['label'] ?? null)
            || !is_array($collection['items'] ?? null)
            || !array_is_list($collection['items'])
            || count($collection['items']) < 1
            || count($collection['items']) > 24
        ) {
            return null;
        }
        $label = red_addon_public_component_scalar($collection['label'], 80);
        if ($label === null || $label === '') {
            return null;
        }
        $items = [];
        foreach ($collection['items'] as $item) {
            if (!is_array($item)
                || array_keys($item) !== ['title', 'facts']
                || !is_string($item['title'] ?? null)
                || !is_array($item['facts'] ?? null)
                || !array_is_list($item['facts'])
                || count($item['facts']) < 1
                || count($item['facts']) > 4
            ) {
                return null;
            }
            $title = red_addon_public_component_scalar($item['title'], 200);
            if ($title === null || $title === '') {
                return null;
            }
            $facts = [];
            foreach ($item['facts'] as $fact) {
                if (!is_array($fact)
                    || array_keys($fact) !== ['label', 'value']
                    || !is_string($fact['label'] ?? null)
                    || !is_string($fact['value'] ?? null)
                ) {
                    return null;
                }
                $factLabel = red_addon_public_component_scalar($fact['label'], 80);
                $value = red_addon_public_component_scalar($fact['value'], 2000);
                if ($factLabel === null || $factLabel === ''
                    || $value === null || $value === ''
                ) {
                    return null;
                }
                $facts[] = ['label' => $factLabel, 'value' => $value];
            }
            $items[] = ['title' => $title, 'facts' => $facts];
        }
        return ['label' => $label, 'items' => $items];
    }
}

if (!function_exists('red_addon_public_component_view_model')) {
    function red_addon_public_component_view_model($viewModel)
    {
        if (!is_array($viewModel)
            || !in_array(
                array_keys($viewModel),
                [
                    ['title', 'summary'],
                    ['title', 'summary', 'facts'],
                    ['title', 'summary', 'mutationForm'],
                    ['title', 'summary', 'facts', 'mutationForm'],
                    ['title', 'summary', 'collection'],
                    ['title', 'summary', 'facts', 'collection'],
                    ['title', 'summary', 'collection', 'mutationForm'],
                    ['title', 'summary', 'facts', 'collection', 'mutationForm'],
                ],
                true
            )
            || !is_string($viewModel['title'])
            || !is_string($viewModel['summary'])
        ) {
            return null;
        }

        $title = red_addon_public_component_scalar($viewModel['title'], 200);
        $summary = red_addon_public_component_scalar($viewModel['summary'], 2000);
        if ($title === null || $title === '' || $summary === null) {
            return null;
        }

        $normalized = [
            'title' => $title,
            'summary' => $summary,
        ];
        if (array_key_exists('facts', $viewModel)) {
            if (!is_array($viewModel['facts'])
                || !array_is_list($viewModel['facts'])
                || count($viewModel['facts']) > 12
            ) {
                return null;
            }
            $facts = [];
            foreach ($viewModel['facts'] as $fact) {
                if (!is_array($fact)
                    || array_keys($fact) !== ['label', 'value']
                    || !is_string($fact['label'])
                    || !is_string($fact['value'])
                ) {
                    return null;
                }
                $label = red_addon_public_component_scalar($fact['label'], 80);
                $value = red_addon_public_component_scalar($fact['value'], 2000);
                if ($label === null || $label === ''
                    || $value === null || $value === ''
                ) {
                    return null;
                }
                $facts[] = ['label' => $label, 'value' => $value];
            }
            $normalized['facts'] = $facts;
        }
        if (array_key_exists('mutationForm', $viewModel)) {
            $mutationForm =
                red_addon_public_component_mutation_form_presentation(
                    $viewModel['mutationForm']
                );
            if (!is_array($mutationForm)) {
                return null;
            }
            $normalized['mutationForm'] = $mutationForm;
        }
        if (array_key_exists('collection', $viewModel)) {
            $collection = red_addon_public_component_collection_presentation(
                $viewModel['collection']
            );
            if (!is_array($collection)) {
                return null;
            }
            $normalized['collection'] = $collection;
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_public_component_escape')) {
    function red_addon_public_component_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('red_addon_public_component_render_unavailable')) {
    function red_addon_public_component_render_unavailable($componentId)
    {
        echo '<section class="red-addon-component" data-red-addon-component="' .
            red_addon_public_component_escape($componentId) .
            '" aria-label="Unavailable content"><p>Content is temporarily unavailable.</p></section>';
    }
}

if (!function_exists('red_addon_public_component_render')) {
    function red_addon_public_component_render(
        array $context,
        $connection = null
    )
    {
        if (array_keys($context) !== ['component', 'active', 'inputs']
            || !is_string($context['component'])
            || !is_bool($context['active'])
            || !is_array($context['inputs'])
            || array_keys($context['inputs']) !== ['recordId', 'layout', 'article', 'position']
        ) {
            throw new InvalidArgumentException('Invalid add-on public component context.');
        }
        if (!$context['active'] || !red_addon_public_component_available($context['component'])) {
            return false;
        }

        $handler = red_addon_runtime_handler('components', $context['component']);
        $viewModel = null;
        $emitted = '';
        $outputLevel = ob_get_level();
        try {
            ob_start();
            $viewModel = $handler([
                'component' => $context['component'],
                'placement' => $context['inputs'],
            ]);
            if (ob_get_level() !== $outputLevel + 1) {
                throw new RuntimeException('Add-on component altered the output buffer stack.');
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $outputLevel) {
                ob_end_clean();
            }
            error_log('RED-CMS add-on component rendering failed: ' . $context['component']);
            red_addon_public_component_render_unavailable($context['component']);
            return true;
        }

        $viewModel = $emitted === ''
            ? red_addon_public_component_view_model($viewModel)
            : null;
        if ($viewModel === null) {
            error_log('RED-CMS add-on component returned an invalid public view: ' . $context['component']);
            red_addon_public_component_render_unavailable($context['component']);
            return true;
        }

        $formHtml = '';
        if (array_key_exists('mutationForm', $viewModel)
            && $connection instanceof mysqli
            && function_exists('red_addon_public_mutation_page_integrate')
        ) {
            $integration = red_addon_public_mutation_page_integrate(
                $connection,
                $context,
                $viewModel
            );
            if (red_addon_public_component_form_integration_valid($integration)
                && !empty($integration['valid'])
                && !empty($integration['available'])
            ) {
                $formHtml = $integration['formHtml'];
            } elseif (($integration['reason'] ?? '')
                !== 'integration_invalid'
            ) {
                error_log(
                    'RED-CMS add-on component form is unavailable: ' .
                    $context['component']
                );
            }
        }

        echo '<section class="red-addon-component" data-red-addon-component="' .
            red_addon_public_component_escape($context['component']) .
            '"><h2>' . red_addon_public_component_escape($viewModel['title']) .
            '</h2>';
        if ($viewModel['summary'] !== '') {
            echo '<p>' . red_addon_public_component_escape($viewModel['summary']) . '</p>';
        }
        if (array_key_exists('facts', $viewModel)
            && $viewModel['facts'] !== []
        ) {
            echo '<dl class="red-addon-component__facts">';
            foreach ($viewModel['facts'] as $fact) {
                echo '<div><dt>'
                    . red_addon_public_component_escape($fact['label'])
                    . '</dt><dd>'
                    . red_addon_public_component_escape($fact['value'])
                    . '</dd></div>';
            }
            echo '</dl>';
        }
        if (array_key_exists('collection', $viewModel)) {
            echo '<section class="red-addon-component__collection" aria-label="'
                . red_addon_public_component_escape($viewModel['collection']['label'])
                . '"><h3>'
                . red_addon_public_component_escape($viewModel['collection']['label'])
                . '</h3><ol>';
            foreach ($viewModel['collection']['items'] as $item) {
                echo '<li><h4>'
                    . red_addon_public_component_escape($item['title'])
                    . '</h4><dl>';
                foreach ($item['facts'] as $fact) {
                    echo '<div><dt>'
                        . red_addon_public_component_escape($fact['label'])
                        . '</dt><dd>'
                        . red_addon_public_component_escape($fact['value'])
                        . '</dd></div>';
                }
                echo '</dl></li>';
            }
            echo '</ol></section>';
        }
        echo $formHtml;
        echo '</section>';
        return true;
    }
}
