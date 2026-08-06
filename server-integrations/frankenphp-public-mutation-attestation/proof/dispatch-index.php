<?php
/**
 * Disposable supported-server public-mutation dispatcher proof endpoint.
 *
 * This file is staged only into a temporary Docker image by the paired proof
 * script. It exercises the real ingress verifier, dispatcher, atomic runner,
 * and fixed response emitter against an isolated MySQL database. It is not a
 * RED-CMS front-controller route, package, or client deployment artifact.
 */

ini_set('display_errors', '0');

if (($_SERVER['REQUEST_URI'] ?? '') === '/healthz') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/includes/addon_public_mutation_dispatch_helpers.php';
require_once __DIR__ . '/includes/addon_public_mutation_frankenphp_ingress_helpers.php';
require_once __DIR__ . '/includes/addon_public_mutation_response_emitter_helpers.php';

if (!function_exists('red_dispatch_fixture_manifest')) {
    function red_dispatch_fixture_manifest()
    {
        return [
            'id' => 'redcms.dispatch-fixture',
            'routes' => [[
                'id' => 'redcms.dispatch-fixture/cart-intent',
                'scope' => 'public',
                'path' => '/addons/redcms/dispatch-fixture/cart-intent',
                'methods' => ['POST'],
                'authentication' => 'public',
                'csrf' => 'required',
            ]],
            'publicMutationContracts' => [[
                'route' => 'redcms.dispatch-fixture/cart-intent',
                'mutation' => 'redcms.dispatch-fixture/add-to-cart',
                'scope' => 'public',
                'authentication' => 'public',
                'method' => 'POST',
                'csrf' => 'required',
                'encoding' => 'application/x-www-form-urlencoded',
                'maxBodyBytes' => 128,
                'requestFields' => [
                    [
                        'key' => 'product',
                        'type' => 'identifier',
                        'required' => true,
                        'minLength' => 1,
                        'maxLength' => 120,
                    ],
                    [
                        'key' => 'quantity',
                        'type' => 'positive-integer',
                        'required' => true,
                        'minimum' => 1,
                        'maximum' => 20,
                    ],
                ],
                'subject' => 'anonymous',
                'idempotency' => 'core-issued-key',
                'privacy' => 'no-store',
                'rateLimit' => 'required',
                'tables' => ['RED_Addon_Dispatch_Fixture_Carts'],
                'postcondition' => 'server-derived-state',
                'audit' => 'commerce.cart.item-added',
                'outcomes' => ['accepted', 'unchanged'],
            ]],
        ];
    }
}

if (!function_exists('red_dispatch_fixture_database')) {
    function red_dispatch_fixture_database()
    {
        $host = getenv('RED_FIXTURE_DB_HOST');
        $user = getenv('RED_FIXTURE_DB_USER');
        $password = getenv('RED_FIXTURE_DB_PASS');
        $database = getenv('RED_FIXTURE_DB_NAME');
        $port = (int) (getenv('RED_FIXTURE_DB_PORT') ?: 3306);
        if (!is_string($host) || $host === ''
            || !is_string($user) || $user === ''
            || !is_string($password)
            || !is_string($database) || $database === ''
            || $port < 1 || $port > 65535
        ) {
            return null;
        }
        mysqli_report(MYSQLI_REPORT_OFF);
        $connection = mysqli_init();
        if (!$connection
            || !mysqli_real_connect(
                $connection,
                $host,
                $user,
                $password,
                $database,
                $port
            )
        ) {
            return null;
        }
        mysqli_set_charset($connection, 'utf8mb4');
        return $connection;
    }
}

if (!function_exists('red_dispatch_fixture_state')) {
    function red_dispatch_fixture_state($connection, $subjectRecordId)
    {
        if (!$connection
            || !is_int($subjectRecordId)
            || $subjectRecordId < 1
        ) {
            throw new RuntimeException('Fixture state is unavailable.');
        }
        $statement = mysqli_prepare(
            $connection,
            'SELECT Product, Quantity
             FROM RED_Addon_Dispatch_Fixture_Carts
             WHERE SubjectRecordID=?
             ORDER BY Product ASC'
        );
        if (!$statement) {
            throw new RuntimeException('Fixture state query is unavailable.');
        }
        mysqli_stmt_bind_param($statement, 'i', $subjectRecordId);
        if (!mysqli_stmt_execute($statement)) {
            mysqli_stmt_close($statement);
            throw new RuntimeException('Fixture state query failed.');
        }
        $query = mysqli_stmt_get_result($statement);
        $items = [];
        while ($query && ($row = mysqli_fetch_assoc($query))) {
            $items[] = [
                'product' => (string) ($row['Product'] ?? ''),
                'quantity' => (int) ($row['Quantity'] ?? 0),
            ];
        }
        if ($query) {
            mysqli_free_result($query);
        }
        mysqli_stmt_close($statement);
        return new RED_Addon_Public_Mutation_State(
            $subjectRecordId,
            ['items' => $items]
        );
    }
}

