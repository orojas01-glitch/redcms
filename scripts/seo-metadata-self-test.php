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
    'websiteTitle' => 'Example',
];
$html = red_public_seo_rich_meta_html($context);
$assert(substr_count($html, '<link rel="canonical"') === 1, 'rich metadata must emit one canonical tag');
$assert(substr_count($html, 'property="og:title"') === 1, 'rich metadata must emit one Open Graph title');
$assert(substr_count($html, 'name="twitter:card"') === 1, 'rich metadata must emit one X/Twitter card');
$assert(substr_count($html, 'name="twitter:image:alt"') === 1, 'rich metadata must emit X/Twitter image alt text');
$assert(strpos($html, 'content="Safe &lt;script&gt;alert(1)&lt;/script&gt; description"') !== false, 'metadata values must be HTML escaped');
$assert(strpos($html, '"@type":"Course"') !== false, 'the selected constrained schema type must render');
$assert(strpos($html, '</script> description') === false, 'JSON-LD must not contain an executable closing script sequence');

$adminHtml = red_admin_seo_fields_html($payload['values'], 'test-seo');
foreach ([
    'name="SEO_Title"',
    'name="MetaDescription"',
    'name="CanonicalURL"',
    'name="OGImage"',
    'name="XCard"',
    'name="SchemaType"',
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
