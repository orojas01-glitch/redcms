<?php
/**
 * Safe public dispatch for enabled, manifest-declared add-on components.
 *
 * Add-on PHP is trusted operator-reviewed code, not a sandbox. This boundary
 * nevertheless keeps the value it receives and the markup it can influence
 * deliberately small: page placement scalars in, a bounded text view model
 * out. Core may render an optional closed list of label/value facts, but
 * package HTML remains forbidden.
 * Core owns the default renderer and never selects a class, template, or
 * callback from a page row.
 */

require_once __DIR__ . '/addon_runtime_helpers.php';

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

if (!function_exists('red_addon_public_component_view_model')) {
    function red_addon_public_component_view_model($viewModel)
    {
        if (!is_array($viewModel)
            || !in_array(
                array_keys($viewModel),
                [
                    ['title', 'summary'],
                    ['title', 'summary', 'facts'],
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
        if (!array_key_exists('facts', $viewModel)) {
            return $normalized;
        }
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
    function red_addon_public_component_render(array $context)
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
        echo '</section>';
        return true;
    }
}
