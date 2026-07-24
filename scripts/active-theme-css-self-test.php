<?php
/** Dependency-free contracts for the active-theme Website CSS editor. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/admin_advanced_helpers.php';

$assertions = 0;
$temporaryDirectory = null;

function red_active_theme_css_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    red_active_theme_css_test_assert(
        red_admin_advanced_row_is_mutable(
            ['Item' => 'Website_Title', 'Language' => 'sp'],
            'Website_Title'
        ),
        'language-scoped Advanced rows remain mutable when the posted item matches'
    );
    red_active_theme_css_test_assert(
        !red_admin_advanced_row_is_mutable(
            ['Item' => 'Website_Title', 'Language' => 'sp'],
            'Website_Slogan'
        ),
        'generic Advanced writes reject record-id and posted-item mismatches'
    );
    red_active_theme_css_test_assert(
        !red_admin_advanced_row_is_mutable(
            ['Item' => 'System_Active_Theme', 'Language' => ''],
            'System_Active_Theme'
        ) && !red_admin_advanced_row_is_mutable(
            ['Item' => 'System_Previous_Theme', 'Language' => ''],
            'System_Previous_Theme'
        ),
        'global theme state rows are reserved from generic Advanced writes'
    );
    $uploadSource = file_get_contents($repositoryRoot . '/admin/bin/post_file.php');
    red_active_theme_css_test_assert(
        is_string($uploadSource)
            && strpos($uploadSource, 'red_admin_advanced_update_logo(') !== false,
        'logo upload delegates target validation to the reserved-row-safe Advanced helper'
    );

    $legacyValidation = red_theme_activation_validate_candidate('legacy-bootstrap', $repositoryRoot);
    $legacyTarget = red_admin_advanced_css_target_from_validation($legacyValidation, $repositoryRoot);
    red_active_theme_css_test_assert(
        is_array($legacyTarget)
            && $legacyTarget['themeId'] === 'legacy-bootstrap'
            && $legacyTarget['relativePath'] === 'css/style.css'
            && $legacyTarget['displayPath'] === 'css/style.css',
        'legacy activation retains the historical css/style.css editor target'
    );
    red_active_theme_css_test_assert(
        $legacyTarget['absolutePath'] === realpath($repositoryRoot . '/css/style.css'),
        'legacy target resolves to the existing project stylesheet'
    );

    $starterValidation = red_theme_activation_validate_candidate('starter-reference', $repositoryRoot);
    $starterTarget = red_admin_advanced_css_target_from_validation($starterValidation, $repositoryRoot);
    red_active_theme_css_test_assert(
        is_array($starterTarget)
            && $starterTarget['themeId'] === 'starter-reference'
            && $starterTarget['relativePath'] === 'assets/css/theme.css',
        'starter activation selects its first local top-level stylesheet'
    );
    red_active_theme_css_test_assert(
        $starterTarget['displayPath'] === 'themes/starter-reference/assets/css/theme.css'
            && $starterTarget['absolutePath'] === realpath(
                $repositoryRoot . '/themes/starter-reference/assets/css/theme.css'
            ),
        'starter target remains confined to and labelled from its package directory'
    );
    red_active_theme_css_test_assert(
        $starterTarget['relativePath'] !== 'assets/css/production.css',
        'production compatibility CSS does not displace the author-facing theme stylesheet'
    );

    $productionFallbackValidation = $starterValidation;
    $productionFallbackValidation['manifest']['assets']['styles'] = [[
        'id' => 'external-style',
        'url' => 'https://example.test/theme.css',
        'location' => 'head',
    ]];
    $productionFallbackTarget = red_admin_advanced_css_target_from_validation(
        $productionFallbackValidation,
        $repositoryRoot
    );
    red_active_theme_css_test_assert(
        is_array($productionFallbackTarget)
            && $productionFallbackTarget['relativePath'] === 'assets/css/production.css',
        'a standard package with no local top-level CSS may use its first local production stylesheet'
    );

    $noLocalCssValidation = $starterValidation;
    $noLocalCssValidation['manifest']['assets']['styles'] = [];
    $noLocalCssValidation['manifest']['production']['assets']['styles'] = [];
    red_active_theme_css_test_assert(
        red_admin_advanced_css_target_from_validation($noLocalCssValidation, $repositoryRoot) === null,
        'a package with no declared local CSS exposes no editable filesystem target'
    );

    $unsafeCssValidation = $starterValidation;
    $unsafeCssValidation['manifest']['assets']['styles'][0]['path'] = '../css/style.css';
    $unsafeCssValidation['manifest']['production']['assets']['styles'] = [];
    red_active_theme_css_test_assert(
        red_admin_advanced_css_target_from_validation($unsafeCssValidation, $repositoryRoot) === null,
        'traversal cannot become an editor target'
    );

    $temporaryDirectory = sys_get_temp_dir() . '/redcms-active-css-' . bin2hex(random_bytes(8));
    if (!mkdir($temporaryDirectory, 0700)) {
        throw new RuntimeException('Could not create the temporary CSS fixture.');
    }
    $temporaryCss = $temporaryDirectory . '/theme.css';
    $originalCss = ".fixture { color: red; }\n";
    if (file_put_contents($temporaryCss, $originalCss) !== strlen($originalCss)) {
        throw new RuntimeException('Could not create the temporary CSS file.');
    }
    $temporaryTarget = [
        'themeId' => 'fixture-theme',
        'relativePath' => 'assets/css/theme.css',
        'absolutePath' => $temporaryCss,
    ];
    $token = red_admin_advanced_css_target_token($temporaryTarget);
    red_active_theme_css_test_assert(
        preg_match('/\A[a-f0-9]{64}\z/', $token) === 1,
        'target token binds a form to the resolved theme, relative path, and current bytes'
    );
    red_active_theme_css_test_assert(
        red_admin_advanced_css_read($temporaryTarget) === $originalCss,
        'editor reads only the already resolved server-side target'
    );
    red_active_theme_css_test_assert(
        red_admin_advanced_css_write($temporaryTarget, str_repeat('0', 64), 'tampered') === 'stale'
            && file_get_contents($temporaryCss) === $originalCss,
        'a stale or forged token cannot write CSS'
    );
    red_active_theme_css_test_assert(
        red_admin_advanced_css_write($temporaryTarget, [$token], 'tampered') === 'stale'
            && file_get_contents($temporaryCss) === $originalCss,
        'array-shaped token input fails closed without a write'
    );
    red_active_theme_css_test_assert(
        red_admin_advanced_css_write($temporaryTarget, $token, ['tampered']) === 'no'
            && file_get_contents($temporaryCss) === $originalCss,
        'array-shaped CSS input cannot clear or rewrite the target'
    );

    $updatedCss = ".fixture { color: blue; }\n";
    red_active_theme_css_test_assert(
        red_admin_advanced_css_write($temporaryTarget, $token, $updatedCss) === 'yes'
            && file_get_contents($temporaryCss) === $updatedCss,
        'the exact current token permits a bounded stylesheet write'
    );
    red_active_theme_css_test_assert(
        red_admin_advanced_css_write($temporaryTarget, $token, $originalCss) === 'stale'
            && file_get_contents($temporaryCss) === $updatedCss,
        'a successful write rotates the content-bound token and blocks lost updates'
    );

    $editSource = file_get_contents($repositoryRoot . '/admin/bin/edit_advanced.php');
    $advancedUiSource = file_get_contents($repositoryRoot . '/includes/admin_advanced_ui_helpers.php');
    $updateSource = file_get_contents($repositoryRoot . '/admin/bin/update_advanced.php');
    red_active_theme_css_test_assert(
        is_string($editSource)
            && strpos($editSource, 'red_admin_advanced_active_css_target') !== false
            && strpos($editSource, 'red_admin_render_advanced_source_editor') !== false
            && is_string($advancedUiSource)
            && strpos($advancedUiSource, "['displayPath']") !== false
            && strpos($advancedUiSource, 'css_target_token') !== false,
        'Website CSS UI labels and binds the active server-derived target'
    );
    red_active_theme_css_test_assert(
        strpos($editSource, 'jumpCSS') === false
            && strpos($editSource, "red_admin_advanced_css_path('style.css')") === false,
        'Website CSS UI no longer sends a selectable filename or hardwires style.css'
    );
    red_active_theme_css_test_assert(
        is_string($updateSource)
            && strpos($updateSource, 'red_admin_advanced_active_css_target') !== false
            && strpos($updateSource, 'red_admin_advanced_css_write') !== false
            && strpos($updateSource, "array_key_exists('CSS', \$_POST)") !== false
            && strpos($updateSource, "\$_POST['jumpCSS']") === false,
        'write endpoint requires CSS, recomputes the active target, and never accepts a browser path'
    );
    red_active_theme_css_test_assert(
        strpos($updateSource, "(string) \$row['Item'] !== 'Website_CSS'") !== false,
        'write endpoint binds the request to the fixed Website_CSS record type'
    );

    echo 'Active-theme Website CSS self-test passed: ' . $assertions . " assertions.\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Active-theme Website CSS self-test failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if (is_string($temporaryDirectory) && $temporaryDirectory !== '') {
        $temporaryCss = $temporaryDirectory . '/theme.css';
        if (is_file($temporaryCss)) {
            unlink($temporaryCss);
        }
        if (is_dir($temporaryDirectory)) {
            rmdir($temporaryDirectory);
        }
    }
}
