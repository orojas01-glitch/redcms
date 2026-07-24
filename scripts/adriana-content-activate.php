<?php
/**
 * Activate the Adriana Granobles theme in an explicitly disposable clone.
 *
 * Required environment:
 *   RED_DB_NAME          Target disposable database.
 *   RED_PRIMARY_DB_NAME  Primary database name used only as a deny-list guard.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/theme_activation_helpers.php';

function red_adriana_activation_output(array $payload, $stream = null)
{
    $stream = $stream ?: STDOUT;
    fwrite(
        $stream,
        json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL
    );
}

function red_adriana_activation_database_guard()
{
    $target = trim((string) getenv('RED_DB_NAME'));
    $primary = trim((string) getenv('RED_PRIMARY_DB_NAME'));

    if ($target === '' || $primary === '') {
        throw new RuntimeException('RED_DB_NAME and RED_PRIMARY_DB_NAME are required.');
    }
    if (!preg_match('/\A[A-Za-z0-9_]+\z/', $target)
        || !preg_match('/\A[A-Za-z0-9_]+\z/', $primary)
    ) {
        throw new RuntimeException('Database guard names contain unsupported characters.');
    }
    if (strpos($target, 'redcms_adriana_28_') !== 0) {
        throw new RuntimeException('Target database is not an Adriana 28-route disposable clone.');
    }
    if (hash_equals($primary, $target)) {
        throw new RuntimeException('Target database must not equal the primary database.');
    }

    return [$target, $primary];
}

$connection = null;
try {
    [$targetDatabase, $primaryDatabase] = red_adriana_activation_database_guard();

    $connection = red_theme_activation_open_connection($projectRoot);
    if (!($connection instanceof mysqli)) {
        throw new RuntimeException('Could not connect to the guarded disposable database.');
    }

    $actualResult = mysqli_query($connection, 'SELECT DATABASE() AS database_name');
    $actualRow = $actualResult ? mysqli_fetch_assoc($actualResult) : null;
    if ($actualResult) {
        mysqli_free_result($actualResult);
    }
    $actualDatabase = is_array($actualRow) ? (string) ($actualRow['database_name'] ?? '') : '';
    if ($actualDatabase === '' || !hash_equals($targetDatabase, $actualDatabase)) {
        throw new RuntimeException('Connected database does not match RED_DB_NAME.');
    }

    $transition = red_theme_activation_apply(
        $connection,
        'activate',
        'adriana-granobles',
        $projectRoot
    );

    $after = red_theme_activation_read_state($connection);
    if (($after['activeThemeId'] ?? '') !== 'adriana-granobles') {
        throw new RuntimeException('Post-activation state did not select adriana-granobles.');
    }

    red_adriana_activation_output([
        'ok' => true,
        'operation' => 'activate-adriana-disposable-theme',
        'targetDatabase' => $targetDatabase,
        'primaryDatabaseGuard' => $primaryDatabase,
        'status' => (string) ($transition['status'] ?? ''),
        'before' => $transition['before'] ?? null,
        'after' => $after,
        'compatibility' => [
            'activationCompatible' => !empty($transition['compatibility']['activationCompatible']),
            'blockingReasons' => array_values($transition['compatibility']['blockingReasons'] ?? []),
            'checks' => $transition['compatibility']['checks'] ?? [],
        ],
    ]);
} catch (Throwable $exception) {
    red_adriana_activation_output([
        'ok' => false,
        'operation' => 'activate-adriana-disposable-theme',
        'error' => $exception->getMessage(),
    ], STDERR);
    exit(1);
} finally {
    if ($connection instanceof mysqli) {
        mysqli_close($connection);
    }
}

