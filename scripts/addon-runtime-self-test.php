<?php

require_once dirname(__DIR__) . '/includes/addon_runtime_helpers.php';

$assertions = 0;
function red_addon_runtime_test_assert($condition, $message)
{
    global $assertions;
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    $assertions++;
}

$root = sys_get_temp_dir() . '/redcms-addon-runtime-' . bin2hex(random_bytes(6));
$packagePath = $root . '/addons/redcms/runtime-fixture';
if (!mkdir($packagePath, 0700, true)) {
    throw new RuntimeException('Could not create runtime fixture.');
}

try {
    $entrypoint = <<<'PHP'
<?php
return static function (RED_Addon_Runtime_Registry $runtime): void {
    $runtime->registerService(
        'runtime.fixture',
        static function (): string {
            return 'runtime-ok';
        }
    );
};
PHP;
    file_put_contents($packagePath . '/addon.php', $entrypoint);
    $checksum = hash_file('sha256', $packagePath . '/addon.php');
    $manifest = [
        'id' => 'redcms.runtime-fixture',
        'version' => '0.1.0',
        'type' => 'service',
        'provides' => [
            'components' => [],
            'services' => ['runtime.fixture'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'routes' => [],
        'migrations' => [],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => $checksum,
            ]],
        ],
    ];
    file_put_contents(
        $packagePath . '/addon.json',
        json_encode($manifest, JSON_UNESCAPED_SLASHES)
    );
    $package = [
        'id' => 'redcms.runtime-fixture',
        'path' => $packagePath,
        'valid' => true,
        'manifest' => $manifest,
        'integrity' => ['inventoryComplete' => true],
    ];

    $registry = red_addon_runtime_register_package($package);
    $snapshot = $registry->snapshot();
    red_addon_runtime_test_assert(
        $snapshot['registrations']['services'] === ['runtime.fixture'],
        'the fixed entry point registers its exact declared service'
    );
    $handler = $registry->handler('services', 'runtime.fixture');
    red_addon_runtime_test_assert(
        is_callable($handler) && $handler() === 'runtime-ok',
        'the registered service handler remains callable'
    );

    $tampered = $package;
    file_put_contents($packagePath . '/addon.php', $entrypoint . "\n ");
    try {
        red_addon_runtime_register_package($tampered);
        red_addon_runtime_test_assert(false, 'tampered entry point must fail');
    } catch (RuntimeException $exception) {
        red_addon_runtime_test_assert(
            str_contains($exception->getMessage(), 'integrity'),
            'entry point checksum drift fails before inclusion'
        );
    }
    file_put_contents($packagePath . '/addon.php', $entrypoint);

    $missingPackage = $package;
    $missingPackage['manifest']['provides']['services'][] = 'runtime.missing';
    try {
        red_addon_runtime_register_package($missingPackage);
        red_addon_runtime_test_assert(false, 'missing registration must fail');
    } catch (LogicException $exception) {
        red_addon_runtime_test_assert(
            str_contains($exception->getMessage(), 'missing'),
            'every declared runtime identifier must register'
        );
    }

    $undeclaredEntrypoint = <<<'PHP'
<?php
return static function (RED_Addon_Runtime_Registry $runtime): void {
    $runtime->registerService('runtime.undeclared', static function (): void {});
};
PHP;
    file_put_contents($packagePath . '/addon.php', $undeclaredEntrypoint);
    $undeclaredPackage = $package;
    $undeclaredPackage['manifest']['integrity']['files'][0]['sha256'] =
        hash_file('sha256', $packagePath . '/addon.php');
    try {
        red_addon_runtime_register_package($undeclaredPackage);
        red_addon_runtime_test_assert(false, 'undeclared registration must fail');
    } catch (LogicException $exception) {
        red_addon_runtime_test_assert(
            str_contains($exception->getMessage(), 'undeclared'),
            'an entry point cannot register an identifier absent from its manifest'
        );
    }

    $outputEntrypoint = <<<'PHP'
<?php
echo 'unexpected output';
return static function (RED_Addon_Runtime_Registry $runtime): void {
    $runtime->registerService('runtime.fixture', static function (): void {});
};
PHP;
    file_put_contents($packagePath . '/addon.php', $outputEntrypoint);
    $outputPackage = $package;
    $outputPackage['manifest']['integrity']['files'][0]['sha256'] =
        hash_file('sha256', $packagePath . '/addon.php');
    try {
        red_addon_runtime_register_package($outputPackage);
        red_addon_runtime_test_assert(false, 'entry-point output must fail');
    } catch (RuntimeException $exception) {
        red_addon_runtime_test_assert(
            str_contains($exception->getMessage(), 'emitted output'),
            'runtime bootstrap rejects output before public rendering begins'
        );
    }

    $nonCallableEntrypoint = "<?php\nreturn ['invalid'];\n";
    file_put_contents($packagePath . '/addon.php', $nonCallableEntrypoint);
    $nonCallablePackage = $package;
    $nonCallablePackage['manifest']['integrity']['files'][0]['sha256'] =
        hash_file('sha256', $packagePath . '/addon.php');
    try {
        red_addon_runtime_register_package($nonCallablePackage);
        red_addon_runtime_test_assert(false, 'non-callable entry point must fail');
    } catch (RuntimeException $exception) {
        red_addon_runtime_test_assert(
            str_contains($exception->getMessage(), 'registrar callable'),
            'the fixed entry point must return one registrar callable'
        );
    }

    $registrarOutputEntrypoint = <<<'PHP'
