<?php
/**
 * Dependency-free SEO metadata contract checks.
 */

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/public_render_helpers.php';
require_once $repositoryRoot . '/includes/admin_seo_helpers.php';

$assertions = 0;
$assert = static function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "SEO metadata self-test failed: {$message}\n");
        exit(1);
    }
};

$valid = false;
$exactTitle = 'CUDA - Coro Universal del Amor | Escuela de canto online';
$assert(
    red_seo_normalize_field('SEO_Title', $exactTitle, $valid) === $exactTitle && $valid,
    'exact SEO titles must preserve capitalization and punctuation'
);

$valid = false;
$assert(
    red_seo_normalize_field('CanonicalURL', 'https://example.com/path/', $valid)
        === 'https://example.com/path/' && $valid,
    'HTTPS canonical URLs must be accepted'
);

foreach ([
    'javascript:alert(1)',
    'https://user:pass@example.com/',
    "https://example.com/\nheader",
] as $unsafeUrl) {
    $valid = true;
    red_seo_normalize_field('CanonicalURL', $unsafeUrl, $valid);
    $assert(!$valid, 'unsafe canonical URLs must be rejected');
}

foreach (['/images/social-card.png', 'https://cdn.example.com/social.jpg'] as $image) {
    $valid = false;
    $assert(
        red_seo_normalize_field('OGImage', $image, $valid) === $image && $valid,
        'safe social-image references must be accepted'
    );
}

foreach (['//other.example/image.jpg', '/../secret.png', 'images/no-root.png'] as $unsafeImage) {
    $valid = true;
    red_seo_normalize_field('OGImage', $unsafeImage, $valid);
    $assert(!$valid, 'unsafe social-image references must be rejected');
}

$payload = red_seo_collect_input([
    'SEO_Title' => $exactTitle,
    'MetaDescription' => 'A precise page description.',
    'SchemaType' => 'Course',
    'OGLocale' => 'es_CO',
    'SchemaIdentityType' => 'Person',
    'SchemaIdentityName' => 'Example Educator',
    'SchemaIdentityURL' => 'https://example.com/',
    'SchemaEducationalLevel' => 'Beginner to Intermediate',
    'SchemaCourseMode' => 'online',
    'SchemaCourseWorkload' => 'PT32H',
    'SchemaInstructorName' => 'Example Educator',
    'SchemaTeaches' => "Melodía\nArmonía\nLetra y poesía",
]);
$assert($payload['present'] && $payload['valid'], 'valid SEO input must be accepted');
$assert(red_seo_has_overrides($payload['values']), 'non-empty SEO input must activate rich metadata');
$assert(!red_seo_has_overrides(red_seo_empty_values()), 'empty SEO input must preserve legacy rendering');

$invalidPayload = red_seo_collect_input([
    'SchemaType' => 'Product',
    'OGLocale' => 'spanish',
]);
$assert(!$invalidPayload['valid'], 'unsupported schema types and locales must be rejected');
$assert(
    $invalidPayload['errors'] === ['OGLocale', 'SchemaType']
        || $invalidPayload['errors'] === ['SchemaType', 'OGLocale'],
    'invalid fields must be reported'
);

$invalidIdentity = red_seo_collect_input([
    'SchemaIdentityURL' => 'https://example.com/provider/',
]);
$assert(!$invalidIdentity['valid'], 'an identity URL without a type and name must be rejected');
$assert(
    in_array('SchemaIdentityType', $invalidIdentity['errors'], true)
        && in_array('SchemaIdentityName', $invalidIdentity['errors'], true),
    'incomplete identity errors must identify both required fields'
);

$invalidSchemaDetails = red_seo_collect_input([
    'SchemaType' => 'Course',
    'SchemaCourseWorkload' => '32 hours',
    'SchemaServiceType' => 'Mismatched service',
    'SchemaMainEntityName' => 'Mismatched main entity',
]);
$assert(!$invalidSchemaDetails['valid'], 'invalid or schema-mismatched details must be rejected');
foreach ([
    'SchemaCourseWorkload',
    'SchemaServiceType',
    'SchemaMainEntityName',
] as $invalidDetailField) {
    $assert(
        in_array($invalidDetailField, $invalidSchemaDetails['errors'], true),
        'structured-data validation must identify ' . $invalidDetailField
    );
}

