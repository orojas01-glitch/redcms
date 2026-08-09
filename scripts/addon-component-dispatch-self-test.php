<?php
/**
 * Disposable database checks for safe public add-on component dispatch.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/legacy_component_helpers.php';
require_once $projectRoot . '/includes/addon_install_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_component)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(STDERR, 'Add-on component dispatch self-test refused non-disposable database: ' . DBNAME . "\n");
    exit(65);
}

$assertions = 0;
$packageId = 'redcms.component-fixture';
$componentId = 'redcms.component-fixture/persistence-card-with-identifier-beyond-legacy-fifty-character-storage';
$contentRecordId = 2147000956;
$packageTable = 'RED_Addon_Component_Persistence_Fixture';
$temporaryRoot = sys_get_temp_dir() . '/redcms-addon-component-' . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$executionMarker = $temporaryRoot . '/execution-marker';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_component_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_component_test_remove_tree($path)
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

function red_addon_component_test_cleanup(
    $connection,
    $packageId,
    $contentRecordId,
    $packageTable,
    $temporaryRoot
)
{
    try {
        if (preg_match('/\ARED_Addon_[A-Za-z0-9_]+\z/', $packageTable) === 1) {
            mysqli_query($connection, 'DROP TABLE IF EXISTS `' . $packageTable . '`');
        }
        $statement = mysqli_prepare(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID=?'
        );
        if ($statement) {
            mysqli_stmt_bind_param($statement, 'i', $contentRecordId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }
        foreach (['RED_Addon_Activity_Log', 'RED_Addon_Migrations', 'RED_Addon_Installations'] as $table) {
            $statement = mysqli_prepare($connection, 'DELETE FROM ' . $table . ' WHERE PackageID=?');
            if ($statement) {
                mysqli_stmt_bind_param($statement, 's', $packageId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
            }
        }
    } catch (Throwable $throwable) {
        error_log('Add-on component dispatch cleanup failed: ' . $throwable->getMessage());
    }
    red_addon_component_test_remove_tree($temporaryRoot);
}

function red_addon_component_test_insert_parent(
    $connection,
    $contentRecordId,
    $componentId
) {
    $result = mysqli_query(
        $connection,
        'SELECT * FROM RED_Articles ORDER BY RecordID LIMIT 1'
    );
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    if (!is_array($row) || $row === []) {
        throw new RuntimeException('Could not read the disposable article seed.');
    }

    $row['RecordID'] = (string) $contentRecordId;
    $row['Component'] = $componentId;
    $row['Alias'] = 'codex-addon-component-persistence-fixture';
    $row['Title'] = 'Disposable add-on component persistence fixture';
    $row['StartDate'] = '1970-01-01 00:00:01';
    $row['EventDate'] = '1970-01-01 00:00:01';
    $row['ExpDate'] = '2099-12-31 23:59:59';
    $columns = array_keys($row);
    $values = [];
    foreach (array_values($row) as $value) {
        $values[] = $value === null
            ? 'NULL'
            : "'" . mysqli_real_escape_string($connection, (string) $value) . "'";
    }
    $sql = 'INSERT INTO RED_Articles (`'
        . implode('`,`', $columns)
        . '`) VALUES ('
        . implode(',', $values)
        . ')';
    if (!mysqli_query($connection, $sql)) {
        throw new RuntimeException('Could not create the disposable component parent.');
    }
}

function red_addon_component_test_write_fixture($project, $packageId, $componentId, $executionMarker)
{
    [$vendor, $package] = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $vendor . '/' . $package;
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create component fixture.');
    }
    $entrypoint = '<?php' . "\n" .
        'return static function (RED_Addon_Runtime_Registry $runtime): void {' .
        "\n    file_put_contents(" . var_export($executionMarker, true) . ', ' . var_export("registered\n", true) . ', FILE_APPEND | LOCK_EX);' .
        "\n    \$runtime->registerComponent(" . var_export($componentId, true) . ', static function (array $context): array {' .
        "\n        if (array_keys(\$context) !== ['component', 'placement']" .
        "\n            || \$context['component'] !== " . var_export($componentId, true) .
        "\n            || !is_array(\$context['placement'])" .
        "\n            || array_keys(\$context['placement']) !== ['recordId', 'layout', 'article', 'position']) {" .
        "\n            throw new RuntimeException('unexpected component context');" .
        "\n        }" .
        "\n        \$placement = \$context['placement'];" .
        "\n        if (\$placement['article'] === 'emit') { echo '<script>fixture-output</script>'; }" .
        "\n        if (\$placement['article'] === 'throw') { throw new RuntimeException('fixture failure must not reach the browser'); }" .
        "\n        if (\$placement['article'] === 'nested') { ob_start(); }" .
        "\n        if (\$placement['article'] === 'invalid') { return ['title' => 'Incomplete']; }" .
        "\n        if (\$placement['article'] === 'invalid-facts') { return ['title' => 'Fixture', 'summary' => '', 'facts' => [['label' => 'Price', 'value' => '']]]; }" .
        "\n        if (\$placement['article'] === 'facts') { return ['title' => '<Product>', 'summary' => 'Bounded facts', 'facts' => [['label' => 'Price <now>', 'value' => 'USD 24.99 & tax'], ['label' => 'Availability', 'value' => 'Available']]]; }" .
        "\n        return ['title' => '<Fixture ' . \$placement['layout'] . '>', 'summary' => 'record=' . \$placement['recordId'] . '&position=' . \$placement['position']];" .
        "\n    });" .
        "\n};\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1, 'id' => $packageId, 'name' => 'Component Dispatch Fixture',
        'description' => 'Disposable public component dispatch fixture.', 'version' => '1.0.0',
        'type' => 'component',
        'compatibility' => ['cms' => '>=5.1 <6.0', 'php' => '>=8.2 <9.0'],
        'provides' => ['components' => [$componentId], 'services' => [], 'adminTools' => [], 'adapters' => []],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$packageId . '.manage'], 'settings' => [], 'migrations' => [], 'routes' => [],
        'jobs' => [], 'outboundHosts' => [], 'assets' => ['public' => [], 'admin' => []],
        'integrity' => ['entrypoint' => 'addon.php', 'files' => [['path' => 'addon.php', 'sha256' => hash('sha256', $entrypoint)]],],
        'uninstall' => ['defaultDataAction' => 'retain', 'allowExplicitPurge' => true],
    ];
    file_put_contents($directory . '/addon.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function red_addon_component_test_insert_installation($connection, array $snapshot, $state)
{
    $statement = mysqli_prepare($connection, 'INSERT INTO RED_Addon_Installations (PackageID, PackageVersion, PackageType, ManifestSHA256, InventorySHA256, LifecycleState, InstalledByAdminRecordID, UpdatedByAdminRecordID) VALUES (?, ?, ?, ?, ?, ?, 2147000955, 2147000955)');
    mysqli_stmt_bind_param($statement, 'ssssss', $snapshot['id'], $snapshot['version'], $snapshot['type'], $snapshot['manifestSha256'], $snapshot['inventorySha256'], $state);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

try {
    red_addon_component_test_cleanup(
        $connection,
        $packageId,
        $contentRecordId,
        $packageTable,
        $temporaryRoot
    );
    red_addon_component_test_write_fixture($fixtureProject, $packageId, $componentId, $executionMarker);
    $catalog = red_addon_discover($fixtureProject, ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]);
    $snapshot = red_addon_registry_snapshot($catalog['packages'][$packageId] ?? []);
    red_addon_component_test_assert(!empty($catalog['valid']) && is_array($snapshot) && !file_exists($executionMarker), 'component discovery is non-executing before registry state is read');

    red_addon_component_test_insert_installation($connection, $snapshot, 'installed_disabled');
    $disabledRuntime = red_addon_runtime_bootstrap($connection, $fixtureProject);
    red_addon_component_test_assert($disabledRuntime['context']->isEmpty() && !file_exists($executionMarker) && red_legacy_public_component_context(['Component' => $componentId, 'RecordID' => $contentRecordId], 'listing', 'safe', 3, true, $connection) === null, 'installed-disabled components are neither executed nor placeable');

    mysqli_query($connection, "UPDATE RED_Addon_Installations SET LifecycleState='enabled' WHERE PackageID='redcms.component-fixture'");
    $runtime = red_addon_runtime_request_bootstrap($connection, $fixtureProject);
    red_addon_component_test_assert($runtime->order() === [$packageId] && file_get_contents($executionMarker) === "registered\n" && red_addon_runtime_owner('components', $componentId) === $packageId, 'only the enabled component registers into the request-local runtime');

    red_addon_component_test_assert(
        red_addon_component_persistence_storage_available($connection)
            && red_addon_component_persistence_binding(
                $connection,
                $contentRecordId,
                $componentId
            ) === null,
        'current component-id storage exists and a missing parent fails closed'
    );

    $packageMigration = 'CREATE TABLE `' . $packageTable . '` ('
        . '`ContentRecordID` int unsigned NOT NULL,'
        . '`Label` varchar(120) NOT NULL,'
        . 'PRIMARY KEY (`ContentRecordID`),'
        . 'CONSTRAINT `fk_red_addon_component_fixture_parent` '
        . 'FOREIGN KEY (`ContentRecordID`) REFERENCES `RED_Articles` (`RecordID`) '
        . 'ON DELETE RESTRICT ON UPDATE RESTRICT'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    red_addon_component_test_assert(
        red_addon_install_sql_guard($packageMigration) === ''
            && red_addon_install_sql_guard(
                'ALTER TABLE RED_Articles ADD COLUMN Unsafe int;'
            ) === 'migration_table_scope'
            && red_addon_install_sql_guard(
                'CREATE TABLE RED_Addon_Unsafe ('
                . 'ParentID int unsigned NOT NULL, '
                . 'FOREIGN KEY (ParentID) REFERENCES RED_Articles(Component));'
            ) === 'migration_table_scope',
        'package SQL permits only the exact numeric article-parent reference and still rejects core writes or alternate columns'
    );
    red_addon_install_execute_sql($connection, $packageMigration);
    red_addon_component_test_insert_parent(
        $connection,
        $contentRecordId,
        $componentId
    );
    mysqli_query(
        $connection,
        "INSERT INTO `$packageTable` (ContentRecordID, Label)
         VALUES ($contentRecordId, 'Package-owned fixture data')"
    );
    $binding = red_addon_component_persistence_binding(
        $connection,
        $contentRecordId,
        $componentId
    );
    red_addon_component_test_assert(
        $binding === [
            'contentRecordId' => $contentRecordId,
            'component' => $componentId,
            'package' => $packageId,
        ],
        'the enabled runtime owner resolves the exact persisted parent binding without selecting package data'
    );
    $orphanRejected = false;
    try {
        $orphanRejected = mysqli_query(
            $connection,
            "INSERT INTO `$packageTable` (ContentRecordID, Label)
             VALUES (2147000957, 'Orphan')"
        ) === false;
    } catch (Throwable $throwable) {
        $orphanRejected = true;
    }
    red_addon_component_test_assert(
        $orphanRejected,
        'the package-owned fixture cannot persist an orphan component record'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Component='redcms.component-fixture/other'
         WHERE RecordID=$contentRecordId"
    );
    red_addon_component_test_assert(
        red_addon_component_persistence_binding(
            $connection,
            $contentRecordId,
            $componentId
        ) === null,
        'component identity drift between the parent and runtime fails closed'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Articles
         SET Component='"
            . mysqli_real_escape_string($connection, $componentId)
            . "'
         WHERE RecordID=$contentRecordId"
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='installed_disabled'
         WHERE PackageID='redcms.component-fixture'"
    );
    red_addon_component_test_assert(
        red_addon_component_persistence_binding(
            $connection,
            $contentRecordId,
            $componentId
        ) === null,
        'a persisted disabled state refuses the binding even if an earlier request context registered it'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='redcms.component-fixture'"
    );

    $context = red_legacy_public_component_context(['Component' => $componentId, 'RecordID' => $contentRecordId], 'listing<script>', 'safe', 3, true, $connection);
    ob_start(); $rendered = red_legacy_render_public_component($context); $output = (string) ob_get_clean();
    red_addon_component_test_assert($rendered === true && str_contains($output, 'class="red-addon-component"') && str_contains($output, 'data-red-addon-component="' . $componentId . '"') && str_contains($output, '&lt;Fixture listing&lt;script&gt;&gt;') && str_contains($output, 'record=' . $contentRecordId . '&amp;position=3') && !str_contains($output, '<script>'), 'core renders the exact text-only view model with escaped accessible markup');

    $factsContext = red_legacy_public_component_context(['Component' => $componentId, 'RecordID' => $contentRecordId], 'listing', 'facts', 3, true, $connection);
    ob_start(); $factsRendered = red_legacy_render_public_component($factsContext); $factsOutput = (string) ob_get_clean();
    red_addon_component_test_assert(
        $factsRendered === true
            && str_contains($factsOutput, '<dl class="red-addon-component__facts">')
            && str_contains($factsOutput, '<dt>Price &lt;now&gt;</dt>')
            && str_contains($factsOutput, '<dd>USD 24.99 &amp; tax</dd>')
            && str_contains($factsOutput, '<dt>Availability</dt>')
            && !str_contains($factsOutput, '<Product>'),
        'core renders a bounded fact-card model with escaped semantic markup'
    );

    $tooManyFacts = array_fill(
        0,
        13,
        ['label' => 'Fact', 'value' => 'Bounded']
    );
    red_addon_component_test_assert(
        red_addon_public_component_view_model([
            'title' => 'Fixture',
            'summary' => '',
            'facts' => $tooManyFacts,
        ]) === null
            && red_addon_public_component_view_model([
                'title' => 'Fixture',
                'summary' => '',
                'facts' => [[
                    'label' => 'Fact',
                    'value' => str_repeat('x', 2001),
                ]],
            ]) === null,
        'fact-card count and scalar bounds fail closed without partial output'
    );

    foreach (['emit', 'throw', 'nested', 'invalid', 'invalid-facts'] as $case) {
        $caseContext = red_legacy_public_component_context(['Component' => $componentId, 'RecordID' => $contentRecordId], 'listing', $case, 3, true, $connection);
        ob_start(); $caseRendered = red_legacy_render_public_component($caseContext); $caseOutput = (string) ob_get_clean();
        red_addon_component_test_assert($caseRendered === true && str_contains($caseOutput, 'Content is temporarily unavailable.') && !str_contains($caseOutput, 'fixture-output') && !str_contains($caseOutput, 'fixture failure') && !str_contains($caseOutput, '<script>'), 'component ' . $case . ' failure is contained by the static unavailable fallback');
    }

    $inactiveContext = red_legacy_public_component_context(['Component' => $componentId, 'RecordID' => $contentRecordId], 'listing', 'safe', 3, false, $connection);
    ob_start(); $inactiveRendered = red_legacy_render_public_component($inactiveContext); $inactiveOutput = (string) ob_get_clean();
    red_addon_component_test_assert($inactiveRendered === false && $inactiveOutput === '', 'inactive add-on placements do not invoke a handler or render a fallback');

    $legacyContext = red_legacy_public_component_context(['Component' => 'Article', 'RecordID' => '42'], 'listing', 'safe', 3, true);
    red_addon_component_test_assert($legacyContext === ['component' => 'Article', 'active' => true, 'inputs' => ['recordId' => '42', 'layout' => 'listing', 'article' => 'safe', 'position' => 3]], 'legacy component context remains unchanged and is not claimed by add-on dispatch');

    red_addon_component_test_cleanup(
        $connection,
        $packageId,
        $contentRecordId,
        $packageTable,
        $temporaryRoot
    );
    $cleanupResult = mysqli_query(
        $connection,
        "SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID='redcms.component-fixture'),
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME='RED_Addon_Component_Persistence_Fixture')
        ) AS CleanupState"
    );
    $cleanupRow = $cleanupResult ? mysqli_fetch_assoc($cleanupResult) : null;
    if ($cleanupResult) {
        mysqli_free_result($cleanupResult);
    }
    red_addon_component_test_assert(
        !file_exists($temporaryRoot)
            && ($cleanupRow['CleanupState'] ?? '') === '0:0:0',
        'component fixture database and filesystem state clean up exactly'
    );
    printf("Add-on component dispatch self-test passed: %d assertions.\n", $assertions);
} catch (Throwable $throwable) {
    red_addon_component_test_cleanup(
        $connection,
        $packageId,
        $contentRecordId,
        $packageTable,
        $temporaryRoot
    );
    fwrite(STDERR, $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n");
    $db->close();
    exit(1);
}
$db->close();