<?php
return static function (RED_Addon_Runtime_Registry $runtime): void {
    $runtime->registerService('runtime.fixture', static function (): void {});
    echo 'unexpected registrar output';
};
PHP;
    file_put_contents($packagePath . '/addon.php', $registrarOutputEntrypoint);
    $registrarOutputPackage = $package;
    $registrarOutputPackage['manifest']['integrity']['files'][0]['sha256'] =
        hash_file('sha256', $packagePath . '/addon.php');
    try {
        red_addon_runtime_register_package($registrarOutputPackage);
        red_addon_runtime_test_assert(false, 'registrar output must fail');
    } catch (RuntimeException $exception) {
        red_addon_runtime_test_assert(
            str_contains($exception->getMessage(), 'registrar emitted output'),
            'runtime registration rejects output before public rendering begins'
        );
    }

    $registrarReturnEntrypoint = <<<'PHP'
<?php
return static function (RED_Addon_Runtime_Registry $runtime): string {
    $runtime->registerService('runtime.fixture', static function (): void {});
    return 'unexpected result';
};
PHP;
    file_put_contents($packagePath . '/addon.php', $registrarReturnEntrypoint);
    $registrarReturnPackage = $package;
    $registrarReturnPackage['manifest']['integrity']['files'][0]['sha256'] =
        hash_file('sha256', $packagePath . '/addon.php');
    try {
        red_addon_runtime_register_package($registrarReturnPackage);
        red_addon_runtime_test_assert(false, 'registrar return value must fail');
    } catch (RuntimeException $exception) {
        red_addon_runtime_test_assert(
            str_contains($exception->getMessage(), 'must return null'),
            'runtime registrar cannot return ambiguous data'
        );
    }
    file_put_contents($packagePath . '/addon.php', $entrypoint);

    $duplicateRegistry = new RED_Addon_Runtime_Registry(
        'redcms.runtime-fixture',
        $manifest
    );
    $duplicateRegistry->registerService(
        'runtime.fixture',
        static function (): void {}
    );
    try {
        $duplicateRegistry->registerService(
            'runtime.fixture',
            static function (): void {}
        );
        red_addon_runtime_test_assert(false, 'duplicate registration must fail');
    } catch (LogicException $exception) {
        red_addon_runtime_test_assert(
            str_contains($exception->getMessage(), 'duplicated'),
            'a runtime identifier has exactly one handler owner'
        );
    }

    $catalog = [
        'packages' => [
            'redcms.dependency' => [
                'valid' => true,
                'manifest' => ['dependencies' => ['required' => []]],
            ],
            'redcms.target' => [
                'valid' => true,
                'manifest' => [
                    'dependencies' => [
                        'required' => [[
                            'id' => 'redcms.dependency',
                            'version' => '>=0.1',
                        ]],
                    ],
                ],
            ],
        ],
    ];
    $errors = [];
    red_addon_runtime_test_assert(
        red_addon_runtime_load_order(
            $catalog,
            ['redcms.target', 'redcms.dependency'],
            $errors
        ) === ['redcms.dependency', 'redcms.target'],
        'required enabled dependencies load before dependents'
    );
    red_addon_runtime_test_assert(
        red_addon_runtime_load_order(
            $catalog,
            ['redcms.target'],
            $errors
        ) === null
            && $errors === ['enabled_dependency_missing:redcms.target'],
        'a missing enabled dependency fails closed'
    );

    $cycleCatalog = [
        'packages' => [
            'redcms.cycle-a' => [
                'valid' => true,
                'manifest' => [
                    'dependencies' => [
                        'required' => [['id' => 'redcms.cycle-b']],
                    ],
                ],
            ],
            'redcms.cycle-b' => [
                'valid' => true,
                'manifest' => [
                    'dependencies' => [
                        'required' => [['id' => 'redcms.cycle-a']],
                    ],
                ],
            ],
        ],
    ];
    red_addon_runtime_test_assert(
        red_addon_runtime_load_order(
            $cycleCatalog,
            ['redcms.cycle-a', 'redcms.cycle-b'],
            $errors
        ) === null
            && $errors === ['runtime_dependency_cycle'],
        'a required runtime dependency cycle fails closed'
    );

    echo "Add-on runtime contract passed $assertions assertions.\n";
} finally {
    @unlink($packagePath . '/addon.php');
    @unlink($packagePath . '/addon.json');
    @rmdir($packagePath);
    @rmdir(dirname($packagePath));
    @rmdir(dirname(dirname($packagePath)));
    @rmdir($root);
}
