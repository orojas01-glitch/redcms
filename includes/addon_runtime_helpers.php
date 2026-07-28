<?php
/**
 * Fixed runtime-registration contract for validated first-party add-ons.
 *
 * Database rows never select executable files or callbacks. Core loads only
 * the fixed, integrity-checked addon.php entry point from a validated package.
 * The entry point must return one registrar callable, and that callable may
 * register only identifiers already declared by the manifest.
 */

require_once __DIR__ . '/addon_registry_helpers.php';

if (!class_exists('RED_Addon_Runtime_Registry', false)) {
    final class RED_Addon_Runtime_Registry
    {
        private string $packageId;
        private array $allowed = [];
        private array $handlers = [];

        public function __construct(string $packageId, array $manifest)
        {
            if (!red_addon_valid_package_id($packageId)) {
                throw new InvalidArgumentException('Runtime package id is invalid.');
            }
            $this->packageId = $packageId;
            foreach (['components', 'services', 'adminTools', 'adapters'] as $type) {
                $values = is_array($manifest['provides'][$type] ?? null)
                    ? $manifest['provides'][$type]
                    : [];
                $this->allowed[$type] = array_fill_keys($values, true);
                $this->handlers[$type] = [];
            }
            $routeIds = [];
            foreach ($manifest['routes'] ?? [] as $route) {
                if (is_array($route) && is_string($route['id'] ?? null)) {
                    $routeIds[] = $route['id'];
                }
            }
            $this->allowed['routes'] = array_fill_keys($routeIds, true);
            $this->handlers['routes'] = [];
        }

        public function packageId(): string
        {
            return $this->packageId;
        }

        public function register(string $type, string $id, callable $handler): void
        {
            if (!isset($this->allowed[$type]) || !isset($this->allowed[$type][$id])) {
                throw new LogicException(
                    'Runtime registration is undeclared: ' . $type . ':' . $id
                );
            }
            if (isset($this->handlers[$type][$id])) {
                throw new LogicException(
                    'Runtime registration is duplicated: ' . $type . ':' . $id
                );
            }
            $this->handlers[$type][$id] = $handler;
        }

        public function registerComponent(string $id, callable $handler): void
        {
            $this->register('components', $id, $handler);
        }

        public function registerService(string $id, callable $handler): void
        {
            $this->register('services', $id, $handler);
        }

        public function registerAdminTool(string $id, callable $handler): void
        {
            $this->register('adminTools', $id, $handler);
        }

        public function registerAdapter(string $id, callable $handler): void
        {
            $this->register('adapters', $id, $handler);
        }

        public function registerRoute(string $id, callable $handler): void
        {
            $this->register('routes', $id, $handler);
        }

        public function assertComplete(): void
        {
            foreach ($this->allowed as $type => $allowed) {
                $missing = array_values(array_diff(
                    array_keys($allowed),
                    array_keys($this->handlers[$type])
                ));
                sort($missing, SORT_STRING);
                if ($missing !== []) {
                    throw new LogicException(
                        'Runtime registrations are missing for ' .
                        $type . ': ' . implode(', ', $missing)
                    );
                }
            }
        }

        public function snapshot(): array
        {
            $snapshot = [];
            foreach ($this->handlers as $type => $handlers) {
                $ids = array_keys($handlers);
                sort($ids, SORT_STRING);
                $snapshot[$type] = $ids;
            }
            return [
                'packageId' => $this->packageId,
                'registrations' => $snapshot,
            ];
        }

        public function handler(string $type, string $id): ?callable
        {
            return $this->handlers[$type][$id] ?? null;
        }
    }
}

if (!function_exists('red_addon_runtime_entrypoint')) {
    function red_addon_runtime_entrypoint(array $package)
    {
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $entrypoint = $manifest['integrity']['entrypoint'] ?? null;
        $packagePath = is_string($package['path'] ?? null)
            ? $package['path']
            : '';
        if ($snapshot === null || $entrypoint !== 'addon.php' || $packagePath === '') {
            throw new RuntimeException('Runtime package snapshot is invalid.');
        }

        $declaredChecksum = '';
        foreach ($manifest['integrity']['files'] ?? [] as $file) {
            if (is_array($file) && ($file['path'] ?? null) === 'addon.php') {
                $declaredChecksum = is_string($file['sha256'] ?? null)
                    ? $file['sha256']
                    : '';
                break;
            }
        }
        $packageReal = realpath($packagePath);
        $entrypointPath = $packagePath . DIRECTORY_SEPARATOR . 'addon.php';
        $entrypointReal = realpath($entrypointPath);
        if (!is_string($packageReal)
            || !is_string($entrypointReal)
            || is_link($entrypointPath)
            || !is_file($entrypointReal)
            || !str_starts_with(
                $entrypointReal,
                $packageReal . DIRECTORY_SEPARATOR
            )
            || !red_addon_valid_sha256($declaredChecksum)
            || !hash_equals($declaredChecksum, (string) hash_file('sha256', $entrypointReal))
        ) {
            throw new RuntimeException('Runtime entry point integrity check failed.');
        }
        return [$snapshot, $manifest, $entrypointReal];
    }
}

