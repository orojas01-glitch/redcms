<?php
/**
 * Permission-scoped, display-only administrator tool dispatch.
 *
 * Core resolves one enabled registrar-owned tool and its data-only manifest
 * contract, performs a fresh exact per-client grant lookup, and accepts only a
 * bounded text view model. Package HTML, forms, scripts, styles, links,
 * actions, database connections, sessions, request globals, and writes are not
 * supplied by this boundary.
 */

require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_component_editor_authorization_helpers.php';

if (!function_exists('red_addon_admin_tool_text')) {
    function red_addon_admin_tool_text($value, $maximum)
    {
        if (!is_string($value)
            || $value === ''
            || trim($value) !== $value
            || strlen($value) > $maximum
            || preg_match('//u', $value) !== 1
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
                === 1
        ) {
            return null;
        }
        return $value;
    }
}

if (!function_exists('red_addon_admin_tool_facts')) {
    function red_addon_admin_tool_facts($facts)
    {
        if (!is_array($facts)
            || !array_is_list($facts)
            || count($facts) > 100
        ) {
            return null;
        }
        $normalized = [];
        foreach ($facts as $fact) {
            if (!is_array($fact)
                || array_is_list($fact)
                || count($fact) !== 2
                || !array_key_exists('label', $fact)
                || !array_key_exists('value', $fact)
            ) {
                return null;
            }
            $label = red_addon_admin_tool_text($fact['label'] ?? null, 120);
            $value = red_addon_admin_tool_text($fact['value'] ?? null, 500);
            if ($label === null || $value === null) {
                return null;
            }
            $normalized[] = ['label' => $label, 'value' => $value];
        }
        return $normalized;
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Request', false)) {
    final class RED_Addon_Admin_Tool_Request
    {
        private string $tool;
        private int $actorRecordId;

        public function __construct(string $tool, int $actorRecordId)
        {
            if (!red_addon_valid_capability($tool) || $actorRecordId < 1) {
                throw new InvalidArgumentException(
                    'Add-on administrator tool request is invalid.'
                );
            }
            $this->tool = $tool;
            $this->actorRecordId = $actorRecordId;
        }

        public function tool(): string
        {
            return $this->tool;
        }

        public function actorRecordId(): int
        {
            return $this->actorRecordId;
        }
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Result', false)) {
    final class RED_Addon_Admin_Tool_Result
    {
        private string $title;
        private string $description;
        private array $facts;

        private function __construct(
            string $title,
            string $description,
            array $facts
        ) {
            $this->title = $title;
            $this->description = $description;
            $this->facts = $facts;
        }

        public static function view(
            string $title,
            string $description,
            array $facts = []
        ): self {
            $title = red_addon_admin_tool_text($title, 120);
            $description = red_addon_admin_tool_text($description, 500);
            $facts = red_addon_admin_tool_facts($facts);
            if ($title === null
                || $description === null
                || !is_array($facts)
            ) {
                throw new InvalidArgumentException(
                    'Add-on administrator tool result is invalid.'
                );
            }
            return new self($title, $description, $facts);
        }

        public function viewModel(): array
        {
            return [
                'title' => $this->title,
                'description' => $this->description,
                'facts' => $this->facts,
            ];
        }
    }
}

if (!function_exists('red_addon_admin_tool_dispatch_result')) {
    function red_addon_admin_tool_dispatch_result(
        $tool,
        $actorRecordId,
        $reason = 'invalid_request'
    ) {
        return [
            'authorized' => false,
            'invoked' => false,
            'success' => false,
            'tool' => is_string($tool) && red_addon_valid_capability($tool)
                ? $tool
                : '',
            'package' => '',
            'actorRecordId' => is_int($actorRecordId) && $actorRecordId > 0
                ? $actorRecordId
                : 0,
            'permission' => '',
            'html' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_binding')) {
    function red_addon_admin_tool_binding($toolId)
    {
        if (!is_string($toolId) || !red_addon_valid_capability($toolId)) {
            return null;
        }
        $owner = red_addon_runtime_owner('adminTools', $toolId);
        $handler = red_addon_runtime_handler('adminTools', $toolId);
        $manifest = is_string($owner)
            ? red_addon_runtime_manifest($owner)
            : null;
        $contract = is_array($manifest)
            ? red_addon_admin_tool_contract($manifest, $toolId)
            : null;
        if (!is_string($owner)
            || !red_addon_valid_package_id($owner)
            || !is_callable($handler)
            || !is_array($manifest)
            || !in_array(
                $toolId,
                $manifest['provides']['adminTools'] ?? [],
                true
            )
            || !is_array($contract)
        ) {
            return null;
        }
        return [
            'tool' => $toolId,
            'package' => $owner,
            'manifest' => $manifest,
            'contract' => $contract,
            'handler' => $handler,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_catalog')) {
    function red_addon_admin_tool_catalog($connection, $actorRecordId)
    {
        $actorRecordId = filter_var(
            $actorRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $context = red_addon_runtime_current_context();
        if ($actorRecordId === false
            || !$context instanceof RED_Addon_Runtime_Context
        ) {
            return [];
        }
        $snapshot = $context->snapshot();
        $tools = array_keys(
            is_array($snapshot['registrations']['adminTools'] ?? null)
                ? $snapshot['registrations']['adminTools']
                : []
        );
        sort($tools, SORT_STRING);
        $catalog = [];
        foreach ($tools as $toolId) {
            $binding = red_addon_admin_tool_binding($toolId);
            if (!is_array($binding)) {
                continue;
            }
            $permission = $binding['contract']['permission'];
            if (!red_addon_component_editor_actor_has_permission(
                $connection,
                $actorRecordId,
                $permission
            )) {
                continue;
            }
            $catalog[] = [
                'tool' => $toolId,
                'package' => $binding['package'],
                'label' => $binding['contract']['label'],
                'description' => $binding['contract']['description'],
                'icon' => $binding['contract']['icon'],
                'permission' => $permission,
                'mode' => 'read-only',
            ];
        }
        usort($catalog, static function (array $left, array $right) {
            $label = strcasecmp($left['label'], $right['label']);
            return $label !== 0 ? $label : strcmp($left['tool'], $right['tool']);
        });
        return $catalog;
    }
}

if (!function_exists('red_addon_admin_tool_html')) {
    function red_addon_admin_tool_html($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('red_addon_admin_tool_render')) {
    function red_addon_admin_tool_render(
        $toolId,
        array $contract,
        array $view
    ) {
        $facts = red_addon_admin_tool_facts($view['facts'] ?? null);
        $title = red_addon_admin_tool_text($view['title'] ?? null, 120);
        $description = red_addon_admin_tool_text(
            $view['description'] ?? null,
            500
        );
        if (!is_string($toolId)
            || !red_addon_valid_capability($toolId)
            || !is_array($facts)
            || $title === null
            || $description === null
            || ($contract['mode'] ?? null) !== 'read-only'
        ) {
            return '';
        }
        $html = '<section class="red-admin-addon-tool" data-addon-tool="' .
            red_addon_admin_tool_html($toolId) . '">';
        $html .= '<header class="red-admin-addon-tool__header">';
        $html .= '<span class="red-admin-addon-tool__eyebrow">Add-on tool</span>';
        $html .= '<h2>' . red_addon_admin_tool_html($title) . '</h2>';
        $html .= '<p>' . red_addon_admin_tool_html($description) . '</p>';
        $html .= '</header>';
        if ($facts !== []) {
            $html .= '<dl class="red-admin-addon-tool__facts">';
            foreach ($facts as $fact) {
                $html .= '<div class="red-admin-addon-tool__fact"><dt>' .
                    red_addon_admin_tool_html($fact['label']) . '</dt><dd>' .
                    red_addon_admin_tool_html($fact['value']) . '</dd></div>';
            }
            $html .= '</dl>';
        }
        $html .= '<p class="red-admin-addon-tool__notice">Read-only package view</p>';
        $html .= '</section>';
        return strlen($html) <= 65536 ? $html : '';
    }
}

if (!function_exists('red_addon_admin_tool_restore_http_state')) {
    function red_addon_admin_tool_restore_http_state(array $headers, $status)
    {
        if (headers_sent()) {
            return;
        }
        header_remove();
        foreach ($headers as $header) {
            if (is_string($header) && $header !== '') {
                header($header, false);
            }
        }
        http_response_code(is_int($status) && $status > 0 ? $status : 200);
    }
}

if (!function_exists('red_addon_admin_tool_dispatch')) {
    function red_addon_admin_tool_dispatch(
        $connection,
        $toolId,
        $actorRecordId
    ) {
        $actorRecordId = filter_var(
            $actorRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $result = red_addon_admin_tool_dispatch_result(
            $toolId,
            $actorRecordId === false ? 0 : $actorRecordId
        );
        if ($actorRecordId === false
            || !is_string($toolId)
            || !red_addon_valid_capability($toolId)
        ) {
            return $result;
        }
        $binding = red_addon_admin_tool_binding($toolId);
        if (!is_array($binding)) {
            $result['reason'] = 'tool_unavailable';
            return $result;
        }
        $result['package'] = $binding['package'];
        $result['permission'] = $binding['contract']['permission'];
        if (!red_addon_component_editor_actor_has_permission(
            $connection,
            $actorRecordId,
            $result['permission']
        )) {
            $result['reason'] = 'permission_denied';
            return $result;
        }
        $result['authorized'] = true;
        try {
            $request = new RED_Addon_Admin_Tool_Request(
                $toolId,
                $actorRecordId
            );
        } catch (Throwable $throwable) {
            $result['authorized'] = false;
            $result['reason'] = 'invalid_request';
            return $result;
        }

        $bufferLevel = ob_get_level();
        $headersBefore = headers_list();
        $statusBefore = http_response_code();
        try {
            ob_start();
            $result['invoked'] = true;
            $toolResult = ($binding['handler'])($request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on administrator tool altered the output buffer stack.'
                );
            }
            if (headers_list() !== $headersBefore
                || http_response_code() !== $statusBefore
            ) {
                throw new RuntimeException(
                    'Add-on administrator tool altered HTTP response state.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== '') {
                $result['reason'] = 'tool_output';
                return $result;
            }
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            red_addon_admin_tool_restore_http_state(
                $headersBefore,
                $statusBefore
            );
            $result['reason'] = 'tool_failed';
            return $result;
        }
        if (!$toolResult instanceof RED_Addon_Admin_Tool_Result) {
            $result['reason'] = 'invalid_result';
            return $result;
        }
        $html = red_addon_admin_tool_render(
            $toolId,
            $binding['contract'],
            $toolResult->viewModel()
        );
        if ($html === '') {
            $result['reason'] = 'invalid_result';
            return $result;
        }
        $result['success'] = true;
        $result['html'] = $html;
        $result['reason'] = 'completed';
        return $result;
    }
}

?>
