<?php
/**
 * Dependency-free tests for the core-owned public route fallback contract.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/public_route_fallback_helpers.php';

$assertions = 0;
function red_public_route_fallback_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_public_route_fallback_test_throws(callable $callback, $className, $message)
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        red_public_route_fallback_test_assert(
            $throwable instanceof $className,
            $message . ' throws ' . $className
        );
        return;
    }
    red_public_route_fallback_test_assert(false, $message . ' fails closed');
}

try {
    $contracts = red_public_route_fallback_contracts();
    red_public_route_fallback_test_assert(
        array_keys($contracts) === ['empty-layout-shell', 'unmatched-theme-404'],
        'boundary owns exactly the matched blank-layout and unmatched 404 states'
    );
    red_public_route_fallback_test_assert(
        red_public_route_fallback_assert_contract('empty-layout-shell', $contracts['empty-layout-shell'])
            && $contracts['empty-layout-shell']['matchedRoute']
            && $contracts['empty-layout-shell']['layoutState'] === 'empty-string'
            && $contracts['empty-layout-shell']['httpStatus'] === 200
            && $contracts['empty-layout-shell']['documentShell']
            && !$contracts['empty-layout-shell']['layoutRendered']
            && !$contracts['empty-layout-shell']['componentsRendered']
            && !$contracts['empty-layout-shell']['redirected']
            && $contracts['empty-layout-shell']['bodyBytesAdded'] === 0,
        'matched blank-layout routes preserve the exact HTTP 200 shell-only behavior'
    );
    red_public_route_fallback_test_assert(
        red_public_route_fallback_assert_contract('unmatched-theme-404', $contracts['unmatched-theme-404'])
            && !$contracts['unmatched-theme-404']['matchedRoute']
            && $contracts['unmatched-theme-404']['layoutState'] === 'null'
            && $contracts['unmatched-theme-404']['httpStatus'] === 404
            && $contracts['unmatched-theme-404']['documentShell']
            && !$contracts['unmatched-theme-404']['layoutRendered']
            && !$contracts['unmatched-theme-404']['componentsRendered']
            && !$contracts['unmatched-theme-404']['redirected']
            && $contracts['unmatched-theme-404']['bodyBytesAdded']
                === strlen(red_public_route_not_found_markup()),
        'unmatched routes preserve the selected theme shell and add the fixed HTTP 404 body'
    );

    $tampered = $contracts['empty-layout-shell'];
    $tampered['httpStatus'] = 404;
    red_public_route_fallback_test_throws(
        static function () use ($tampered) {
            red_public_route_fallback_assert_contract('empty-layout-shell', $tampered);
        },
        InvalidArgumentException::class,
        'status tampering'
    );
    red_public_route_fallback_test_throws(
        static function () {
            red_public_route_fallback_contract('/caller-route');
        },
        InvalidArgumentException::class,
        'caller route injection'
    );
    red_public_route_fallback_test_throws(
        static function () {
            red_public_route_fallback_classify(['index-1']);
        },
        InvalidArgumentException::class,
        'non-scalar layout result'
    );

    red_public_route_fallback_test_assert(
        red_public_route_fallback_classify('')['id'] === 'empty-layout-shell'
            && red_public_route_fallback_classify(null)['id'] === 'unmatched-theme-404'
            && red_public_route_fallback_classify('index-1') === null
            && red_public_route_fallback_classify(' ') === null,
        'classification is strict and does not widen the fallback states'
    );

    http_response_code(418);
    ob_start();
    $emptyHandled = red_public_route_fallback_render('');
    $emptyOutput = ob_get_clean();
    red_public_route_fallback_test_assert(
        $emptyHandled && $emptyOutput === '' && http_response_code() === 200,
        'empty-layout fallback adds zero bytes and fixes the response at HTTP 200'
    );

    http_response_code(418);
    ob_start();
    $unmatchedHandled = red_public_route_fallback_render(null);
    $unmatchedOutput = ob_get_clean();
    red_public_route_fallback_test_assert(
        $unmatchedHandled
            && $unmatchedOutput === red_public_route_not_found_markup()
            && strlen($unmatchedOutput) === $contracts['unmatched-theme-404']['bodyBytesAdded']
            && http_response_code() === 404,
        'unmatched fallback emits the exact fixed body and fixes the response at HTTP 404'
    );
    red_public_route_fallback_test_assert(
        substr_count($unmatchedOutput, 'id="main-content"') === 1
            && substr_count($unmatchedOutput, '<h1 id="red-public-not-found-title">Page not found</h1>') === 1
            && substr_count($unmatchedOutput, 'href="/"') === 1
            && strpos($unmatchedOutput, '/caller-route') === false,
        'not-found markup is semantic, actionable, and contains no caller route data'
    );

    http_response_code(202);
    ob_start();
    $layoutHandled = red_public_route_fallback_render('index-1');
    $layoutOutput = ob_get_clean();
    red_public_route_fallback_test_assert(
        !$layoutHandled && $layoutOutput === '' && http_response_code() === 202,
        'resolved layouts pass through without response or output changes'
    );

    $pageLayoutSource = file_get_contents($repositoryRoot . '/class/class_page_layout.php');
    $pageTitleSource = file_get_contents($repositoryRoot . '/class/class_pagetitle.php');
    $helperSource = file_get_contents($repositoryRoot . '/includes/public_route_fallback_helpers.php');
    red_public_route_fallback_test_assert(
        is_string($pageLayoutSource)
            && strpos($pageLayoutSource, 'includes/public_route_fallback_helpers.php') !== false
            && strpos($pageLayoutSource, 'red_public_route_fallback_render($this->layout)') !== false
            && strpos($pageLayoutSource, 'red_public_route_fallback_render($this->layout)')
                < strpos($pageLayoutSource, '$redThemeAdapter->renderPublicLayout('),
        'live public layout path applies the core fallback before theme dispatch'
    );
    $controlPanelMethodPosition = strpos($pageLayoutSource, 'public function cp_layout');
    $controlPanelFallbackPosition = strpos(
        $pageLayoutSource,
        'red_public_route_fallback_classify($this->layout)',
        $controlPanelMethodPosition
    );
    $controlPanelAdapterPosition = strpos(
        $pageLayoutSource,
        '$redThemeAdapter->renderControlPanelLayout(',
        $controlPanelMethodPosition
    );
    red_public_route_fallback_test_assert(
        $controlPanelMethodPosition !== false
            && $controlPanelFallbackPosition !== false
            && $controlPanelAdapterPosition !== false
            && $controlPanelFallbackPosition < $controlPanelAdapterPosition,
        'administrator overlay skips absent layouts before active-theme control-panel dispatch'
    );
    red_public_route_fallback_test_assert(
        is_string($pageTitleSource)
            && substr_count($pageTitleSource, '$Website_Title . \' | Page not found\'') === 2
            && substr_count($pageTitleSource, "echo 'Page not found';") === 1,
        'document title identifies unmatched area, article, and unsupported route shapes'
    );
    red_public_route_fallback_test_assert(
        is_string($helperSource)
            && strpos($helperSource, '$_GET') === false
            && strpos($helperSource, '$_POST') === false
            && strpos($helperSource, '$_SESSION') === false
            && strpos($helperSource, 'mysqli') === false
            && strpos($helperSource, 'red_theme_') === false
            && substr_count($helperSource, 'echo red_public_route_not_found_markup();') === 1,
        'boundary accepts no caller route and performs no database, session, or theme selection work'
    );
    red_public_route_fallback_test_assert(
        hash_file('sha256', $repositoryRoot . '/includes/public_route_fallback_helpers.php')
            === 'b1ec2cd86e8b36de0145daa9cd1c3b9922dbf429f48a081b567e32196f1be67b'
            && hash_file('sha256', $repositoryRoot . '/class/class_page_layout.php')
                === '7d8caf9cfe37fae38978463a858ca4e75f9c046bea1e8082d2dfbcd40d1928b3',
        'live fallback boundary and connection hashes remain exact'
    );

    echo 'Public route fallback self-test passed (' . $assertions . " assertions).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Public route fallback self-test failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);
