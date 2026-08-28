<?php
/**
 * Build a credential-free RED-CMS + Store Lite client deployment archive.
 *
 * Dry-run is the default. Apply requires the exact printed plan SHA-256 and
 * writes only to a new absolute output path outside every source repository.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function red_client_kit_usage(): void
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/client-deployment-kit.php --output=/absolute/path.tar.gz \\\n\n" .
        "    --store-lite-repo=/path --stripe-repo=/path --wompi-repo=/path \\\n\n" .
        "    [--adapters=stripe,wompi] [--json]\n" .
        "  Repeat the command with --confirm-plan-sha256=SHA256 --apply.\n"
    );
}

function red_client_kit_run(array $command, ?string $cwd = null): array
{
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, $cwd);
    if (!is_resource($process)) {
        return ['exit' => 127, 'stdout' => '', 'stderr' => 'process_start_failed'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [
        'exit' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

function red_client_kit_realpath(string $path): string
{
    $resolved = realpath($path);
    return is_string($resolved) ? rtrim($resolved, DIRECTORY_SEPARATOR) : '';
}

function red_client_kit_path_within(string $path, string $root): bool
{
    return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
}

function red_client_kit_valid_sha256(mixed $value): bool
{
    return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
}

function red_client_kit_valid_commit(mixed $value): bool
{
    return is_string($value) && preg_match('/\A[a-f0-9]{40}\z/', $value) === 1;
}

function red_client_kit_canonicalize(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('red_client_kit_canonicalize', $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = red_client_kit_canonicalize($item);
    }
    return $value;
}

function red_client_kit_hash(array $value): string
{
    $json = json_encode(
        red_client_kit_canonicalize($value),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if (!is_string($json)) {
        throw new RuntimeException('plan_encoding_failed');
    }
    return hash('sha256', $json);
}

function red_client_kit_git(string $repository, array $arguments): array
{
    return red_client_kit_run(array_merge(['git', '-C', $repository], $arguments));
}

function red_client_kit_manifest(string $path): array
{
    $bytes = @file_get_contents($path);
    if (!is_string($bytes)) {
        throw new RuntimeException('release_manifest_unreadable');
    }
    try {
        $manifest = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('release_manifest_invalid_json');
    }
    if (!is_array($manifest)
        || ($manifest['schemaVersion'] ?? null) !== 1
        || ($manifest['id'] ?? null) !== 'redcms.store-lite-client-kit'
        || !isset($manifest['core'], $manifest['packages'])
        || !is_array($manifest['core'])
        || !is_array($manifest['packages'])
    ) {
        throw new RuntimeException('release_manifest_contract_invalid');
    }
    return $manifest;
}

function red_client_kit_package_manifest(
    string $repository,
    array $releasePackage
): array {
    $sourcePath = $releasePackage['sourcePath'] ?? '';
    if (!is_string($sourcePath)
        || preg_match('#\A[a-zA-Z0-9._/-]+\z#', $sourcePath) !== 1
        || str_contains($sourcePath, '..')
    ) {
        throw new RuntimeException('package_source_path_invalid');
    }
    $packageRoot = $repository . DIRECTORY_SEPARATOR . $sourcePath;
    $manifestPath = $packageRoot . DIRECTORY_SEPARATOR . 'addon.json';
    $bytes = @file_get_contents($manifestPath);
    if (!is_string($bytes)) {
        throw new RuntimeException('package_manifest_unreadable');
    }
    try {
        $manifest = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('package_manifest_invalid_json');
    }
    if (!is_array($manifest)
        || ($manifest['id'] ?? null) !== ($releasePackage['id'] ?? null)
        || ($manifest['version'] ?? null) !== ($releasePackage['version'] ?? null)
    ) {
        throw new RuntimeException('package_identity_mismatch');
    }
    $files = $manifest['integrity']['files'] ?? null;
    if (!is_array($files) || $files === []) {
        throw new RuntimeException('package_integrity_inventory_missing');
    }
    $seen = [];
    foreach ($files as $file) {
        $relative = is_array($file) ? ($file['path'] ?? null) : null;
        $expected = is_array($file) ? ($file['sha256'] ?? null) : null;
        if (!is_string($relative)
            || $relative === ''
            || str_starts_with($relative, '/')
            || str_contains($relative, '..')
            || str_contains($relative, '\\')
            || isset($seen[$relative])
            || !red_client_kit_valid_sha256($expected)
        ) {
            throw new RuntimeException('package_integrity_inventory_invalid');
        }
        $seen[$relative] = true;
        $absolute = $packageRoot . DIRECTORY_SEPARATOR . $relative;
        if (!is_file($absolute)
            || is_link($absolute)
            || !hash_equals($expected, hash_file('sha256', $absolute))
        ) {
            throw new RuntimeException('package_integrity_mismatch:' . $relative);
        }
    }
    return [
        'id' => $manifest['id'],
        'version' => $manifest['version'],
        'verifiedFiles' => count($files),
    ];
}

function red_client_kit_remove_tree(string $path, string $expectedParent): void
{
    $parent = dirname($path);
    if ($parent !== $expectedParent || !str_starts_with(basename($path), 'redcms-client-kit-')) {
        throw new RuntimeException('temporary_cleanup_refused');
    }
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isLink() || $item->isFile()) {
            if (!unlink($item->getPathname())) {
                throw new RuntimeException('temporary_file_cleanup_failed');
            }
        } elseif (!rmdir($item->getPathname())) {
            throw new RuntimeException('temporary_directory_cleanup_failed');
        }
    }
    if (!rmdir($path)) {
        throw new RuntimeException('temporary_root_cleanup_failed');
    }
}

function red_client_kit_stage_secret_scan(string $root): void
{
    $patterns = [
        '/\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{16,}\b/',
        '/\bwhsec_[A-Za-z0-9]{16,}\b/',
        '/\b(?:pub|prv)_(?:prod|test)_[A-Za-z0-9]{16,}\b/',
        '/\b(?:prod|test)_(?:events|integrity)_[A-Za-z0-9]{16,}\b/',
    ];
    $blockedNames = ['config.local.php', '.env', '.env.local'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item->isFile() || $item->isLink()) {
            continue;
        }
        if (in_array($item->getFilename(), $blockedNames, true)) {
            throw new RuntimeException('secret_file_in_artifact:' . $item->getFilename());
        }
        if ($item->getSize() > 4 * 1024 * 1024) {
            continue;
        }
        $bytes = file_get_contents($item->getPathname());
        if (!is_string($bytes) || str_contains($bytes, "\0")) {
            continue;
        }
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $bytes) === 1) {
                throw new RuntimeException('secret_value_pattern_in_artifact');
            }
        }
    }
}

$projectRoot = dirname(__DIR__);
$manifestPath = $projectRoot . '/release/client-deployment-kit-v1.json';
$options = [
    'output' => '',
    'adapters' => ['stripe', 'wompi'],
    'repositories' => [],
    'confirmPlanSha256' => '',
    'json' => false,
    'apply' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--json') {
        $options['json'] = true;
    } elseif ($argument === '--apply') {
        $options['apply'] = true;
    } elseif (str_starts_with($argument, '--output=')) {
        $options['output'] = substr($argument, strlen('--output='));
    } elseif (str_starts_with($argument, '--adapters=')) {
        $selected = substr($argument, strlen('--adapters='));
        $options['adapters'] = $selected === '' ? [] : explode(',', $selected);
    } elseif (str_starts_with($argument, '--store-lite-repo=')) {
        $options['repositories']['store-lite'] = substr($argument, strlen('--store-lite-repo='));
    } elseif (str_starts_with($argument, '--stripe-repo=')) {
        $options['repositories']['stripe'] = substr($argument, strlen('--stripe-repo='));
    } elseif (str_starts_with($argument, '--wompi-repo=')) {
        $options['repositories']['wompi'] = substr($argument, strlen('--wompi-repo='));
    } elseif (str_starts_with($argument, '--confirm-plan-sha256=')) {
        $options['confirmPlanSha256'] = substr($argument, strlen('--confirm-plan-sha256='));
    } else {
        red_client_kit_usage();
        exit(64);
    }
}

try {
    if ($options['output'] === '' || !str_starts_with($options['output'], DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('absolute_output_required');
    }
    if ($options['adapters'] === []
        || count($options['adapters']) !== count(array_unique($options['adapters']))
    ) {
        throw new RuntimeException('adapter_selection_invalid');
    }
    foreach ($options['adapters'] as $adapter) {
        if (!in_array($adapter, ['stripe', 'wompi'], true)) {
            throw new RuntimeException('adapter_not_release_ready:' . $adapter);
        }
    }

    $release = red_client_kit_manifest($manifestPath);
    $projectResolved = red_client_kit_realpath($projectRoot);
    $outputParent = red_client_kit_realpath(dirname($options['output']));
    if ($projectResolved === '' || $outputParent === '') {
        throw new RuntimeException('source_or_output_parent_unavailable');
    }
    $output = $outputParent . DIRECTORY_SEPARATOR . basename($options['output']);
    if (!str_ends_with($output, '.tar.gz')) {
        throw new RuntimeException('output_extension_must_be_tar_gz');
    }
    if (file_exists($output) || file_exists($output . '.sha256')) {
        throw new RuntimeException('output_already_exists');
    }

    $coreStatus = red_client_kit_git($projectResolved, ['status', '--porcelain', '--untracked-files=no']);
    $coreHead = red_client_kit_git($projectResolved, ['rev-parse', 'HEAD']);
    $minimum = (string) ($release['core']['minimumCommit'] ?? '');
    $ancestor = red_client_kit_git(
        $projectResolved,
        ['merge-base', '--is-ancestor', $minimum, 'HEAD']
    );
    if ($coreStatus['exit'] !== 0
        || trim($coreStatus['stdout']) !== ''
        || $coreHead['exit'] !== 0
        || !red_client_kit_valid_commit(trim($coreHead['stdout']))
        || !red_client_kit_valid_commit($minimum)
        || $ancestor['exit'] !== 0
    ) {
        throw new RuntimeException('core_release_source_not_clean_or_compatible');
    }
    if (red_client_kit_path_within($output, $projectResolved)) {
        throw new RuntimeException('output_must_be_outside_source_repositories');
    }
    $boundary = red_client_kit_run(
        [PHP_BINARY, $projectResolved . '/scripts/clean-starter-boundary-self-test.php'],
        $projectResolved
    );
    if ($boundary['exit'] !== 0) {
        throw new RuntimeException('clean_starter_boundary_failed');
    }

    $selectedPackages = [];
    foreach ($release['packages'] as $package) {
        if (!is_array($package)) {
            throw new RuntimeException('release_package_invalid');
        }
        $selection = $package['selection'] ?? null;
        $required = ($package['required'] ?? null) === true;
        if (!is_string($selection)
            || (!$required && !in_array($selection, $options['adapters'], true))
        ) {
            continue;
        }
        $repository = red_client_kit_realpath((string) ($options['repositories'][$selection] ?? ''));
        if ($repository === '') {
            throw new RuntimeException('repository_unavailable:' . $selection);
        }
        if (red_client_kit_path_within($output, $repository)) {
            throw new RuntimeException('output_must_be_outside_source_repositories');
        }
        $status = red_client_kit_git($repository, ['status', '--porcelain', '--untracked-files=no']);
        $head = red_client_kit_git($repository, ['rev-parse', 'HEAD']);
        $expectedCommit = $package['commit'] ?? null;
        if ($status['exit'] !== 0
            || trim($status['stdout']) !== ''
            || $head['exit'] !== 0
            || !red_client_kit_valid_commit($expectedCommit)
            || !hash_equals($expectedCommit, trim($head['stdout']))
        ) {
            throw new RuntimeException('package_release_source_mismatch:' . $selection);
        }
        $verified = red_client_kit_package_manifest($repository, $package);
        $selectedPackages[] = [
            'selection' => $selection,
            'id' => $verified['id'],
            'version' => $verified['version'],
            'commit' => $expectedCommit,
            'verifiedFiles' => $verified['verifiedFiles'],
            'releaseState' => $package['releaseState'],
            'sourcePath' => $package['sourcePath'],
            'archivePath' => $package['archivePath'],
            'repositoryPath' => $repository,
        ];
    }

    $plan = [
        'schemaVersion' => 1,
        'releaseId' => $release['id'],
        'releaseVersion' => $release['version'],
        'core' => [
            'id' => $release['core']['id'],
            'version' => $release['core']['version'],
            'commit' => trim($coreHead['stdout']),
            'archivePath' => $release['core']['archivePath'],
        ],
        'packages' => array_map(
            static fn(array $package): array => [
                'selection' => $package['selection'],
                'id' => $package['id'],
                'version' => $package['version'],
                'commit' => $package['commit'],
                'verifiedFiles' => $package['verifiedFiles'],
                'releaseState' => $package['releaseState'],
                'archivePath' => $package['archivePath'],
            ],
            $selectedPackages
        ),
        'outputFile' => basename($output),
        'containsSecrets' => false,
        'installState' => 'files_only_not_installed',
        'providerContact' => false,
        'clientStateChanged' => false,
    ];
    $planSha256 = red_client_kit_hash($plan);
    $report = [
        'valid' => true,
        'mode' => $options['apply'] ? 'apply' : 'dry-run',
        'planSha256' => $planSha256,
        'plan' => $plan,
    ];

    if (!$options['apply']) {
        if ($options['json']) {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } else {
            printf("Release: %s %s\n", $release['id'], $release['version']);
            printf("Core: %s %s at %s\n", $plan['core']['id'], $plan['core']['version'], $plan['core']['commit']);
            foreach ($plan['packages'] as $package) {
                printf(
                    "Package: %s %s at %s [%s]\n",
                    $package['id'],
                    $package['version'],
                    $package['commit'],
                    $package['releaseState']
                );
            }
            printf("Output: %s\n", $output);
            printf("Plan SHA-256: %s\n", $planSha256);
            echo "DRY RUN: no file, database, client, secret, or provider state changed.\n";
        }
        exit(0);
    }

    if (!red_client_kit_valid_sha256($options['confirmPlanSha256'])
        || !hash_equals($planSha256, $options['confirmPlanSha256'])
    ) {
        throw new RuntimeException('exact_plan_confirmation_required');
    }

    $temporaryParent = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    $temporary = $temporaryParent . DIRECTORY_SEPARATOR . 'redcms-client-kit-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700) || !mkdir($temporary . '/stage', 0700)) {
        throw new RuntimeException('temporary_stage_creation_failed');
    }
    try {
        $coreTar = $temporary . '/core.tar';
        $coreArchive = red_client_kit_git(
            $projectResolved,
            ['archive', '--format=tar', '--prefix=redcms/', '--output=' . $coreTar, 'HEAD']
        );
        if ($coreArchive['exit'] !== 0) {
            throw new RuntimeException('core_archive_failed');
        }
        $extract = red_client_kit_run(['tar', '-xf', $coreTar, '-C', $temporary . '/stage']);
        if ($extract['exit'] !== 0) {
            throw new RuntimeException('core_archive_extract_failed');
        }

        foreach ($selectedPackages as $index => $package) {
            $packageTar = $temporary . '/package-' . $index . '.tar';
            $archive = red_client_kit_git(
                $package['repositoryPath'],
                [
                    'archive',
                    '--format=tar',
                    '--prefix=' . rtrim($package['archivePath'], '/') . '/',
                    '--output=' . $packageTar,
                    'HEAD:' . $package['sourcePath'],
                ]
            );
            if ($archive['exit'] !== 0) {
                throw new RuntimeException('package_archive_failed:' . $package['selection']);
            }
            $extract = red_client_kit_run(['tar', '-xf', $packageTar, '-C', $temporary . '/stage']);
            if ($extract['exit'] !== 0) {
                throw new RuntimeException('package_archive_extract_failed:' . $package['selection']);
            }
        }

        $evidence = $plan;
        $evidence['planSha256'] = $planSha256;
        $evidence['builtAtUtc'] = gmdate('Y-m-d\TH:i:s\Z');
        $evidencePath = $temporary . '/stage/RELEASE-EVIDENCE.json';
        $evidenceBytes = json_encode(
            $evidence,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
        if (!is_string($evidenceBytes)
            || file_put_contents($evidencePath, $evidenceBytes . PHP_EOL) === false
        ) {
            throw new RuntimeException('release_evidence_write_failed');
        }

        red_client_kit_stage_secret_scan($temporary . '/stage');
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temporary . '/stage', FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile() && !$item->isLink()) {
                $relative = substr($item->getPathname(), strlen($temporary . '/stage/'));
                if ($relative !== 'SHA256SUMS') {
                    $files[$relative] = hash_file('sha256', $item->getPathname());
                }
            }
        }
        ksort($files, SORT_STRING);
        $checksumLines = [];
        foreach ($files as $relative => $sha256) {
            $checksumLines[] = $sha256 . '  ' . $relative;
        }
        if (file_put_contents(
            $temporary . '/stage/SHA256SUMS',
            implode(PHP_EOL, $checksumLines) . PHP_EOL
        ) === false) {
            throw new RuntimeException('checksum_manifest_write_failed');
        }

        $tar = red_client_kit_run(
            ['tar', '-czf', $output, '-C', $temporary . '/stage', '.']
        );
        if ($tar['exit'] !== 0 || !is_file($output)) {
            throw new RuntimeException('release_archive_write_failed');
        }
        $archiveSha256 = hash_file('sha256', $output);
        if (file_put_contents(
            $output . '.sha256',
            $archiveSha256 . '  ' . basename($output) . PHP_EOL
        ) === false) {
            throw new RuntimeException('release_archive_checksum_write_failed');
        }
    } finally {
        red_client_kit_remove_tree($temporary, $temporaryParent);
    }

    $report['archive'] = $output;
    $report['archiveSha256'] = $archiveSha256;
    if ($options['json']) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        printf("Built credential-free client kit: %s\n", $output);
        printf("Archive SHA-256: %s\n", $archiveSha256);
        echo "No database, client installation, secret, or provider state changed.\n";
    }
    exit(0);
} catch (Throwable $exception) {
    $error = $exception->getMessage();
    if ($options['json']) {
        echo json_encode(
            ['valid' => false, 'error' => $error],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
    } else {
        fwrite(STDERR, 'Client deployment kit refused: ' . $error . PHP_EOL);
    }
    exit(65);
}
