<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['REQUEST_URI'] = '/admin/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/class/class_connection.php';
require_once dirname(__DIR__) . '/includes/admin_custom_layout_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_article_helpers.php';

if (!preg_match('/\Aredcms_(?:acceptance|layout_builder)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(
        STDERR,
        "Refusing to run: RED_DB_NAME must name a disposable redcms_acceptance_* or redcms_layout_builder_* database.\n"
    );
    exit(64);
}

$_SESSION['AdminRecordID'] = 2147000979;
$_SESSION['alias'] = 'LayoutBuilderQA';
$_SESSION['AdminType'] = 'webmaster';

$assertions = 0;
$assert = static function ($condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$expectInvalid = static function (callable $callback, string $message) use ($assert): void {
    $rejected = false;
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        $rejected = true;
    }
    $assert($rejected, $message);
};

$layoutId = 'custom-layout-builder-qa';
$sectionId = 2147000980;
$sectionAlias = 'layout-builder-qa';
$articleId = 2147000981;
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

$themeRows = [];
$themeResult = mysqli_query(
    $connection,
    "SELECT RecordID, Item, Content FROM RED_Advanced " .
    "WHERE Language='' AND Item IN ('System_Active_Theme','System_Previous_Theme') ORDER BY RecordID"
);
while ($themeResult && ($themeRow = mysqli_fetch_assoc($themeResult))) {
    $themeRows[] = $themeRow;
}
if ($themeResult) {
    mysqli_free_result($themeResult);
}

$setThemeValue = static function ($connection, int $recordId, string $content): bool {
    $stmt = mysqli_prepare($connection, 'UPDATE RED_Advanced SET Content=? WHERE RecordID=?');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'si', $content, $recordId);
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $saved;
};

$setThemeState = static function ($connection, array $rows, string $active, string $previous) use ($setThemeValue): bool {
    $saved = true;
    foreach ($rows as $row) {
        $value = (string) ($row['Item'] ?? '') === 'System_Active_Theme' ? $active : $previous;
        $saved = $setThemeValue($connection, (int) ($row['RecordID'] ?? 0), $value) && $saved;
    }
    return $saved;
};

$restoreThemeState = static function ($connection, array $rows) use ($setThemeValue): void {
    foreach ($rows as $row) {
        $setThemeValue(
            $connection,
            (int) ($row['RecordID'] ?? 0),
            (string) ($row['Content'] ?? '')
        );
    }
};

$deleteFixtures = static function ($connection) use ($layoutId, $sectionId, $articleId): void {
    mysqli_query($connection, 'DELETE FROM RED_Articles WHERE RecordID=' . (int) $articleId);
    mysqli_query($connection, 'DELETE FROM RED_Sections WHERE RecordID=' . (int) $sectionId);
    $escapedLayoutId = mysqli_real_escape_string($connection, $layoutId);
    mysqli_query(
        $connection,
        "DELETE FROM RED_Custom_Layout_Revisions WHERE LayoutID='" . $escapedLayoutId . "'"
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Custom_Layouts WHERE LayoutID='" . $escapedLayoutId . "'"
    );
};

$definition = [
    'schemaVersion' => 1,
    'rows' => [[
        'columns' => [
            ['position' => 1, 'span' => 7, 'label' => 'Primary content'],
            ['position' => 2, 'span' => 5, 'label' => 'Supporting sidebar'],
        ],
    ]],
    'mobile' => 'stack',
];
$singlePositionDefinition = [
    'schemaVersion' => 1,
    'rows' => [[
        'columns' => [
            ['position' => 1, 'span' => 12, 'label' => 'Primary content'],
        ],
    ]],
    'mobile' => 'stack',
];
$evolvedDefinition = [
    'schemaVersion' => 1,
    'rows' => [[
        'columns' => [
            ['position' => 1, 'span' => 3, 'label' => 'Narrow content'],
            ['position' => 2, 'span' => 9, 'label' => 'Wide content'],
        ],
    ]],
    'mobile' => 'stack',
];

