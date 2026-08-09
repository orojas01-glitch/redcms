<?php
/**
 * Dependency-free checks for administrator-form runtime setting declarations.
 *
 * This exercises manifest validation only. It never loads package PHP, opens a
 * database connection, resolves a setting value, or creates an endpoint.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/addon_manifest_helpers.php';
require_once $projectRoot . '/includes/addon_admin_tool_form_preflight_helpers.php';

$assertions = 0;
$temporaryRoot = sys_get_temp_dir() . '/redcms-runtime-setting-contract-' .
    bin2hex(random_bytes(8));

function red_addon_runtime_setting_contract_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_runtime_setting_contract_remove_tree($path)
{
    if (!is_string($path)
        || !str_starts_with(
            $path,
            sys_get_temp_dir() . '/redcms-runtime-setting-contract-'
        )
        || !file_exists($path)
    ) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isLink() || $entry->isFile()) {
            unlink($entry->getPathname());
        } else {
            rmdir($entry->getPathname());
        }
    }
    rmdir($path);
}

function red_addon_runtime_setting_contract_manifest(array $settings, array $runtimeSettings)
{
    return [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.runtime-fixture',
        'name' => 'Runtime setting fixture',
        'description' => 'A manifest-only runtime setting fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.0 <6.0',
            'php' => '>=8.1 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => ['redcms.runtime-fixture/products'],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [
            'fixture.products.manage',
            'fixture.settings.manage',
        ],
        'settings' => $settings,
        'adminToolFormContracts' => [[
            'tool' => 'redcms.runtime-fixture/products',
            'form' => 'redcms.runtime-fixture/product-editor',
            'label' => 'Product editor',
            'description' => 'Prepare one product editor.',
            'permission' => 'fixture.products.manage',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/json',
            'maxBodyBytes' => 65536,
            'runtimeSettings' => $runtimeSettings,
        ]],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => hash('sha256', "<?php\n"),
            ]],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => true,
        ],
    ];
}

function red_addon_runtime_setting_contract_validate(
    $temporaryRoot,
    array $manifest
) {
    $directory = $temporaryRoot . '/addons/redcms/runtime-fixture';
    if (!is_dir($directory)
        && !mkdir($directory, 0700, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException('Could not create runtime setting fixture.');
    }
    if (file_put_contents($directory . '/addon.php', "<?php\n") === false
        || file_put_contents(
            $directory . '/addon.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        ) === false
    ) {
        throw new RuntimeException('Could not write runtime setting fixture.');
    }
    return red_addon_validate_manifest(
        'redcms.runtime-fixture',
        $temporaryRoot,
        ['cmsVersion' => '5.1.0']
    );
}

function red_addon_runtime_setting_contract_has_error(array $result, $needle)
{
    foreach ($result['errors'] ?? [] as $error) {
        if (str_contains((string) $error, $needle)) {
            return true;
        }
    }
    return false;
}

try {
    $configuredLater = [[
        'key' => 'fixture.currency',
        'label' => 'Catalog currency',
        'type' => 'text',
        'secret' => false,
        'permission' => 'fixture.settings.manage',
        'default' => null,
    ]];
    $validManifest = red_addon_runtime_setting_contract_manifest(
        $configuredLater,
        ['fixture.currency']
    );
    $valid = red_addon_runtime_setting_contract_validate(
        $temporaryRoot,
        $validManifest
    );
    red_addon_runtime_setting_contract_assert(
        ($valid['errors'] ?? []) === [],
        'one declared non-secret setting without a value default is accepted'
    );
    $contract = red_addon_admin_tool_form_contract(
        $validManifest,
        'redcms.runtime-fixture/products',
        'redcms.runtime-fixture/product-editor'
    );
    red_addon_runtime_setting_contract_assert(
        is_array($contract)
            && ($contract['runtimeSettings'] ?? null) === ['fixture.currency'],
        'the closed runtime setting declaration is preserved by form lookup'
    );
    $withoutRuntimeContract = $contract;
    unset($withoutRuntimeContract['runtimeSettings']);
    red_addon_runtime_setting_contract_assert(
        !hash_equals(
            red_addon_admin_tool_form_contract_fingerprint($contract),
            red_addon_admin_tool_form_contract_fingerprint(
                $withoutRuntimeContract
            )
        ),
        'runtime setting declaration changes invalidate form contract evidence'
    );

    $withoutRuntime = $validManifest;
    unset($withoutRuntime['adminToolFormContracts'][0]['runtimeSettings']);
    $withoutRuntime['settings'] = [];
    $withoutRuntimeResult = red_addon_runtime_setting_contract_validate(
        $temporaryRoot,
        $withoutRuntime
    );
    red_addon_runtime_setting_contract_assert(
        ($withoutRuntimeResult['errors'] ?? []) === [],
        'existing form contracts remain valid without a runtime declaration'
    );

    $unknown = red_addon_runtime_setting_contract_validate(
        $temporaryRoot,
        red_addon_runtime_setting_contract_manifest(
            $configuredLater,
            ['fixture.unknown']
        )
    );
    red_addon_runtime_setting_contract_assert(
        red_addon_runtime_setting_contract_has_error(
            $unknown,
            'runtime setting "fixture.unknown" must be declared'
        ),
        'an undeclared setting cannot be exposed to a form'
    );

    $secret = red_addon_runtime_setting_contract_validate(
        $temporaryRoot,
        red_addon_runtime_setting_contract_manifest(
            [[
                'key' => 'fixture.api-token',
                'label' => 'API token',
                'type' => 'secret-reference',
                'secret' => true,
                'permission' => 'fixture.settings.manage',
            ]],
            ['fixture.api-token']
        )
    );
    red_addon_runtime_setting_contract_assert(
        red_addon_runtime_setting_contract_has_error(
            $secret,
            'runtime setting "fixture.api-token" must be non-secret'
        ),
        'secret settings cannot be declared as form runtime values'
    );

    $defaulted = red_addon_runtime_setting_contract_validate(
        $temporaryRoot,
        red_addon_runtime_setting_contract_manifest(
            [[
                'key' => 'fixture.currency',
                'label' => 'Catalog currency',
                'type' => 'text',
                'secret' => false,
                'permission' => 'fixture.settings.manage',
                'default' => 'USD',
            ]],
            ['fixture.currency']
        )
    );
    red_addon_runtime_setting_contract_assert(
        red_addon_runtime_setting_contract_has_error(
            $defaulted,
            'runtime setting "fixture.currency" must not have a non-null default'
        ),
        'runtime settings must be configured later per installation'
    );

    $duplicate = red_addon_runtime_setting_contract_validate(
        $temporaryRoot,
        red_addon_runtime_setting_contract_manifest(
            $configuredLater,
            ['fixture.currency', 'fixture.currency']
        )
    );
    red_addon_runtime_setting_contract_assert(
        red_addon_runtime_setting_contract_has_error(
            $duplicate,
            'runtimeSettings repeats "fixture.currency"'
        ),
        'runtime setting declarations are deduplicated'
    );

    red_addon_runtime_setting_contract_remove_tree($temporaryRoot);
    printf(
        "Add-on runtime setting contract self-test passed (%d assertions).\n",
        $assertions
    );
} catch (Throwable $throwable) {
    red_addon_runtime_setting_contract_remove_tree($temporaryRoot);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
