<?php

require_once dirname(__DIR__) . '/includes/addon_public_route_helpers.php';

$assertions = 0;
$calls = 0;
function red_addon_public_route_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_route_test_context(array $route, callable $handler)
{
    $manifest = [
        'id' => 'redcms.route-fixture',
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [],
            'adapters' => [],
        ],
        'componentEditors' => [],
        'routes' => [$route],
    ];
    $registry = new RED_Addon_Runtime_Registry(
        'redcms.route-fixture',
        $manifest
    );
    $registry->registerRoute($route['id'], $handler);
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        ['redcms.route-fixture'],
        ['redcms.route-fixture' => $registry]
    );
}

$route = [
    'id' => 'fixture.catalog',
    'scope' => 'public',
    'path' => '/addons/redcms/route-fixture/catalog',
    'methods' => ['GET'],
    'authentication' => 'public',
    'csrf' => 'not-applicable',
];

try {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $missing = red_addon_public_route_dispatch(
        'GET',
        $route['path'],
        []
    );
    red_addon_public_route_test_assert(
        $missing['claimed'] === false && $missing['reason'] === 'not_matched',
        'a route is never claimed without request-local enabled ownership'
    );

    $handler = static function (RED_Addon_Public_Route_Request $request) use (&$calls) {
        $calls++;
        return RED_Addon_Public_Route_Result::success([
            'route' => $request->route(),
            'method' => $request->method(),
            'path' => $request->path(),
            'limit' => $request->query()['limit'] ?? '',
        ]);
    };
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_public_route_test_context($route, $handler);

    red_addon_public_route_test_assert(
        red_addon_public_route_query($route['path']) === []
            && red_addon_public_route_query(
                $route['path'] . '?limit=10&language=sp'
            ) === [
                'limit' => '10',
                'language' => 'sp',
            ]
            && red_addon_public_route_query(
                $route['path'] . '?q%5B%5D=first&q%5B%5D=second'
            ) === [
                'q' => ['first', 'second'],
            ]
            && red_addon_public_route_query('/invalid path?q=test') === null,
        'public route queries come from the bounded request target'
    );

    foreach (['/unrelated', $route['path'] . '/', $route['path'] . '%2F'] as $path) {
        $unmatched = red_addon_public_route_dispatch('GET', $path, []);
        red_addon_public_route_test_assert(
            $unmatched['claimed'] === false && $calls === 0,
            'only the exact unencoded static manifest path is claimed'
        );
    }

    $method = red_addon_public_route_dispatch('POST', $route['path'], []);
    red_addon_public_route_test_assert(
        $method['claimed'] === true
            && $method['invoked'] === false
            && $method['status'] === 405
            && ($method['headers']['Allow'] ?? '') === 'GET'
            && $calls === 0,
        'non-GET requests are refused before package invocation'
    );

    $invalid = red_addon_public_route_dispatch(
        'GET',
        $route['path'],
        ['bad-key' => 'value']
    );
    red_addon_public_route_test_assert(
        $invalid['claimed'] === true
            && $invalid['invoked'] === false
            && $invalid['status'] === 400
            && $calls === 0,
        'invalid query data is refused before package invocation'
    );

    $completed = red_addon_public_route_dispatch(
        'GET',
        $route['path'] . '?limit=10',
        ['limit' => '10']
    );
    red_addon_public_route_test_assert(
        $completed['claimed'] === true
            && $completed['invoked'] === true
            && $completed['success'] === true
            && $completed['status'] === 200
            && $completed['reason'] === 'completed'
            && $completed['route'] === 'fixture.catalog'
            && $completed['package'] === 'redcms.route-fixture'
            && json_decode($completed['body'], true) === [
                'ok' => true,
                'data' => [
                    'route' => 'fixture.catalog',
                    'method' => 'GET',
                    'path' => $route['path'],
                    'limit' => '10',
                ],
            ]
            && $calls === 1,
        'the exact owner receives one typed request and returns core JSON'
    );

    $failureContext = red_addon_public_route_test_context(
        $route,
        static fn () => RED_Addon_Public_Route_Result::failure(
            'not_found',
            404
        )
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] = $failureContext;
    $failure = red_addon_public_route_dispatch('GET', $route['path'], []);
    red_addon_public_route_test_assert(
        $failure['invoked'] === true
            && $failure['success'] === false
            && $failure['status'] === 404
            && $failure['reason'] === 'route_error'
            && json_decode($failure['body'], true) === [
                'ok' => false,
                'error' => 'not_found',
            ],
        'typed package failures retain only their bounded status and code'
    );

    $modes = [
        'output' => static function () {
            echo 'forbidden';
            return RED_Addon_Public_Route_Result::success();
        },
        'malformed' => static fn () => ['ok' => true],
        'exception' => static function () {
            throw new RuntimeException('contained');
        },
        'buffer' => static function () {
            ob_end_clean();
            return RED_Addon_Public_Route_Result::success();
        },
        'oversized' => static fn () => RED_Addon_Public_Route_Result::success([
            'a' => str_repeat('a', 4096),
            'b' => str_repeat('b', 4096),
            'c' => str_repeat('c', 4096),
            'd' => str_repeat('d', 4096),
            'e' => str_repeat('e', 4096),
        ]),
        'status' => static function () {
            http_response_code(201);
            return RED_Addon_Public_Route_Result::success();
        },
    ];
    foreach ($modes as $mode => $modeHandler) {
        $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
            red_addon_public_route_test_context($route, $modeHandler);
        $contained = red_addon_public_route_dispatch(
            'GET',
            $route['path'],
            []
        );
        red_addon_public_route_test_assert(
            $contained['claimed'] === true
                && $contained['invoked'] === true
                && $contained['success'] === false
                && $contained['status'] === 503
                && json_decode($contained['body'], true) === [
                    'ok' => false,
                    'error' => 'temporarily_unavailable',
                ],
            'route ' . $mode . ' behavior is contained behind core JSON'
        );
    }

    foreach (
        [
            'member' => array_merge($route, ['authentication' => 'member']),
            'unsafe' => array_merge($route, [
                'methods' => ['POST'],
                'csrf' => 'required',
            ]),
            'placeholder' => array_merge($route, [
                'path' => '/addons/redcms/route-fixture/{item}',
            ]),
        ]
        as $surface => $closedRoute
    ) {
        $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
            red_addon_public_route_test_context(
                $closedRoute,
                static fn () => RED_Addon_Public_Route_Result::success()
            );
        $closed = red_addon_public_route_dispatch(
            $surface === 'unsafe' ? 'POST' : 'GET',
            $closedRoute['path'],
            []
        );
        red_addon_public_route_test_assert(
            $closed['claimed'] === true
                && $closed['invoked'] === false
                && $closed['status'] === 503,
            $surface . ' routes remain closed in the first public slice'
        );
    }

    red_addon_public_route_test_assert(
        ob_get_level() === 0,
        'all public route paths restore the output buffer stack'
    );
    red_addon_public_route_test_assert(
        http_response_code() === 200,
        'package HTTP status changes are restored before core responds'
    );

    $index = file_get_contents(dirname(__DIR__) . '/index.php');
    $dispatchAt = strpos($index, 'red_addon_public_route_dispatch(');
    $renderAt = strpos($index, '$redThemeAdapter->renderDocumentStart()');
    red_addon_public_route_test_assert(
        strpos($index, "includes/addon_public_route_helpers.php") !== false
            && $dispatchAt !== false
            && $renderAt !== false
            && $dispatchAt < $renderAt
            && substr_count(
                $index,
                'red_addon_public_route_query('
            ) === 2
            && strpos($index, '$_GET ?? []') === false,
        'the public front controller dispatches request-target queries before theme output'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
}

fwrite(
    STDOUT,
    'Add-on public route dispatch self-test passed ('
        . $assertions . " assertions).\n"
);

?>