$servicePayload = red_seo_collect_input([
    'SchemaType' => 'Service',
    'SchemaIdentityType' => 'Organization',
    'SchemaIdentityName' => 'Example Studio',
    'SchemaServiceType' => 'Recording service',
]);
$assert($servicePayload['valid'], 'matching Service details must be accepted');

$context = [
    'title' => $exactTitle,
    'description' => 'Safe <script>alert(1)</script> description',
    'tags' => 'music, voice',
    'canonical' => 'https://example.com/cuda/',
    'robots' => ['index' => 'index', 'follow' => 'follow'],
    'og' => [
        'locale' => 'es_CO',
        'type' => 'article',
        'title' => $exactTitle,
        'description' => 'Social description',
        'url' => 'https://example.com/cuda/',
        'image' => [
            'url' => 'https://example.com/images/cuda.jpg',
            'mime' => 'image/jpeg',
            'width' => 1200,
            'height' => 630,
        ],
        'imageAlt' => 'Singer performing',
    ],
    'x' => [
        'card' => 'summary_large_image',
        'title' => $exactTitle,
        'description' => 'Social description',
        'image' => ['url' => 'https://example.com/images/cuda.jpg'],
    ],
    'schemaType' => 'Course',
    'schemaLanguage' => 'es',
    'schema' => [
        'identityType' => 'Person',
        'identityName' => 'Example Educator',
        'identityUrl' => 'https://example.com/',
        'mainEntityName' => '',
        'educationalLevel' => 'Beginner to Intermediate',
        'courseMode' => 'online',
        'courseWorkload' => 'PT32H',
        'instructorName' => 'Example Educator',
        'teaches' => "Melodía\nArmonía\nMelodía\nLetra y poesía",
        'serviceType' => 'Must not leak from a Course',
    ],
    'websiteTitle' => 'Example',
];
$html = red_public_seo_rich_meta_html($context);
$assert(substr_count($html, '<link rel="canonical"') === 1, 'rich metadata must emit one canonical tag');
$assert(substr_count($html, 'property="og:title"') === 1, 'rich metadata must emit one Open Graph title');
$assert(substr_count($html, 'name="twitter:card"') === 1, 'rich metadata must emit one X/Twitter card');
$assert(substr_count($html, 'name="twitter:image:alt"') === 1, 'rich metadata must emit X/Twitter image alt text');
$assert(strpos($html, 'content="Safe &lt;script&gt;alert(1)&lt;/script&gt; description"') !== false, 'metadata values must be HTML escaped');
$assert(strpos($html, '"@type":"Course"') !== false, 'the selected constrained schema type must render');
$assert(strpos($html, '"inLanguage":"es"') !== false, 'schema language must render');
$assert(strpos($html, '"provider":{"@type":"Person","name":"Example Educator","url":"https://example.com/"}') !== false, 'typed provider must render');
$assert(strpos($html, '"educationalLevel":"Beginner to Intermediate"') !== false, 'typed educational level must render');
$assert(strpos($html, '"courseMode":"online"') !== false, 'typed Course instance must render');
$assert(strpos($html, '"courseWorkload":"PT32H"') !== false, 'ISO Course workload must render');
$assert(strpos($html, '"teaches":["Melodía","Armonía","Letra y poesía"]') !== false, 'Course topics must render as a deduplicated JSON-LD array');
$assert(strpos($html, '"serviceType"') === false, 'Service details must not leak into Course JSON-LD');
$assert(strpos($html, '</script> description') === false, 'JSON-LD must not contain an executable closing script sequence');

