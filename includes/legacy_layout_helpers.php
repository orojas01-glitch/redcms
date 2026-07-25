<?php
/**
 * Transitional slot contracts for the four live legacy layouts.
 *
 * Public layout HTML is loaded from validated legacy theme views. This helper
 * owns the stable public/control-panel slot inventory, the prepared inputs,
 * and the final dispatch to the unchanged content component switch.
 */

if (!function_exists('red_legacy_layout_slot_inventory')) {
    function red_legacy_layout_slot_inventory()
    {
        static $inventory = [
            'index' => [
                'public' => [
                    'method' => 'articles',
                    'positions' => ['1', '2', '3'],
                ],
                'control-panel' => [
                    'method' => 'cp_articles',
                    'positions' => ['1', '2', '3', '0'],
                ],
            ],
            'index-1' => [
                'public' => [
                    'method' => 'articles',
                    'positions' => ['1', '2', '3', '4'],
                ],
                'control-panel' => [
                    'method' => 'cp_articles',
                    'positions' => ['1', '2', '3', '4', '0'],
                ],
            ],
            'index-2' => [
                'public' => [
                    'method' => 'articles',
                    'positions' => ['1', '2', '3', '4'],
                ],
                'control-panel' => [
                    'method' => 'cp_articles',
                    'positions' => ['1', '2', '3', '4', '0'],
                ],
            ],
            'index-3' => [
                'public' => [
                    'method' => 'articles',
                    'positions' => ['1', '2'],
                ],
                'control-panel' => [
                    'method' => 'cp_articles',
                    'positions' => ['1', '2', '0'],
                ],
            ],
        ];

        return $inventory;
    }
}

if (!function_exists('red_legacy_layout_slot_context')) {
    function red_legacy_layout_slot_context(
        $articleQuery,
        $varFeatures,
        $varPosition,
        $position,
        $layout,
        $limit,
        $mode = 'public',
        $table = null
    ) {
        $layout = (string) $layout;
        $position = (string) $position;
        $mode = (string) $mode;
        $inventory = red_legacy_layout_slot_inventory();

        if (!isset($inventory[$layout])) {
            throw new InvalidArgumentException('Unsupported legacy layout id: ' . $layout);
        }
        if (!isset($inventory[$layout][$mode])) {
            throw new InvalidArgumentException('Unsupported legacy layout slot mode: ' . $mode);
        }

        $contract = $inventory[$layout][$mode];
        if (!in_array($position, $contract['positions'], true)) {
            throw new InvalidArgumentException(
                'Unsupported position ' . $position . ' for legacy layout ' . $layout . ' in ' . $mode . ' mode.'
            );
        }

        return [
            'mode' => $mode,
            'method' => $contract['method'],
            'articleQuery' => $articleQuery,
            'varFeatures' => $varFeatures,
            'varPosition' => $varPosition,
            'position' => $position,
            'layout' => $layout,
            'limit' => $limit,
            'table' => $mode === 'control-panel' ? $table : null,
        ];
    }
}

if (!function_exists('red_legacy_control_panel_slot_wrapper_context_from_data')) {
    function red_legacy_control_panel_slot_wrapper_context_from_data($layout, $position)
    {
        $layout = (string) $layout;
        $position = (string) $position;
        $inventory = red_legacy_layout_slot_inventory();

        if (!isset($inventory[$layout]['control-panel'])
            || !in_array($position, $inventory[$layout]['control-panel']['positions'], true)
        ) {
            throw new InvalidArgumentException('Unsupported legacy control-panel wrapper slot.');
        }

        $hidden = $position === '0';

        return [
            'layout' => $layout,
            'position' => $position,
            'hidden' => $hidden,
            'titles' => [
                'enabled' => !$hidden,
                'className' => 'cp_titles',
            ],
            'item' => [
                'hiddenStyle' => 'float:left; padding-right:5px; margin-right:5px;',
            ],
            'order' => [
                'enabled' => !$hidden,
                'endpoint' => '/admin/bin/update_order.php',
                'formId' => 'update_order_' . $position,
                'functionName' => 'run_update_order_' . $position,
                'alertId' => 'msggbox_alert_' . $position,
                'csrfRequired' => true,
                'successMessage' => 'Order Updated',
                'failureMessage' => 'Nothing to Update. Please try again.',
            ],
        ];
    }
}

if (!function_exists('red_legacy_control_panel_slot_wrapper_context_validate')) {
    function red_legacy_control_panel_slot_wrapper_context_validate($context, $layout, $position)
    {
        if (!is_array($context)
            || $context !== red_legacy_control_panel_slot_wrapper_context_from_data($layout, $position)
        ) {
            throw new InvalidArgumentException('Invalid legacy control-panel wrapper context.');
        }

        return $context;
    }
}

if (!function_exists('red_legacy_render_layout_slot')) {
    function red_legacy_render_layout_slot(array $context, $renderer = null)
    {
        $requiredKeys = [
            'mode',
            'method',
            'articleQuery',
            'varFeatures',
            'varPosition',
            'position',
            'layout',
            'limit',
            'table',
        ];
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $context)) {
                throw new InvalidArgumentException('Incomplete legacy layout slot context.');
            }
        }

        $inventory = red_legacy_layout_slot_inventory();
        $layout = (string) $context['layout'];
        $mode = (string) $context['mode'];
        $position = (string) $context['position'];
        if (
            !isset($inventory[$layout][$mode])
            || $context['method'] !== $inventory[$layout][$mode]['method']
            || !in_array($position, $inventory[$layout][$mode]['positions'], true)
        ) {
            throw new InvalidArgumentException('Invalid legacy layout slot context.');
        }

        if ($renderer !== null) {
            if (!is_callable($renderer)) {
                throw new InvalidArgumentException('Legacy layout slot renderer must be callable.');
            }

            return $renderer($context);
        }

        if (!class_exists('content')) {
            throw new RuntimeException('The legacy content renderer is not available.');
        }

        $content = new content();
        if ($mode === 'control-panel') {
            $wrapperContext = red_legacy_control_panel_slot_wrapper_context_from_data($layout, $position);

            return $content->cp_articles(
                $context['articleQuery'],
                $context['varFeatures'],
                $context['varPosition'],
                $position,
                $layout,
                $context['limit'],
                $context['table'],
                $wrapperContext
            );
        }

        return $content->articles(
            $context['articleQuery'],
            $context['varFeatures'],
            $context['varPosition'],
            $position,
            $layout,
            $context['limit']
        );
    }
}
