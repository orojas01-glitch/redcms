<?php

if (!function_exists('red_admin_list_ui_html')) {
    function red_admin_list_ui_html($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('red_admin_list_ui_edit_icon')) {
    function red_admin_list_ui_edit_icon()
    {
        return '<svg viewBox="0 0 24 24" focusable="false"><path d="M4.5 19.5l4.2-.9L19 8.3a1.8 1.8 0 0 0 0-2.6l-.7-.7a1.8 1.8 0 0 0-2.6 0L5.4 15.3z"/><path d="m14.2 6.5 3.3 3.3M4.5 19.5h15"/></svg>';
    }
}

if (!function_exists('red_admin_list_ui_action_button')) {
    function red_admin_list_ui_action_button($onClick, $accessibleLabel, $buttonLabel = 'Edit')
    {
        return '<button type="button" class="red-admin-area-list__action" onclick="' . red_admin_list_ui_html($onClick) . '" aria-label="' . red_admin_list_ui_html($accessibleLabel) . '">' .
            '<span class="red-admin-area-list__action-icon" aria-hidden="true">' . red_admin_list_ui_edit_icon() . '</span>' .
            '<span class="red-admin-area-list__action-label">' . red_admin_list_ui_html($buttonLabel) . '</span>' .
            '</button>';
    }
}

if (!function_exists('red_admin_list_ui_action_link')) {
    function red_admin_list_ui_action_link($href, $accessibleLabel, $buttonLabel = 'Open')
    {
        return '<a class="red-admin-area-list__action" href="' . red_admin_list_ui_html($href) . '" aria-label="' . red_admin_list_ui_html($accessibleLabel) . '">' .
            '<span class="red-admin-area-list__action-icon" aria-hidden="true">' . red_admin_list_ui_edit_icon() . '</span>' .
            '<span class="red-admin-area-list__action-label">' . red_admin_list_ui_html($buttonLabel) . '</span>' .
            '</a>';
    }
}

if (!function_exists('red_admin_list_ui_status')) {
    function red_admin_list_ui_status($value)
    {
        $normalized = strtoupper(trim((string) $value));
        $isActive = in_array($normalized, ['Y', 'YES', '1', 'ACTIVE'], true);
        $label = $isActive ? 'Active' : 'Inactive';
        $modifier = $isActive ? 'active' : 'inactive';

        return '<span class="red-admin-area-list__status red-admin-area-list__status--' . $modifier . '" aria-label="Status: ' . $label . '">' .
            '<span class="red-admin-area-list__status-dot" aria-hidden="true"></span>' . $label . '</span>';
    }
}

if (!function_exists('red_admin_list_ui_item_count')) {
    function red_admin_list_ui_item_count($count)
    {
        $count = (int) $count;
        return $count . ' ' . ($count === 1 ? 'item' : 'items');
    }
}

