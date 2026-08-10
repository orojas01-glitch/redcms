<?php
/**
 * Disposable checks for core-owned public component form integration.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot .
    '/includes/addon_public_component_form_integration_helpers.php';
require_once $projectRoot .
    '/includes/addon_public_mutation_page_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_public_component_form)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Component-form integration self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$subjectIds = [];
$callbackInvocations = 0;
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_component_form_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_component_form_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_component_form_test_counts($connection)
{
    return red_addon_component_form_test_scalar(
        $connection,
        'SELECT CONCAT_WS(CHAR(58),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys)
         )'
    );
}

function red_addon_component_form_test_manifest()
{
    return [
        'id' => 'redcms.component-form-fixture',
        'provides' => [
            'components' => ['redcms.component-form-fixture/product'],
            'services' => [],
            'adminTools' => [],
            'adapters' => [],
        ],
        'routes' => [[
            'id' => 'redcms.component-form-fixture/cart-intent',
            'scope' => 'public',
            'path' => '/addons/redcms/component-form-fixture/cart-intent',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => 'redcms.component-form-fixture/cart-intent',
            'mutation' => 'redcms.component-form-fixture/add-to-cart',
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 512,
            'requestFields' => [[
                'key' => 'product',
                'type' => 'identifier',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 64,
            ], [
                'key' => 'quantity',
                'type' => 'positive-integer',
                'required' => true,
                'minimum' => 1,
                'maximum' => 100,
            ], [
                'key' => 'variant',
                'type' => 'identifier',
                'required' => false,
                'minLength' => 1,
                'maxLength' => 64,
            ]],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => ['RED_Addon_Component_Form_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_component_form_test_context()
{
    return [
        'component' => 'redcms.component-form-fixture/product',
        'active' => true,
        'inputs' => [
            'recordId' => 42,
            'layout' => 'homepage',
            'article' => 'store',
            'position' => 1,
        ],
    ];
}

function red_addon_component_form_test_view($variable = false)
{
    $fields = [[
        'key' => 'product',
        'control' => 'hidden',
        'value' => $variable ? 'studio-shirt' : 'banana-bunch',
    ], [
        'key' => 'quantity',
        'control' => 'number',
        'label' => 'Quantity',
        'value' => 1,
    ]];
    if ($variable) {
        $fields[] = [
            'key' => 'variant',
            'control' => 'select',
            'label' => 'Options',
            'value' => 'shirt-medium-blue',
            'options' => [[
                'value' => 'shirt-small-red',
                'label' => 'Size: Small · Color: Red',
            ], [
                'value' => 'shirt-medium-blue',
                'label' => 'Size: Medium · Color: Blue',
            ]],
        ];
    }
    return [
        'title' => $variable ? 'Studio shirt' : 'Banana bunch',
        'summary' => 'Current public product.',
        'facts' => [[
            'label' => 'Availability',
            'value' => 'Available',
        ]],
        'mutationForm' => [
            'route' => 'redcms.component-form-fixture/cart-intent',
            'mutation' => 'redcms.component-form-fixture/add-to-cart',
            'submitLabel' => 'Add to cart',
            'fields' => $fields,
        ],
    ];
}

try {
    $manifest = red_addon_component_form_test_manifest();
    $packageId = $manifest['id'];
    $componentId = $manifest['provides']['components'][0];
    $routeId = $manifest['routes'][0]['id'];
    $mutationId = $manifest['publicMutationContracts'][0]['mutation'];
    $callback = static function () use (&$callbackInvocations) {
        $callbackInvocations++;
        throw new RuntimeException('Integration invoked a package callback.');
    };
    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $registry->registerComponent($componentId, $callback);
    $registry->registerRoute($routeId, $callback);
    $registry->registerPublicMutation(
        $mutationId,
        $callback,
        ['RED_Addon_Component_Form_Fixture_Carts']
    );
    $registry->registerPublicMutationStateLoader($mutationId, $callback);
    $registry->assertComplete();
    red_addon_runtime_set_request_context(
        new RED_Addon_Runtime_Context(
            [$packageId],
            [$packageId => $registry]
        )
    );

    red_addon_component_form_test_assert(
        red_addon_component_form_test_counts($connection) === '0:0:0',
        'component form fixture starts with empty evidence storage'
    );

    $withoutPresentation = red_addon_component_form_test_view();
    unset($withoutPresentation['mutationForm']);
    $absent = red_addon_public_component_form_integrate(
        $connection,
        red_addon_component_form_test_context(),
        $withoutPresentation,
        str_repeat('x', 300)
    );
    red_addon_component_form_test_assert(
        red_addon_public_component_form_integration_valid($absent)
            && $absent['valid'] === true
            && $absent['available'] === false
            && $absent['reason'] === 'presentation_absent'
            && red_addon_component_form_test_counts($connection) === '0:0:0',
        'a display-only component returns no form and issues no evidence'
    );

    $malformed = red_addon_component_form_test_view();
    $malformed['mutationForm']['csrfToken'] = str_repeat('a', 64);
    $invalid = red_addon_public_component_form_integrate(
        $connection,
        red_addon_component_form_test_context(),
        $malformed
    );
    red_addon_component_form_test_assert(
        red_addon_public_component_form_integration_valid($invalid)
            && $invalid['reason'] === 'integration_invalid'
            && red_addon_component_form_test_counts($connection) === '0:0:0',
        'raw package authority invalidates the view before evidence issuance'
    );

    $foreign = red_addon_component_form_test_view();
    $foreign['mutationForm']['route'] = 'redcms.foreign/cart-intent';
    $ownershipRefusal = red_addon_public_component_form_integrate(
        $connection,
        red_addon_component_form_test_context(),
        $foreign
    );
    red_addon_component_form_test_assert(
        red_addon_public_component_form_integration_valid($ownershipRefusal)
            && $ownershipRefusal['reason'] === 'ownership_invalid'
            && red_addon_component_form_test_counts($connection) === '0:0:0',
        'cross-package route ownership fails before evidence issuance'
    );

    ob_start();
    $simple = red_addon_public_component_form_integrate(
        $connection,
        red_addon_component_form_test_context(),
        red_addon_component_form_test_view()
    );
    $emitted = (string) ob_get_clean();
    $subjectIds[] = $simple['lifecycle']['subjectRecordId'] ?? 0;
    red_addon_component_form_test_assert(
        red_addon_public_component_form_integration_valid($simple)
            && $simple['available'] === true
            && $simple['package'] === $packageId
            && $simple['lifecycle']['state'] === 'issued'
            && $simple['formModel']['instanceId'] === 'component-42'
            && array_column($simple['formModel']['fields'], 'key')
                === ['product', 'quantity']
            && $emitted === ''
            && red_addon_component_form_test_counts($connection) === '1:1:1',
        'simple integration returns one silent core form and issued lifecycle'
    );
    red_addon_component_form_test_assert(
        str_contains(
            $simple['formHtml'],
            '<form id="red-public-mutation-component-42"'
        )
            && str_contains(
                $simple['formHtml'],
                'name="product" value="banana-bunch"'
            )
            && str_contains($simple['formHtml'], 'name="quantity"')
            && str_contains($simple['formHtml'], 'data-red-csrf-token=')
            && str_contains($simple['formHtml'], 'data-red-idempotency-key=')
            && str_contains($simple['formHtml'], 'aria-live="polite"'),
        'core renders semantic fetch-controller markup from the simple model'
    );
    red_addon_component_form_test_assert(
        preg_match(
            '/\Aredcms_public_mutation_subject=([a-f0-9]{64});/',
            $simple['lifecycle']['setCookieValue'],
            $cookieMatches
        ) === 1
            && !str_contains($simple['formHtml'], $cookieMatches[1]),
        'the issued subject cookie value stays outside returned form markup'
    );

    $variable = red_addon_public_component_form_integrate(
        $connection,
        red_addon_component_form_test_context(),
        red_addon_component_form_test_view(true),
        $cookieMatches[1]
    );
    red_addon_component_form_test_assert(
        red_addon_public_component_form_integration_valid($variable)
            && $variable['lifecycle']['state'] === 'resolved'
            && $variable['lifecycle']['setCookieValue'] === ''
            && $variable['lifecycle']['subjectRecordId']
                === $simple['lifecycle']['subjectRecordId']
            && count($variable['formModel']['fields'][2]['options']) === 2
            && str_contains(
                $variable['formHtml'],
                'Size: Medium · Color: Blue'
            )
            && red_addon_component_form_test_counts($connection) === '1:2:2',
        'variable integration reuses the subject and renders bounded choices'
    );

    $tampered = $variable;
    $tampered['formHtml'] .= '<script>forged</script>';
    red_addon_component_form_test_assert(
        !red_addon_public_component_form_integration_valid($tampered),
        'result validation rejects markup not derived from the exact core model'
    );
    red_addon_component_form_test_assert(
        $callbackInvocations === 0,
        'component integration resolves ownership without invoking package callbacks'
    );

    $beforePageCounts = red_addon_component_form_test_counts($connection);
    $duplicateCookie = red_addon_public_mutation_subject_cookie_name() . '='
        . str_repeat('a', 64) . '; '
        . red_addon_public_mutation_subject_cookie_name() . '='
        . str_repeat('b', 64);
    $invalidPage = red_addon_public_mutation_page_begin(
        true,
        $duplicateCookie
    );
    red_addon_component_form_test_assert(
        $invalidPage['enabled'] === false
            && $invalidPage['reason'] === 'cookie_invalid'
            && red_addon_public_mutation_page_delivery()['active'] === false
            && red_addon_component_form_test_counts($connection)
                === $beforePageCounts,
        'duplicate raw subject cookies disable page forms without issuing evidence'
    );

    red_addon_public_mutation_page_begin(true, '');
    $pageSimple = red_addon_public_mutation_page_integrate(
        $connection,
        red_addon_component_form_test_context(),
        red_addon_component_form_test_view()
    );
    $subjectIds[] = $pageSimple['lifecycle']['subjectRecordId'] ?? 0;
    $firstDelivery = red_addon_public_mutation_page_delivery();
    $pageSubject = red_addon_public_mutation_page_subject_context($connection);
    red_addon_component_form_test_assert(
        red_addon_public_component_form_integration_valid($pageSimple)
            && $pageSimple['available'] === true
            && red_addon_public_mutation_page_delivery_valid($firstDelivery)
            && $firstDelivery['active'] === true
            && $firstDelivery['formCount'] === 1
            && $firstDelivery['lifecycle']['state'] === 'issued'
            && red_addon_public_mutation_page_controller_tag($firstDelivery)
                === '<script src="/js/public-addon-mutation.js" defer></script>'
            && red_addon_public_mutation_subject_cookie_emitter_values(
                $firstDelivery['lifecycle']
            ) === [$firstDelivery['lifecycle']['setCookieValue']],
        'the first accepted page form owns one issued subject, cookie, and controller delivery'
    );
    red_addon_component_form_test_assert(
        $pageSubject === [
            'valid' => true,
            'subjectRecordId' => $pageSimple['lifecycle']['subjectRecordId'],
            'reason' => 'subject_resolved',
        ]
            && red_addon_component_form_test_counts($connection) === '2:3:3',
        'a package read model receives only the core-resolved current subject'
    );

    $pageVariable = red_addon_public_mutation_page_integrate(
        $connection,
        red_addon_component_form_test_context(),
        red_addon_component_form_test_view(true)
    );
    $secondDelivery = red_addon_public_mutation_page_delivery();
    red_addon_component_form_test_assert(
        red_addon_public_component_form_integration_valid($pageVariable)
            && $pageVariable['lifecycle']['state'] === 'resolved'
            && $pageVariable['lifecycle']['subjectRecordId']
                === $pageSimple['lifecycle']['subjectRecordId']
            && red_addon_public_mutation_page_delivery_valid($secondDelivery)
            && $secondDelivery['formCount'] === 2
            && $secondDelivery['lifecycle'] === $firstDelivery['lifecycle']
            && red_addon_component_form_test_counts($connection) === '2:4:4',
        'later page forms reuse one subject while retaining the first response lifecycle'
    );

    $GLOBALS['RED_ADDON_PUBLIC_MUTATION_PAGE_CONTEXT']['formCount'] = 129;
    $invalidContext = red_addon_public_mutation_page_context_current();
    $invalidSubject = red_addon_public_mutation_page_subject_context($connection);
    red_addon_component_form_test_assert(
        $invalidContext['enabled'] === false
            && $invalidContext['reason'] === 'page_context_invalid'
            && $invalidSubject === [
                'valid' => false,
                'subjectRecordId' => 0,
                'reason' => 'subject_unavailable',
            ]
            && red_addon_public_mutation_page_delivery()['active'] === false,
        'request-local page coordination fails closed after global state drift'
    );

    $source = file_get_contents(
        $projectRoot .
            '/includes/addon_public_component_form_integration_helpers.php'
    );
    red_addon_component_form_test_assert(
        is_string($source)
            && strpos($source, '$_GET') === false
            && strpos($source, '$_POST') === false
            && strpos($source, '$_COOKIE') === false
            && strpos($source, '$_SESSION') === false
            && strpos($source, 'header(') === false
            && strpos($source, 'setcookie(') === false
            && strpos($source, 'addon.php') === false,
        'integration reads no request globals and has no emission or package path'
    );
    $pageSource = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_page_helpers.php'
    );
    $rendererSource = file_get_contents(
        $projectRoot . '/includes/addon_component_render_helpers.php'
    );
    red_addon_component_form_test_assert(
        is_string($pageSource)
            && is_string($rendererSource)
            && str_contains(
                $rendererSource,
                'red_addon_public_mutation_page_integrate('
            )
            && str_contains($rendererSource, 'echo $formHtml;')
            && !preg_match(
                '/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $rendererSource
            ),
        'the generic component renderer appends only the core-coordinated form result'
    );
} finally {
    $ids = array_values(array_filter(
        array_unique(array_map('intval', $subjectIds)),
        static function ($recordId) {
            return $recordId > 0;
        }
    ));
    if ($ids !== []) {
        mysqli_query(
            $connection,
            'DELETE FROM RED_Addon_Public_Mutation_Subjects WHERE RecordID IN (' .
                implode(',', $ids) . ')'
        );
    }
}

red_addon_component_form_test_assert(
    red_addon_component_form_test_counts($connection) === '0:0:0',
    'component form fixture leaves no subject, CSRF, or idempotency evidence'
);

echo 'Public component form integration self-test passed (' .
    $assertions . " assertions).\n";

?>