$webPageContext = $context;
$webPageContext['schemaType'] = 'WebPage';
$webPageContext['canonical'] = 'https://www.example.com/path/';
$webPageContext['schema']['mainEntityName'] = 'Elige tu camino musical';
$webPageSchema = red_public_seo_schema($webPageContext);
$assert(
    ($webPageSchema['isPartOf']['url'] ?? '') === 'https://www.example.com/',
    'WebPage isPartOf must use the canonical website origin'
);
$assert(
    ($webPageSchema['about']['name'] ?? '') === 'Example Educator',
    'WebPage about must use the constrained identity'
);
$assert(
    ($webPageSchema['mainEntity']['name'] ?? '') === 'Elige tu camino musical',
    'WebPage mainEntity must render a named Course'
);
$assert(
    !isset($webPageSchema['educationalLevel'])
        && !isset($webPageSchema['hasCourseInstance'])
        && !isset($webPageSchema['teaches']),
    'Course-only details must not leak into WebPage JSON-LD'
);

$serviceContext = $context;
$serviceContext['schemaType'] = 'Service';
$serviceContext['schema']['identityType'] = 'Organization';
$serviceContext['schema']['identityName'] = 'Example Studio';
$serviceContext['schema']['identityUrl'] = 'https://example.com/studio/';
$serviceContext['schema']['serviceType'] = 'Recording service';
$serviceSchema = red_public_seo_schema($serviceContext);
$assert(
    ($serviceSchema['provider']['@type'] ?? '') === 'Organization'
        && ($serviceSchema['serviceType'] ?? '') === 'Recording service',
    'Service provider and type must render from constrained values'
);
$assert(
    !isset($serviceSchema['educationalLevel'])
        && !isset($serviceSchema['hasCourseInstance'])
        && !isset($serviceSchema['teaches'])
        && !isset($serviceSchema['inLanguage']),
    'Course-only details and CreativeWork language must not leak into Service JSON-LD'
);

$websiteContext = $webPageContext;
$websiteContext['schemaType'] = 'WebSite';
$websiteSchema = red_public_seo_schema($websiteContext);
$assert(
    isset($websiteSchema['about']) && !isset($websiteSchema['isPartOf']),
    'WebSite may identify its subject but must not point to itself with isPartOf'
);

$adminHtml = red_admin_seo_fields_html($payload['values'], 'test-seo');
foreach ([
    'name="SEO_Title"',
    'name="MetaDescription"',
    'name="CanonicalURL"',
    'name="OGImage"',
    'name="XCard"',
    'name="SchemaType"',
    'name="SchemaIdentityType"',
    'name="SchemaIdentityName"',
    'name="SchemaIdentityURL"',
    'name="SchemaMainEntityName"',
    'name="SchemaEducationalLevel"',
    'name="SchemaCourseMode"',
    'name="SchemaCourseWorkload"',
    'name="SchemaInstructorName"',
    'name="SchemaTeaches"',
    'name="SchemaServiceType"',
] as $fieldMarker) {
    $assert(strpos($adminHtml, $fieldMarker) !== false, 'administrator SEO form field is missing: ' . $fieldMarker);
}
$assert(
    strpos($adminHtml, red_admin_area_html($exactTitle)) !== false,
    'administrator SEO fields must preserve and escape the stored title'
);

$migration = file_get_contents(
    $repositoryRoot . '/database/migrations/2026-07-25-page-seo-metadata.sql'
);
$assert(is_string($migration) && strpos($migration, 'CREATE TABLE IF NOT EXISTS `RED_Page_SEO`') !== false, 'SEO migration must create the nullable table');
$assert(stripos($migration, 'INSERT INTO `RED_Page_SEO`') === false, 'SEO migration must not seed client metadata');
$assert(strpos($migration, 'UNIQUE KEY `uniq_red_page_seo_owner`') !== false, 'SEO owners must be unique');
$schemaDetailsMigration = file_get_contents(
    $repositoryRoot . '/database/migrations/2026-07-26-seo-schema-details.sql'
);
$assert(
    is_string($schemaDetailsMigration)
        && strpos($schemaDetailsMigration, 'ADD COLUMN `SchemaIdentityType`') !== false
        && strpos($schemaDetailsMigration, 'ADD COLUMN `SchemaCourseWorkload`') !== false
        && strpos($schemaDetailsMigration, 'ADD COLUMN `SchemaServiceType`') !== false,
    'typed structured-data migration must add the constrained detail columns'
);
$assert(
    stripos((string) $schemaDetailsMigration, 'INSERT INTO `RED_Page_SEO`') === false
        && strpos((string) $schemaDetailsMigration, 'SchemaCourseCode') === false
        && strpos((string) $schemaDetailsMigration, 'AggregateRating') === false,
    'typed migration must remain nullable, client-neutral, and inside the approved field boundary'
);

