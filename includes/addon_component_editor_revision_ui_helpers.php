<?php
/**
 * Display-only administrator markup for validated add-on component revisions.
 *
 * The caller must supply the value-free result returned by
 * red_addon_component_revision_history() and the current core-owned state
 * hash. This helper performs no authorization lookup, package execution,
 * restore preflight, form rendering, or database write.
 */

if (!function_exists('red_addon_component_revision_ui_html')) {
    function red_addon_component_revision_ui_html($value)
    {
        return htmlspecialchars(
            is_scalar($value) ? (string) $value : '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('red_addon_component_revision_ui_unavailable')) {
    function red_addon_component_revision_ui_unavailable()
    {
        return '<div class="red-admin-addon-history red-admin-addon-history--unavailable"'
            . ' data-red-addon-revision-history-unavailable role="status">'
            . 'Component history is unavailable.</div>';
    }
}

if (!function_exists('red_addon_component_revision_ui_state')) {
    function red_addon_component_revision_ui_state(
        array $history,
        $currentStateHash
    ) {
        if ($history === []
            || !array_is_list($history)
            || !is_string($currentStateHash)
            || preg_match('/\A[a-f0-9]{64}\z/D', $currentStateHash) !== 1
        ) {
            return null;
        }

        $expectedKeys = [
            'revisionId',
            'revisionNumber',
            'operation',
            'actorRecordId',
            'actorAlias',
            'stateHash',
            'restoredFromRevisionId',
            'createdAt',
        ];
        $allowedOperations = ['baseline', 'checkpoint', 'save', 'restore'];
        $seenRevisionIds = [];
        $previousRevisionNumber = null;
        $entries = [];

        foreach ($history as $index => $entry) {
            if (!is_array($entry)
                || array_keys($entry) !== $expectedKeys
                || !is_int($entry['revisionId'])
                || $entry['revisionId'] < 1
                || isset($seenRevisionIds[$entry['revisionId']])
                || !is_int($entry['revisionNumber'])
                || $entry['revisionNumber'] < 1
                || ($previousRevisionNumber !== null
                    && $entry['revisionNumber'] >= $previousRevisionNumber)
                || !is_string($entry['operation'])
                || !in_array($entry['operation'], $allowedOperations, true)
                || !is_int($entry['actorRecordId'])
                || $entry['actorRecordId'] < 1
                || !is_string($entry['actorAlias'])
                || preg_match('/[\x00-\x1F\x7F]/', $entry['actorAlias']) === 1
                || !is_string($entry['stateHash'])
                || preg_match('/\A[a-f0-9]{64}\z/D', $entry['stateHash']) !== 1
                || !is_int($entry['restoredFromRevisionId'])
                || ($entry['operation'] === 'restore'
                    ? $entry['restoredFromRevisionId'] < 1
                    : $entry['restoredFromRevisionId'] !== 0)
                || !is_string($entry['createdAt'])
                || $entry['createdAt'] === ''
                || strlen($entry['createdAt']) > 64
                || preg_match('/[\x00-\x1F\x7F]/', $entry['createdAt']) === 1
            ) {
                return null;
            }

            $matchesCurrent = hash_equals(
                $currentStateHash,
                $entry['stateHash']
            );
            if ($index === 0 && !$matchesCurrent) {
                return null;
            }
            $status = $index === 0
                ? 'current'
                : ($matchesCurrent ? 'matches_current' : 'preflight_required');
            $entries[] = $entry + ['status' => $status];
            $seenRevisionIds[$entry['revisionId']] = true;
            $previousRevisionNumber = $entry['revisionNumber'];
        }

        return ['entries' => $entries];
    }
}

if (!function_exists('red_addon_component_revision_ui_render')) {
    function red_addon_component_revision_ui_render(
        array $history,
        $currentStateHash,
        $componentLabel = 'Component',
        $idPrefix = 'addon-history'
    ) {
        if (!is_string($componentLabel)
            || $componentLabel === ''
            || strlen($componentLabel) > 120
            || preg_match('/[\x00-\x1F\x7F]/', $componentLabel) === 1
            || !is_string($idPrefix)
            || preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $idPrefix) !== 1
        ) {
            return red_addon_component_revision_ui_unavailable();
        }
        $state = red_addon_component_revision_ui_state(
            $history,
            $currentStateHash
        );
        if (!is_array($state)) {
            return red_addon_component_revision_ui_unavailable();
        }

        $operationLabels = [
            'baseline' => 'Baseline',
            'checkpoint' => 'Checkpoint',
            'save' => 'Saved',
            'restore' => 'Restored',
        ];
        $statusLabels = [
            'current' => 'Current',
            'matches_current' => 'Matches current',
            'preflight_required' => 'Restore check required',
        ];
        $headingId = $idPrefix . '-heading';
        $html = '<section class="red-admin-addon-history"'
            . ' data-red-addon-revision-history aria-labelledby="'
            . red_addon_component_revision_ui_html($headingId) . '">'
            . '<div class="red-admin-addon-history__header">'
            . '<div><h3 id="' . red_addon_component_revision_ui_html($headingId)
            . '">Version history</h3><p>'
            . red_addon_component_revision_ui_html($componentLabel)
            . ' · validated package-value revisions</p></div>'
            . '<span class="red-admin-addon-history__count">'
            . count($state['entries']) . ' shown</span></div>'
            . '<p class="red-admin-addon-history__notice">This timeline is read-only. '
            . 'A restore still requires a fresh permission, integrity, and state check.</p>'
            . '<ol class="red-admin-addon-history__list">';

        foreach ($state['entries'] as $entry) {
            $actor = $entry['actorAlias'] !== ''
                ? $entry['actorAlias']
                : 'Administrator #' . $entry['actorRecordId'];
            $status = $statusLabels[$entry['status']];
            $html .= '<li class="red-admin-addon-history__item"'
                . ' data-revision-number="' . $entry['revisionNumber'] . '">'
                . '<div class="red-admin-addon-history__revision">'
                . '<strong>Revision ' . $entry['revisionNumber'] . '</strong>'
                . '<span class="red-admin-addon-history__status red-admin-addon-history__status--'
                . red_addon_component_revision_ui_html($entry['status']) . '">'
                . red_addon_component_revision_ui_html($status) . '</span></div>'
                . '<dl class="red-admin-addon-history__meta">'
                . '<div><dt>Change</dt><dd>'
                . red_addon_component_revision_ui_html(
                    $operationLabels[$entry['operation']]
                ) . '</dd></div>'
                . '<div><dt>By</dt><dd>'
                . red_addon_component_revision_ui_html($actor) . '</dd></div>'
                . '<div><dt>Recorded</dt><dd><time>'
                . red_addon_component_revision_ui_html($entry['createdAt'])
                . '</time></dd></div>';
            if ($entry['operation'] === 'restore') {
                $html .= '<div><dt>Source</dt><dd>Revision record #'
                    . $entry['restoredFromRevisionId'] . '</dd></div>';
            }
            $html .= '</dl></li>';
        }

        return $html . '</ol></section>';
    }
}
