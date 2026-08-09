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
require_once __DIR__ . '/addon_admin_tool_form_runtime_setting_helpers.php';

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

if (!class_exists('RED_Addon_Admin_Tool_Form_Target_Request', false)) {
    final class RED_Addon_Admin_Tool_Form_Target_Request
    {
        private string $tool;
        private string $form;
        private int $actorRecordId;
        private ?string $cursor;
        private RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings;

        public function __construct(
            string $tool,
            string $form,
            int $actorRecordId,
            ?string $cursor,
            RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings
        ) {
            if (!red_addon_valid_capability($tool)
                || !red_addon_valid_capability($form)
                || $actorRecordId < 1
                || $actorRecordId > 2147483647
                || ($cursor !== null
                    && preg_match('/\A[A-Za-z0-9._-]{1,64}\z/D', $cursor)
                        !== 1)
            ) {
                throw new InvalidArgumentException(
                    'Add-on administrator form target request is invalid.'
                );
            }
            $this->tool = $tool;
            $this->form = $form;
            $this->actorRecordId = $actorRecordId;
            $this->cursor = $cursor;
            $this->runtimeSettings = $runtimeSettings;
        }

        public function tool(): string
        {
            return $this->tool;
        }

        public function form(): string
        {
            return $this->form;
        }

        public function actorRecordId(): int
        {
            return $this->actorRecordId;
        }

        public function cursor(): ?string
        {
            return $this->cursor;
        }

        public function limit(): int
        {
            return 25;
        }

        public function runtimeSettings(): RED_Addon_Admin_Tool_Form_Runtime_Settings
        {
            return $this->runtimeSettings;
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_target_facts')) {
    function red_addon_admin_tool_form_target_facts($facts)
    {
        if (!is_array($facts)
            || !array_is_list($facts)
            || count($facts) > 4
        ) {
            return null;
        }
        $normalized = [];
        foreach ($facts as $fact) {
            if (!is_array($fact)
                || array_keys($fact) !== ['label', 'value']
            ) {
                return null;
            }
            $label = red_addon_admin_tool_text($fact['label'] ?? null, 80);
            $value = red_addon_admin_tool_text($fact['value'] ?? null, 160);
            if ($label === null || $value === null) {
                return null;
            }
            $normalized[] = ['label' => $label, 'value' => $value];
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_admin_tool_form_targets')) {
    function red_addon_admin_tool_form_targets($items)
    {
        if (!is_array($items)
            || !array_is_list($items)
            || count($items) > 25
        ) {
            return null;
        }
        $normalized = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)
                || array_keys($item) !== [
                    'targetRecordId',
                    'label',
                    'description',
                    'facts',
                ]
            ) {
                return null;
            }
            $targetRecordId = $item['targetRecordId'] ?? null;
            $label = red_addon_admin_tool_text($item['label'] ?? null, 120);
            $description = red_addon_admin_tool_text(
                $item['description'] ?? null,
                240
            );
            $facts = red_addon_admin_tool_form_target_facts(
                $item['facts'] ?? null
            );
            if (!is_int($targetRecordId)
                || $targetRecordId < 1
                || $targetRecordId > 2147483647
                || isset($seen[$targetRecordId])
                || $label === null
                || $description === null
                || !is_array($facts)
            ) {
                return null;
            }
            $seen[$targetRecordId] = true;
            $normalized[] = [
                'targetRecordId' => $targetRecordId,
                'label' => $label,
                'description' => $description,
                'facts' => $facts,
            ];
        }
        return $normalized;
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Form_Targets', false)) {
    final class RED_Addon_Admin_Tool_Form_Targets
    {
        private array $items;
        private ?string $nextCursor;

        private function __construct(array $items, ?string $nextCursor)
        {
            $this->items = $items;
            $this->nextCursor = $nextCursor;
        }

        public static function page(
            array $items,
            ?string $nextCursor = null
        ): self {
            $items = red_addon_admin_tool_form_targets($items);
            if (!is_array($items)
                || ($nextCursor !== null
                    && preg_match(
                        '/\A[A-Za-z0-9._-]{1,64}\z/D',
                        $nextCursor
                    ) !== 1)
                || ($nextCursor !== null && $items === [])
            ) {
                throw new InvalidArgumentException(
                    'Add-on administrator form targets are invalid.'
                );
            }
            return new self($items, $nextCursor);
        }

        public function pageModel(): array
        {
            return [
                'items' => $this->items,
                'nextCursor' => $this->nextCursor,
            ];
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_target_binding')) {
    function red_addon_admin_tool_form_target_binding($toolId, $formId)
    {
        if (!is_string($toolId)
            || !red_addon_valid_capability($toolId)
            || !is_string($formId)
            || !red_addon_valid_capability($formId)
        ) {
            return null;
        }
        $tool = red_addon_admin_tool_binding($toolId);
        $owner = red_addon_runtime_owner(
            'adminToolFormTargetLoaders',
            $formId
        );
        $handler = red_addon_runtime_handler(
            'adminToolFormTargetLoaders',
            $formId
        );
        $manifest = is_array($tool)
            ? ($tool['manifest'] ?? null)
            : null;
        $contract = is_array($manifest)
            ? red_addon_admin_tool_form_contract(
                $manifest,
                $toolId,
                $formId
            )
            : null;
        if (!is_array($tool)
            || !is_string($owner)
            || !hash_equals($tool['package'], $owner)
            || !is_callable($handler)
            || !is_array($contract)
            || !is_array($contract['fields'] ?? null)
            || $contract['fields'] === []
        ) {
            return null;
        }
        return [
            'tool' => $toolId,
            'form' => $formId,
            'package' => $owner,
            'permission' => $contract['permission'],
            'contract' => $contract,
            'handler' => $handler,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_create_action')) {
    function red_addon_admin_tool_form_create_action(array $binding)
    {
        $package = $binding['package'] ?? null;
        $form = $binding['form'] ?? null;
        $create = $binding['contract']['create'] ?? null;
        if (!is_string($package)
            || !red_addon_valid_package_id($package)
            || !is_string($form)
            || !red_addon_valid_capability($form)
            || !is_array($create)
        ) {
            return null;
        }
        foreach (
            [
                'adminToolFormValueLoaders',
                'adminToolFormInitialValueLoaders',
                'adminToolFormCreators',
            ] as $type
        ) {
            $owner = red_addon_runtime_owner($type, $form);
            if (!is_string($owner)
                || !hash_equals($package, $owner)
                || !is_callable(red_addon_runtime_handler($type, $form))
            ) {
                return null;
            }
        }
        $metadata = red_addon_runtime_metadata(
            'adminToolFormCreators',
            $form
        );
        if (!is_array($metadata)
            || !is_array($metadata['tables'] ?? null)
            || $metadata['tables'] === []
        ) {
            return null;
        }
        return $create;
    }
}

if (!function_exists('red_addon_admin_tool_form_target_page')) {
    function red_addon_admin_tool_form_target_page(
        $connection,
        $toolId,
        $formId,
        $actorRecordId,
        $cursor = null
    ) {
        $result = [
            'loaded' => false,
            'tool' => is_string($toolId) ? $toolId : '',
            'form' => is_string($formId) ? $formId : '',
            'package' => '',
            'permission' => '',
            'label' => '',
            'description' => '',
            'create' => null,
            'items' => [],
            'nextCursor' => null,
            'reason' => 'invalid_request',
        ];
        $binding = red_addon_admin_tool_form_target_binding($toolId, $formId);
        if (!$connection
            || !is_int($actorRecordId)
            || $actorRecordId < 1
            || ($cursor !== null && !is_string($cursor))
            || !is_array($binding)
        ) {
            return $result;
        }
        $result['package'] = $binding['package'];
        $result['permission'] = $binding['permission'];
        $result['label'] = $binding['contract']['label'];
        $result['description'] = $binding['contract']['description'];
        $result['create'] = red_addon_admin_tool_form_create_action($binding);
        if (!red_addon_component_editor_actor_has_permission(
            $connection,
            $actorRecordId,
            $binding['permission']
        )) {
            $result['reason'] = 'permission_denied';
            return $result;
        }
        $runtimeSettings =
            red_addon_admin_tool_form_runtime_settings_resolve(
                $connection,
                $binding
            );
        if (($runtimeSettings['resolved'] ?? false) !== true
            || !($runtimeSettings['settings'] ?? null)
                instanceof RED_Addon_Admin_Tool_Form_Runtime_Settings
        ) {
            $result['reason'] = 'target_settings_unavailable';
            return $result;
        }
        try {
            $request = new RED_Addon_Admin_Tool_Form_Target_Request(
                $toolId,
                $formId,
                $actorRecordId,
                $cursor,
                $runtimeSettings['settings']
            );
        } catch (Throwable $throwable) {
            return $result;
        }

        $bufferLevel = ob_get_level();
        $headersBefore = headers_list();
        $statusBefore = http_response_code();
        try {
            ob_start();
            $page = ($binding['handler'])($connection, $request);
            if (ob_get_level() !== $bufferLevel + 1
                || headers_list() !== $headersBefore
                || http_response_code() !== $statusBefore
            ) {
                throw new RuntimeException(
                    'Add-on administrator form target loader altered response state.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== '') {
                $result['reason'] = 'target_output';
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
            $result['reason'] = 'target_failed';
            return $result;
        }
        if (!$page instanceof RED_Addon_Admin_Tool_Form_Targets) {
            $result['reason'] = 'invalid_target_result';
            return $result;
        }
        $model = $page->pageModel();
        $items = red_addon_admin_tool_form_targets($model['items'] ?? null);
        $nextCursor = $model['nextCursor'] ?? null;
        if (!is_array($items)
            || ($nextCursor !== null
                && (!is_string($nextCursor)
                    || preg_match(
                        '/\A[A-Za-z0-9._-]{1,64}\z/D',
                        $nextCursor
                    ) !== 1))
        ) {
            $result['reason'] = 'invalid_target_result';
            return $result;
        }
        $result['loaded'] = true;
        $result['items'] = $items;
        $result['nextCursor'] = $nextCursor;
        $result['reason'] = 'loaded';
        return $result;
    }
}

if (!function_exists('red_addon_admin_tool_form_target_pages')) {
    function red_addon_admin_tool_form_target_pages(
        $connection,
        array $binding,
        $actorRecordId
    ) {
        $pages = [];
        foreach ($binding['manifest']['adminToolFormContracts'] ?? [] as $form) {
            if (!is_array($form)
                || ($form['tool'] ?? '') !== ($binding['tool'] ?? '')
                || !is_string($form['form'] ?? null)
                || red_addon_runtime_owner(
                    'adminToolFormTargetLoaders',
                    $form['form']
                ) === null
            ) {
                continue;
            }
            $page = red_addon_admin_tool_form_target_page(
                $connection,
                $binding['tool'],
                $form['form'],
                $actorRecordId
            );
            if (($page['loaded'] ?? false) !== true) {
                return null;
            }
            $pages[] = $page;
        }
        return $pages;
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
        array $view,
        array $targetPages = []
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
        foreach ($targetPages as $page) {
            if (!is_array($page)
                || ($page['loaded'] ?? false) !== true
                || !red_addon_valid_capability($page['form'] ?? null)
                || !is_array($page['items'] ?? null)
            ) {
                return '';
            }
            $html .= '<section class="red-admin-addon-tool__targets"'
                . ' data-red-addon-admin-form-targets>';
            $html .= '<header><div><h3>'
                . red_addon_admin_tool_html($page['label'] ?? '')
                . '</h3><p>'
                . red_addon_admin_tool_html($page['description'] ?? '')
                . '</p></div>';
            if (is_array($page['create'] ?? null)) {
                $html .= '<button type="button"'
                    . ' class="red-admin-addon-tool__create"'
                    . ' data-red-addon-admin-form-create-target'
                    . ' data-create-action="/admin/bin/new_addon_tool_form.php"'
                    . ' data-tool="'
                    . red_addon_admin_tool_html($toolId) . '"'
                    . ' data-form="'
                    . red_addon_admin_tool_html($page['form']) . '">'
                    . red_addon_admin_tool_html(
                        $page['create']['label'] ?? 'Create'
                    )
                    . '</button>';
            }
            $html .= '</header>';
            if ($page['items'] === []) {
                $html .= '<p class="red-admin-addon-tool__empty">'
                    . 'No editable records are available.</p>';
            } else {
                $html .= '<div class="red-admin-addon-tool__target-list">';
                foreach ($page['items'] as $item) {
                    $html .= '<article class="red-admin-addon-tool__target">'
                        . '<div class="red-admin-addon-tool__target-copy"><h4>'
                        . red_addon_admin_tool_html($item['label'])
                        . '</h4><p>'
                        . red_addon_admin_tool_html($item['description'])
                        . '</p>';
                    if ($item['facts'] !== []) {
                        $html .= '<dl>';
                        foreach ($item['facts'] as $fact) {
                            $html .= '<div><dt>'
                                . red_addon_admin_tool_html($fact['label'])
                                . '</dt><dd>'
                                . red_addon_admin_tool_html($fact['value'])
                                . '</dd></div>';
                        }
                        $html .= '</dl>';
                    }
                    $html .= '</div><button type="button"'
                        . ' data-red-addon-admin-form-target'
                        . ' data-edit-action="/admin/bin/edit_addon_tool_form.php"'
                        . ' data-tool="'
                        . red_addon_admin_tool_html($toolId) . '"'
                        . ' data-form="'
                        . red_addon_admin_tool_html($page['form']) . '"'
                        . ' data-target-record-id="'
                        . (int) $item['targetRecordId'] . '">Edit</button>'
                        . '</article>';
                }
                $html .= '</div>';
            }
            if (($page['nextCursor'] ?? null) !== null) {
                $html .= '<p class="red-admin-addon-tool__notice">'
                    . 'More records are available; pagination is not yet enabled.'
                    . '</p>';
            }
            $html .= '</section>';
        }
        $html .= '<p class="red-admin-addon-tool__notice">Core-owned package view</p>';
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
        $targetPages = red_addon_admin_tool_form_target_pages(
            $connection,
            $binding,
            $actorRecordId
        );
        if (!is_array($targetPages)) {
            $result['reason'] = 'target_failed';
            return $result;
        }
        $html = red_addon_admin_tool_render(
            $toolId,
            $binding['contract'],
            $toolResult->viewModel(),
            $targetPages
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
