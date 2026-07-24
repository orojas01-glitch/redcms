<?php
/**
 * Reusable administrator version-history disclosure.
 */

if (!function_exists('red_admin_content_revision_panel')) {
    function red_admin_content_revision_panel($contentRecordId)
    {
        $contentRecordId = (int) $contentRecordId;
        if ($contentRecordId <= 0) {
            return;
        }
        ?>
        <details
            class="red-admin-revisions"
            data-red-revision-panel
            data-content-record-id="<?php echo $contentRecordId; ?>"
            data-current-hash=""
        >
            <summary data-red-revision-summary>
                <span class="red-admin-revisions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 12a8 8 0 1 0 2.3-5.7L4 8.6"></path>
                        <path d="M4 4v4.6h4.6M12 7.5V12l3 1.8"></path>
                    </svg>
                </span>
                <span class="red-admin-revisions__copy">
                    <strong>Version history</strong>
                    <small>Review earlier saves and safely restore one</small>
                </span>
                <span class="red-admin-revisions__count" data-red-revision-count>Load history</span>
                <svg class="red-admin-revisions__chevron" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5"></path>
                </svg>
            </summary>
            <div class="red-admin-revisions__body">
                <div class="red-admin-revisions__notice">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM12 10v6M12 7.5h.01"></path></svg>
                    <p><strong>Restoring is non-destructive.</strong> The current content is preserved as a new version before an earlier version becomes active.</p>
                </div>
                <p class="red-admin-revisions__status" data-red-revision-status role="status" aria-live="polite">Open this panel to load saved versions.</p>
                <ol class="red-admin-revisions__list" data-red-revision-list></ol>
            </div>
        </details>
        <?php
    }
}

?>
