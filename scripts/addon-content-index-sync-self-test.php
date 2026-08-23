<?php
/**
 * Dependency-free checks for bounded post-commit content-index notifications.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/addon_content_index_sync_helpers.php';

$assertions = 0;
function red_addon_content_index_sync_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$request = red_addon_content_index_sync_request(
    'article.updated',
    [9, '2', 9, 4]
);
red_addon_content_index_sync_test_assert(
    $request === [
        'event' => 'article.updated',
        'recordIds' => [2, 4, 9],
    ],
    'record ids are positive, unique, and sorted'
);
red_addon_content_index_sync_test_assert(
    red_addon_content_index_sync_request('unknown', [1]) === null,
    'unknown events are refused'
);
red_addon_content_index_sync_test_assert(
    red_addon_content_index_sync_request('article.updated', []) === null,
    'empty record sets are refused'
);
red_addon_content_index_sync_test_assert(
    red_addon_content_index_sync_request(
        'article.updated',
        range(1, 65)
    ) === null,
    'more than 64 records are refused'
);
foreach ([0, -1, true, '1.5', 'x'] as $invalidId) {
    red_addon_content_index_sync_test_assert(
        red_addon_content_index_sync_request(
            'article.updated',
            [$invalidId]
        ) === null,
        'invalid record identity is refused'
    );
}

$endpointContracts = [
    'admin/bin/insert_content.php' => ['article.created', 'article.updated'],
    'admin/bin/update_content.php' => ['article.updated'],
    'admin/bin/delete_label.php' => ['article.deleted'],
    'admin/bin/content_revisions.php' => ['article.restored'],
    'admin/bin/run_tool_movecontent.php' => ['article.moved'],
    'admin/bin/run_tool_filterareas.php' => ['article.moved'],
];
foreach ($endpointContracts as $relativePath => $events) {
    $source = file_get_contents($projectRoot . '/' . $relativePath);
    $valid = is_string($source)
        && str_contains($source, 'addon_content_index_sync_helpers.php')
        && str_contains($source, 'red_addon_content_index_sync_notify');
    foreach ($events as $event) {
        $valid = $valid && str_contains($source, "'$event'");
    }
    red_addon_content_index_sync_test_assert(
        $valid,
        $relativePath . ' owns its post-commit index notification'
    );
}

printf(
    "Content index synchronization self-test passed (%d assertions).\n",
    $assertions
);

?>
