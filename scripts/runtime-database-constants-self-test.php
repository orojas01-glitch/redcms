<?php
declare(strict_types=1);

$values = [
    'RED_DB_HOST' => 'runtime-db.example.test:3307',
    'RED_DB_USER' => 'runtime-user',
    'RED_DB_PASS' => 'runtime-password',
    'RED_DB_NAME' => 'runtime-database',
];
foreach ($values as $key => $value) {
    putenv($key . '=' . $value);
}
$_SERVER['RED_DB_PASS'] = 'request-controlled-value';

require_once dirname(__DIR__) . '/includes/runtime_config_helpers.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$assert(
    red_runtime_database_constants_bootstrap() === true,
    'database constants bootstrap from server-only configuration'
);
$assert(DBHOST === $values['RED_DB_HOST'], 'database host is exact');
$assert(DBUSER === $values['RED_DB_USER'], 'database user is exact');
$assert(DBPASS === $values['RED_DB_PASS'], 'database password ignores request state');
$assert(DBNAME === $values['RED_DB_NAME'], 'database name is exact');
$assert(
    red_runtime_database_constants_bootstrap() === true
        && DBPASS === $values['RED_DB_PASS'],
    'repeated bootstrap preserves existing constants'
);
$frontController = file_get_contents(dirname(__DIR__) . '/index.php');
$assert(
    is_string($frontController)
        && substr_count(
            $frontController,
            'red_runtime_database_constants_bootstrap()'
        ) === 2,
    'webhook and public-mutation early routes bootstrap package database constants'
);

echo 'Runtime database constants passed ' . $assertions . " assertions.\n";
echo "No database, network, provider, browser, deployment, or live-mode action occurred.\n";

?>
