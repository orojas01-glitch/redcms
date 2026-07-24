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
require_once dirname(__DIR__) . '/includes/admin_content_revision_helpers.php';

if (!preg_match('/\Aredcms_(?:acceptance|layout)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(STDERR, "Refusing to run: RED_DB_NAME must name a disposable redcms_acceptance_* or redcms_layout_* database.\n");
    exit(64);
}

$_SESSION['AdminRecordID'] = 2147000880;
$_SESSION['alias'] = 'LayoutQA';
$_SESSION['AdminType'] = 'webmaster';

$assertions = 0;
$assert = static function ($condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$fixtureIds = [2147000881, 2147000882, 2147000883];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$deleteFixtures = static function ($connection, array $ids): void {
    $safeIds = implode(',', array_map('intval', $ids));
    mysqli_query($connection, "DELETE FROM RED_Content_Revisions WHERE ContentRecordID IN ($safeIds)");
    mysqli_query($connection, "DELETE FROM RED_Articles WHERE RecordID IN ($safeIds)");
};

try {
    $deleteFixtures($connection, $fixtureIds);
    $assert(
        red_admin_article_distribution_items([
            ['recordId' => 1, 'position' => 1, 'order' => 1],
            ['recordId' => 2, 'position' => 1, 'order' => 1],
        ]) === null,
        'target distribution rejects duplicate position and order slots'
    );
    $assert(
        red_admin_article_distribution_items([
            ['recordId' => 1, 'position' => 1, 'order' => 0],
        ]) === null,
        'target distribution requires one-based visual order'
    );
    $assert(
        red_admin_article_distribution_expected_items([
            ['recordId' => 1, 'position' => 1, 'order' => 0],
        ]) === [['recordId' => 1, 'position' => 1, 'order' => 0]],
        'stale-state distribution preserves an exact legacy zero order'
    );
    $assert(
        red_admin_article_distribution_expected_items([
            ['recordId' => 1, 'position' => 1, 'order' => 2147483647],
        ]) === [['recordId' => 1, 'position' => 1, 'order' => 2147483647]],
        'stale-state distribution accepts the full legacy signed INT order range'
    );

    $layoutResult = mysqli_query(
        $connection,
        "SELECT Layout FROM RED_Sections WHERE Sections='home' ORDER BY RecordID ASC LIMIT 1"
    );
    $layoutRow = $layoutResult ? mysqli_fetch_assoc($layoutResult) : null;
    if ($layoutResult) {
        mysqli_free_result($layoutResult);
    }
    $layout = (string) ($layoutRow['Layout'] ?? '');
    $positions = red_admin_area_layout_position_options($connection, $layout, true);
    $assert($layout !== '', 'Home layout fixture is available');
    $assert(isset($positions[0], $positions[1], $positions[2]), 'Home layout exposes hidden and two visible targets');

    $initial = [
        ['recordId' => $fixtureIds[0], 'position' => 1, 'order' => 0],
        ['recordId' => $fixtureIds[1], 'position' => 1, 'order' => 2],
        ['recordId' => $fixtureIds[2], 'position' => 2, 'order' => 0],
    ];
    foreach ($initial as $index => $item) {
        $data = red_admin_article_default_insert_data($item['recordId']);
        $data['Title'] = 'Layout distribution fixture ' . ($index + 1);
        $data['Alias'] = 'layout-distribution-fixture-' . ($index + 1);
        $data['Sections'] = 'home';
        $data['Layout'] = $layout;
        $data['Component'] = 'Article';
        $data['PagePosition'] = 0;
        $data['HomePosition'] = $item['position'];
        $data['HomePositionOrder'] = $item['order'];
        $data['Active'] = 'N';
        $data['Language'] = 'sp';
        $data['EditedBy'] = 'LayoutQA';
        $assert(
            red_admin_article_insert($connection, $item['recordId'], $data),
            'layout fixture ' . ($index + 1) . ' inserts'
        );
    }

    $current = red_admin_article_distribution_current($connection, 'HomePosition', $initial);
    $assert($current === $initial, 'current distribution preserves exact stored positions and legacy orders');

    $target = [
        ['recordId' => $fixtureIds[0], 'position' => 2, 'order' => 2],
        ['recordId' => $fixtureIds[1], 'position' => 1, 'order' => 1],
        ['recordId' => $fixtureIds[2], 'position' => 0, 'order' => 1],
    ];
    $move = red_admin_article_update_distribution_batch(
        $connection,
        'HomePosition',
        $layout,
        $initial,
        $target
    );
    $assert(!empty($move['ok']) && (int) ($move['changed'] ?? 0) === 3, 'cross-position arrangement commits all changed records atomically');
    $assert(
        red_admin_article_distribution_current($connection, 'HomePosition', $target) === $target,
        'saved distribution matches visible, hidden, and ordered targets exactly'
    );

    $operationRows = red_admin_article_fetch_all(
        $connection,
        'SELECT ContentRecordID, Operation, RevisionNumber FROM RED_Content_Revisions '
            . 'WHERE ContentRecordID IN (?,?,?) ORDER BY ContentRecordID, RevisionNumber',
        'iii',
        $fixtureIds,
        'Layout revision operation lookup failed'
    );
    $operations = [];
    foreach ($operationRows as $row) {
        $operations[(int) $row['ContentRecordID']][] = (string) $row['Operation'];
    }
    $assert(
        ($operations[$fixtureIds[0]] ?? []) === ['baseline', 'move']
            && ($operations[$fixtureIds[1]] ?? []) === ['baseline', 'order']
            && ($operations[$fixtureIds[2]] ?? []) === ['baseline', 'move'],
        'arrangement history distinguishes moves from order-only changes'
    );
    $assert(count($operationRows) === 6, 'each changed component receives one baseline and one arrangement revision');

    $staleTarget = $target;
    $staleTarget[0]['order'] = 3;
    $stale = red_admin_article_update_distribution_batch(
        $connection,
        'HomePosition',
        $layout,
        $initial,
        $staleTarget
    );
    $assert(empty($stale['ok']) && ($stale['reason'] ?? '') === 'conflict', 'stale arrangement is rejected');
    $assert(
        red_admin_article_distribution_current($connection, 'HomePosition', $target) === $target,
        'stale rejection preserves the complete saved distribution'
    );

    $invalidTarget = $target;
    $invalidTarget[0]['position'] = 99;
    $invalid = red_admin_article_update_distribution_batch(
        $connection,
        'HomePosition',
        $layout,
        $target,
        $invalidTarget
    );
    $assert(empty($invalid['ok']) && ($invalid['reason'] ?? '') === 'position', 'undeclared layout position is rejected');

    $noOp = red_admin_article_update_distribution_batch(
        $connection,
        'HomePosition',
        $layout,
        $target,
        $target
    );
    $assert(!empty($noOp['ok']) && (int) ($noOp['changed'] ?? -1) === 0, 'no-op arrangement succeeds without writes');
    $revisionCount = red_admin_article_fetch_all(
        $connection,
        'SELECT RevisionID FROM RED_Content_Revisions WHERE ContentRecordID IN (?,?,?)',
        'iii',
        $fixtureIds,
        'Layout revision count lookup failed'
    );
    $assert(count($revisionCount) === 6, 'stale, invalid, and no-op requests create no revision noise');

    $undoTarget = [
        ['recordId' => $fixtureIds[0], 'position' => 1, 'order' => 1],
        ['recordId' => $fixtureIds[1], 'position' => 1, 'order' => 2],
        ['recordId' => $fixtureIds[2], 'position' => 2, 'order' => 1],
    ];
    $undo = red_admin_article_update_distribution_batch(
        $connection,
        'HomePosition',
        $layout,
        $target,
        $undoTarget
    );
    $assert(!empty($undo['ok']) && (int) ($undo['changed'] ?? 0) === 3, 'undo arrangement commits its inverse move and order changes');
    $assert(
        red_admin_article_distribution_current($connection, 'HomePosition', $undoTarget) === $undoTarget,
        'undo returns the prior visual arrangement with normalized order'
    );

    echo "Layout distribution self-test passed: {$assertions} assertions.\n";
} finally {
    $deleteFixtures($connection, $fixtureIds);
    $db->close();
}
?>