try {
    $deleteFixtures($connection);
    $assert(red_custom_layout_valid_id($layoutId), 'custom layout ID contract accepts a scoped stable ID');
    $assert(
        !red_custom_layout_valid_id('index-1') && !red_custom_layout_valid_id('custom-UPPER'),
        'custom layout IDs cannot impersonate packaged layouts or use uppercase characters'
    );
    $assert(
        red_custom_layout_slug(' Services + Right Sidebar ') === 'custom-services-right-sidebar',
        'layout names produce deterministic safe IDs'
    );
    $normalized = red_custom_layout_normalize_definition($definition);
    $assert($normalized === $definition, 'valid 12-unit definition remains canonical');
    $assert(
        red_custom_layout_definition_hash($definition) === red_custom_layout_definition_hash($normalized),
        'canonical definition hashing is stable'
    );
    $expectInvalid(
        static function (): void {
            red_custom_layout_normalize_definition([
                'schemaVersion' => 1,
                'rows' => [['columns' => [
                    ['position' => 1, 'span' => 5, 'label' => 'Incomplete'],
                ]]],
                'mobile' => 'stack',
            ]);
        },
        'a row that does not total 12 units is rejected'
    );
    $expectInvalid(
        static function (): void {
            red_custom_layout_normalize_definition([
                'schemaVersion' => 1,
                'rows' => [['columns' => [
                    ['position' => 1, 'span' => 6, 'label' => 'First'],
                    ['position' => 1, 'span' => 6, 'label' => 'Duplicate'],
                ]]],
                'mobile' => 'stack',
            ]);
        },
        'duplicate content positions are rejected'
    );
    $assert(
        red_custom_layout_span_distribution([2, 1]) === [8, 4],
        'packaged-layout weights convert to an exact 12-unit row'
    );
    $assert(
        red_custom_layout_tables_available($connection, true),
        'custom layout and immutable revision tables are available'
    );

    $assert(
        count($themeRows) === 2
            && $setThemeState($connection, $themeRows, 'legacy-bootstrap', 'legacy-bootstrap'),
        'self-test starts from the legacy recovery adapter deterministically'
    );

    $created = red_admin_custom_layout_save_draft(
        $connection,
        $layoutId,
        'Layout Builder QA',
        $definition,
        '',
        dirname(__DIR__)
    );
    $assert(
        !empty($created['ok'])
            && !empty($created['changed'])
            && (int) ($created['layout']['revisionNumber'] ?? 0) === 1,
        'first save creates one private draft and one immutable revision'
    );
    $assert(
        empty($created['layout']['published'])
            && ($created['layout']['draftDefinition'] ?? null) === $definition,
        'saving a draft does not expose it to public layout selectors'
    );
    $assert(
        !isset(red_custom_layout_published_catalog($connection)[$layoutId]),
        'unpublished draft is absent from the core public catalog'
    );

    $duplicate = red_admin_custom_layout_save_draft(
        $connection,
        $layoutId,
        'Duplicate attempt',
        $definition,
        '',
        dirname(__DIR__)
    );
    $assert(
        empty($duplicate['ok']) && ($duplicate['reason'] ?? '') === 'conflict',
        'a second editor cannot overwrite an existing layout without its state token'
    );

    $legacyPublish = red_admin_custom_layout_publish(
        $connection,
        $layoutId,
        (string) ($created['layout']['stateHash'] ?? ''),
        dirname(__DIR__)
    );
    $assert(
        empty($legacyPublish['ok']) && ($legacyPublish['reason'] ?? '') === 'theme',
        'legacy recovery mode permits drafts but blocks custom layout publishing'
    );

    $assert(
        $setThemeState($connection, $themeRows, 'starter-reference', 'legacy-bootstrap'),
        'self-test switches the disposable site to a standard template'
    );
    $published = red_admin_custom_layout_publish(
        $connection,
        $layoutId,
        (string) ($created['layout']['stateHash'] ?? ''),
        dirname(__DIR__)
    );
    $assert(
        !empty($published['ok'])
            && !empty($published['changed'])
            && !empty($published['layout']['published']),
        'a valid standard-template draft can be published'
    );
    $catalog = red_custom_layout_published_catalog($connection);
    $assert(
        isset($catalog[$layoutId])
            && ($catalog[$layoutId]['positions'] ?? []) === [
                1 => 'Primary content',
                2 => 'Supporting sidebar',
            ],
        'published layout overlays the core catalog with labeled positions'
    );
    $activeDefinition = red_theme_active_layout_definition($connection, $layoutId, dirname(__DIR__));
    $assert(
        ($activeDefinition['id'] ?? '') === $layoutId
            && ($activeDefinition['themeType'] ?? '') === 'standard'
            && empty($activeDefinition['usesAlias']),
        'active layout resolution recognizes a custom layout as a core-owned exact ID'
    );

    $stmt = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Sections ' .
        '(RecordID, Sections, Title, Layout, QueryLimit, AccessLevel, Features, Active, Description, Tags, Language) ' .
        'VALUES (?, ?, ?, ?, \'100\', \'Public\', \'\', \'Y\', \'\', \'\', \'sp\')'
    );
    $sectionTitle = 'Layout Builder QA';
    mysqli_stmt_bind_param($stmt, 'isss', $sectionId, $sectionAlias, $sectionTitle, $layoutId);
    $assert(mysqli_stmt_execute($stmt), 'disposable Section can be assigned to the published custom layout');
    mysqli_stmt_close($stmt);

    $articleData = red_admin_article_default_insert_data($articleId);
    $articleData['Title'] = 'Layout Builder position fixture';
    $articleData['Alias'] = 'layout-builder-position-fixture';
    $articleData['Sections'] = $sectionAlias;
    $articleData['Layout'] = $layoutId;
    $articleData['Component'] = 'Article';
    $articleData['SectionPosition'] = 2;
    $articleData['SectionPositionOrder'] = 1;
    $articleData['Active'] = 'N';
    $articleData['Language'] = 'sp';
    $articleData['EditedBy'] = 'LayoutQA';
    $assert(
        red_admin_article_insert($connection, $articleId, $articleData),
        'disposable content can occupy custom position 2'
    );

    $assignment = red_admin_custom_layout_assignment_inventory($connection, $layoutId);
    $assert(
        (int) ($assignment['count'] ?? 0) >= 2
            && in_array(2, $assignment['requiredPositions'] ?? [], true),
        'assignment inventory finds both owning records and occupied position 2'
    );

    $current = red_custom_layout_fetch($connection, $layoutId);
    $removedPositionDraft = red_admin_custom_layout_save_draft(
        $connection,
        $layoutId,
        'Layout Builder QA',
        $singlePositionDefinition,
        (string) ($current['StateHash'] ?? ''),
        dirname(__DIR__)
    );
    $assert(
        !empty($removedPositionDraft['ok']),
        'an incompatible structural experiment can be saved privately as a draft'
    );
    $blockedPublish = red_admin_custom_layout_publish(
        $connection,
        $layoutId,
        (string) ($removedPositionDraft['layout']['stateHash'] ?? ''),
        dirname(__DIR__)
    );
    $assert(
        empty($blockedPublish['ok'])
            && ($blockedPublish['reason'] ?? '') === 'positions',
        'publishing cannot remove a position that still contains content'
    );
    $assert(
        isset(red_custom_layout_published_catalog($connection)[$layoutId]['positions'][2]),
        'blocked publish preserves the previous public structure'
    );

    $current = red_custom_layout_fetch($connection, $layoutId);
    $restoredPositionDraft = red_admin_custom_layout_save_draft(
        $connection,
        $layoutId,
        'Layout Builder QA',
        $definition,
        (string) ($current['StateHash'] ?? ''),
        dirname(__DIR__)
    );
    $assert(!empty($restoredPositionDraft['ok']), 'occupied position can be restored to the draft');
    $republished = red_admin_custom_layout_publish(
        $connection,
        $layoutId,
        (string) ($restoredPositionDraft['layout']['stateHash'] ?? ''),
        dirname(__DIR__)
    );
    $assert(!empty($republished['ok']), 'compatible restored structure publishes successfully');

    $assignedArchive = red_admin_custom_layout_set_archived(
        $connection,
        $layoutId,
        (string) ($republished['layout']['stateHash'] ?? ''),
        true,
        dirname(__DIR__)
    );
    $assert(
        empty($assignedArchive['ok']) && ($assignedArchive['reason'] ?? '') === 'assigned',
        'an assigned layout cannot be archived'
    );

    mysqli_query($connection, 'DELETE FROM RED_Articles WHERE RecordID=' . (int) $articleId);
    mysqli_query($connection, 'DELETE FROM RED_Sections WHERE RecordID=' . (int) $sectionId);
    $current = red_custom_layout_fetch($connection, $layoutId);
    $archived = red_admin_custom_layout_set_archived(
        $connection,
        $layoutId,
        (string) ($current['StateHash'] ?? ''),
        true,
        dirname(__DIR__)
    );
    $assert(!empty($archived['ok']) && !empty($archived['layout']['archived']), 'unassigned layout can be archived');
    $assert(
        !isset(red_custom_layout_published_catalog($connection)[$layoutId]),
        'archived layout leaves public selectors without deleting its history'
    );

    $unarchived = red_admin_custom_layout_set_archived(
        $connection,
        $layoutId,
        (string) ($archived['layout']['stateHash'] ?? ''),
        false,
        dirname(__DIR__)
    );
    $assert(!empty($unarchived['ok']) && empty($unarchived['layout']['archived']), 'archived layout can be restored');

    $evolved = red_admin_custom_layout_save_draft(
        $connection,
        $layoutId,
        'Layout Builder QA evolved',
        $evolvedDefinition,
        (string) ($unarchived['layout']['stateHash'] ?? ''),
        dirname(__DIR__)
    );
    $assert(!empty($evolved['ok']), 'later structural edit creates another private draft');
    $history = red_admin_custom_layout_history($connection, $layoutId, 50);
    $createRevision = null;
    foreach ($history as $revision) {
        if (($revision['operation'] ?? '') === 'create') {
            $createRevision = $revision;
            break;
        }
    }
    $assert(
        is_array($createRevision) && count($history) >= 7,
        'version history preserves the complete create, draft, publish, and archive timeline'
    );
    $restored = red_admin_custom_layout_restore_revision(
        $connection,
        $layoutId,
        (int) ($createRevision['revisionId'] ?? 0),
        (string) ($evolved['layout']['stateHash'] ?? '')
    );
    $assert(
        !empty($restored['ok'])
            && ($restored['layout']['draftLabel'] ?? '') === 'Layout Builder QA'
            && ($restored['layout']['draftDefinition'] ?? null) === $definition
            && !empty($restored['layout']['hasUnpublishedChanges']) === false,
        'an earlier immutable version restores as the current draft without rewriting history'
    );
    $historyAfterRestore = red_admin_custom_layout_history($connection, $layoutId, 50);
    $assert(
        ($historyAfterRestore[0]['operation'] ?? '') === 'restore'
            && (int) ($historyAfterRestore[0]['restoredFromRevisionId'] ?? 0)
                === (int) ($createRevision['revisionId'] ?? 0),
        'restore appends a traceable revision that points to its source version'
    );

    $deleteFixtures($connection);
    $remaining = mysqli_query(
        $connection,
        "SELECT " .
        "(SELECT COUNT(*) FROM RED_Custom_Layouts WHERE LayoutID='" .
        mysqli_real_escape_string($connection, $layoutId) . "') + " .
        "(SELECT COUNT(*) FROM RED_Custom_Layout_Revisions WHERE LayoutID='" .
        mysqli_real_escape_string($connection, $layoutId) . "') AS fixture_count"
    );
    $remainingRow = $remaining ? mysqli_fetch_assoc($remaining) : null;
    if ($remaining) {
        mysqli_free_result($remaining);
    }
    $assert((int) ($remainingRow['fixture_count'] ?? -1) === 0, 'self-test removes every layout fixture and revision');

    echo "Custom Layout Builder self-test passed: {$assertions} assertions.\n";
} finally {
    $deleteFixtures($connection);
    $restoreThemeState($connection, $themeRows);
    $db->close();
}
?>