$editorFiles = [
    'Article create editor' => 'admin/bin/new_article.php',
    'Article edit editor' => 'admin/bin/edit_article.php',
    'Form editor' => 'includes/admin_form_ui_helpers.php',
    'Gallery editor' => 'includes/admin_gallery_ui_helpers.php',
    'Video editor' => 'includes/admin_video_ui_helpers.php',
    'Banner editor' => 'includes/admin_banner_ui_helpers.php',
    'Other editor' => 'includes/admin_other_ui_helpers.php',
    'Section create editor' => 'admin/class/class_new_section.php',
    'Section edit editor' => 'admin/bin/edit_section.php',
    'Category create editor' => 'admin/class/class_new_category.php',
    'Category edit editor' => 'admin/bin/edit_category.php',
    'Subcategory create editor' => 'admin/class/class_new_subcategory.php',
    'Subcategory edit editor' => 'admin/bin/edit_subcategory.php',
];
foreach ($editorFiles as $label => $relativePath) {
    $source = file_get_contents($repositoryRoot . '/' . $relativePath);
    $assert(
        is_string($source) && strpos($source, 'red_admin_seo_fields_html') !== false,
        $label . ' must render the shared SEO field contract'
    );
}

foreach ([
    'admin/bin/insert_content.php',
    'admin/bin/update_content.php',
    'admin/bin/insert_form.php',
    'admin/bin/update_form.php',
    'admin/bin/insert_gallery.php',
    'admin/bin/update_gallery.php',
] as $relativePath) {
    $source = file_get_contents($repositoryRoot . '/' . $relativePath);
    $assert(
        is_string($source)
            && strpos($source, 'red_admin_seo_save') !== false
            && strpos($source, "'RED_Page_SEO'") !== false,
        $relativePath . ' must save SEO in the content transaction'
    );
}

foreach ([
    'admin/bin/insert_section.php',
    'admin/bin/insert_category.php',
    'admin/bin/insert_subcategory.php',
] as $relativePath) {
    $source = file_get_contents($repositoryRoot . '/' . $relativePath);
    $assert(
        is_string($source)
            && strpos($source, 'red_admin_seo_insert_area') !== false
            && strpos($source, '$seoInput') !== false,
        $relativePath . ' must create its area and SEO row atomically'
    );
}

foreach ([
    'admin/bin/update_section.php',
    'admin/bin/update_category.php',
    'admin/bin/update_subcategory.php',
] as $relativePath) {
    $source = file_get_contents($repositoryRoot . '/' . $relativePath);
    $assert(
        is_string($source)
            && strpos($source, 'red_admin_seo_area_save_callback') !== false
            && strpos($source, "'RED_Page_SEO'") !== false,
        $relativePath . ' must update area SEO inside the route-aware transaction'
    );
}

$assert(
    red_admin_seo_area_owner_type('RED_Sections') === 'section'
        && red_admin_seo_area_owner_type('RED_Categories') === 'category'
        && red_admin_seo_area_owner_type('RED_SubCategories') === 'subcategory',
    'area tables must map to constrained SEO owner types'
);
$assert(
    red_admin_seo_area_owner_type('RED_Articles') === '',
    'area owner mapping must reject non-area tables'
);

echo "SEO metadata self-test passed: {$assertions} assertions.\n";
?>
