<?php
/**
 * Import the deterministic Adriana Granobles 28-route package into a disposable clone.
 *
 * This command is intentionally destructive inside its guarded target database. It
 * preserves only admin/administracion content, replaces the public content model,
 * and cannot run when its target is the declared primary database.
 *
 * Required environment:
 *   RED_DB_NAME          Target disposable database.
 *   RED_PRIMARY_DB_NAME  Primary database name used only as a deny-list guard.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (PHP_INT_SIZE < 8) {
    fwrite(STDERR, "This migration requires a 64-bit PHP integer build.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/theme_activation_helpers.php';
require_once $projectRoot . '/includes/admin_article_helpers.php';
require_once $projectRoot . '/includes/admin_form_helpers.php';

const RED_ADRIANA_MIGRATION_ID = 'adriana-granobles-v4';
const RED_ADRIANA_MANIFEST_SHA256 = '018fb1a336a7635c85fe883ec94feaad5ff447153d819d4497bd30b9c498937c';
const RED_ADRIANA_EDITOR = 'adriana28';
const RED_ADRIANA_ARTICLE_BASE_ID = 3400000000;
const RED_ADRIANA_CONTACT_ARTICLE_ID = 3400000100;
const RED_ADRIANA_CONTACT_FORM_ID = 3400000200;
const RED_ADRIANA_HOME_SECTION_ID = 3400000300;
const RED_ADRIANA_MENU_BASE_ID = 1800000000;
const RED_ADRIANA_CONTACT_TEMPLATE_SETTING = 'System_Adriana_28_Contact_Template';
const RED_ADRIANA_CONTACT_TEMPLATE_SCHEMA_VERSION = 1;

function red_adriana_migration_output(array $payload, $stream = null)
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

function red_adriana_migration_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException((string) $message);
    }
}

function red_adriana_migration_database_guard()
{
    $target = trim((string) getenv('RED_DB_NAME'));
    $primary = trim((string) getenv('RED_PRIMARY_DB_NAME'));

    red_adriana_migration_assert(
        $target !== '' && $primary !== '',
        'RED_DB_NAME and RED_PRIMARY_DB_NAME are required.'
    );
    red_adriana_migration_assert(
        preg_match('/\A[A-Za-z0-9_]+\z/', $target) === 1
            && preg_match('/\A[A-Za-z0-9_]+\z/', $primary) === 1,
        'Database guard names contain unsupported characters.'
    );
    red_adriana_migration_assert(
        strpos($target, 'redcms_adriana_28_') === 0,
        'Target database is not an Adriana 28-route disposable clone.'
    );
    red_adriana_migration_assert(
        !hash_equals($primary, $target),
        'Target database must not equal the primary database.'
    );

    return [$target, $primary];
}

function red_adriana_migration_bind($statement, $types, array &$values)
{
    if ($types === '') {
        return true;
    }

    $references = [];
    foreach ($values as $index => &$value) {
        $references[$index] = &$value;
    }

    return mysqli_stmt_bind_param($statement, $types, ...$references);
}

function red_adriana_migration_statement($connection, $sql, $types = '', array $values = [])
{
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare migration statement: ' . mysqli_error($connection));
    }
    if (!red_adriana_migration_bind($statement, $types, $values)) {
        mysqli_stmt_close($statement);
        throw new RuntimeException('Could not bind migration statement.');
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Could not execute migration statement: ' . $error);
    }

    return $statement;
}

function red_adriana_migration_execute($connection, $sql, $types = '', array $values = [])
{
    $statement = red_adriana_migration_statement($connection, $sql, $types, $values);
    $affectedRows = mysqli_stmt_affected_rows($statement);
    mysqli_stmt_close($statement);
    return $affectedRows;
}

function red_adriana_migration_fetch_all($connection, $sql, $types = '', array $values = [])
{
    $statement = red_adriana_migration_statement($connection, $sql, $types, $values);
    $result = mysqli_stmt_get_result($statement);
    if (!$result) {
        mysqli_stmt_close($statement);
        throw new RuntimeException('Could not read migration result set.');
    }
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);
    return $rows;
}

function red_adriana_migration_fetch_one($connection, $sql, $types = '', array $values = [])
{
    $rows = red_adriana_migration_fetch_all($connection, $sql, $types, $values);
    return $rows[0] ?? null;
}

function red_adriana_migration_scalar($connection, $sql, $types = '', array $values = [])
{
    $row = red_adriana_migration_fetch_one($connection, $sql, $types, $values);
    if (!is_array($row) || $row === []) {
        return null;
    }
    return reset($row);
}

function red_adriana_migration_fingerprint(array $rows)
{
    return hash(
        'sha256',
        json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

function red_adriana_migration_article_select_list()
{
    return implode(', ', array_map(static function ($column) {
        return '`' . $column . '`';
    }, array_keys(red_admin_article_columns())));
}

function red_adriana_migration_normalize_article_row(array $row)
{
    $normalized = [];
    $integerColumns = red_admin_article_integer_columns();
    foreach (array_keys(red_admin_article_columns()) as $column) {
        red_adriana_migration_assert(
            array_key_exists($column, $row),
            'Managed article contract is missing the ' . $column . ' column.'
        );
        $normalized[$column] = isset($integerColumns[$column])
            ? (int) $row[$column]
            : (string) $row[$column];
    }

    return $normalized;
}

function red_adriana_migration_expected_article_row($recordId, array $data)
{
    $data['RecordID'] = (int) $recordId;
    return red_adriana_migration_normalize_article_row($data);
}

function red_adriana_migration_contact_template_data(array $data)
{
    $template = [];
    foreach (array_keys(red_admin_form_columns()) as $column) {
        if ($column === 'RecordID' || $column === 'RefID') {
            continue;
        }
        red_adriana_migration_assert(
            array_key_exists($column, $data) && is_string($data[$column]),
            'Contact template metadata has a missing or non-string ' . $column . ' field.'
        );
        $template[$column] = $data[$column];
    }
    red_adriana_migration_assert(
        array_keys($data) === array_keys($template),
        'Contact template metadata contains unexpected or out-of-order fields.'
    );
    red_adriana_migration_assert(
        strcasecmp($template['FormType'], 'Contact') === 0 && $template['LongDesc'] !== '',
        'Contact template metadata is incomplete.'
    );

    return $template;
}

function red_adriana_migration_contact_template_contract(array $data)
{
    $template = red_adriana_migration_contact_template_data($data);
    $metadata = [
        'schemaVersion' => RED_ADRIANA_CONTACT_TEMPLATE_SCHEMA_VERSION,
        'migrationId' => RED_ADRIANA_MIGRATION_ID,
        'template' => $template,
    ];
    $metadataJson = json_encode(
        $metadata,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    return [
        'data' => $template,
        'sha256' => red_adriana_migration_fingerprint([$template]),
        'metadataJson' => $metadataJson,
        'metadataSha256' => hash('sha256', $metadataJson),
    ];
}

function red_adriana_migration_expected_routes()
{
    return [
        '/' => 'home-editorial',
        '/clases-de-musica.html' => 'directory-hub',
        '/instrumentos.html' => 'directory-hub',
        '/canto.html' => 'directory-hub',
        '/estudio-de-grabacion.html' => 'directory-hub',
        '/testimonios.html' => 'directory-hub',
        '/escuela-canto.html' => 'service-detail',
        '/escuela-piano.html' => 'service-detail',
        '/escuela-guitarra.html' => 'service-detail',
        '/escuela-bateria.html' => 'service-detail',
        '/escuela-percusion.html' => 'service-detail',
        '/escuela-bajo.html' => 'service-detail',
        '/escuela-flauta.html' => 'service-detail',
        '/escuela-clarinete.html' => 'service-detail',
        '/escuela-teoria-musical.html' => 'service-detail',
        '/escuela-composicion-produccion.html' => 'service-detail',
        '/escuela-violin.html' => 'service-detail',
        '/coaching-ontologico.html' => 'service-detail',
        '/canto-terapeutico.html' => 'service-detail',
        '/composicion.html' => 'service-detail',
        '/produccion-musical.html' => 'service-detail',
        '/clases-de-musica-online-para-ninos.html' => 'campaign-story',
        '/programa-cuda.html' => 'campaign-story',
        '/el-cantautor.html' => 'campaign-story',
        '/bodas-y-eventos.html' => 'campaign-story',
        '/la-voz-que-sana.html' => 'campaign-story',
        '/sobre-adriana.html' => 'campaign-story',
        '/contacto.html' => 'contact-conversion',
    ];
}

function red_adriana_migration_manifest($projectRoot)
{
    $packageRoot = $projectRoot . '/content-migrations/' . RED_ADRIANA_MIGRATION_ID;
    $manifestFile = $packageRoot . '/routes.json';
    red_adriana_migration_assert(is_file($manifestFile), 'The staged routes manifest is missing.');

    $manifestJson = file_get_contents($manifestFile);
    red_adriana_migration_assert(is_string($manifestJson), 'The staged routes manifest could not be read.');
    red_adriana_migration_assert(
        hash_equals(RED_ADRIANA_MANIFEST_SHA256, hash('sha256', $manifestJson)),
        'The staged routes manifest does not match the approved package digest.'
    );
    $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
    red_adriana_migration_assert(is_array($manifest), 'The staged routes manifest is invalid.');
    red_adriana_migration_assert(($manifest['schemaVersion'] ?? null) === 1, 'Unsupported routes manifest schema.');
    red_adriana_migration_assert(
        ($manifest['migrationId'] ?? '') === RED_ADRIANA_MIGRATION_ID,
        'Unexpected routes manifest migration id.'
    );

    $routes = $manifest['routes'] ?? null;
    $media = $manifest['media'] ?? null;
    $counts = $manifest['counts'] ?? null;
    $shell = $manifest['shell'] ?? null;
    red_adriana_migration_assert(is_array($routes) && is_array($media), 'Manifest routes/media are invalid.');
    red_adriana_migration_assert(is_array($counts) && is_array($shell), 'Manifest counts/shell are invalid.');
    red_adriana_migration_assert(count($routes) === 28, 'Manifest must contain exactly 28 routes.');
    red_adriana_migration_assert(count($media) === 42, 'Manifest must contain exactly 42 media records.');
    red_adriana_migration_assert(
        (int) ($counts['routes'] ?? 0) === 28
            && (int) ($counts['nonHomeAliases'] ?? 0) === 27
            && (int) ($counts['sourceSections'] ?? 0) === 153
            && (int) ($counts['mediaFiles'] ?? 0) === 42,
        'Manifest declared counts do not match the migration contract.'
    );

    $expectedRoutes = red_adriana_migration_expected_routes();
    $actualRoutes = [];
    $seenAliases = [];
    $sectionCount = 0;
    $convertedFrames = 0;
    $replacedStaticForms = 0;
    $nativeFormAnchors = 0;

    foreach ($routes as $index => $route) {
        red_adriana_migration_assert(is_array($route), 'Manifest route is not an object.');
        $path = (string) ($route['path'] ?? '');
        $alias = (string) ($route['alias'] ?? '');
        $layout = (string) ($route['layout'] ?? '');
        $bodyHtml = (string) ($route['bodyHtml'] ?? '');
        $bodyHash = (string) ($route['bodySha256'] ?? '');
        $sourceHash = (string) ($route['sourceSha256'] ?? '');
        $sourceMarker = (string) ($route['sourceMarker'] ?? '');
        $decisions = is_array($route['decisions'] ?? null) ? $route['decisions'] : [];

        red_adriana_migration_assert(
            isset($expectedRoutes[$path]) && $expectedRoutes[$path] === $layout,
            'Manifest route/layout inventory drifted at index ' . $index . '.'
        );
        red_adriana_migration_assert(!isset($actualRoutes[$path]), 'Manifest route path is duplicated.');
        $actualRoutes[$path] = $layout;

        $expectedAlias = $path === '/'
            ? ''
            : substr(basename($path), 0, -strlen('.html'));
        red_adriana_migration_assert($alias === $expectedAlias, 'Manifest route alias does not match its path.');
        if ($alias !== '') {
            red_adriana_migration_assert(
                preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $alias) === 1,
                'Manifest route alias is not an admin-safe slug.'
            );
            red_adriana_migration_assert(!isset($seenAliases[$alias]), 'Manifest route alias is duplicated.');
            $seenAliases[$alias] = true;
        }

        $expectedSource = $path === '/' ? 'index.html' : ltrim($path, '/');
        red_adriana_migration_assert(($route['source'] ?? '') === $expectedSource, 'Manifest source filename drifted.');
        red_adriana_migration_assert(
            ($route['canonical'] ?? '') === 'https://adrianagranobles.com' . $path,
            'Manifest canonical URL drifted.'
        );
        red_adriana_migration_assert(
            preg_match('/\A[a-f0-9]{64}\z/', $sourceHash) === 1,
            'Manifest source hash is malformed.'
        );
        red_adriana_migration_assert(
            preg_match('/\A[a-f0-9]{64}\z/', $bodyHash) === 1
                && hash_equals($bodyHash, hash('sha256', $bodyHtml)),
            'Manifest body hash failed for ' . $path . '.'
        );
        red_adriana_migration_assert(
            $bodyHtml !== '' && $sourceMarker !== ''
                && strpos(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $sourceMarker) !== false,
            'Manifest source marker is absent from ' . $path . '.'
        );

        $pageMarker = $alias === '' ? 'home' : $alias;
        red_adriana_migration_assert(
            substr_count($bodyHtml, 'data-redcms-source-page="' . $pageMarker . '"') === 1,
            'Manifest page marker is invalid for ' . $path . '.'
        );
        $routeSectionCount = (int) ($route['sectionCount'] ?? 0);
        red_adriana_migration_assert(
            $routeSectionCount > 0
                && substr_count($bodyHtml, 'data-redcms-source-section=') === $routeSectionCount,
            'Manifest section inventory is invalid for ' . $path . '.'
        );
        $sectionCount += $routeSectionCount;

        red_adriana_migration_assert(
            preg_match('/<(?:script|style|noscript|iframe|form)\b/i', $bodyHtml) !== 1
                && preg_match('/\son[a-z]+\s*=/i', $bodyHtml) !== 1
                && stripos($bodyHtml, 'javascript:') === false,
            'Executable or unsafe markup remains in ' . $path . '.'
        );

        $frames = $decisions['convertedFrames'] ?? [];
        red_adriana_migration_assert(is_array($frames), 'Converted-frame ledger is invalid.');
        $convertedFrames += count($frames);
        $replacedStaticForms += (int) ($decisions['replacedStaticForms'] ?? 0);
        $nativeFormAnchors += (int) ($decisions['nativeFormAnchors'] ?? 0);

        if ($path === '/') {
            red_adriana_migration_assert(
                (int) ($decisions['replacedStaticForms'] ?? 0) === 1,
                'Homepage static-form replacement marker is missing.'
            );
        }
        if ($path === '/contacto.html') {
            red_adriana_migration_assert(
                preg_match('/<div\b[^>]*data-redcms-native-form-anchor(?:="[^"]*")?[^>]*>\s*<\/div>/i', $bodyHtml) === 1
                    && (int) ($decisions['nativeFormAnchors'] ?? 0) === 1,
                'Contact native-form anchor is invalid.'
            );
        } else {
            red_adriana_migration_assert(
                strpos($bodyHtml, 'data-redcms-native-form-anchor') === false,
                'Native-form anchor leaked outside the contact route.'
            );
        }
    }

    red_adriana_migration_assert($actualRoutes === $expectedRoutes, 'Manifest route ordering/inventory drifted.');
    red_adriana_migration_assert(count($seenAliases) === 27, 'Manifest alias count is not 27.');
    red_adriana_migration_assert($sectionCount === 153, 'Manifest section total is not 153.');
    red_adriana_migration_assert($convertedFrames === 4, 'Converted content-frame total is not 4.');
    red_adriana_migration_assert($replacedStaticForms === 1, 'Static-form replacement total is not 1.');
    red_adriana_migration_assert($nativeFormAnchors === 1, 'Native-form anchor total is not 1.');

    $footerHtml = (string) ($shell['footerHtml'] ?? '');
    $footerHash = (string) ($shell['footerSha256'] ?? '');
    red_adriana_migration_assert(
        $footerHtml !== ''
            && substr_count($footerHtml, 'data-redcms-source-footer="adriana-granobles-v4"') === 1
            && preg_match('/\A[a-f0-9]{64}\z/', $footerHash) === 1
            && hash_equals($footerHash, hash('sha256', $footerHtml)),
        'Source footer hash/marker is invalid.'
    );
    red_adriana_migration_assert(
        preg_match('/<(?:script|style|noscript|iframe|form)\b/i', $footerHtml) !== 1
            && preg_match('/\son[a-z]+\s*=/i', $footerHtml) !== 1
            && stripos($footerHtml, 'javascript:') === false,
        'Unsafe markup remains in the source footer.'
    );

    $seenTargets = [];
    $seenPublicPaths = [];
    $mediaBytes = 0;
    $mediaRoot = realpath($packageRoot . '/media');
    red_adriana_migration_assert(is_string($mediaRoot), 'Staged media directory is missing.');
    foreach ($media as $record) {
        red_adriana_migration_assert(is_array($record), 'Manifest media record is invalid.');
        $target = (string) ($record['target'] ?? '');
        $publicPath = (string) ($record['publicPath'] ?? '');
        $sha256 = (string) ($record['sha256'] ?? '');
        $bytes = (int) ($record['bytes'] ?? -1);

        red_adriana_migration_assert(
            preg_match('/\Amedia\/[A-Za-z0-9._-]+\z/', $target) === 1,
            'Manifest media target is unsafe.'
        );
        red_adriana_migration_assert(
            preg_match('#\A/images/articles/adriana-granobles-v4/[A-Za-z0-9._-]+\z#', $publicPath) === 1,
            'Manifest public media path is unsafe.'
        );
        red_adriana_migration_assert(
            !isset($seenTargets[$target]) && !isset($seenPublicPaths[$publicPath]),
            'Manifest media target/public path is duplicated.'
        );
        $seenTargets[$target] = true;
        $seenPublicPaths[$publicPath] = true;

        $file = realpath($packageRoot . '/' . $target);
        red_adriana_migration_assert(
            is_string($file) && strpos($file, $mediaRoot . DIRECTORY_SEPARATOR) === 0 && is_file($file),
            'Staged media file is missing or escaped its package.'
        );
        red_adriana_migration_assert(filesize($file) === $bytes, 'Staged media size does not match manifest.');
        red_adriana_migration_assert(
            preg_match('/\A[a-f0-9]{64}\z/', $sha256) === 1
                && hash_equals($sha256, hash_file('sha256', $file)),
            'Staged media hash does not match manifest.'
        );
        $mediaBytes += $bytes;
    }

    return [
        'manifest' => $manifest,
        'manifestFile' => $manifestFile,
        'manifestSha256' => hash('sha256', $manifestJson),
        'packageRoot' => $packageRoot,
        'summary' => [
            'routes' => 28,
            'nonHomeAliases' => 27,
            'sourceSections' => 153,
            'mediaFiles' => 42,
            'mediaBytes' => $mediaBytes,
            'convertedFrames' => 4,
            'replacedStaticForms' => 1,
            'nativeFormAnchors' => 1,
            'footerSha256' => $footerHash,
        ],
    ];
}

function red_adriana_migration_menu_rows()
{
    $tree = [
        ['label' => 'Inicio', 'link' => '/', 'children' => []],
        [
            'label' => 'Clases de Música',
            'link' => '/clases-de-musica/',
            'children' => [
                ['label' => 'Canto', 'link' => '/clases-de-musica/canto'],
                ['label' => 'Clases para niños', 'link' => '/clases-de-musica/clases-para-ninos'],
                ['label' => 'Piano', 'link' => '/clases-de-musica/piano'],
                ['label' => 'Guitarra', 'link' => '/clases-de-musica/guitarra'],
                ['label' => 'Batería', 'link' => '/clases-de-musica/bateria'],
                ['label' => 'Percusión', 'link' => '/clases-de-musica/percusion'],
                ['label' => 'Bajo', 'link' => '/clases-de-musica/bajo'],
                ['label' => 'Flauta traversa', 'link' => '/clases-de-musica/flauta-traversa'],
                ['label' => 'Clarinete', 'link' => '/clases-de-musica/clarinete'],
                ['label' => 'Teoría musical', 'link' => '/clases-de-musica/teoria-musical'],
                ['label' => 'Composición y producción musical', 'link' => '/clases-de-musica/composicion-y-produccion'],
                ['label' => 'Violín', 'link' => '/clases-de-musica/violin'],
                ['label' => 'Instrumentos', 'link' => '/clases-de-musica/instrumentos'],
                ['label' => 'Testimonios', 'link' => '/clases-de-musica/testimonios'],
            ],
        ],
        ['label' => 'CUDA', 'link' => '/programa-cuda', 'children' => []],
        ['label' => 'El Cantautor', 'link' => '/el-cantautor', 'children' => []],
        ['label' => 'Eventos', 'link' => '/bodas-y-eventos', 'children' => []],
        [
            'label' => 'Estudio de Grabación',
            'link' => '/estudio-de-grabacion/',
            'children' => [
                ['label' => 'Composición', 'link' => '/estudio-de-grabacion/composicion'],
                ['label' => 'Producción Musical', 'link' => '/estudio-de-grabacion/produccion-musical'],
            ],
        ],
        [
            'label' => 'Voz y Transformación',
            'link' => '/voz-y-transformacion/',
            'children' => [
                ['label' => 'Coaching Ontológico', 'link' => '/voz-y-transformacion/coaching-ontologico'],
                ['label' => 'Canto Terapéutico', 'link' => '/voz-y-transformacion/canto-terapeutico'],
                ['label' => 'La Voz que Sana', 'link' => '/voz-y-transformacion/la-voz-que-sana'],
            ],
        ],
        ['label' => 'Sobre Adriana', 'link' => '/sobre-adriana', 'children' => []],
        ['label' => 'Contacto', 'link' => '/contacto', 'children' => []],
    ];

    $rows = [];
    $nextId = RED_ADRIANA_MENU_BASE_ID;
    foreach ($tree as $rootIndex => $root) {
        $rootId = $nextId++;
        $rows[] = [
            'RecordID' => $rootId,
            'RootOrder' => '1',
            'Title' => 'Top Navigation',
            'Label' => $root['label'],
            'Parent' => 0,
            'Link' => $root['link'],
            'NewWindow' => '',
            'MenuOrder' => $rootIndex + 1,
            'Active' => 'Y',
            'Language' => 'sp',
        ];
        foreach ($root['children'] as $childIndex => $child) {
            $rows[] = [
                'RecordID' => $nextId++,
                'RootOrder' => '2',
                'Title' => 'Top Navigation',
                'Label' => $child['label'],
                'Parent' => $rootId,
                'Link' => $child['link'],
                'NewWindow' => '',
                'MenuOrder' => $childIndex + 1,
                'Active' => 'Y',
                'Language' => 'sp',
            ];
        }
    }

    red_adriana_migration_assert(count($rows) === 28, 'Canonical navigation inventory must contain 28 rows.');
    return $rows;
}

function red_adriana_migration_admin_fingerprints($connection)
{
    $articles = red_adriana_migration_fetch_all(
        $connection,
        "SELECT * FROM RED_Articles WHERE LOWER(Sections) IN ('admin','administracion') ORDER BY RecordID"
    );
    $sections = red_adriana_migration_fetch_all(
        $connection,
        "SELECT * FROM RED_Sections WHERE LOWER(Sections) IN ('admin','administracion') ORDER BY RecordID"
    );

    return [
        'articleCount' => count($articles),
        'articleSha256' => red_adriana_migration_fingerprint($articles),
        'sectionCount' => count($sections),
        'sectionSha256' => red_adriana_migration_fingerprint($sections),
    ];
}

function red_adriana_migration_contact_template($connection, $forUpdate = false)
{
    $sql = "SELECT f.* FROM RED_C_Form AS f\n" .
        "INNER JOIN RED_Articles AS a ON CAST(f.RefID AS UNSIGNED)=a.RecordID\n" .
        "WHERE LOWER(f.FormType)='contact'\n" .
        "ORDER BY CASE WHEN LOWER(a.Sections)='contacto' THEN 0 ELSE 1 END, f.RecordID\n" .
        'LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $row = red_adriana_migration_fetch_one($connection, $sql);
    red_adriana_migration_assert(is_array($row), 'No existing Contact form template is available to clone.');

    $template = [];
    foreach (array_keys(red_admin_form_columns()) as $column) {
        if ($column === 'RecordID' || $column === 'RefID') {
            continue;
        }
        $template[$column] = (string) ($row[$column] ?? '');
    }
    return red_adriana_migration_contact_template_contract($template);
}

function red_adriana_migration_contact_template_metadata($connection, $forUpdate = false)
{
    $rows = red_adriana_migration_fetch_all(
        $connection,
        "SELECT RecordID, Content FROM RED_Advanced WHERE Item=? AND Language='' ORDER BY RecordID" .
            ($forUpdate ? ' FOR UPDATE' : ''),
        's',
        [RED_ADRIANA_CONTACT_TEMPLATE_SETTING]
    );
    red_adriana_migration_assert(
        count($rows) <= 1,
        'Canonical Contact template metadata is duplicated.'
    );
    if ($rows === []) {
        return null;
    }

    $content = (string) ($rows[0]['Content'] ?? '');
    try {
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        throw new RuntimeException('Canonical Contact template metadata is not valid JSON.');
    }
    red_adriana_migration_assert(
        is_array($decoded)
            && array_keys($decoded) === ['schemaVersion', 'migrationId', 'template']
            && ($decoded['schemaVersion'] ?? null) === RED_ADRIANA_CONTACT_TEMPLATE_SCHEMA_VERSION
            && ($decoded['migrationId'] ?? '') === RED_ADRIANA_MIGRATION_ID
            && is_array($decoded['template'] ?? null),
        'Canonical Contact template metadata has an invalid envelope.'
    );

    $contract = red_adriana_migration_contact_template_contract($decoded['template']);
    red_adriana_migration_assert(
        hash_equals($contract['metadataJson'], $content),
        'Canonical Contact template metadata is not in its exact canonical form.'
    );
    $contract['metadataRecordId'] = (int) $rows[0]['RecordID'];
    return $contract;
}

function red_adriana_migration_store_contact_template_metadata($connection, array $contract)
{
    red_adriana_migration_assert(
        red_adriana_migration_contact_template_metadata($connection, true) === null,
        'Canonical Contact template metadata already exists unexpectedly.'
    );
    red_adriana_migration_assert(
        isset($contract['metadataJson'], $contract['metadataSha256'], $contract['sha256'], $contract['data'])
            && hash_equals((string) $contract['metadataSha256'], hash('sha256', (string) $contract['metadataJson'])),
        'Canonical Contact template contract is invalid before persistence.'
    );
    $inserted = red_adriana_migration_execute(
        $connection,
        "INSERT INTO RED_Advanced (Item, Content, Language) VALUES (?, ?, '')",
        'ss',
        [RED_ADRIANA_CONTACT_TEMPLATE_SETTING, (string) $contract['metadataJson']]
    );
    red_adriana_migration_assert($inserted === 1, 'Could not persist canonical Contact template metadata.');

    $stored = red_adriana_migration_contact_template_metadata($connection, true);
    red_adriana_migration_assert(
        is_array($stored)
            && hash_equals((string) $contract['sha256'], (string) $stored['sha256'])
            && hash_equals((string) $contract['metadataSha256'], (string) $stored['metadataSha256'])
            && $contract['data'] === $stored['data'],
        'Persisted canonical Contact template metadata failed verification.'
    );
    return $stored;
}

function red_adriana_migration_upsert_setting($connection, $item, $content)
{
    $rows = red_adriana_migration_fetch_all(
        $connection,
        "SELECT RecordID FROM RED_Advanced WHERE Item=? AND Language='sp' ORDER BY RecordID FOR UPDATE",
        's',
        [$item]
    );
    red_adriana_migration_assert(count($rows) <= 1, 'Advanced setting is duplicated: ' . $item . '.');

    if ($rows !== []) {
        red_adriana_migration_execute(
            $connection,
            "UPDATE RED_Advanced SET Content=? WHERE RecordID=? AND Item=? AND Language='sp'",
            'sis',
            [$content, (int) $rows[0]['RecordID'], $item]
        );
        return;
    }

    red_adriana_migration_execute(
        $connection,
        "INSERT INTO RED_Advanced (Item, Content, Language) VALUES (?, ?, 'sp')",
        'ss',
        [$item, $content]
    );
}

function red_adriana_migration_upsert_home_section($connection, array $homeRoute)
{
    $rows = red_adriana_migration_fetch_all(
        $connection,
        "SELECT RecordID FROM RED_Sections WHERE LOWER(Sections)='home' AND Language='sp' ORDER BY RecordID FOR UPDATE"
    );
    red_adriana_migration_assert(count($rows) <= 1, 'Spanish home section is duplicated.');

    $values = [
        'Home',
        'home-editorial',
        '100',
        'Public',
        '',
        'Y',
        (string) $homeRoute['description'],
        red_admin_tag_list((string) $homeRoute['title']),
    ];

    if ($rows !== []) {
        $values[] = (int) $rows[0]['RecordID'];
        red_adriana_migration_execute(
            $connection,
            "UPDATE RED_Sections SET Title=?, Layout=?, QueryLimit=?, AccessLevel=?, Features=?, Active=?, Description=?, Tags=?\n" .
                "WHERE RecordID=? AND LOWER(Sections)='home' AND Language='sp'",
            'ssssssssi',
            $values
        );
        return (int) $rows[0]['RecordID'];
    }

    $collision = (int) red_adriana_migration_scalar(
        $connection,
        'SELECT COUNT(*) FROM RED_Sections WHERE RecordID=?',
        'i',
        [RED_ADRIANA_HOME_SECTION_ID]
    );
    red_adriana_migration_assert($collision === 0, 'Reserved home-section RecordID is already occupied.');
    red_adriana_migration_execute(
        $connection,
        "INSERT INTO RED_Sections\n" .
            '(RecordID, Sections, Title, Layout, QueryLimit, AccessLevel, Features, Active, Description, Tags, Language) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'issssssssss',
        array_merge(
            [RED_ADRIANA_HOME_SECTION_ID, 'home'],
            $values,
            ['sp']
        )
    );
    return RED_ADRIANA_HOME_SECTION_ID;
}

function red_adriana_migration_insert_menu($connection, array $rows)
{
    foreach ($rows as $row) {
        red_adriana_migration_execute(
            $connection,
            "INSERT INTO RED_Menu\n" .
                '(RecordID, RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, Active, Language) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'isssississ',
            [
                (int) $row['RecordID'],
                (string) $row['RootOrder'],
                (string) $row['Title'],
                (string) $row['Label'],
                (int) $row['Parent'],
                (string) $row['Link'],
                (string) $row['NewWindow'],
                (int) $row['MenuOrder'],
                (string) $row['Active'],
                (string) $row['Language'],
            ]
        );
    }
}

function red_adriana_migration_owner_data(array $route, $isHome)
{
    $bodyHtml = (string) $route['bodyHtml'];
    return [
        'Title' => (string) $route['title'],
        'Component' => 'Other',
        'Alias' => (string) $route['alias'],
        'Sections' => 'Home',
        'HomePosition' => $isHome ? 1 : 0,
        'HomePositionOrder' => $isHome ? 1 : 0,
        'SectionPosition' => 0,
        'SectionPositionOrder' => 0,
        'Categories' => '',
        'CategoryPosition' => 0,
        'CategoryPositionOrder' => 0,
        'SubCategories' => '',
        'SubCategoryPosition' => 0,
        'SubCategoryPositionOrder' => 0,
        'Layout' => (string) $route['layout'],
        'Article' => '',
        'PagePosition' => 1,
        'PagePositionOrder' => 1,
        'Tags' => red_admin_tag_list((string) $route['title']),
        'Active' => 'Y',
        'HomeFeature' => '',
        'HomeFeatures' => '',
        'HomeFeatures_Order' => 0,
        'SectionFeatures' => '',
        'SectionFeatures_Order' => 0,
        'CategoryFeatures' => '',
        'CategoryFeatures_Order' => 0,
        'SubCategoryFeatures' => '',
        'SubCategoryFeatures_Order' => 0,
        'ArticleFeatures' => '',
        'StartDate' => '1970-01-01 00:00:00',
        'EventDate' => '1970-01-01 00:00:00',
        'ExpDate' => '9999-12-31 23:59:59',
        'ShortDesc' => $isHome ? $bodyHtml : (string) $route['description'],
        'LongDesc' => $bodyHtml,
        'SliderDesc' => '',
        'Link' => '',
        'NewWindow' => '',
        'VideoSrc' => '',
        'AlbumSrc' => '',
        'BigPict' => '',
        'SmallPict' => '',
        'SmallPictAlign' => '',
        'SmallPict2' => '',
        'SmallPictAlign2' => '',
        'EditedBy' => RED_ADRIANA_EDITOR,
        'Language' => 'sp',
    ];
}

function red_adriana_migration_contact_article_data()
{
    return [
        'Title' => 'Formulario de contacto',
        'Component' => 'Form',
        'Alias' => 'contacto-form',
        'Sections' => 'Home',
        'HomePosition' => 0,
        'HomePositionOrder' => 0,
        'SectionPosition' => 0,
        'SectionPositionOrder' => 0,
        'Categories' => '',
        'CategoryPosition' => 0,
        'CategoryPositionOrder' => 0,
        'SubCategories' => '',
        'SubCategoryPosition' => 0,
        'SubCategoryPositionOrder' => 0,
        'Layout' => 'contact-conversion',
        'Article' => 'contacto',
        'PagePosition' => 2,
        'PagePositionOrder' => 1,
        'Tags' => 'contacto',
        'Active' => 'Y',
        'HomeFeature' => '',
        'HomeFeatures' => '',
        'HomeFeatures_Order' => 0,
        'SectionFeatures' => '',
        'SectionFeatures_Order' => 0,
        'CategoryFeatures' => '',
        'CategoryFeatures_Order' => 0,
        'SubCategoryFeatures' => '',
        'SubCategoryFeatures_Order' => 0,
        'ArticleFeatures' => '',
        'StartDate' => '1970-01-01 00:00:00',
        'EventDate' => '1970-01-01 00:00:00',
        'ExpDate' => '9999-12-31 23:59:59',
        'ShortDesc' => '',
        'LongDesc' => '',
        'SliderDesc' => '',
        'Link' => '',
        'NewWindow' => '',
        'VideoSrc' => '',
        'AlbumSrc' => '',
        'BigPict' => '',
        'SmallPict' => '',
        'SmallPictAlign' => '',
        'SmallPict2' => '',
        'SmallPictAlign2' => '',
        'EditedBy' => RED_ADRIANA_EDITOR,
        'Language' => 'sp',
    ];
}

function red_adriana_migration_verify_database(
    $connection,
    array $manifest,
    array $menuRows,
    array $adminBefore,
    array $contactTemplate,
    $projectRoot
) {
    $state = red_theme_activation_read_state($connection);
    red_adriana_migration_assert(
        ($state['activeThemeId'] ?? '') === 'adriana-granobles',
        'Active theme drifted during content migration.'
    );
    $compatibility = red_theme_compatibility_live_preflight(
        'adriana-granobles',
        $connection,
        $projectRoot
    );
    red_adriana_migration_assert(
        !empty($compatibility['compatible']),
        'Final migrated content is not compatible with the active Adriana theme.'
    );

    $adminAfter = red_adriana_migration_admin_fingerprints($connection);
    red_adriana_migration_assert($adminAfter === $adminBefore, 'Preserved admin content fingerprint drifted.');

    $storedContactTemplate = red_adriana_migration_contact_template_metadata($connection, false);
    red_adriana_migration_assert(
        is_array($storedContactTemplate)
            && hash_equals((string) $contactTemplate['sha256'], (string) $storedContactTemplate['sha256'])
            && hash_equals(
                (string) $contactTemplate['metadataSha256'],
                (string) $storedContactTemplate['metadataSha256']
            )
            && $contactTemplate['data'] === $storedContactTemplate['data'],
        'Canonical Contact template metadata drifted.'
    );

    $ownerRows = red_adriana_migration_fetch_all(
        $connection,
        "SELECT RecordID FROM RED_Articles WHERE EditedBy=? AND Component='Other' ORDER BY RecordID",
        's',
        [RED_ADRIANA_EDITOR]
    );
    $allManagedCount = (int) red_adriana_migration_scalar(
        $connection,
        'SELECT COUNT(*) FROM RED_Articles WHERE EditedBy=?',
        's',
        [RED_ADRIANA_EDITOR]
    );
    $publicArticleCount = (int) red_adriana_migration_scalar(
        $connection,
        "SELECT COUNT(*) FROM RED_Articles WHERE LOWER(Sections) NOT IN ('admin','administracion')"
    );
    red_adriana_migration_assert(count($ownerRows) === 28, 'Imported Other owner count is not 28.');
    red_adriana_migration_assert($allManagedCount === 29, 'Imported managed article count is not 29.');
    red_adriana_migration_assert($publicArticleCount === 29, 'Unexpected legacy public articles remain.');

    $routeHashes = [];
    foreach ($manifest['routes'] as $index => $route) {
        $expectedId = RED_ADRIANA_ARTICLE_BASE_ID + $index;
        $row = red_adriana_migration_fetch_one(
            $connection,
            'SELECT ' . red_adriana_migration_article_select_list() .
                ' FROM RED_Articles WHERE RecordID=? LIMIT 1',
            'i',
            [$expectedId]
        );
        red_adriana_migration_assert(is_array($row), 'Imported route owner is missing: ' . $route['path'] . '.');
        $isHome = $route['path'] === '/';
        $normalizedRow = red_adriana_migration_normalize_article_row($row);
        $expectedRow = red_adriana_migration_expected_article_row(
            $expectedId,
            red_adriana_migration_owner_data($route, $isHome)
        );
        red_adriana_migration_assert(
            $normalizedRow === $expectedRow,
            'Imported route owner contract drifted: ' . $route['path'] . '.'
        );
        $storedBody = $isHome ? $normalizedRow['ShortDesc'] : $normalizedRow['LongDesc'];
        red_adriana_migration_assert(
            hash_equals((string) $route['bodySha256'], hash('sha256', $storedBody)),
            'Stored route body hash drifted: ' . $route['path'] . '.'
        );
        if ($isHome) {
            red_adriana_migration_assert(
                hash_equals((string) $route['bodySha256'], hash('sha256', $normalizedRow['LongDesc'])),
                'Stored homepage LongDesc mirror drifted.'
            );
        } else {
            red_adriana_migration_assert(
                $normalizedRow['ShortDesc'] === $route['description'],
                'Stored route description drifted: ' . $route['path'] . '.'
            );
        }
        $routeHashes[$route['path']] = hash('sha256', $storedBody);
    }

    $nonHomeOwnerAliases = (int) red_adriana_migration_scalar(
        $connection,
        "SELECT COUNT(*) FROM RED_Articles WHERE EditedBy=? AND Component='Other' AND TRIM(Alias)<>''",
        's',
        [RED_ADRIANA_EDITOR]
    );
    red_adriana_migration_assert($nonHomeOwnerAliases === 27, 'Imported non-home alias count is not 27.');

    $contactArticle = red_adriana_migration_fetch_one(
        $connection,
        'SELECT ' . red_adriana_migration_article_select_list() .
            ' FROM RED_Articles WHERE RecordID=? LIMIT 1',
        'i',
        [RED_ADRIANA_CONTACT_ARTICLE_ID]
    );
    $normalizedContactArticle = is_array($contactArticle)
        ? red_adriana_migration_normalize_article_row($contactArticle)
        : null;
    $expectedContactArticle = red_adriana_migration_expected_article_row(
        RED_ADRIANA_CONTACT_ARTICLE_ID,
        red_adriana_migration_contact_article_data()
    );
    red_adriana_migration_assert(
        is_array($normalizedContactArticle) && $normalizedContactArticle === $expectedContactArticle,
        'Native contact Form article contract drifted.'
    );

    $contactForm = red_adriana_migration_fetch_one(
        $connection,
        'SELECT * FROM RED_C_Form WHERE RecordID=? AND RefID=? LIMIT 1',
        'is',
        [RED_ADRIANA_CONTACT_FORM_ID, (string) RED_ADRIANA_CONTACT_ARTICLE_ID]
    );
    red_adriana_migration_assert(is_array($contactForm), 'Native contact RED_C_Form row is missing.');
    $contactFormData = [];
    foreach (array_keys(red_admin_form_columns()) as $column) {
        if ($column === 'RecordID' || $column === 'RefID') {
            continue;
        }
        $contactFormData[$column] = (string) ($contactForm[$column] ?? '');
    }
    red_adriana_migration_assert(
        $contactFormData === $contactTemplate['data']
            && hash_equals($contactTemplate['sha256'], red_adriana_migration_fingerprint([$contactFormData])),
        'Cloned Contact form template drifted.'
    );

    foreach (['RED_C_Form', 'RED_C_Gallery', 'RED_C_Menu'] as $childTable) {
        $refExpression = $childTable === 'RED_C_Menu'
            ? 'dependent.RefID'
            : 'CAST(dependent.RefID AS UNSIGNED)';
        $orphanCount = (int) red_adriana_migration_scalar(
            $connection,
            "SELECT COUNT(*) FROM {$childTable} AS dependent\n" .
                "LEFT JOIN RED_Articles AS article ON article.RecordID={$refExpression}\n" .
                'WHERE article.RecordID IS NULL'
        );
        red_adriana_migration_assert($orphanCount === 0, $childTable . ' contains orphan rows after migration.');
    }

    red_adriana_migration_assert(
        (int) red_adriana_migration_scalar($connection, 'SELECT COUNT(*) FROM RED_Categories') === 0,
        'RED_Categories was not cleared.'
    );
    red_adriana_migration_assert(
        (int) red_adriana_migration_scalar($connection, 'SELECT COUNT(*) FROM RED_SubCategories') === 0,
        'RED_SubCategories was not cleared.'
    );

    $homeSection = red_adriana_migration_fetch_one(
        $connection,
        "SELECT * FROM RED_Sections WHERE LOWER(Sections)='home' AND Language='sp' LIMIT 1"
    );
    red_adriana_migration_assert(
        is_array($homeSection)
            && $homeSection['Layout'] === 'home-editorial'
            && $homeSection['Features'] === ''
            && $homeSection['Active'] === 'Y'
            && $homeSection['Description'] === $manifest['routes'][0]['description'],
        'Home section contract drifted.'
    );

    $storedMenu = red_adriana_migration_fetch_all(
        $connection,
        'SELECT RecordID, RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, Active, Language ' .
            "FROM RED_Menu WHERE Language='sp' ORDER BY RecordID"
    );
    $expectedMenu = $menuRows;
    usort($expectedMenu, static function (array $left, array $right) {
        return $left['RecordID'] <=> $right['RecordID'];
    });
    foreach ($storedMenu as &$storedMenuRow) {
        foreach (['RecordID', 'Parent', 'MenuOrder'] as $integerField) {
            $storedMenuRow[$integerField] = (int) $storedMenuRow[$integerField];
        }
    }
    unset($storedMenuRow);
    red_adriana_migration_assert($storedMenu === $expectedMenu, 'Source navigation rows drifted.');

    $expectedSettings = [
        'Website_Title' => (string) $manifest['shell']['websiteTitle'],
        'Website_Slogan' => (string) $manifest['shell']['websiteSlogan'],
        'Website_Header' => '',
        'Website_Footer' => (string) $manifest['shell']['footerHtml'],
    ];
    $settingRows = red_adriana_migration_fetch_all(
        $connection,
        "SELECT Item, Content FROM RED_Advanced\n" .
            "WHERE Language='sp' AND Item IN ('Website_Title','Website_Slogan','Website_Header','Website_Footer')\n" .
            'ORDER BY Item'
    );
    red_adriana_migration_assert(count($settingRows) === 4, 'Migrated Advanced setting count is not 4.');
    foreach ($settingRows as $settingRow) {
        $item = (string) $settingRow['Item'];
        red_adriana_migration_assert(
            array_key_exists($item, $expectedSettings) && $settingRow['Content'] === $expectedSettings[$item],
            'Migrated Advanced setting drifted: ' . $item . '.'
        );
    }

    return [
        'activeThemeId' => $state['activeThemeId'],
        'ownerArticles' => count($ownerRows),
        'nonHomeAliases' => $nonHomeOwnerAliases,
        'managedArticles' => $allManagedCount,
        'contactFormRows' => 1,
        'menuRows' => count($storedMenu),
        'homeSectionRecordId' => (int) $homeSection['RecordID'],
        'adminPreserved' => $adminAfter,
        'routeBodySha256' => $routeHashes,
        'contactTemplateSha256' => $contactTemplate['sha256'],
        'contactTemplateMetadataSha256' => $contactTemplate['metadataSha256'],
        'footerSha256' => hash('sha256', $expectedSettings['Website_Footer']),
        'themeCompatibility' => [
            'compatible' => true,
            'checks' => $compatibility['checks'] ?? [],
        ],
    ];
}

function red_adriana_migration_success_payload(
    $status,
    $targetDatabase,
    $primaryDatabase,
    array $package,
    array $contactTemplate,
    array $verification
) {
    return [
        'ok' => true,
        'operation' => 'migrate-adriana-disposable-content',
        'status' => (string) $status,
        'migrationId' => RED_ADRIANA_MIGRATION_ID,
        'targetDatabase' => (string) $targetDatabase,
        'primaryDatabaseGuard' => (string) $primaryDatabase,
        'manifestSha256' => $package['manifestSha256'],
        'package' => $package['summary'],
        'contactTemplateSource' => [
            'sha256' => (string) $contactTemplate['sha256'],
            'metadataSha256' => (string) $contactTemplate['metadataSha256'],
        ],
        'verification' => $verification,
        'idContract' => [
            'routeOwnerStart' => RED_ADRIANA_ARTICLE_BASE_ID,
            'routeOwnerEnd' => RED_ADRIANA_ARTICLE_BASE_ID + 27,
            'contactArticle' => RED_ADRIANA_CONTACT_ARTICLE_ID,
            'contactForm' => RED_ADRIANA_CONTACT_FORM_ID,
            'menuStart' => RED_ADRIANA_MENU_BASE_ID,
        ],
    ];
}

$connection = null;
try {
    [$targetDatabase, $primaryDatabase] = red_adriana_migration_database_guard();
    $package = red_adriana_migration_manifest($projectRoot);
    $manifest = $package['manifest'];
    $menuRows = red_adriana_migration_menu_rows();

    $connection = red_theme_activation_open_connection($projectRoot);
    red_adriana_migration_assert(
        $connection instanceof mysqli,
        'Could not connect to the guarded disposable database.'
    );

    $actualDatabase = (string) red_adriana_migration_scalar($connection, 'SELECT DATABASE()');
    red_adriana_migration_assert(
        $actualDatabase !== '' && hash_equals($targetDatabase, $actualDatabase),
        'Connected database does not match RED_DB_NAME.'
    );

    $themeState = red_theme_activation_read_state($connection);
    red_adriana_migration_assert(
        ($themeState['activeThemeId'] ?? '') === 'adriana-granobles',
        'Activate adriana-granobles in the disposable clone before importing content.'
    );

    $adminBefore = red_adriana_migration_admin_fingerprints($connection);
    $existingManagedCount = (int) red_adriana_migration_scalar(
        $connection,
        'SELECT COUNT(*) FROM RED_Articles WHERE EditedBy=?',
        's',
        [RED_ADRIANA_EDITOR]
    );
    $persistedContactTemplate = red_adriana_migration_contact_template_metadata($connection, false);
    red_adriana_migration_assert(
        $existingManagedCount === 0 || is_array($persistedContactTemplate),
        'Managed Adriana content exists without valid canonical Contact template metadata.'
    );
    if ($existingManagedCount === 29) {
        try {
            $existingVerification = red_adriana_migration_verify_database(
                $connection,
                $manifest,
                $menuRows,
                $adminBefore,
                $persistedContactTemplate,
                $projectRoot
            );
            red_adriana_migration_output(
                red_adriana_migration_success_payload(
                    'unchanged',
                    $targetDatabase,
                    $primaryDatabase,
                    $package,
                    $persistedContactTemplate,
                    $existingVerification
                )
            );
            return;
        } catch (Throwable $ignored) {
            // A partial or edited disposable corpus is replaced transactionally below.
        }
    }

    $transactionVerification = null;
    $transactionContactTemplate = null;
    $transactionError = '';

    $committed = red_admin_theme_contract_write_transaction(
        $connection,
        function () use (
            $connection,
            $manifest,
            $menuRows,
            $adminBefore,
            $projectRoot,
            $existingManagedCount,
            $persistedContactTemplate,
            &$transactionVerification,
            &$transactionContactTemplate,
            &$transactionError
        ) {
            try {
                $lockedContactTemplate = red_adriana_migration_contact_template_metadata($connection, true);
                if (is_array($persistedContactTemplate)) {
                    red_adriana_migration_assert(
                        is_array($lockedContactTemplate)
                            && hash_equals(
                                (string) $persistedContactTemplate['sha256'],
                                (string) $lockedContactTemplate['sha256']
                            )
                            && hash_equals(
                                (string) $persistedContactTemplate['metadataSha256'],
                                (string) $lockedContactTemplate['metadataSha256']
                            )
                            && $persistedContactTemplate['data'] === $lockedContactTemplate['data'],
                        'Canonical Contact template metadata changed before repair.'
                    );
                    $contactTemplate = $lockedContactTemplate;
                } else {
                    red_adriana_migration_assert(
                        $existingManagedCount === 0 && $lockedContactTemplate === null,
                        'Fresh-clone Contact template initialization is not safe.'
                    );
                    $sourceContactTemplate = red_adriana_migration_contact_template($connection, true);
                    $contactTemplate = red_adriana_migration_store_contact_template_metadata(
                        $connection,
                        $sourceContactTemplate
                    );
                }
                $transactionContactTemplate = $contactTemplate;

                $preservedCategoryReferences = (int) red_adriana_migration_scalar(
                    $connection,
                    "SELECT COUNT(*) FROM RED_Articles\n" .
                        "WHERE LOWER(Sections) IN ('admin','administracion')\n" .
                        "AND (TRIM(Categories)<>'' OR TRIM(SubCategories)<>'')"
                );
                red_adriana_migration_assert(
                    $preservedCategoryReferences === 0,
                    'Preserved admin content references categories/subcategories; refusing to clear them.'
                );

                foreach (['RED_C_Form', 'RED_C_Gallery'] as $childTable) {
                    red_adriana_migration_execute(
                        $connection,
                        "DELETE dependent FROM {$childTable} AS dependent\n" .
                            "LEFT JOIN RED_Articles AS article ON article.RecordID=CAST(dependent.RefID AS UNSIGNED)\n" .
                            "WHERE article.RecordID IS NULL OR LOWER(article.Sections) NOT IN ('admin','administracion')"
                    );
                }
                red_adriana_migration_execute(
                    $connection,
                    "DELETE dependent FROM RED_C_Menu AS dependent\n" .
                        "LEFT JOIN RED_Articles AS article ON article.RecordID=dependent.RefID\n" .
                        "WHERE article.RecordID IS NULL OR LOWER(article.Sections) NOT IN ('admin','administracion')"
                );
                red_adriana_migration_execute(
                    $connection,
                    "DELETE FROM RED_Articles WHERE LOWER(Sections) NOT IN ('admin','administracion')"
                );
                red_adriana_migration_execute($connection, 'DELETE FROM RED_SubCategories');
                red_adriana_migration_execute($connection, 'DELETE FROM RED_Categories');
                red_adriana_migration_execute(
                    $connection,
                    "DELETE FROM RED_Sections\n" .
                        "WHERE LOWER(Sections) NOT IN ('admin','administracion')\n" .
                        "AND NOT (LOWER(Sections)='home' AND Language='sp')"
                );
                red_adriana_migration_execute(
                    $connection,
                    "DELETE FROM RED_Menu WHERE Language='sp'"
                );

                $articleCollision = (int) red_adriana_migration_scalar(
                    $connection,
                    'SELECT COUNT(*) FROM RED_Articles WHERE RecordID BETWEEN ? AND ?',
                    'ii',
                    [RED_ADRIANA_ARTICLE_BASE_ID, RED_ADRIANA_CONTACT_ARTICLE_ID]
                );
                $formCollision = (int) red_adriana_migration_scalar(
                    $connection,
                    'SELECT COUNT(*) FROM RED_C_Form WHERE RecordID=?',
                    'i',
                    [RED_ADRIANA_CONTACT_FORM_ID]
                );
                $menuCollision = (int) red_adriana_migration_scalar(
                    $connection,
                    'SELECT COUNT(*) FROM RED_Menu WHERE RecordID BETWEEN ? AND ?',
                    'ii',
                    [RED_ADRIANA_MENU_BASE_ID, RED_ADRIANA_MENU_BASE_ID + 99]
                );
                red_adriana_migration_assert(
                    $articleCollision === 0 && $formCollision === 0 && $menuCollision === 0,
                    'A deterministic migration RecordID is occupied by preserved content.'
                );

                red_adriana_migration_upsert_home_section($connection, $manifest['routes'][0]);
                red_adriana_migration_upsert_setting(
                    $connection,
                    'Website_Title',
                    (string) $manifest['shell']['websiteTitle']
                );
                red_adriana_migration_upsert_setting(
                    $connection,
                    'Website_Slogan',
                    (string) $manifest['shell']['websiteSlogan']
                );
                red_adriana_migration_upsert_setting($connection, 'Website_Header', '');
                red_adriana_migration_upsert_setting(
                    $connection,
                    'Website_Footer',
                    (string) $manifest['shell']['footerHtml']
                );
                red_adriana_migration_insert_menu($connection, $menuRows);

                foreach ($manifest['routes'] as $index => $route) {
                    $recordId = RED_ADRIANA_ARTICLE_BASE_ID + $index;
                    $inserted = red_admin_article_insert_unlocked(
                        $connection,
                        $recordId,
                        red_adriana_migration_owner_data($route, $route['path'] === '/')
                    );
                    red_adriana_migration_assert(
                        $inserted,
                        'Could not insert route owner: ' . $route['path'] . '.'
                    );
                }

                red_adriana_migration_assert(
                    red_admin_article_insert_unlocked(
                        $connection,
                        RED_ADRIANA_CONTACT_ARTICLE_ID,
                        red_adriana_migration_contact_article_data()
                    ),
                    'Could not insert the native Contact Form article.'
                );
                red_adriana_migration_assert(
                    red_admin_form_insert(
                        $connection,
                        RED_ADRIANA_CONTACT_FORM_ID,
                        RED_ADRIANA_CONTACT_ARTICLE_ID,
                        $contactTemplate['data']
                    ),
                    'Could not clone the native RED_C_Form Contact template.'
                );

                $transactionVerification = red_adriana_migration_verify_database(
                    $connection,
                    $manifest,
                    $menuRows,
                    $adminBefore,
                    $contactTemplate,
                    $projectRoot
                );
                return true;
            } catch (Throwable $exception) {
                $transactionError = $exception->getMessage();
                throw $exception;
            }
        },
        [
            'RED_Advanced',
            'RED_Articles',
            'RED_Categories',
            'RED_C_Form',
            'RED_C_Gallery',
            'RED_C_Menu',
            'RED_Menu',
            'RED_Sections',
            'RED_SubCategories',
        ]
    );

    red_adriana_migration_assert(
        $committed && is_array($transactionVerification) && is_array($transactionContactTemplate),
        $transactionError !== ''
            ? 'Disposable migration rolled back: ' . $transactionError
            : 'Disposable migration could not acquire/commit its content-contract transaction.'
    );

    $postCommitVerification = red_adriana_migration_verify_database(
        $connection,
        $manifest,
        $menuRows,
        $adminBefore,
        $transactionContactTemplate,
        $projectRoot
    );

    red_adriana_migration_output(
        red_adriana_migration_success_payload(
            'changed',
            $targetDatabase,
            $primaryDatabase,
            $package,
            $transactionContactTemplate,
            $postCommitVerification
        )
    );
} catch (Throwable $exception) {
    red_adriana_migration_output([
        'ok' => false,
        'operation' => 'migrate-adriana-disposable-content',
        'error' => $exception->getMessage(),
    ], STDERR);
    exit(1);
} finally {
    if ($connection instanceof mysqli) {
        mysqli_close($connection);
    }
}
