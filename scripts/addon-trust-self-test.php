<?php
/**
 * Dependency-free adversarial checks for read-only add-on trust discovery.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/addon_manifest_helpers.php';
require_once $repositoryRoot . '/includes/admin_addon_authorization_helpers.php';

$assertions = 0;
$temporaryRoot = sys_get_temp_dir() . '/redcms-addon-trust-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/package-executed.txt';

function red_addon_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_test_remove_tree($path)
{
    $path = (string) $path;
    if ($path === ''
        || !str_starts_with($path, sys_get_temp_dir() . '/redcms-addon-trust-')
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

function red_addon_test_project($temporaryRoot, $name, $withAddonRoot = true)
{
    $project = $temporaryRoot . '/' . $name;
    if (!mkdir($project, 0700, true) && !is_dir($project)) {
        throw new RuntimeException('Could not create test project: ' . $name);
    }
    if ($withAddonRoot && !mkdir($project . '/addons', 0700, true) && !is_dir($project . '/addons')) {
        throw new RuntimeException('Could not create test add-on root: ' . $name);
    }
    return $project;
}

function red_addon_test_manifest($packageId, array $files, $executionMarker)
{
    $integrityFiles = [];
    foreach ($files as $path => $content) {
        $integrityFiles[] = [
            'path' => $path,
            'sha256' => hash('sha256', $content),
        ];
    }
    usort(
        $integrityFiles,
        static function ($left, $right) {
            return strcmp($left['path'], $right['path']);
        }
    );

    return [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Test ' . $packageId,
        'description' => 'A dependency-free trust-gate fixture.',
        'version' => '1.2.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.0 <6.0',
            'php' => '>=8.1 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [$packageId . '/service'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => [
            'required' => [],
            'optional' => [],
        ],
        'permissions' => [$packageId . '.settings.manage'],
        'settings' => [],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => [
            'public' => [],
            'admin' => [],
        ],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => $integrityFiles,
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => true,
        ],
    ];
}

function red_addon_test_write_package(
    $project,
    $packageId,
    $executionMarker,
    array $extraFiles = [],
    $mutator = null
) {
    $parts = red_addon_package_parts($packageId);
    if ($parts === null) {
        throw new RuntimeException('Test package id is invalid: ' . $packageId);
    }
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create package directory: ' . $packageId);
    }

    $files = array_merge([
        'addon.php' => "<?php\nfile_put_contents(" . var_export($executionMarker, true) . ", 'executed');\n",
    ], $extraFiles);
    foreach ($files as $path => $content) {
        $parent = dirname($directory . '/' . $path);
        if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
            throw new RuntimeException('Could not create package file directory.');
        }
        file_put_contents($directory . '/' . $path, $content);
    }

    $manifest = red_addon_test_manifest($packageId, $files, $executionMarker);
    if (is_callable($mutator)) {
        $mutator($manifest, $files, $directory);
    }
    file_put_contents(
        $directory . '/addon.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    return $directory;
}

function red_addon_test_error_contains(array $result, $needle)
{
    foreach ($result['errors'] ?? [] as $error) {
        if (str_contains($error, $needle)) {
            return true;
        }
    }
    return false;
}

try {
    if (!mkdir($temporaryRoot, 0700, true) && !is_dir($temporaryRoot)) {
        throw new RuntimeException('Could not create add-on trust test root.');
    }

    $schema = json_decode(
        (string) file_get_contents($repositoryRoot . '/docs/addon-manifest.schema.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    red_addon_test_assert(
        ($schema['additionalProperties'] ?? null) === false
            && ($schema['properties']['$schema']['const'] ?? '') ===
                'https://red-sphere.com/schemas/addon-manifest-v1.json'
            && ($schema['properties']['integrity']['properties']['entrypoint']['const'] ?? '') === 'addon.php'
            && ($schema['properties']['componentEditors']['items']['$ref'] ?? '')
                === '#/$defs/componentEditor'
            && ($schema['properties']['adminToolContracts']['items']['$ref'] ?? '')
                === '#/$defs/adminToolContract'
            && ($schema['properties']['uninstall']['properties']['defaultDataAction']['const'] ?? '') === 'retain',
        'the published schema is closed, fixes the entry point, declares bounded component editors and administrator tools, and defaults uninstall to data retention'
    );

    $contractSource = (string) file_get_contents($repositoryRoot . '/docs/ADD-ON-CONTRACT.md');
    if (preg_match('/```json\s*(\{.*?\})\s*```/s', $contractSource, $exampleMatches) !== 1) {
        throw new RuntimeException('Could not locate the documented manifest example.');
    }
    $exampleManifest = json_decode($exampleMatches[1], true, 512, JSON_THROW_ON_ERROR);

    red_addon_test_assert(
        red_addon_valid_package_id('redcms.store-lite')
            && !red_addon_valid_package_id('store-lite')
            && !red_addon_valid_package_id('redcms.Store')
            && !red_addon_valid_package_id('../redcms.store'),
        'package ids require two safe lowercase slugs'
    );
    red_addon_test_assert(
        red_addon_valid_relative_path('src/Service.php')
            && !red_addon_valid_relative_path('../Service.php')
            && !red_addon_valid_relative_path('/tmp/Service.php')
            && !red_addon_valid_relative_path('src//Service.php'),
        'package-relative paths reject traversal, absolute paths, and empty segments'
    );
    red_addon_test_assert(
        red_addon_version_satisfies('5.1.0', '>=5.1 <6.0')
            && !red_addon_version_satisfies('6.0.0', '>=5.1 <6.0')
            && !red_addon_version_range_valid('>=5.1 || <6.0'),
        'version ranges use the bounded conjunction grammar'
    );

    $emptyProject = red_addon_test_project($temporaryRoot, 'empty-project', false);
    $emptyCatalog = red_addon_discover($emptyProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        !empty($emptyCatalog['valid']) && $emptyCatalog['packages'] === [],
        'a clean starter with no add-on root discovers no executable package'
    );

    $validProject = red_addon_test_project($temporaryRoot, 'valid-project');
    $validDirectory = red_addon_test_write_package(
        $validProject,
        'redcms.foundation',
        $executionMarker
    );
    $entrypointMtime = filemtime($validDirectory . '/addon.php');
    $valid = red_addon_validate_manifest(
        'redcms.foundation',
        $validProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => '8.5.8']
    );
    red_addon_test_assert(
        !empty($valid['valid'])
            && ($valid['integrity']['declaredFiles'] ?? 0) === 1
            && ($valid['integrity']['verifiedFiles'] ?? 0) === 1
            && !empty($valid['integrity']['inventoryComplete']),
        'a compatible package with an exact SHA-256 inventory passes'
    );
    red_addon_test_assert(
        !file_exists($executionMarker)
            && filemtime($validDirectory . '/addon.php') === $entrypointMtime,
        'manifest validation neither executes nor rewrites addon.php'
    );

    $editorProject = red_addon_test_project($temporaryRoot, 'component-editor-project');
    red_addon_test_write_package(
        $editorProject,
        'redcms.editor',
        $executionMarker,
        [],
        static function (&$manifest) {
            $componentId = 'redcms.editor/product';
            $permission = 'editor.products.manage';
            $manifest['type'] = 'component';
            $manifest['provides']['components'] = [$componentId];
            $manifest['provides']['services'] = [];
            $manifest['permissions'] = [$permission];
            $manifest['componentEditors'] = [[
                'component' => $componentId,
                'label' => 'Product',
                'description' => 'Bounded disposable component editor.',
                'icon' => 'package',
                'permissions' => [
                    'create' => $permission,
                    'view' => $permission,
                    'edit' => $permission,
                    'delete' => $permission,
                    'publish' => $permission,
                    'restore' => $permission,
                ],
                'fields' => [
                    [
                        'key' => 'title',
                        'label' => 'Title',
                        'type' => 'text',
                        'required' => true,
                        'maxLength' => 200,
                    ],
                    [
                        'key' => 'price-minor',
                        'label' => 'Price',
                        'type' => 'integer',
                        'required' => true,
                        'minimum' => 0,
                        'maximum' => 2147483647,
                    ],
                    [
                        'key' => 'availability',
                        'label' => 'Availability',
                        'type' => 'select',
                        'required' => true,
                        'options' => [
                            ['value' => 'available', 'label' => 'Available'],
                            ['value' => 'unavailable', 'label' => 'Unavailable'],
                        ],
                    ],
                ],
            ]];
        }
    );
    $editorResult = red_addon_validate_manifest(
        'redcms.editor',
        $editorProject,
        ['cmsVersion' => '5.1.0']
    );
    $editorSchema = red_addon_component_editor_schema(
        is_array($editorResult['manifest'] ?? null)
            ? $editorResult['manifest']
            : [],
        'redcms.editor/product'
    );
    red_addon_test_assert(
        !empty($editorResult['valid'])
            && is_array($editorSchema)
            && ($editorSchema['component'] ?? '') === 'redcms.editor/product'
            && array_column($editorSchema['fields'] ?? [], 'key') === [
                'title',
                'price-minor',
                'availability',
            ]
            && !file_exists($executionMarker),
        'bounded component editor metadata validates and normalizes without package execution'
    );

    $toolProject = red_addon_test_project(
        $temporaryRoot,
        'administrator-tool-project'
    );
    red_addon_test_write_package(
        $toolProject,
        'redcms.tool',
        $executionMarker,
        [],
        static function (&$manifest) {
            $toolId = 'redcms.tool/orders';
            $permission = 'tool.orders.view';
            $manifest['provides']['adminTools'] = [$toolId];
            $manifest['permissions'][] = $permission;
            $manifest['adminToolContracts'] = [[
                'tool' => $toolId,
                'label' => 'Orders',
                'description' => 'Read-only order status.',
                'icon' => 'orders',
                'permission' => $permission,
                'mode' => 'read-only',
            ]];
        }
    );
    $toolResult = red_addon_validate_manifest(
        'redcms.tool',
        $toolProject,
        ['cmsVersion' => '5.1.0']
    );
    $toolContract = red_addon_admin_tool_contract(
        is_array($toolResult['manifest'] ?? null)
            ? $toolResult['manifest']
            : [],
        'redcms.tool/orders'
    );
    red_addon_test_assert(
        !empty($toolResult['valid'])
            && $toolContract === [
                'tool' => 'redcms.tool/orders',
                'label' => 'Orders',
                'description' => 'Read-only order status.',
                'icon' => 'orders',
                'permission' => 'tool.orders.view',
                'mode' => 'read-only',
            ]
            && !file_exists($executionMarker),
        'administrator tool metadata maps one provided tool to one declared permission without package execution'
    );

    $invalidToolProject = red_addon_test_project(
        $temporaryRoot,
        'invalid-administrator-tool-project'
    );
    red_addon_test_write_package(
        $invalidToolProject,
        'redcms.invalid-tool',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['provides']['adminTools'] = [
                'redcms.invalid-tool/declared',
            ];
            $manifest['adminToolContracts'] = [[
                'tool' => 'redcms.invalid-tool/undeclared',
                'label' => 'Invalid',
                'description' => 'Invalid administrator tool fixture.',
                'icon' => '../unsafe',
                'permission' => 'invalid.missing',
                'mode' => 'write',
                'callback' => 'dangerous',
            ]];
        }
    );
    $invalidTool = red_addon_validate_manifest(
        'redcms.invalid-tool',
        $invalidToolProject,
        ['cmsVersion' => '5.1.0']
    );
    red_addon_test_assert(
        empty($invalidTool['valid'])
            && red_addon_test_error_contains(
                $invalidTool,
                'contains unsupported field "callback"'
            )
            && red_addon_test_error_contains(
                $invalidTool,
                'must appear in Provides adminTools'
            )
            && red_addon_test_error_contains(
                $invalidTool,
                'permission must appear in Permissions'
            )
            && red_addon_test_error_contains(
                $invalidTool,
                'mode must be read-only'
            )
            && !file_exists($executionMarker),
        'administrator tool contracts reject executable, undeclared, ungranted, and writable metadata'
    );

    $nullEditorProject = red_addon_test_project(
        $temporaryRoot,
        'null-component-editor-project'
    );
    red_addon_test_write_package(
        $nullEditorProject,
        'redcms.null-editor',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['componentEditors'] = null;
        }
    );
    $nullEditor = red_addon_validate_manifest(
        'redcms.null-editor',
        $nullEditorProject,
        ['cmsVersion' => '5.1.0']
    );

    $invalidEditorProject = red_addon_test_project(
        $temporaryRoot,
        'invalid-component-editor-project'
    );
    red_addon_test_write_package(
        $invalidEditorProject,
        'redcms.invalid-editor',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['provides']['components'] = [
                'redcms.invalid-editor/declared',
            ];
            $manifest['permissions'] = ['invalid-editor.manage'];
            $manifest['componentEditors'] = [[
                'component' => 'redcms.invalid-editor/undeclared',
                'label' => 'Invalid',
                'description' => 'Invalid component editor fixture.',
                'icon' => '../unsafe',
                'permissions' => [
                    'create' => 'invalid-editor.manage',
                    'view' => 'invalid-editor.manage',
                    'edit' => 'invalid-editor.missing',
                    'delete' => 'invalid-editor.manage',
                    'publish' => 'invalid-editor.manage',
                    'restore' => 'invalid-editor.manage',
                ],
                'fields' => [
                    [
                        'key' => 'content',
                        'label' => 'Content',
                        'type' => 'html',
                        'required' => true,
                        'callback' => 'dangerous',
                    ],
                    [
                        'key' => 'content',
                        'label' => 'Duplicate',
                        'type' => 'integer',
                        'required' => true,
                        'minimum' => 10,
                        'maximum' => 1,
                    ],
                ],
            ]];
        }
    );
    $invalidEditor = red_addon_validate_manifest(
        'redcms.invalid-editor',
        $invalidEditorProject,
        ['cmsVersion' => '5.1.0']
    );
    red_addon_test_assert(
        empty($invalidEditor['valid'])
            && red_addon_test_error_contains(
                $invalidEditor,
                'component must appear in Provides components'
            )
            && red_addon_test_error_contains(
                $invalidEditor,
                'permission "edit" must appear in Permissions'
            )
            && red_addon_test_error_contains($invalidEditor, 'icon token is invalid')
            && red_addon_test_error_contains(
                $invalidEditor,
                'unsupported field "callback"'
            )
            && red_addon_test_error_contains($invalidEditor, 'type is unsupported')
            && red_addon_test_error_contains($invalidEditor, 'field key "content" is duplicated')
            && red_addon_test_error_contains($invalidEditor, 'integer bounds are invalid')
            && empty($nullEditor['valid'])
            && red_addon_test_error_contains(
                $nullEditor,
                'Component editors must be an array'
            )
            && red_addon_component_editor_schema(
                is_array($invalidEditor['manifest'] ?? null)
                    ? $invalidEditor['manifest']
                    : [],
                'redcms.invalid-editor/undeclared'
            ) === null
            && !file_exists($executionMarker),
        'component editor schemas fail closed on null metadata, undeclared ownership, permissions, executable-looking fields, unsupported types, duplicates, and invalid bounds'
    );

    $exampleProject = red_addon_test_project($temporaryRoot, 'documented-example-project');
    $exampleDirectory = red_addon_test_write_package(
        $exampleProject,
        'redcms.store-lite',
        $executionMarker
    );
    $exampleManifest['integrity']['files'][0]['sha256'] = hash_file(
        'sha256',
        $exampleDirectory . '/addon.php'
    );
    file_put_contents(
        $exampleDirectory . '/addon.json',
        json_encode($exampleManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    $exampleResult = red_addon_validate_manifest(
        'redcms.store-lite',
        $exampleProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => '8.5.8']
    );
    red_addon_test_assert(
        !empty($exampleResult['valid']) && !file_exists($executionMarker),
        'the published illustrative manifest matches the executable PHP validation contract'
    );

    $validCatalog = red_addon_discover($validProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        !empty($validCatalog['valid'])
            && array_keys($validCatalog['packages']) === ['redcms.foundation']
            && !file_exists($executionMarker),
        'filesystem discovery returns the validated package without executing it'
    );

    $helperSource = (string) file_get_contents($repositoryRoot . '/includes/addon_manifest_helpers.php');
    red_addon_test_assert(
        !preg_match(
            '/(?m)^\s*(?:include|include_once|require|require_once|eval)\s*(?:\(|[\'"]|[A-Za-z_$])/',
            $helperSource
        )
            && !str_contains($helperSource, 'mysqli_')
            && !str_contains($helperSource, 'call_user_func'),
        'the read-only trust helper has no package execution or database primitive'
    );

    $closedProject = red_addon_test_project($temporaryRoot, 'closed-project');
    red_addon_test_write_package(
        $closedProject,
        'redcms.closed',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['callback'] = 'dangerous';
        }
    );
    $closed = red_addon_validate_manifest('redcms.closed', $closedProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($closed['valid']) && red_addon_test_error_contains($closed, 'unsupported field "callback"'),
        'unknown executable-looking manifest fields fail closed'
    );

    $schemaProject = red_addon_test_project($temporaryRoot, 'schema-project');
    red_addon_test_write_package(
        $schemaProject,
        'redcms.schema',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['$schema'] = 'https://example.com/untrusted-schema.json';
        }
    );
    $schemaResult = red_addon_validate_manifest(
        'redcms.schema',
        $schemaProject,
        ['cmsVersion' => '5.1.0']
    );
    red_addon_test_assert(
        empty($schemaResult['valid']) && red_addon_test_error_contains($schemaResult, 'must identify'),
        'an explicit schema reference cannot substitute a different manifest contract'
    );

    $arrayProject = red_addon_test_project($temporaryRoot, 'array-project');
    $arrayDirectory = red_addon_test_write_package($arrayProject, 'redcms.array', $executionMarker);
    file_put_contents($arrayDirectory . '/addon.json', '[]');
    $arrayRoot = red_addon_validate_manifest('redcms.array', $arrayProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($arrayRoot['valid']) && red_addon_test_error_contains($arrayRoot, 'root must be an object'),
        'a JSON array cannot masquerade as a manifest object'
    );

    $mismatchProject = red_addon_test_project($temporaryRoot, 'mismatch-project');
    red_addon_test_write_package(
        $mismatchProject,
        'redcms.expected',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['id'] = 'redcms.different';
        }
    );
    $mismatch = red_addon_validate_manifest('redcms.expected', $mismatchProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($mismatch['valid']) && red_addon_test_error_contains($mismatch, 'must match'),
        'manifest id must match the vendor/package directory'
    );

    $checksumProject = red_addon_test_project($temporaryRoot, 'checksum-project');
    red_addon_test_write_package(
        $checksumProject,
        'redcms.checksum',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['integrity']['files'][0]['sha256'] = str_repeat('0', 64);
        }
    );
    $checksum = red_addon_validate_manifest('redcms.checksum', $checksumProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($checksum['valid']) && red_addon_test_error_contains($checksum, 'checksum mismatch'),
        'a changed package file fails its declared SHA-256 check'
    );

    $undeclaredProject = red_addon_test_project($temporaryRoot, 'undeclared-project');
    $undeclaredDirectory = red_addon_test_write_package(
        $undeclaredProject,
        'redcms.undeclared',
        $executionMarker
    );
    file_put_contents($undeclaredDirectory . '/rogue.php', "<?php echo 'rogue';\n");
    $undeclared = red_addon_validate_manifest('redcms.undeclared', $undeclaredProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($undeclared['valid']) && red_addon_test_error_contains($undeclared, 'missing from integrity inventory'),
        'undeclared package PHP fails the exact inventory check'
    );

    $entrypointProject = red_addon_test_project($temporaryRoot, 'entrypoint-project');
    red_addon_test_write_package(
        $entrypointProject,
        'redcms.entrypoint',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['integrity']['files'] = [];
        }
    );
    $entrypoint = red_addon_validate_manifest('redcms.entrypoint', $entrypointProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($entrypoint['valid']) && red_addon_test_error_contains($entrypoint, 'must declare the fixed addon.php'),
        'the fixed entry point must appear in the integrity inventory'
    );

    $traversalProject = red_addon_test_project($temporaryRoot, 'traversal-project');
    red_addon_test_write_package(
        $traversalProject,
        'redcms.traversal',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['integrity']['files'][] = [
                'path' => '../outside.php',
                'sha256' => str_repeat('a', 64),
            ];
        }
    );
    $traversal = red_addon_validate_manifest('redcms.traversal', $traversalProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($traversal['valid']) && red_addon_test_error_contains($traversal, 'path is unsafe'),
        'integrity paths cannot traverse outside the package'
    );

    if (function_exists('symlink')) {
        $symlinkProject = red_addon_test_project($temporaryRoot, 'symlink-project');
        $outsideFile = $symlinkProject . '/outside.php';
        file_put_contents($outsideFile, "<?php echo 'outside';\n");
        $symlinkDirectory = red_addon_test_write_package(
            $symlinkProject,
            'redcms.symlink',
            $executionMarker
        );
        $linked = symlink($outsideFile, $symlinkDirectory . '/linked.php');
        if ($linked) {
            $manifest = json_decode(
                (string) file_get_contents($symlinkDirectory . '/addon.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $manifest['integrity']['files'][] = [
                'path' => 'linked.php',
                'sha256' => hash_file('sha256', $outsideFile),
            ];
            file_put_contents(
                $symlinkDirectory . '/addon.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            $symlinkResult = red_addon_validate_manifest(
                'redcms.symlink',
                $symlinkProject,
                ['cmsVersion' => '5.1.0']
            );
            red_addon_test_assert(
                empty($symlinkResult['valid']) && red_addon_test_error_contains($symlinkResult, 'symbolic link'),
                'symbolic-linked files fail even when their target checksum matches'
            );
        }
    }

    $compatProject = red_addon_test_project($temporaryRoot, 'compat-project');
    red_addon_test_write_package(
        $compatProject,
        'redcms.compat',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['compatibility']['cms'] = '>=6.0 <7.0';
            $manifest['compatibility']['php'] = '>=9.0 <10.0';
        }
    );
    $compat = red_addon_validate_manifest(
        'redcms.compat',
        $compatProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => '8.5.8']
    );
    red_addon_test_assert(
        empty($compat['valid'])
            && red_addon_test_error_contains($compat, 'incompatible with RED-CMS')
            && red_addon_test_error_contains($compat, 'incompatible with PHP'),
        'incompatible RED-CMS and PHP ranges both fail before execution'
    );

    $permissionProject = red_addon_test_project($temporaryRoot, 'permission-project');
    red_addon_test_write_package(
        $permissionProject,
        'redcms.permission',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['permissions'][] = $manifest['permissions'][0];
        }
    );
    $permission = red_addon_validate_manifest(
        'redcms.permission',
        $permissionProject,
        ['cmsVersion' => '5.1.0']
    );
    red_addon_test_assert(
        empty($permission['valid']) && red_addon_test_error_contains($permission, 'repeats'),
        'duplicate requested permissions fail validation'
    );

    $secretProject = red_addon_test_project($temporaryRoot, 'secret-project');
    red_addon_test_write_package(
        $secretProject,
        'redcms.secret',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['settings'][] = [
                'key' => 'secret.api-key',
                'label' => 'API key',
                'type' => 'secret-reference',
                'secret' => true,
                'default' => 'must-not-ship',
            ];
        }
    );
    $secret = red_addon_validate_manifest('redcms.secret', $secretProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($secret['valid']) && red_addon_test_error_contains($secret, 'must not contain a secret default'),
        'secret references cannot contain packaged secret values'
    );

    $defaultProject = red_addon_test_project($temporaryRoot, 'default-project');
    red_addon_test_write_package(
        $defaultProject,
        'redcms.default',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['settings'][] = [
                'key' => 'catalog.tax-rate',
                'label' => 'Tax rate',
                'type' => 'text',
                'secret' => false,
                'default' => 1.5,
            ];
        }
    );
    $defaultResult = red_addon_validate_manifest(
        'redcms.default',
        $defaultProject,
        ['cmsVersion' => '5.1.0']
    );
    red_addon_test_assert(
        empty($defaultResult['valid'])
            && red_addon_test_error_contains($defaultResult, 'string, integer, boolean, or null'),
        'setting defaults match the closed JSON Schema scalar types and reject floating-point drift'
    );

    $limitProject = red_addon_test_project($temporaryRoot, 'limit-project');
    red_addon_test_write_package(
        $limitProject,
        'redcms.limit',
        $executionMarker,
        [],
        static function (&$manifest) {
            for ($index = 0; $index <= 200; $index++) {
                $manifest['settings'][] = [
                    'key' => 'limit.setting-' . $index,
                    'label' => 'Setting ' . $index,
                    'type' => 'text',
                    'secret' => false,
                ];
            }
        }
    );
    $limitResult = red_addon_validate_manifest(
        'redcms.limit',
        $limitProject,
        ['cmsVersion' => '5.1.0']
    );
    red_addon_test_assert(
        empty($limitResult['valid']) && red_addon_test_error_contains($limitResult, 'Settings exceeds 200'),
        'the PHP trust gate enforces the published manifest collection limits'
    );

    $routeProject = red_addon_test_project($temporaryRoot, 'route-project');
    red_addon_test_write_package(
        $routeProject,
        'redcms.route',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['routes'][] = [
                'id' => 'redcms.route/write',
                'scope' => 'public',
                'path' => '/outside-package',
                'methods' => ['POST'],
                'authentication' => 'public',
                'csrf' => 'not-applicable',
            ];
            $manifest['routes'][] = [
                'id' => 'redcms.route/empty',
                'scope' => 'public',
                'path' => '/addons/redcms/route/empty',
                'methods' => [],
                'authentication' => 'public',
                'csrf' => 'not-applicable',
            ];
        }
    );
    $route = red_addon_validate_manifest('redcms.route', $routeProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($route['valid'])
            && red_addon_test_error_contains($route, 'reserved package namespace')
            && red_addon_test_error_contains($route, 'unsafe methods require CSRF')
            && red_addon_test_error_contains($route, 'methods must not be empty'),
        'routes cannot escape their namespace, omit methods, or omit CSRF for unsafe methods'
    );

    $migrationProject = red_addon_test_project($temporaryRoot, 'migration-project');
    $migrationSql = "CREATE TABLE RED_Addon_Test (RecordID int NOT NULL);\n";
    red_addon_test_write_package(
        $migrationProject,
        'redcms.migration',
        $executionMarker,
        ['migrations/2026-07-25-create-test.sql' => $migrationSql],
        static function (&$manifest) use ($migrationSql) {
            $manifest['migrations'][] = [
                'id' => '2026-07-25-create-test',
                'path' => 'migrations/2026-07-25-create-test.sql',
                'sha256' => hash('sha256', $migrationSql),
            ];
        }
    );
    $migration = red_addon_validate_manifest(
        'redcms.migration',
        $migrationProject,
        ['cmsVersion' => '5.1.0']
    );
    red_addon_test_assert(
        !empty($migration['valid']) && ($migration['integrity']['verifiedFiles'] ?? 0) === 2,
        'reviewed migration files must match both their ordered declaration and exact package inventory'
    );

    $conflictProject = red_addon_test_project($temporaryRoot, 'reference-conflict-project');
    red_addon_test_write_package(
        $conflictProject,
        'redcms.reference-conflict',
        $executionMarker,
        ['migrations/2026-07-25-create-test.sql' => $migrationSql],
        static function (&$manifest) use ($migrationSql) {
            $manifest['migrations'][] = [
                'id' => '2026-07-25-create-test',
                'path' => 'migrations/2026-07-25-create-test.sql',
                'sha256' => hash('sha256', $migrationSql),
            ];
            $manifest['assets']['admin'][] = [
                'path' => 'migrations/2026-07-25-create-test.sql',
                'sha256' => str_repeat('0', 64),
                'location' => 'body-end',
            ];
        }
    );
    $conflict = red_addon_validate_manifest(
        'redcms.reference-conflict',
        $conflictProject,
        ['cmsVersion' => '5.1.0']
    );
    red_addon_test_assert(
        empty($conflict['valid']) && red_addon_test_error_contains($conflict, 'checksum conflicts'),
        'one package file cannot carry conflicting checksums across manifest declarations'
    );

    $dependencyProject = red_addon_test_project($temporaryRoot, 'dependency-project');
    red_addon_test_write_package(
        $dependencyProject,
        'redcms.base',
        $executionMarker
    );
    red_addon_test_write_package(
        $dependencyProject,
        'redcms.consumer',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['dependencies']['required'][] = [
                'id' => 'redcms.base',
                'version' => '>=1.0 <2.0',
            ];
            $manifest['dependencies']['optional'][] = [
                'id' => 'redcms.absent',
                'version' => '>=1.0 <2.0',
            ];
        }
    );
    $dependencyCatalog = red_addon_discover($dependencyProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        !empty($dependencyCatalog['valid'])
            && !empty($dependencyCatalog['dependency']['valid'])
            && ($dependencyCatalog['dependency']['graph']['redcms.consumer'] ?? []) === ['redcms.base'],
        'required compatible dependencies pass and absent optional dependencies remain optional'
    );

    $missingProject = red_addon_test_project($temporaryRoot, 'missing-dependency-project');
    red_addon_test_write_package(
        $missingProject,
        'redcms.missing-consumer',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['dependencies']['required'][] = [
                'id' => 'redcms.missing-base',
                'version' => '>=1.0 <2.0',
            ];
        }
    );
    $missingCatalog = red_addon_discover($missingProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($missingCatalog['valid'])
            && str_contains(implode(' ', $missingCatalog['dependency']['errors']), 'requires missing package'),
        'a missing required package fails dependency preflight'
    );

    $rangeProject = red_addon_test_project($temporaryRoot, 'dependency-range-project');
    red_addon_test_write_package($rangeProject, 'redcms.range-base', $executionMarker);
    red_addon_test_write_package(
        $rangeProject,
        'redcms.range-consumer',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['dependencies']['required'][] = [
                'id' => 'redcms.range-base',
                'version' => '>=2.0 <3.0',
            ];
        }
    );
    $rangeCatalog = red_addon_discover($rangeProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($rangeCatalog['valid'])
            && str_contains(implode(' ', $rangeCatalog['dependency']['errors']), 'does not satisfy'),
        'an incompatible required dependency version fails preflight'
    );

    $cycleProject = red_addon_test_project($temporaryRoot, 'cycle-project');
    red_addon_test_write_package(
        $cycleProject,
        'redcms.cycle-a',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['dependencies']['required'][] = [
                'id' => 'redcms.cycle-b',
                'version' => '>=1.0 <2.0',
            ];
        }
    );
    red_addon_test_write_package(
        $cycleProject,
        'redcms.cycle-b',
        $executionMarker,
        [],
        static function (&$manifest) {
            $manifest['dependencies']['required'][] = [
                'id' => 'redcms.cycle-a',
                'version' => '>=1.0 <2.0',
            ];
        }
    );
    $cycleCatalog = red_addon_discover($cycleProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($cycleCatalog['valid'])
            && str_contains(implode(' ', $cycleCatalog['dependency']['errors']), 'Dependency cycle detected'),
        'circular dependencies fail preflight'
    );

    $unsafeProject = red_addon_test_project($temporaryRoot, 'unsafe-catalog-project');
    mkdir($unsafeProject . '/addons/Bad Vendor', 0700, true);
    $unsafeCatalog = red_addon_discover($unsafeProject, ['cmsVersion' => '5.1.0']);
    red_addon_test_assert(
        empty($unsafeCatalog['valid'])
            && str_contains(implode(' ', $unsafeCatalog['errors']), 'Unsafe add-on vendor entry'),
        'unsafe filesystem entries are reported instead of silently executed or trusted'
    );

    $lifecycleCapabilities = red_admin_addon_lifecycle_capabilities();
    red_addon_test_assert(
        $lifecycleCapabilities === [
            'addons.install',
            'addons.enable',
            'addons.disable',
            'addons.upgrade',
            'addons.uninstall',
            'addons.purge',
        ],
        'the lifecycle capability vocabulary is fixed and bounded'
    );
    foreach (['guest', 'webmaster', 'superadmin'] as $legacyRole) {
        red_addon_test_assert(
            !red_admin_addon_actor_can(
                ['role' => $legacyRole, 'capabilities' => $lifecycleCapabilities],
                'addons.install'
            ),
            'legacy role ' . $legacyRole . ' receives no implicit package lifecycle authority'
        );
    }
    red_addon_test_assert(
        !red_admin_addon_actor_can(['role' => 'owner', 'capabilities' => []], 'addons.install')
            && red_admin_addon_actor_can(
                ['role' => 'owner', 'capabilities' => ['addons.install']],
                'addons.install'
            )
            && !red_admin_addon_actor_can(
                ['role' => 'owner', 'capabilities' => ['addons.install']],
                'addons.purge'
            ),
        'Owner status and the exact lifecycle capability are both required'
    );
    $_SESSION = [
        'AdminRecordID' => 1,
        'AdminType' => 'webmaster',
    ];
    red_addon_test_assert(
        !red_admin_addon_current_actor_can('addons.install'),
        'current RED-CMS sessions fail closed because no Owner role or capability session is active'
    );

    $adminFiles = glob($repositoryRoot . '/admin/bin/*addon*') ?: [];
    $lifecycleAdminFiles = array_values(array_filter(
        $adminFiles,
        static function ($path) {
            return preg_match(
                '/(?:install|enable|disable|upgrade|uninstall|purge).*addon|addon.*(?:install|enable|disable|upgrade|uninstall|purge)/i',
                basename($path)
            ) === 1;
        }
    ));
    red_addon_test_assert(
        $lifecycleAdminFiles === [],
        'the trust batch exposes no web install, enable, disable, upgrade, uninstall, or purge endpoint'
    );
    red_addon_test_assert(
        !file_exists($executionMarker),
        'no adversarial discovery or validation case executed package PHP'
    );

    printf("Add-on trust self-test passed: %d assertions.\n", $assertions);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n");
    red_addon_test_remove_tree($temporaryRoot);
    exit(1);
}

red_addon_test_remove_tree($temporaryRoot);
