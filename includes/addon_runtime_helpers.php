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
        private array $manifest;
        private array $allowed = [];
        private array $handlers = [];
        private array $metadata = [];

        public function __construct(string $packageId, array $manifest)
        {
            if (!red_addon_valid_package_id($packageId)) {
                throw new InvalidArgumentException('Runtime package id is invalid.');
            }
            $this->packageId = $packageId;
            $this->manifest = $manifest;
            foreach (['components', 'services', 'adminTools', 'adapters'] as $type) {
                $values = is_array($manifest['provides'][$type] ?? null)
                    ? $manifest['provides'][$type]
                    : [];
                $this->allowed[$type] = array_fill_keys($values, true);
                $this->handlers[$type] = [];
                $this->metadata[$type] = [];
            }
            $componentDataLoaders = [];
            $componentEditors = is_array($manifest['componentEditors'] ?? null)
                ? $manifest['componentEditors']
                : [];
            foreach ($componentEditors as $editor) {
                $componentId = is_array($editor)
                    && is_string($editor['component'] ?? null)
                        ? $editor['component']
                        : '';
                if ($componentId !== ''
                    && isset($this->allowed['components'][$componentId])
                ) {
                    $componentDataLoaders[$componentId] = true;
                }
            }
            $this->allowed['componentDataLoaders'] = $componentDataLoaders;
            $this->handlers['componentDataLoaders'] = [];
            $this->metadata['componentDataLoaders'] = [];
            $this->allowed['componentDataWriters'] = $componentDataLoaders;
            $this->handlers['componentDataWriters'] = [];
            $this->metadata['componentDataWriters'] = [];
            $routeIds = [];
            foreach ($manifest['routes'] ?? [] as $route) {
                if (is_array($route) && is_string($route['id'] ?? null)) {
                    $routeIds[] = $route['id'];
                }
            }
            $this->allowed['routes'] = array_fill_keys($routeIds, true);
            $this->handlers['routes'] = [];
            $this->metadata['routes'] = [];
        }

        public function packageId(): string
        {
            return $this->packageId;
        }

        public function manifest(): array
        {
            return $this->manifest;
        }

        public function register(
            string $type,
            string $id,
            callable $handler,
            array $metadata = []
        ): void
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
            $this->metadata[$type][$id] = $metadata;
        }

        public function registerComponent(string $id, callable $handler): void
        {
            $this->register('components', $id, $handler);
        }

        public function registerService(string $id, callable $handler): void
        {
            $this->register('services', $id, $handler);
        }

        public function registerComponentDataLoader(
            string $id,
            callable $handler
        ): void {
            $this->register('componentDataLoaders', $id, $handler);
        }

        public function registerComponentDataWriter(
            string $id,
            callable $handler,
            array $tables
        ): void {
            $normalized = [];
            $reserved = [
                'red_addon_installations',
                'red_addon_migrations',
                'red_addon_activity_log',
            ];
            foreach ($tables as $table) {
                if (!is_string($table)
                    || preg_match('/\ARED_Addon_[A-Za-z0-9_]{1,54}\z/', $table)
                        !== 1
                    || in_array(strtolower($table), $reserved, true)
                    || isset($normalized[$table])
                ) {
                    throw new LogicException(
                        'Component data-writer transaction table is invalid.'
                    );
                }
                $normalized[$table] = true;
            }
            if ($normalized === [] || count($normalized) > 8) {
                throw new LogicException(
                    'Component data writer requires one to eight package tables.'
                );
            }
            $this->register(
                'componentDataWriters',
                $id,
                $handler,
                ['tables' => array_keys($normalized)]
            );
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
                if ($type === 'componentDataWriters') {
                    continue;
                }
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

        public function metadata(string $type, string $id): array
        {
            return $this->metadata[$type][$id] ?? [];
        }
    }
}

