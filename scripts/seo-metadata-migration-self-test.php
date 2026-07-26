<?php
/**
 * Dependency-free checks for the generic SEO migration manifest contract.
 */

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/seo_metadata_migration_helpers.php';

$assertions = 0;
$assert = static function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback, $message) use ($assert) {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
};

$manifest = [
    'schemaVersion' => 1,
    'migrationId' => 'generic-seo-fixture',
    'siteOrigin' => 'https://example.com',
    'entries' => [
        [
            'source' => 'home.html',
            'routePath' => '/',
            'owner' => [
                'type' => 'section',
                'alias' => 'home',
                'language' => 'sp',
                'recordId' => 100,
            ],
            'metadata' => [
                'SEO_Title' => 'Exact Home Title',
                'MetaDescription' => 'Exact description.',
                'CanonicalURL' => 'https://example.com/',
                'SchemaType' => 'WebPage',
            ],
            'decisions' => [
                [
                    'category' => 'derived',
                    'field' => 'XDescription',
                    'sourceValue' => '',
                    'targetValue' => 'Exact description.',
                    'reason' => 'Uses the documented Open Graph and meta fallback.',
                ],
                [
                    'category' => 'non_importable',
                    'field' => 'jsonld.about',
                    'sourceValue' => 'Visible subject',
                    'targetValue' => '',
                    'reason' => 'The typed core model does not yet expose this property.',
                ],
            ],
        ],
    ],
];

$normalized = red_seo_import_manifest(json_encode($manifest, JSON_THROW_ON_ERROR));
$assert($normalized['migrationId'] === 'generic-seo-fixture', 'migration identifier changed');
$assert(count($normalized['entries']) === 1, 'entry count changed');
$assert($normalized['entries'][0]['metadata']['SEO_Title'] === 'Exact Home Title', 'title changed');
$assert(count($normalized['entries'][0]['metadata']) === 16, 'nullable metadata fields are incomplete');
$assert(red_seo_import_values_equal(
    $normalized['entries'][0]['metadata'],
    $normalized['entries'][0]['metadata']
), 'equal metadata must compare equal');
$changed = $normalized['entries'][0]['metadata'];
$changed['SEO_Title'] = 'Different';
$assert(!red_seo_import_values_equal(
    $normalized['entries'][0]['metadata'],
    $changed
), 'different metadata must not compare equal');
$counts = red_seo_import_decision_counts($normalized['entries']);
$assert($counts === [
    'importedFields' => 4,
    'derivedValues' => 1,
    'skippedValues' => 0,
    'nonImportableValues' => 1,
], 'migration decision counts changed');

foreach ([
    'duplicate owner' => static function () use ($manifest) {
        $manifest['entries'][] = $manifest['entries'][0];
        $manifest['entries'][1]['routePath'] = '/second';
        red_seo_import_manifest(json_encode($manifest, JSON_THROW_ON_ERROR));
    },
    'duplicate route' => static function () use ($manifest) {
        $manifest['entries'][] = $manifest['entries'][0];
        $manifest['entries'][1]['owner']['alias'] = 'second';
        red_seo_import_manifest(json_encode($manifest, JSON_THROW_ON_ERROR));
    },
    'unsafe canonical' => static function () use ($manifest) {
        $manifest['entries'][0]['metadata']['CanonicalURL'] = 'javascript:alert(1)';
        red_seo_import_manifest(json_encode($manifest, JSON_THROW_ON_ERROR));
    },
    'unknown field' => static function () use ($manifest) {
        $manifest['entries'][0]['metadata']['ClientSecret'] = 'no';
        red_seo_import_manifest(json_encode($manifest, JSON_THROW_ON_ERROR));
    },
    'unsupported decision' => static function () use ($manifest) {
        $manifest['entries'][0]['decisions'][0]['category'] = 'silently-dropped';
        red_seo_import_manifest(json_encode($manifest, JSON_THROW_ON_ERROR));
    },
    'query route' => static function () use ($manifest) {
        $manifest['entries'][0]['routePath'] = '/?preview=1';
        red_seo_import_manifest(json_encode($manifest, JSON_THROW_ON_ERROR));
    },
] as $label => $callback) {
    $throws($callback, $label . ' must fail closed');
}

$cliSource = file_get_contents($repositoryRoot . '/scripts/seo-metadata-import.php');
$assert(
    is_string($cliSource)
        && strpos($cliSource, 'Refusing the configured primary database.') !== false
        && strpos($cliSource, '--confirm-database') !== false
        && strpos($cliSource, 'mysqli_begin_transaction') !== false
        && strpos($cliSource, 'mysqli_rollback') !== false,
    'CLI primary refusal, exact confirmation, and transaction guards are required'
);

echo "SEO metadata migration self-test passed: {$assertions} assertions.\n";
?>
