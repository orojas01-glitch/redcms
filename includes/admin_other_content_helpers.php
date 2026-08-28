<?php
/**
 * Canonical content rules for the core Other component.
 *
 * Other has one administrator HTML source. Successful content writes mirror
 * those exact bytes to RED_Articles.ShortDesc and LongDesc so embedded and
 * dedicated-page renderers cannot drift. Article intentionally does not use
 * these helpers because its two descriptions remain independent fields.
 */

require_once __DIR__ . '/admin_article_helpers.php';
require_once __DIR__ . '/admin_authorization_helpers.php';

if (!function_exists('red_admin_other_registry_component')) {
    function red_admin_other_registry_component($connection)
    {
        $row = red_admin_component_registry_row($connection, 'Other');
        return is_array($row) && ($row['UniqueName'] ?? '') === 'Other'
            ? (string) $row['UniqueName']
            : '';
    }
}

if (!function_exists('red_admin_other_content_action')) {
    function red_admin_other_content_action(array $post, $mode)
    {
        if ($mode === 'create') {
            return 'create';
        }

        $action = isset($post['OtherContentAction']) && !is_array($post['OtherContentAction'])
            ? (string) $post['OtherContentAction']
            : '';
        if (in_array($action, ['preserve', 'update', 'reconcile'], true)) {
            return $action;
        }

        // Compatibility for a direct content request made before 5.1.1. A
        // request with no content field is metadata-only and must not sync.
        return array_key_exists('OtherContentBase64', $post) || array_key_exists('ShortDesc', $post)
            ? 'update'
            : 'preserve';
    }
}

if (!function_exists('red_admin_other_submitted_content')) {
    function red_admin_other_submitted_content(array $post)
    {
        if (array_key_exists('OtherContentBase64', $post)) {
            if (is_array($post['OtherContentBase64'])) {
                return ['valid' => false, 'html' => ''];
            }
            $encoded = (string) $post['OtherContentBase64'];
            $decoded = base64_decode($encoded, true);
            if (!is_string($decoded) || !hash_equals(base64_encode($decoded), $encoded)) {
                return ['valid' => false, 'html' => ''];
            }
            return ['valid' => true, 'html' => $decoded];
        }

        if (!array_key_exists('ShortDesc', $post) || is_array($post['ShortDesc'])) {
            return ['valid' => false, 'html' => ''];
        }
        return ['valid' => true, 'html' => (string) $post['ShortDesc']];
    }
}

if (!function_exists('red_admin_other_prepare_content')) {
    function red_admin_other_prepare_content(array $post, array $databaseRow, $mode)
    {
        $action = red_admin_other_content_action($post, $mode);
        $short = array_key_exists('ShortDesc', $databaseRow)
            ? red_admin_article_scalar($databaseRow['ShortDesc'])
            : '';
        $long = array_key_exists('LongDesc', $databaseRow)
            ? red_admin_article_scalar($databaseRow['LongDesc'])
            : '';
        $mismatched = $mode === 'update' && $short !== $long;

        if ($action === 'preserve') {
            return [
                'ok' => true,
                'reason' => 'preserved',
                'action' => $action,
                'mismatched' => $mismatched,
                'data' => [],
            ];
        }

        if ($action === 'reconcile') {
            $source = isset($post['OtherReconcileSource']) && !is_array($post['OtherReconcileSource'])
                ? (string) $post['OtherReconcileSource']
                : '';
            if (!$mismatched || !in_array($source, ['short', 'long'], true)) {
                return [
                    'ok' => false,
                    'reason' => $mismatched ? 'selection_required' : 'not_mismatched',
                    'action' => $action,
                    'mismatched' => $mismatched,
                    'data' => [],
                ];
            }
            $html = $source === 'short' ? $short : $long;
            return [
                'ok' => true,
                'reason' => 'reconcile',
                'action' => $action,
                'source' => $source,
                'mismatched' => true,
                'data' => ['ShortDesc' => $html, 'LongDesc' => $html],
            ];
        }

        if ($mode === 'update' && $mismatched) {
            return [
                'ok' => false,
                'reason' => 'reconciliation_required',
                'action' => $action,
                'mismatched' => true,
                'data' => [],
            ];
        }

        $content = red_admin_other_submitted_content($post);
        if (empty($content['valid'])) {
            return [
                'ok' => false,
                'reason' => 'invalid_content',
                'action' => $action,
                'mismatched' => $mismatched,
                'data' => [],
            ];
        }

        $html = (string) $content['html'];
        return [
            'ok' => true,
            'reason' => $mode === 'create' ? 'create' : 'update',
            'action' => $action,
            'mismatched' => $mismatched,
            'data' => ['ShortDesc' => $html, 'LongDesc' => $html],
        ];
    }
}

if (!function_exists('red_admin_other_current_hash_input')) {
    function red_admin_other_current_hash_input(array $post)
    {
        $hash = isset($post['CurrentHash']) && !is_array($post['CurrentHash'])
            ? (string) $post['CurrentHash']
            : '';
        return preg_match('/\A[a-f0-9]{64}\z/', $hash) === 1 ? $hash : '';
    }
}

?>
