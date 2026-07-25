<?php
/**
 * Dependency-free safety, determinism, and isolation tests for fixture preview.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_preview_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_preview_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_preview_test_expect_exception(callable $callback, $messageFragment, $message)
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $exception) {
        $caught = $exception;
    }

    red_theme_preview_test_assert(
        $caught instanceof Throwable
            && ($messageFragment === '' || strpos($caught->getMessage(), $messageFragment) !== false),
        $message
    );
}

function red_theme_preview_test_remove_tree($path)
{
    if (is_link($path) || is_file($path)) {
        unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entryPath = $entry->getPathname();
        if ($entry->isLink() || $entry->isFile()) {
            unlink($entryPath);
        } else {
            rmdir($entryPath);
        }
    }
    rmdir($path);
}

function red_theme_preview_test_copy_tree($source, $destination)
{
    if (!mkdir($destination, 0700, true) && !is_dir($destination)) {
        throw new RuntimeException('Could not create copied-theme fixture directory.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry) {
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $target = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($entry->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0700, true)) {
                throw new RuntimeException('Could not copy theme fixture directory.');
            }
        } elseif (!copy($entry->getPathname(), $target)) {
            throw new RuntimeException('Could not copy theme fixture file.');
        }
    }
}

function red_theme_preview_test_scalar_tree($value)
{
    if (is_array($value)) {
        foreach ($value as $child) {
            if (!red_theme_preview_test_scalar_tree($child)) {
                return false;
            }
        }
        return true;
    }

    return is_scalar($value) || $value === null;
}

function red_theme_preview_test_fixture_for_layout(array $fixture, $layoutId, array $positionIds)
{
    $availableSlots = array_values($fixture['page']['slots']);
    $fixture['page']['layout'] = $layoutId;
    $fixture['page']['slots'] = [];
    foreach ($positionIds as $index => $positionId) {
        $fixture['page']['slots'][(string) $positionId] = $availableSlots[$index] ?? [];
    }

    return $fixture;
}

function red_theme_preview_test_write_json($path, array $value)
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) {
        throw new RuntimeException('Could not write JSON test fixture.');
    }
}

$token = bin2hex(random_bytes(8));
$fixtureRoot = sys_get_temp_dir() . '/redcms-theme-preview-test-' . $token;
$temporaryProjectRoot = $fixtureRoot . '/project';
$temporaryTheme = $temporaryProjectRoot . '/themes/starter-reference';
$outsideRoot = $fixtureRoot . '/outside';

$sessionWasSet = isset($_SESSION);
$previousSession = $sessionWasSet ? $_SESSION : null;
$previousGet = $_GET;
$previousPost = $_POST;

try {
    if (!mkdir($outsideRoot, 0700, true)) {
        throw new RuntimeException('Could not create preview-test directories.');
    }

    $validation = red_theme_preview_validate_reference_theme('starter-reference', $repositoryRoot);
    $fixture = red_theme_preview_load_fixture($validation);
    $first = red_theme_preview_render_fixture('starter-reference', $fixture, $repositoryRoot);
    $second = red_theme_preview_render_fixture('starter-reference', $fixture, $repositoryRoot);

    red_theme_preview_test_assert($first['html'] === $second['html'], 'two renders are byte-for-byte deterministic');
    red_theme_preview_test_assert($first['sha256'] === $second['sha256'], 'two renders have the same digest');
    red_theme_preview_test_assert(
        $first['sha256'] === '106c984a77643cb0a8b4f0154a59e0558b1d082ff90267d5cbd7e785bbd02a7d',
        'reference fixture digest matches the reviewed deterministic output'
    );
    red_theme_preview_test_assert(
        $first['bytes'] === strlen($first['html']) && $first['bytes'] > 10000,
        'reported byte count matches complete rendered output'
    );
    red_theme_preview_test_assert(
        red_theme_preview_test_scalar_tree($first['contract']),
        'prepared contract contains only arrays and scalar values'
    );
    red_theme_preview_test_assert(
        $first['scope'] === [
            'databaseReads' => 0,
            'databaseWrites' => 0,
            'sessionReads' => 0,
            'sessionWrites' => 0,
            'liveRuntimeChanges' => 0,
        ],
        'preview result records the complete zero-side-effect scope'
    );

    $html = $first['html'];
    foreach (
        [
            '<!doctype html>',
            'Isolated fixture preview',
            'id="overview"',
            'id="preview-form"',
            'id="preview-gallery"',
            'id="preview-notes"',
            'starter-layout--index-1',
            'aria-current="page"',
            '<style data-theme-asset="starter-theme">',
            'data:image/svg+xml;base64,',
            'type="button" aria-disabled="true"',
        ]
        as $expectedMarker
    ) {
        red_theme_preview_test_assert(
            strpos($html, $expectedMarker) !== false,
            'rendered output contains marker ' . $expectedMarker
        );
    }
    red_theme_preview_test_assert(strpos($html, '<script') === false, 'preview emits no client-side script');
    red_theme_preview_test_assert(strpos($html, '<link') === false, 'preview emits no external stylesheet link');
    red_theme_preview_test_assert(strpos($html, 'method="post"') === false, 'fixture form has no write method');
    red_theme_preview_test_assert(strpos($html, 'action=') === false, 'fixture form has no submission endpoint');

    $videoFixture = $fixture;
    $videoFixture['page']['slots']['3'][0]['data'] = [
        'title' => 'Video Gallery contract',
        'video' => [
            'provider' => 'youtube',
            'id' => 'pP8VJwjSnqA',
            'caption' => 'How to add content',
        ],
    ];
    $youtubeVideo = red_theme_preview_render_fixture('starter-reference', $videoFixture, $repositoryRoot);
    $youtubeVideoAgain = red_theme_preview_render_fixture('starter-reference', $videoFixture, $repositoryRoot);
    red_theme_preview_test_assert(
        $youtubeVideo['html'] === $youtubeVideoAgain['html']
            && $youtubeVideo['sha256'] === $youtubeVideoAgain['sha256'],
        'Gallery Video renders deterministically through the fixture-only contract'
    );
    red_theme_preview_test_assert(
        $youtubeVideo['bytes'] === 15083
            && $youtubeVideo['sha256'] === 'd7b30add407ec65f2e7c68a5f83865d5e4b26c4e8b5b2a029e2dd583813e49b0',
        'Gallery Video output matches the reviewed offline fixture digest'
    );
    $youtubeHtml = $youtubeVideo['html'];
    red_theme_preview_test_assert(
        strpos($youtubeHtml, 'class="starter-video-contract"') !== false
            && strpos($youtubeHtml, 'data-video-provider="youtube"') !== false
            && strpos($youtubeHtml, 'data-video-id="pP8VJwjSnqA"') !== false
            && strpos($youtubeHtml, 'YouTube video') !== false
            && strpos($youtubeHtml, 'How to add content') !== false,
        'YouTube provider, id, label, and caption cross the exact data-only view boundary'
    );
    red_theme_preview_test_assert(
        preg_match('/<(?:iframe|object|embed|script)\b/i', $youtubeHtml) !== 1
            && stripos($youtubeHtml, 'http://') === false
            && stripos($youtubeHtml, 'https://') === false
            && strpos($youtubeHtml, 'External playback is intentionally disabled') !== false,
        'Gallery Video emits only an offline placeholder with no executable or external media'
    );
    red_theme_preview_test_assert(
        $youtubeVideo['scope'] === [
            'databaseReads' => 0,
            'databaseWrites' => 0,
            'sessionReads' => 0,
            'sessionWrites' => 0,
            'liveRuntimeChanges' => 0,
        ],
        'Gallery Video retains the fixture zero-database/session/runtime scope'
    );

    $escapedVideoFixture = $videoFixture;
    $escapedVideoFixture['page']['slots']['3'][0]['data']['video']['caption'] =
        '<img src=x onerror=alert(1)>';
    $escapedVideo = red_theme_preview_render_fixture(
        'starter-reference',
        $escapedVideoFixture,
        $repositoryRoot
    )['html'];
    red_theme_preview_test_assert(
        strpos($escapedVideo, '<img src=x') === false
            && strpos($escapedVideo, '&lt;img src=x onerror=alert(1)&gt;') !== false,
        'Gallery Video caption remains escaped plain text'
    );

    $vimeoFixture = $videoFixture;
    $vimeoFixture['page']['slots']['3'][0]['data']['video'] = [
        'provider' => 'vimeo',
        'id' => '76979871',
        'caption' => 'Vimeo contract example',
    ];
    $vimeoVideo = red_theme_preview_render_fixture('starter-reference', $vimeoFixture, $repositoryRoot);
    red_theme_preview_test_assert(
        strpos($vimeoVideo['html'], 'data-video-provider="vimeo"') !== false
            && strpos($vimeoVideo['html'], 'data-video-id="76979871"') !== false
            && strpos($vimeoVideo['html'], 'Vimeo video') !== false,
        'Vimeo provider and numeric id use the same bounded offline representation'
    );
    red_theme_preview_test_assert(
        preg_match('/<(?:iframe|object|embed|script)\b/i', $vimeoVideo['html']) !== 1
            && stripos($vimeoVideo['html'], 'vimeo.com') === false,
        'Vimeo fixture output also performs no external media execution'
    );

    $tamperedVideo = $videoFixture;
    $tamperedVideo['page']['slots']['3'][0]['data']['items'] = [];
    red_theme_preview_test_expect_exception(
        static function () use ($tamperedVideo, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tamperedVideo, $repositoryRoot);
        },
        'unexpected key',
        'mixed image and Video Gallery shapes are rejected'
    );
    $tamperedVideo = $videoFixture;
    $tamperedVideo['page']['slots']['3'][0]['data']['url'] = 'https://www.youtube.com/watch?v=pP8VJwjSnqA';
    red_theme_preview_test_expect_exception(
        static function () use ($tamperedVideo, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tamperedVideo, $repositoryRoot);
        },
        'unexpected key',
        'Gallery Video refuses a caller-supplied URL'
    );
    $tamperedVideo = $videoFixture;
    $tamperedVideo['page']['slots']['3'][0]['data']['video']['embedHtml'] = '<iframe></iframe>';
    red_theme_preview_test_expect_exception(
        static function () use ($tamperedVideo, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tamperedVideo, $repositoryRoot);
        },
        'unexpected key',
        'Gallery Video refuses caller-supplied embed markup'
    );
    foreach (['YouTube', 'dailymotion'] as $unsupportedProvider) {
        $tamperedVideo = $videoFixture;
        $tamperedVideo['page']['slots']['3'][0]['data']['video']['provider'] = $unsupportedProvider;
        red_theme_preview_test_expect_exception(
            static function () use ($tamperedVideo, $repositoryRoot) {
                red_theme_preview_render_fixture('starter-reference', $tamperedVideo, $repositoryRoot);
            },
            'exactly youtube or vimeo',
            'Gallery Video rejects unsupported or noncanonical provider ' . $unsupportedProvider
        );
    }
    foreach (['https://youtu.be/pP8VJwjSnqA', 'short'] as $invalidYoutubeId) {
        $tamperedVideo = $videoFixture;
        $tamperedVideo['page']['slots']['3'][0]['data']['video']['id'] = $invalidYoutubeId;
        red_theme_preview_test_expect_exception(
            static function () use ($tamperedVideo, $repositoryRoot) {
                red_theme_preview_render_fixture('starter-reference', $tamperedVideo, $repositoryRoot);
            },
            'provider-specific shape',
            'Gallery Video rejects invalid YouTube id input'
        );
    }
    foreach (['not-numeric', '012345'] as $invalidVimeoId) {
        $tamperedVideo = $vimeoFixture;
        $tamperedVideo['page']['slots']['3'][0]['data']['video']['id'] = $invalidVimeoId;
        red_theme_preview_test_expect_exception(
            static function () use ($tamperedVideo, $repositoryRoot) {
                red_theme_preview_render_fixture('starter-reference', $tamperedVideo, $repositoryRoot);
            },
            'provider-specific shape',
            'Gallery Video rejects invalid Vimeo id input'
        );
    }
    $tamperedVideo = $videoFixture;
    unset($tamperedVideo['page']['slots']['3'][0]['data']['video']['caption']);
    red_theme_preview_test_expect_exception(
        static function () use ($tamperedVideo, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tamperedVideo, $repositoryRoot);
        },
        'missing required key',
        'Gallery Video requires its exact provider/id/caption shape'
    );
    $tamperedVideo = $videoFixture;
    $tamperedVideo['page']['slots']['3'][0]['data']['video'] = 'pP8VJwjSnqA';
    red_theme_preview_test_expect_exception(
        static function () use ($tamperedVideo, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tamperedVideo, $repositoryRoot);
        },
        'must be an object',
        'Gallery Video rejects a scalar media shortcut'
    );
    $videoContract = red_theme_preview_contract($videoFixture, $validation);
    red_theme_preview_test_expect_exception(
        static function () use ($validation, $videoContract, $repositoryRoot) {
            red_theme_preview_render_prepared_contract(
                $validation,
                $videoContract,
                'read-only-home-preview',
                null,
                $repositoryRoot . '/images/gallery'
            );
        },
        'offline fixture contract',
        'Gallery Video cannot cross into the fixed real Home preview mode'
    );

    $_SESSION = ['preview_sentinel' => 'session-unchanged'];
    $_GET = ['preview_sentinel' => 'get-unchanged'];
    $_POST = ['preview_sentinel' => 'post-unchanged'];
    red_theme_preview_render_fixture('starter-reference', $fixture, $repositoryRoot);
    red_theme_preview_test_assert(
        $_SESSION === ['preview_sentinel' => 'session-unchanged'],
        'render leaves session state untouched'
    );
    red_theme_preview_test_assert(
        $_GET === ['preview_sentinel' => 'get-unchanged']
            && $_POST === ['preview_sentinel' => 'post-unchanged'],
        'render leaves request input untouched'
    );

    $escapedFixture = $fixture;
    $escapedFixture['page']['slots']['1'][0]['data']['title'] = '<script>alert("preview")</script>';
    $escaped = red_theme_preview_render_fixture('starter-reference', $escapedFixture, $repositoryRoot)['html'];
    red_theme_preview_test_assert(
        strpos($escaped, '<script>alert') === false
            && strpos($escaped, '&lt;script&gt;alert(&quot;preview&quot;)&lt;/script&gt;') !== false,
        'untrusted fixture text is escaped by the component view'
    );

    $layoutPositions = [
        'index' => [1, 2, 3],
        'index-1' => [1, 2, 3, 4],
        'index-2' => [1, 2, 3, 4],
        'index-3' => [1, 2],
        'feature-grid' => [1, 2, 3, 4, 5],
    ];
    foreach ($layoutPositions as $layoutId => $positions) {
        $layoutFixture = red_theme_preview_test_fixture_for_layout($fixture, $layoutId, $positions);
        $layoutResult = red_theme_preview_render_fixture('starter-reference', $layoutFixture, $repositoryRoot);
        red_theme_preview_test_assert(
            $layoutResult['layout'] === $layoutId
                && strpos($layoutResult['html'], 'starter-layout--' . $layoutId . '"') !== false,
            'declared layout ' . $layoutId . ' renders through the fixed contract'
        );
    }

    $runtime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_preview_test_assert(
        $runtime['themeId'] === 'legacy-bootstrap' && !empty($runtime['resolution']['usedFallback']),
        'live runtime still hard-falls back from starter-reference to legacy-bootstrap'
    );
    red_theme_preview_test_expect_exception(
        static function () use ($repositoryRoot) {
            red_theme_preview_render('legacy-bootstrap', $repositoryRoot);
        },
        'only the audited starter-reference',
        'preview refuses the live legacy adapter and every non-reference package'
    );

    $tampered = $fixture;
    $tampered['page']['slots']['1'][0]['data']['callback'] = 'render';
    red_theme_preview_test_expect_exception(
        static function () use ($tampered, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tampered, $repositoryRoot);
        },
        'forbidden executable mapping key',
        'callback mapping is rejected before rendering'
    );

    $tampered = $fixture;
    $tampered['regions']['hero']['action']['class'] = 'PreviewRunner';
    red_theme_preview_test_expect_exception(
        static function () use ($tampered, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tampered, $repositoryRoot);
        },
        'forbidden executable mapping key',
        'class mapping is rejected before rendering'
    );

    $tampered = $fixture;
    $tampered['page']['layout'] = 'not-declared';
    red_theme_preview_test_expect_exception(
        static function () use ($tampered, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tampered, $repositoryRoot);
        },
        'not declared',
        'undeclared layout is rejected'
    );

    $tampered = $fixture;
    unset($tampered['page']['slots']['4']);
    red_theme_preview_test_expect_exception(
        static function () use ($tampered, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tampered, $repositoryRoot);
        },
        'exactly match',
        'missing layout slot is rejected'
    );

    $tampered = $fixture;
    $tampered['page']['slots']['5'] = [];
    red_theme_preview_test_expect_exception(
        static function () use ($tampered, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tampered, $repositoryRoot);
        },
        'exactly match',
        'extra layout slot is rejected'
    );

    $tampered = $fixture;
    $tampered['page']['slots']['1'][0]['component'] = 'Video';
    red_theme_preview_test_expect_exception(
        static function () use ($tampered, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tampered, $repositoryRoot);
        },
        'not declared',
        'undeclared component is rejected'
    );

    $tampered = $fixture;
    $tampered['regions']['hero']['action']['url'] = 'javascript:alert(1)';
    red_theme_preview_test_expect_exception(
        static function () use ($tampered, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tampered, $repositoryRoot);
        },
        'must be a fragment',
        'executable URL scheme is rejected'
    );

    $tampered = $fixture;
    $tampered['page']['slots']['3'][0]['data']['items'][0]['image'] = '../outside.svg';
    red_theme_preview_test_expect_exception(
        static function () use ($tampered, $repositoryRoot) {
            red_theme_preview_render_fixture('starter-reference', $tampered, $repositoryRoot);
        },
        'safe theme-relative path',
        'gallery path traversal is rejected'
    );

    $safeOutput = $fixtureRoot . '/safe-preview.html';
    red_theme_preview_test_assert(
        red_theme_preview_temp_output_path($safeOutput)
            === realpath(dirname($safeOutput)) . DIRECTORY_SEPARATOR . basename($safeOutput),
        'new output file inside system temp is accepted'
    );
    red_theme_preview_test_assert(
        basename(red_theme_preview_temp_output_path('/tmp/redcms-preview-' . $token . '.html'))
            === 'redcms-preview-' . $token . '.html',
        'canonical /tmp output is accepted on platforms with a distinct system temp root'
    );
    red_theme_preview_test_expect_exception(
        static function () use ($repositoryRoot) {
            red_theme_preview_temp_output_path($repositoryRoot . '/preview-output.html');
        },
        'inside the system temp',
        'repository output path is rejected'
    );

    $linkedOutput = $fixtureRoot . '/linked-output.html';
    if (function_exists('symlink') && @symlink($repositoryRoot . '/index.php', $linkedOutput)) {
        red_theme_preview_test_expect_exception(
            static function () use ($linkedOutput) {
                red_theme_preview_temp_output_path($linkedOutput);
            },
            'inside the system temp',
            'symbolic-link output file is rejected'
        );
    }

    red_theme_preview_test_copy_tree($repositoryRoot . '/themes/starter-reference', $temporaryTheme);
    $temporaryFixture = $temporaryTheme . '/fixtures/preview.json';
    $outsideFixture = $outsideRoot . '/preview.json';
    red_theme_preview_test_write_json($outsideFixture, $fixture);
    unlink($temporaryFixture);
    if (function_exists('symlink') && @symlink($outsideFixture, $temporaryFixture)) {
        red_theme_preview_test_expect_exception(
            static function () use ($temporaryProjectRoot) {
                red_theme_preview_render('starter-reference', $temporaryProjectRoot);
            },
            'resolves outside',
            'fixture symbolic-link escape is rejected'
        );
        unlink($temporaryFixture);
    }
    copy($repositoryRoot . '/themes/starter-reference/fixtures/preview.json', $temporaryFixture);

    $temporaryArticle = $temporaryTheme . '/components/article.php';
    file_put_contents($temporaryArticle, '<?php $_SESSION["unsafe"] = true;' . PHP_EOL);
    red_theme_preview_test_expect_exception(
        static function () use ($temporaryProjectRoot) {
            red_theme_preview_render('starter-reference', $temporaryProjectRoot);
        },
        'request/session/global state',
        'template session access is rejected by source inspection'
    );
    copy($repositoryRoot . '/themes/starter-reference/components/article.php', $temporaryArticle);

    file_put_contents($temporaryArticle, '<?php file_put_contents("/tmp/unsafe", "x");' . PHP_EOL);
    red_theme_preview_test_expect_exception(
        static function () use ($temporaryProjectRoot) {
            red_theme_preview_render('starter-reference', $temporaryProjectRoot);
        },
        'outside the fixed allowlist',
        'template filesystem function is rejected by the call allowlist'
    );
    copy($repositoryRoot . '/themes/starter-reference/components/article.php', $temporaryArticle);

    file_put_contents($temporaryArticle, '<?php \\file_put_contents("/tmp/unsafe", "x");' . PHP_EOL);
    red_theme_preview_test_expect_exception(
        static function () use ($temporaryProjectRoot) {
            red_theme_preview_render('starter-reference', $temporaryProjectRoot);
        },
        'forbidden executable token',
        'fully-qualified PHP function call is rejected'
    );
    copy($repositoryRoot . '/themes/starter-reference/components/article.php', $temporaryArticle);

    file_put_contents($temporaryArticle, '<?php DateTime::createFromFormat("Y", "2026");' . PHP_EOL);
    red_theme_preview_test_expect_exception(
        static function () use ($temporaryProjectRoot) {
            red_theme_preview_render('starter-reference', $temporaryProjectRoot);
        },
        'forbidden executable token',
        'static method dispatch is rejected'
    );
    copy($repositoryRoot . '/themes/starter-reference/components/article.php', $temporaryArticle);

    file_put_contents($temporaryArticle, '<?php ("file_put_contents")("/tmp/unsafe", "x");' . PHP_EOL);
    red_theme_preview_test_expect_exception(
        static function () use ($temporaryProjectRoot) {
            red_theme_preview_render('starter-reference', $temporaryProjectRoot);
        },
        'dynamic callable expression',
        'parenthesized literal callable is rejected'
    );
    copy($repositoryRoot . '/themes/starter-reference/components/article.php', $temporaryArticle);

    file_put_contents($temporaryArticle, '<img src="x" onerror="alert(1)">' . PHP_EOL);
    red_theme_preview_test_expect_exception(
        static function () use ($temporaryProjectRoot) {
            red_theme_preview_render('starter-reference', $temporaryProjectRoot);
        },
        'executable browser markup',
        'template event-handler markup is rejected by source inspection'
    );
    copy($repositoryRoot . '/themes/starter-reference/components/article.php', $temporaryArticle);

    $temporaryCss = $temporaryTheme . '/assets/css/theme.css';
    file_put_contents($temporaryCss, 'body { background: url(https://example.invalid/pixel); }' . PHP_EOL);
    red_theme_preview_test_expect_exception(
        static function () use ($temporaryProjectRoot) {
            red_theme_preview_render('starter-reference', $temporaryProjectRoot);
        },
        'external-resource or executable construct',
        'stylesheet network resource is rejected'
    );
    copy($repositoryRoot . '/themes/starter-reference/assets/css/theme.css', $temporaryCss);

    $temporarySvg = $temporaryTheme . '/assets/images/preview-one.svg';
    file_put_contents(
        $temporarySvg,
        '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.invalid/image.png"/></svg>'
    );
    red_theme_preview_test_expect_exception(
        static function () use ($temporaryProjectRoot) {
            red_theme_preview_render('starter-reference', $temporaryProjectRoot);
        },
        'external-resource markup',
        'SVG resource loading is rejected'
    );
    copy($repositoryRoot . '/themes/starter-reference/assets/images/preview-one.svg', $temporarySvg);

    $temporaryManifestPath = $temporaryTheme . '/theme.json';
    $temporaryManifest = json_decode(file_get_contents($temporaryManifestPath), true, 512, JSON_THROW_ON_ERROR);
    $temporaryManifest['assets']['scripts'][] = [
        'id' => 'unsafe-preview-script',
        'path' => 'assets/unsafe.js',
        'location' => 'body-end',
    ];
    file_put_contents($temporaryTheme . '/assets/unsafe.js', 'document.body.dataset.unsafe = "yes";' . PHP_EOL);
    red_theme_preview_test_write_json($temporaryManifestPath, $temporaryManifest);
    red_theme_preview_test_expect_exception(
        static function () use ($temporaryProjectRoot) {
            red_theme_preview_render('starter-reference', $temporaryProjectRoot);
        },
        'does not execute client-side scripts',
        'declared client-side script is rejected'
    );

    echo 'Theme fixture-preview self-test passed: ' . $assertions . ' assertions.' . PHP_EOL;
} finally {
    if ($sessionWasSet) {
        $_SESSION = $previousSession;
    } else {
        unset($_SESSION);
    }
    $_GET = $previousGet;
    $_POST = $previousPost;
    red_theme_preview_test_remove_tree($fixtureRoot);
}
