<?php
/**
 * Core-owned contract for public routes without a renderable layout.
 *
 * A matched route with an explicitly blank layout keeps the historical empty
 * shell. An unmatched route keeps the already-selected theme shell, returns
 * HTTP 404, and receives the fixed semantic not-found body below.
 */

if (!function_exists('red_public_route_not_found_markup')) {
    function red_public_route_not_found_markup()
    {
        return '<main id="main-content" class="red-public-not-found" tabindex="-1">' . "\n"
            . '    <section class="red-public-not-found__panel" aria-labelledby="red-public-not-found-title">' . "\n"
            . '        <p class="red-public-not-found__code" aria-hidden="true">404</p>' . "\n"
            . '        <h1 id="red-public-not-found-title">Page not found</h1>' . "\n"
            . '        <p class="red-public-not-found__message">The page you requested may have moved or no longer exists.</p>' . "\n"
            . '        <a class="red-public-not-found__action" href="/">Return to the homepage</a>' . "\n"
            . '    </section>' . "\n"
            . '</main>' . "\n";
    }
}

if (!function_exists('red_public_route_fallback_contracts')) {
    function red_public_route_fallback_contracts()
    {
        return [
            'empty-layout-shell' => [
                'matchedRoute' => true,
                'layoutState' => 'empty-string',
                'httpStatus' => 200,
                'documentShell' => true,
                'layoutRendered' => false,
                'componentsRendered' => false,
                'redirected' => false,
                'bodyBytesAdded' => 0,
            ],
            'unmatched-theme-404' => [
                'matchedRoute' => false,
                'layoutState' => 'null',
                'httpStatus' => 404,
                'documentShell' => true,
                'layoutRendered' => false,
                'componentsRendered' => false,
                'redirected' => false,
                'bodyBytesAdded' => strlen(red_public_route_not_found_markup()),
            ],
        ];
    }

    function red_public_route_fallback_assert_contract($id, array $contract)
    {
        $contracts = red_public_route_fallback_contracts();
        if (!is_string($id) || !isset($contracts[$id]) || $contract !== $contracts[$id]) {
            throw new InvalidArgumentException('Public route fallback contract is invalid or outside the fixed scope.');
        }
        return true;
    }

    function red_public_route_fallback_contract($id)
    {
        $contracts = red_public_route_fallback_contracts();
        if (!is_string($id) || !isset($contracts[$id])) {
            throw new InvalidArgumentException('Unknown public route fallback contract.');
        }
        red_public_route_fallback_assert_contract($id, $contracts[$id]);
        return $contracts[$id];
    }

    function red_public_route_fallback_classify($layout)
    {
        if ($layout === '') {
            return [
                'id' => 'empty-layout-shell',
                'contract' => red_public_route_fallback_contract('empty-layout-shell'),
            ];
        }
        if ($layout === null) {
            return [
                'id' => 'unmatched-theme-404',
                'contract' => red_public_route_fallback_contract('unmatched-theme-404'),
            ];
        }
        if (!is_string($layout)) {
            throw new InvalidArgumentException('Public route layout result must be a string or null.');
        }
        return null;
    }

    function red_public_route_fallback_render($layout)
    {
        $fallback = red_public_route_fallback_classify($layout);
        if ($fallback === null) {
            return false;
        }

        $contract = $fallback['contract'];
        red_public_route_fallback_assert_contract($fallback['id'], $contract);
        if (!headers_sent()) {
            http_response_code($contract['httpStatus']);
        }

        // The surrounding public renderer already emitted the active theme's
        // document header and will still emit its footer.
        if ($fallback['id'] === 'unmatched-theme-404') {
            echo red_public_route_not_found_markup();
        }

        return true;
    }
}