if (!function_exists('red_addon_runtime_register_package')) {
    function red_addon_runtime_register_package(array $package)
    {
        [$snapshot, $manifest, $entrypoint] =
            red_addon_runtime_entrypoint($package);
        $registry = new RED_Addon_Runtime_Registry($snapshot['id'], $manifest);

        $bufferLevel = ob_get_level();
        ob_start();
        try {
            $registrar = (static function ($path) {
                return include $path;
            })($entrypoint);
            $output = ob_get_clean();
            if ($output !== '') {
                throw new RuntimeException('Runtime entry point emitted output.');
            }
            if (!is_callable($registrar)) {
                throw new RuntimeException(
                    'Runtime entry point must return one registrar callable.'
                );
            }

            ob_start();
            $result = $registrar($registry);
            $output = ob_get_clean();
            if ($output !== '') {
                throw new RuntimeException('Runtime registrar emitted output.');
            }
            if ($result !== null) {
                throw new RuntimeException('Runtime registrar must return null.');
            }
            $registry->assertComplete();
            return $registry;
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            throw $throwable;
        }
    }
}

if (!function_exists('red_addon_runtime_load_order')) {
    function red_addon_runtime_load_order(
        array $catalog,
        array $enabledPackageIds,
        array &$errors = []
    ) {
        $errors = [];
        $enabled = array_fill_keys($enabledPackageIds, true);
        $state = [];
        $order = [];
        $visit = function ($packageId) use (
            &$visit,
            &$state,
            &$order,
            &$errors,
            $catalog,
            $enabled
        ) {
            if (($state[$packageId] ?? 0) === 2) {
                return;
            }
            if (($state[$packageId] ?? 0) === 1) {
                $errors[] = 'runtime_dependency_cycle';
                return;
            }
            $package = $catalog['packages'][$packageId] ?? null;
            if (!is_array($package) || empty($package['valid'])) {
                $errors[] = 'enabled_package_invalid:' . $packageId;
                return;
            }
            $state[$packageId] = 1;
            $required = $package['manifest']['dependencies']['required'] ?? [];
            foreach ($required as $dependency) {
                $dependencyId = is_array($dependency)
                    ? (string) ($dependency['id'] ?? '')
                    : '';
                if ($dependencyId === '' || !isset($enabled[$dependencyId])) {
                    $errors[] = 'enabled_dependency_missing:' . $packageId;
                    continue;
                }
                $visit($dependencyId);
            }
            $state[$packageId] = 2;
            $order[] = $packageId;
        };
        $ids = array_keys($enabled);
        sort($ids, SORT_STRING);
        foreach ($ids as $packageId) {
            $visit($packageId);
        }
        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        return $errors === [] ? array_values(array_unique($order)) : null;
    }
}

if (!function_exists('red_addon_runtime_bootstrap')) {
    function red_addon_runtime_bootstrap($connection, $projectRoot)
    {
        if (!red_addon_registry_storage_available($connection)) {
            throw new RuntimeException('Add-on runtime registry storage is unavailable.');
        }
        $catalog = red_addon_discover($projectRoot, [
            'cmsVersion' => '5.1.0',
            'phpVersion' => PHP_VERSION,
        ]);
        if (empty($catalog['valid'])) {
            throw new RuntimeException('Add-on runtime catalog is invalid.');
        }
        $installations = red_addon_registry_installations($connection);
        $enabledIds = [];
        foreach ($installations as $packageId => $installation) {
            if (($installation['LifecycleState'] ?? '') === 'enabled') {
                $enabledIds[] = $packageId;
            }
        }
        $errors = [];
        $order = red_addon_runtime_load_order($catalog, $enabledIds, $errors);
        if ($order === null) {
            throw new RuntimeException(
                'Add-on runtime dependency check failed: ' .
                implode(', ', $errors)
            );
        }

        $registries = [];
        foreach ($order as $packageId) {
            $report = red_addon_registry_package_report(
                $connection,
                $catalog['packages'][$packageId]
            );
            if (($report['status'] ?? '') !== 'enabled_runtime_unavailable'
                || !empty($report['errors'])
            ) {
                throw new RuntimeException(
                    'Enabled add-on registry evidence is not current: ' .
                    $packageId
                );
            }
            $registries[$packageId] = red_addon_runtime_register_package(
                $catalog['packages'][$packageId]
            );
        }
        return [
            'order' => $order,
            'packages' => $registries,
        ];
    }
}

?>