if (!function_exists('red_dispatch_fixture_handler')) {
    function red_dispatch_fixture_handler($connection, $request)
    {
        $product = $request->field('product');
        $quantity = $request->field('quantity');
        if (!is_string($product) || !is_int($quantity)) {
            throw new RuntimeException('Fixture command is invalid.');
        }
        $subjectRecordId = $request->subjectRecordId();
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Dispatch_Fixture_Carts
                (SubjectRecordID, Product, Quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE Quantity=Quantity+VALUES(Quantity)'
        );
        if (!$statement) {
            throw new RuntimeException('Fixture write is unavailable.');
        }
        mysqli_stmt_bind_param(
            $statement,
            'isi',
            $subjectRecordId,
            $product,
            $quantity
        );
        if (!mysqli_stmt_execute($statement)) {
            mysqli_stmt_close($statement);
            throw new RuntimeException('Fixture write failed.');
        }
        mysqli_stmt_close($statement);
        return RED_Addon_Public_Mutation_Execution_Result::accepted(
            red_dispatch_fixture_state($connection, $subjectRecordId)
        );
    }
}

if (!function_exists('red_dispatch_fixture_runtime_context')) {
    function red_dispatch_fixture_runtime_context(array $manifest)
    {
        $packageId = $manifest['id'];
        $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
        $registry->registerRoute(
            'redcms.dispatch-fixture/cart-intent',
            static function () {
                return null;
            }
        );
        $registry->registerPublicMutation(
            'redcms.dispatch-fixture/add-to-cart',
            'red_dispatch_fixture_handler',
            ['RED_Addon_Dispatch_Fixture_Carts']
        );
        $registry->registerPublicMutationStateLoader(
            'redcms.dispatch-fixture/add-to-cart',
            static function ($connection, $command) {
                return red_dispatch_fixture_state(
                    $connection,
                    $command->subjectRecordId()
                );
            }
        );
        return red_addon_runtime_set_request_context(
            new RED_Addon_Runtime_Context(
                [$packageId],
                [$packageId => $registry]
            )
        );
    }
}

if (!function_exists('red_dispatch_fixture_json')) {
    function red_dispatch_fixture_json($status, array $payload)
    {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$manifest = red_dispatch_fixture_manifest();
$packageId = $manifest['id'];
$routeId = 'redcms.dispatch-fixture/cart-intent';
$mutationId = 'redcms.dispatch-fixture/add-to-cart';

if (($_SERVER['REQUEST_URI'] ?? '') === '/__fixture/bootstrap') {
    $bootstrapSecret = getenv('RED_FIXTURE_BOOTSTRAP_SECRET');
    $providedSecret = $_SERVER['HTTP_X_RED_CMS_FIXTURE_SECRET'] ?? '';
    if (!is_string($bootstrapSecret)
        || $bootstrapSecret === ''
        || !is_string($providedSecret)
        || !hash_equals($bootstrapSecret, $providedSecret)
    ) {
        red_dispatch_fixture_json(404, ['ok' => false]);
    }
    $connection = red_dispatch_fixture_database();
    if (!$connection) {
        red_dispatch_fixture_json(503, ['ok' => false]);
    }
    red_dispatch_fixture_runtime_context($manifest);
    $plan = red_addon_public_mutation_declaration_preflight(
        $manifest,
        $routeId,
        $mutationId
    );
    if (!red_addon_public_mutation_declaration_preflight_is_valid($plan)) {
        red_dispatch_fixture_json(503, ['ok' => false]);
    }
    $subject = red_addon_public_mutation_subject_issue($connection);
    $csrf = red_addon_public_mutation_csrf_issue(
        $connection,
        $subject,
        $plan
    );
    $idempotency = red_addon_public_mutation_idempotency_issue(
        $connection,
        $subject,
        $plan
    );
    if (empty($subject['valid'])
        || empty($csrf['valid'])
        || empty($idempotency['valid'])
    ) {
        red_dispatch_fixture_json(503, ['ok' => false]);
    }
    red_dispatch_fixture_json(200, [
        'ok' => true,
        'subjectToken' => $subject['cookie']['value'],
        'csrfToken' => $csrf['token'],
        'idempotencyKey' => $idempotency['key'],
    ]);
}

$connection = red_dispatch_fixture_database();
if (!$connection) {
    red_dispatch_fixture_json(503, ['ok' => false]);
}
red_dispatch_fixture_runtime_context($manifest);
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$requestTarget = $_SERVER['REQUEST_URI'] ?? '';
$capture = red_addon_public_mutation_frankenphp_ingress_capture_current();
$result = red_addon_public_mutation_dispatch(
    $connection,
    $method,
    $requestTarget,
    $capture
);
if (!is_array($result)
    || empty($result['claimed'])
    || !is_array($result['response'] ?? null)
) {
    red_dispatch_fixture_json(404, ['ok' => false]);
}
red_addon_public_mutation_response_emit($result['response']);

?>
