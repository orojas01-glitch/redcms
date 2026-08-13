<?php
/**
 * Exact runtime-registration evidence for the bounded operational profile.
 *
 * The expected shape is derived only from the trusted manifest. The validator
 * consumes a request-local registry only after the existing integrity-checked
 * registrar executor has completed; it never invokes a handler.
 */

require_once __DIR__ . '/addon_operational_enablement_preflight_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';

if (!function_exists('red_addon_operational_expected_registrations')) {
    function red_addon_operational_expected_registrations(array $manifest)
    {
        $contract = red_addon_operational_contract($manifest);
        if (empty($contract['valid'])) {
            return null;
        }
        $expected = [];
        foreach (
            [
                'components',
                'services',
                'adminTools',
                'adapters',
                'adminToolActions',
                'adminToolActionStateLoaders',
                'adminToolFormValueLoaders',
                'adminToolFormTargetLoaders',
                'adminToolFormWriters',
                'adminToolFormInitialValueLoaders',
                'adminToolFormCreators',
                'publicMutationHandlers',
                'publicMutationStateLoaders',
                'componentDataLoaders',
                'componentDataCreators',
                'componentDataWriters',
                'componentDataDeleters',
                'routes',
            ] as $type
        ) {
            $expected[$type] = [];
        }

        foreach (['components', 'services', 'adminTools'] as $type) {
            $expected[$type] = array_values($manifest['provides'][$type]);
        }
        $componentIds = $expected['components'];
        foreach (
            [
                'componentDataLoaders',
                'componentDataCreators',
                'componentDataWriters',
                'componentDataDeleters',
            ] as $type
        ) {
            $expected[$type] = $componentIds;
        }
        foreach ($manifest['adminToolFormContracts'] as $form) {
            $formId = (string) $form['form'];
            foreach (
                [
                    'adminToolFormValueLoaders',
                    'adminToolFormTargetLoaders',
                    'adminToolFormWriters',
                ] as $type
            ) {
                $expected[$type][] = $formId;
            }
            if (isset($form['create']) && is_array($form['create'])) {
                $expected['adminToolFormInitialValueLoaders'][] = $formId;
                $expected['adminToolFormCreators'][] = $formId;
            }
        }
        foreach ($manifest['publicMutationContracts'] as $mutation) {
            $mutationId = (string) $mutation['mutation'];
            $expected['publicMutationHandlers'][] = $mutationId;
            $expected['publicMutationStateLoaders'][] = $mutationId;
        }
        foreach ($manifest['routes'] as $route) {
            $expected['routes'][] = (string) $route['id'];
        }
        foreach ($expected as &$ids) {
            $ids = array_values(array_unique($ids));
            sort($ids, SORT_STRING);
        }
        unset($ids);
        return $expected;
    }
}