if (!class_exists('RED_Addon_Runtime_Context', false)) {
    final class RED_Addon_Runtime_Context
    {
        private array $order;
        private array $packages;
        private array $handlers = [];
        private array $owners = [];
        private array $metadata = [];

        public function __construct(array $order, array $packages)
        {
            $this->order = array_values($order);
            $this->packages = $packages;
            foreach (
                [
                    'components',
                    'componentDataLoaders',
                    'componentDataWriters',
                    'services',
                    'adminTools',
                    'adapters',
                    'routes',
                ]
                as $type
            ) {
                $this->handlers[$type] = [];
                $this->owners[$type] = [];
                $this->metadata[$type] = [];
            }

            foreach ($this->order as $packageId) {
                $registry = $this->packages[$packageId] ?? null;
                if (!$registry instanceof RED_Addon_Runtime_Registry
                    || $registry->packageId() !== $packageId
                ) {
                    throw new LogicException(
                        'Runtime context package evidence is invalid: ' .
                        $packageId
                    );
                }
                foreach ($registry->snapshot()['registrations'] as $type => $ids) {
                    foreach ($ids as $id) {
                        if (isset($this->owners[$type][$id])) {
                            throw new LogicException(
                                'Runtime context registration is duplicated: ' .
                                $type . ':' . $id
                            );
                        }
                        $handler = $registry->handler($type, $id);
                        if (!is_callable($handler)) {
                            throw new LogicException(
                                'Runtime context handler is unavailable: ' .
                                $type . ':' . $id
                            );
                        }
                        $this->handlers[$type][$id] = $handler;
                        $this->owners[$type][$id] = $packageId;
                        $this->metadata[$type][$id] =
                            $registry->metadata($type, $id);
                    }
                }
            }
        }

        public function order(): array
        {
            return $this->order;
        }

        public function isEmpty(): bool
        {
            return $this->order === [];
        }

        public function handler(string $type, string $id): ?callable
        {
            return $this->handlers[$type][$id] ?? null;
        }

        public function owner(string $type, string $id): ?string
        {
            return $this->owners[$type][$id] ?? null;
        }

        public function metadata(string $type, string $id): array
        {
            return $this->metadata[$type][$id] ?? [];
        }

        public function manifest(string $packageId): ?array
        {
            $registry = $this->packages[$packageId] ?? null;
            return $registry instanceof RED_Addon_Runtime_Registry
                && $registry->packageId() === $packageId
                    ? $registry->manifest()
                    : null;
        }

        public function snapshot(): array
        {
            $registrations = [];
            foreach ($this->owners as $type => $owners) {
                ksort($owners, SORT_STRING);
                $registrations[$type] = $owners;
            }
            return [
                'order' => $this->order,
                'registrations' => $registrations,
            ];
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

if (!function_exists('red_addon_runtime_namespace_errors')) {
    function red_addon_runtime_namespace_errors(
        array $catalog,
        array $enabledPackageIds
    ) {
        $errors = [];
        $owners = [];
        $routeMethods = [];
        $packageIds = array_values(array_unique($enabledPackageIds));
        sort($packageIds, SORT_STRING);

        foreach ($packageIds as $packageId) {
            $manifest = $catalog['packages'][$packageId]['manifest'] ?? null;
            if (!is_array($manifest)) {
                $errors[] = 'enabled_runtime_manifest_missing:' . $packageId;
                continue;
            }
            foreach (
                ['components', 'services', 'adminTools', 'adapters']
                as $type
            ) {
                foreach ($manifest['provides'][$type] ?? [] as $id) {
                    $ownerKey = $type . "\0" . $id;
                    if (isset($owners[$ownerKey])) {
                        $errors[] = 'enabled_runtime_capability_conflict:' .
                            $type . ':' . $id;
                        continue;
                    }
                    $owners[$ownerKey] = $packageId;
                }
            }
            foreach ($manifest['routes'] ?? [] as $route) {
                if (!is_array($route)) {
                    continue;
                }
                $routeId = (string) ($route['id'] ?? '');
                $routeOwnerKey = 'routes' . "\0" . $routeId;
                if (isset($owners[$routeOwnerKey])) {
                    $errors[] = 'enabled_runtime_route_id_conflict:' . $routeId;
                } else {
                    $owners[$routeOwnerKey] = $packageId;
                }
                $scope = (string) ($route['scope'] ?? '');
                $path = (string) ($route['path'] ?? '');
                foreach ($route['methods'] ?? [] as $method) {
                    $methodKey = $scope . "\0" . $path . "\0" . $method;
                    if (isset($routeMethods[$methodKey])) {
                        $errors[] = 'enabled_runtime_route_method_conflict:' .
                            $routeId . ':' . $method;
                        continue;
                    }
                    $routeMethods[$methodKey] = $packageId;
                }
            }
        }

        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        return $errors;
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

        $namespaceErrors = red_addon_runtime_namespace_errors(
            $catalog,
            $enabledIds
        );
        if ($namespaceErrors !== []) {
            throw new RuntimeException(
                'Add-on runtime namespace check failed: ' .
                implode(', ', $namespaceErrors)
            );
        }

        foreach ($order as $packageId) {
            $report = red_addon_registry_package_report(
                $connection,
                $catalog['packages'][$packageId]
            );
            if (($report['status'] ?? '') !== 'enabled_current'
                || !empty($report['errors'])
            ) {
                throw new RuntimeException(
                    'Enabled add-on registry evidence is not current: ' .
                    $packageId
                );
            }
        }

        $registries = [];
        foreach ($order as $packageId) {
            $registries[$packageId] = red_addon_runtime_register_package(
                $catalog['packages'][$packageId]
            );
        }
        $context = new RED_Addon_Runtime_Context($order, $registries);
        return [
            'order' => $order,
            'packages' => $registries,
            'context' => $context,
        ];
    }
}

if (!function_exists('red_addon_runtime_set_request_context')) {
    function red_addon_runtime_set_request_context(
        RED_Addon_Runtime_Context $context
    ) {
        $key = 'RED_ADDON_RUNTIME_CONTEXT';
        $existing = $GLOBALS[$key] ?? null;
        if ($existing instanceof RED_Addon_Runtime_Context) {
            if ($existing->snapshot() !== $context->snapshot()) {
                throw new LogicException(
                    'Add-on runtime request context is already initialized.'
                );
            }
            return $existing;
        }
        $GLOBALS[$key] = $context;
        return $context;
    }
}

if (!function_exists('red_addon_runtime_request_bootstrap')) {
    function red_addon_runtime_request_bootstrap($connection, $projectRoot)
    {
        $existing = $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] ?? null;
        if ($existing instanceof RED_Addon_Runtime_Context) {
            return $existing;
        }
        $projectRoot = red_addon_project_root($projectRoot);
        $addonRoot = red_addon_root($projectRoot);
        if (!red_addon_registry_storage_available($connection)
            && !file_exists($addonRoot)
        ) {
            return red_addon_runtime_set_request_context(
                new RED_Addon_Runtime_Context([], [])
            );
        }
        $runtime = red_addon_runtime_bootstrap($connection, $projectRoot);
        return red_addon_runtime_set_request_context($runtime['context']);
    }
}

if (!function_exists('red_addon_runtime_current_context')) {
    function red_addon_runtime_current_context()
    {
        $context = $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] ?? null;
        return $context instanceof RED_Addon_Runtime_Context
            ? $context
            : null;
    }
}

if (!function_exists('red_addon_runtime_handler')) {
    function red_addon_runtime_handler($type, $id)
    {
        $context = red_addon_runtime_current_context();
        return $context instanceof RED_Addon_Runtime_Context
            ? $context->handler((string) $type, (string) $id)
            : null;
    }
}

if (!function_exists('red_addon_runtime_owner')) {
    function red_addon_runtime_owner($type, $id)
    {
        $context = red_addon_runtime_current_context();
        return $context instanceof RED_Addon_Runtime_Context
            ? $context->owner((string) $type, (string) $id)
            : null;
    }
}

if (!function_exists('red_addon_runtime_manifest')) {
    function red_addon_runtime_manifest($packageId)
    {
        $context = red_addon_runtime_current_context();
        return $context instanceof RED_Addon_Runtime_Context
            ? $context->manifest((string) $packageId)
            : null;
    }
}

if (!function_exists('red_addon_runtime_metadata')) {
    function red_addon_runtime_metadata($type, $id)
    {
        $context = red_addon_runtime_current_context();
        return $context instanceof RED_Addon_Runtime_Context
            ? $context->metadata((string) $type, (string) $id)
            : [];
    }
}

?>