if (!function_exists('red_addon_operational_registration_fingerprint')) {
    function red_addon_operational_registration_fingerprint(
        array $manifest,
        array $registrations,
        array $transactionTables
    ) {
        $contract = red_addon_operational_contract($manifest);
        if (empty($contract['valid'])) {
            return '';
        }
        ksort($transactionTables, SORT_STRING);
        $encoded = json_encode(
            [
                'profileId' => 'operational_content_package',
                'contractSha256' => $contract['contractSha256'],
                'registrations' => $registrations,
                'transactionTables' => $transactionTables,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_operational_registrar_evidence')) {
    function red_addon_operational_registrar_evidence(
        $connection,
        $registry,
        array $manifest
    ) {
        $result = [
            'valid' => false,
            'profileId' => 'operational_content_package',
            'registrationCount' => 0,
            'transactionBindingCount' => 0,
            'registrationSha256' => '',
            'errors' => [],
        ];
        $expected = red_addon_operational_expected_registrations($manifest);
        if (!$connection instanceof mysqli
            || !$registry instanceof RED_Addon_Runtime_Registry
            || !is_array($expected)
            || $registry->manifest() !== $manifest
        ) {
            $result['errors'][] = 'operational_registrar_invalid';
            return $result;
        }
        $snapshot = $registry->snapshot();
        $registrations = is_array($snapshot['registrations'] ?? null)
            ? $snapshot['registrations']
            : [];
        if (array_keys($registrations) !== array_keys($expected)
            || $registrations !== $expected
        ) {
            $result['errors'][] = 'operational_registration_shape_invalid';
            return $result;
        }

        $mutationTables = [];
        foreach ($manifest['publicMutationContracts'] as $mutation) {
            $mutationId = (string) $mutation['mutation'];
            $tables = array_values($mutation['tables']);
            sort($tables, SORT_STRING);
            $mutationTables[$mutationId] = $tables;
        }
        ksort($mutationTables, SORT_STRING);
        $transactionTables = [];
        foreach ($expected['publicMutationHandlers'] as $mutationId) {
            $metadata = $registry->metadata(
                'publicMutationHandlers',
                $mutationId
            );
            $tables = is_array($metadata['tables'] ?? null)
                ? array_values($metadata['tables'])
                : [];
            sort($tables, SORT_STRING);
            if ($tables !== $mutationTables[$mutationId]) {
                $result['errors'][] =
                    'public_mutation_transaction_binding_invalid';
                return $result;
            }
            $transactionTables['publicMutationHandlers:' . $mutationId] =
                $tables;
        }
        foreach (
            [
                'componentDataCreators',
                'componentDataWriters',
                'componentDataDeleters',
                'adminToolFormCreators',
                'adminToolFormWriters',
            ] as $type
        ) {
            foreach ($expected[$type] as $id) {
                $metadata = $registry->metadata($type, $id);
                $tables = is_array($metadata['tables'] ?? null)
                    ? array_values($metadata['tables'])
                    : [];
                sort($tables, SORT_STRING);
                if ($tables === []) {
                    $result['errors'][] =
                        'operational_transaction_binding_missing';
                    return $result;
                }
                $transactionTables[$type . ':' . $id] = $tables;
            }
        }
        $allTables = [];
        foreach ($transactionTables as $tables) {
            foreach ($tables as $table) {
                $allTables[$table] = true;
            }
        }
        if ($allTables === [] || count($allTables) > 64) {
            $result['errors'][] = 'operational_transaction_table_set_invalid';
            return $result;
        }
        foreach (array_keys($allTables) as $table) {
            try {
                $statement = mysqli_prepare(
                    $connection,
                    'SELECT ENGINE
                     FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND BINARY TABLE_NAME=BINARY ?
                     LIMIT 1'
                );
                if (!$statement) {
                    $result['errors'][] =
                        'operational_transaction_storage_unavailable';
                    return $result;
                }
                $engine = '';
                mysqli_stmt_bind_param($statement, 's', $table);
                mysqli_stmt_bind_result($statement, $engine);
                $executed = mysqli_stmt_execute($statement);
                $found = $executed && mysqli_stmt_fetch($statement) === true;
                mysqli_stmt_close($statement);
            } catch (Throwable $throwable) {
                $result['errors'][] =
                    'operational_transaction_storage_unavailable';
                return $result;
            }
            if (!$found || $engine !== 'InnoDB') {
                $result['errors'][] =
                    'operational_transaction_table_not_innodb';
                return $result;
            }
        }
        ksort($transactionTables, SORT_STRING);
        foreach ($registrations as $ids) {
            $result['registrationCount'] += count($ids);
        }
        $result['transactionBindingCount'] = count($transactionTables);
        $result['registrationSha256'] =
            red_addon_operational_registration_fingerprint(
                $manifest,
                $registrations,
                $transactionTables
            );
        if (!red_addon_valid_sha256($result['registrationSha256'])) {
            $result['errors'][] = 'operational_registration_encoding_failed';
            $result['registrationSha256'] = '';
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

?>
