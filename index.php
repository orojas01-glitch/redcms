<?php
$redAddonAssetRequestUri = $_SERVER['REQUEST_URI'] ?? '';
if (is_string($redAddonAssetRequestUri)
    && str_starts_with($redAddonAssetRequestUri, '/_red/addons/')
) {
    require_once __DIR__ . '/includes/runtime_config_helpers.php';
    require_once __DIR__ . '/includes/addon_asset_endpoint_helpers.php';

    $redAddonAssetConnection = null;
    try {
        $redAddonAssetConnection = @mysqli_connect(
            red_config_value('DBHOST', ['RED_DB_HOST', 'DBHOST'], 'localhost'),
            red_config_value('DBUSER', ['RED_DB_USER', 'DBUSER'], ''),
            red_config_value('DBPASS', ['RED_DB_PASS', 'DBPASS'], ''),
            red_config_value('DBNAME', ['RED_DB_NAME', 'DBNAME'], '')
        );
        if (!$redAddonAssetConnection
            || !@mysqli_set_charset($redAddonAssetConnection, 'utf8mb4')
        ) {
            throw new RuntimeException(
                'The add-on asset database connection is unavailable.'
            );
        }
        $redAddonAssetResponse = red_addon_asset_delivery_dispatch(
            $redAddonAssetConnection,
            __DIR__,
            $_SERVER['REQUEST_METHOD'] ?? '',
            $redAddonAssetRequestUri
        );
    } catch (Throwable $exception) {
        error_log(
            'RED-CMS add-on asset delivery failed: ' .
            $exception->getMessage()
        );
        $redAddonAssetResponse = red_addon_asset_delivery_http_error(
            503,
            $_SERVER['REQUEST_METHOD'] ?? ''
        );
    } finally {
        if ($redAddonAssetConnection instanceof mysqli) {
            mysqli_close($redAddonAssetConnection);
        }
    }

    red_addon_asset_delivery_emit($redAddonAssetResponse);
    exit;
}

$redPublicReadMethod = $_SERVER['REQUEST_METHOD'] ?? '';
$redPublicReadTarget = $_SERVER['REQUEST_URI'] ?? '';
if (is_string($redPublicReadMethod)
    && $redPublicReadMethod !== ''
    && is_string($redPublicReadTarget)
    && str_starts_with($redPublicReadTarget, '/addons/')
) {
    require_once __DIR__ . '/includes/runtime_config_helpers.php';
    require_once __DIR__ . '/includes/addon_runtime_helpers.php';
    require_once __DIR__ . '/includes/addon_public_route_helpers.php';

    $redPublicReadConnection = null;
    $redPublicReadRoute = red_addon_public_route_result();
    try {
        $redPublicReadConnection = @mysqli_connect(
            red_config_value(
                'DBHOST',
                ['RED_DB_HOST', 'DBHOST'],
                'localhost'
            ),
            red_config_value('DBUSER', ['RED_DB_USER', 'DBUSER'], ''),
            red_config_value('DBPASS', ['RED_DB_PASS', 'DBPASS'], ''),
            red_config_value('DBNAME', ['RED_DB_NAME', 'DBNAME'], '')
        );
        if (!$redPublicReadConnection
            || !@mysqli_set_charset($redPublicReadConnection, 'utf8mb4')
        ) {
            throw new RuntimeException(
                'The public read-route database connection is unavailable.'
            );
        }
        red_addon_runtime_request_bootstrap(
            $redPublicReadConnection,
            __DIR__
        );
        $redPublicReadPath = red_addon_public_route_path(
            $redPublicReadTarget
        );
        $redPublicReadDeclaration = $redPublicReadPath !== null
            ? red_addon_public_route_declaration($redPublicReadPath)
            : null;
        $redPublicReadContract = is_array($redPublicReadDeclaration)
            ? ($redPublicReadDeclaration['route'] ?? null)
            : null;
        if (is_array($redPublicReadContract)
            && ($redPublicReadContract['scope'] ?? null) === 'public'
            && ($redPublicReadContract['authentication'] ?? null) === 'public'
            && ($redPublicReadContract['csrf'] ?? null) === 'not-applicable'
            && in_array('GET', $redPublicReadContract['methods'] ?? [], true)
        ) {
            $redPublicReadRoute = red_addon_public_route_dispatch(
                $redPublicReadMethod,
                $redPublicReadTarget,
                red_addon_public_route_query($redPublicReadTarget)
            );
        }
    } catch (Throwable $exception) {
        error_log(
            'RED-CMS public add-on read route failed: ' .
            $exception->getMessage()
        );
        $redPublicReadRoute = red_addon_public_route_response(
            red_addon_public_route_result('runtime_unavailable'),
            503,
            ['ok' => false, 'error' => 'temporarily_unavailable'],
            'runtime_unavailable'
        );
        $redPublicReadRoute['claimed'] = true;
    } finally {
        if ($redPublicReadConnection instanceof mysqli) {
            mysqli_close($redPublicReadConnection);
        }
    }
    if (!empty($redPublicReadRoute['claimed'])) {
        red_addon_public_route_emit($redPublicReadRoute);
        exit;
    }
}

$redPublicMutationMethod = $_SERVER['REQUEST_METHOD'] ?? '';
$redPublicMutationTarget = $_SERVER['REQUEST_URI'] ?? '';
if (is_string($redPublicMutationMethod)
    && $redPublicMutationMethod !== ''
    && is_string($redPublicMutationTarget)
    && str_starts_with($redPublicMutationTarget, '/addons/')
) {
    require_once __DIR__ . '/includes/runtime_config_helpers.php';
    require_once __DIR__ . '/includes/addon_runtime_helpers.php';
    require_once __DIR__ .
        '/includes/addon_public_mutation_endpoint_helpers.php';

    $redPublicMutationEndpoint =
        red_addon_public_mutation_endpoint_dispatch(
            null,
            $redPublicMutationMethod,
            $redPublicMutationTarget,
            red_addon_public_mutation_server_request_result(
                'transport_unavailable'
            ),
            false
        );
    if (red_addon_public_mutation_endpoint_enabled()) {
        $redPublicMutationConnection = null;
        try {
            $redPublicMutationConnection = @mysqli_connect(
                red_config_value(
                    'DBHOST',
                    ['RED_DB_HOST', 'DBHOST'],
                    'localhost'
                ),
                red_config_value('DBUSER', ['RED_DB_USER', 'DBUSER'], ''),
                red_config_value('DBPASS', ['RED_DB_PASS', 'DBPASS'], ''),
                red_config_value('DBNAME', ['RED_DB_NAME', 'DBNAME'], '')
            );
            if (!$redPublicMutationConnection
                || !@mysqli_set_charset(
                    $redPublicMutationConnection,
                    'utf8mb4'
                )
            ) {
                throw new RuntimeException(
                    'The public-mutation database connection is unavailable.'
                );
            }
            red_addon_runtime_request_bootstrap(
                $redPublicMutationConnection,
                __DIR__
            );
            $redPublicMutationEndpoint =
                red_addon_public_mutation_endpoint_dispatch_current(
                    $redPublicMutationConnection
                );
        } catch (Throwable $exception) {
            error_log(
                'RED-CMS public-mutation endpoint failed: ' .
                $exception->getMessage()
            );
            $redPublicMutationEndpoint =
                red_addon_public_mutation_endpoint_result(
                    'endpoint_unavailable'
                );
            $redPublicMutationEndpoint['claimed'] = true;
            $redPublicMutationEndpoint['response'] =
                red_addon_public_mutation_response_refusal(
                    'runtime_unavailable'
                );
        } finally {
            if ($redPublicMutationConnection instanceof mysqli) {
                mysqli_close($redPublicMutationConnection);
            }
        }
    }
    if (!empty($redPublicMutationEndpoint['claimed'])) {
        red_addon_public_mutation_endpoint_emit(
            $redPublicMutationEndpoint
        );
        exit;
    }
}

/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 5.1 - (2026/08/14)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.tv/documentation/ 
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/theme_runtime.php';
require_once __DIR__ . '/includes/theme_activation_helpers.php';

$redThemeRequestBufferLevel = ob_get_level();
ob_start();

try {
    $redThemeRequestedId = red_theme_activation_active_id_from_project(__DIR__);
    $redThemeRuntime = red_theme_runtime_bootstrap(
        $redThemeRequestedId,
        __DIR__,
        'legacy-bootstrap',
        true
    );
    $redThemeAdapter = $redThemeRuntime['adapter'];
} catch (Throwable $exception) {
    while (ob_get_level() > $redThemeRequestBufferLevel) {
        ob_end_clean();
    }
    error_log('RED-CMS theme bootstrap failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Theme rendering is temporarily unavailable.');
}

red_start_session();
$timezone = $_SESSION['time'] ?? 'America/New_York'; // default timezone
?>
<?php
/*COMMENTS:
class_connection.php: contains database connection
config.php  contains settings about language & ip location.*/
?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php
/*COMMENTS:
class_build_page.php: integrates class calls.
class_build_query.php: generates queries for all website structures.
class class_page_layout.php: write the html of the different layouts.
class_limit.php: gets the limit per section, category, subcategories, and articles.
class_layout.php: call page layout.
class_content.php: call all components.*/
?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_build_query.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_build_page.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_layout.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_limit.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_page_layout.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_metatags.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_pagetitle.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_main_menu.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_content.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_article.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_other.php' ?>


<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_gallery.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_forms.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_build_breadcrumb.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_feature_slider.php' ?>

<?php
require_once __DIR__ . '/includes/addon_runtime_helpers.php';
require_once __DIR__ . '/includes/addon_public_route_helpers.php';
require_once __DIR__ . '/includes/addon_asset_injection_helpers.php';
require_once __DIR__ . '/includes/addon_public_mutation_endpoint_helpers.php';
require_once __DIR__ . '/includes/addon_public_mutation_page_helpers.php';

$redAddonRuntimeConnection = null;
$redAddonAssetInjection = red_addon_asset_injection_result(false);
try {
    $redAddonRuntimeConnection = mysqli_connect(
        DBHOST,
        DBUSER,
        DBPASS,
        DBNAME
    );
    if (!$redAddonRuntimeConnection
        || !mysqli_set_charset($redAddonRuntimeConnection, 'utf8mb4')
    ) {
        throw new RuntimeException(
            'The add-on runtime database connection is unavailable.'
        );
    }
    $redAddonRuntimeContext = red_addon_runtime_request_bootstrap(
        $redAddonRuntimeConnection,
        __DIR__
    );
    $redAddonAssetInjection = red_addon_asset_injection_plan(
        $redAddonRuntimeConnection,
        __DIR__,
        isset($_SESSION['alias'])
            && is_string($_SESSION['alias'])
            && trim($_SESSION['alias']) !== ''
    );
    $redPublicMutationCookieHeader = $_SERVER['HTTP_COOKIE'] ?? '';
    red_addon_public_mutation_page_begin(
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
            && red_addon_public_mutation_endpoint_page_enabled_current(),
        is_string($redPublicMutationCookieHeader)
            ? $redPublicMutationCookieHeader
            : ''
    );
} catch (Throwable $exception) {
    while (ob_get_level() > $redThemeRequestBufferLevel) {
        ob_end_clean();
    }
    error_log(
        'RED-CMS add-on request bootstrap failed: ' .
        $exception->getMessage()
    );
    http_response_code(503);
    exit('Site extensions are temporarily unavailable.');
} finally {
    if ($redAddonRuntimeConnection instanceof mysqli) {
        mysqli_close($redAddonRuntimeConnection);
    }
}

$redAddonPublicRoute = red_addon_public_route_dispatch(
    $_SERVER['REQUEST_METHOD'] ?? '',
    $_SERVER['REQUEST_URI'] ?? '',
    red_addon_public_route_query($_SERVER['REQUEST_URI'] ?? '')
);
if (!empty($redAddonPublicRoute['claimed'])) {
    while (ob_get_level() > $redThemeRequestBufferLevel) {
        ob_end_clean();
    }
    red_addon_public_route_emit($redAddonPublicRoute);
    exit;
}
?>

<?php try {
ob_start();
$redThemeAdapter->renderDocumentStart();
$redThemeDocumentStart = ob_get_clean();
echo red_addon_asset_injection_insert_document(
    $redThemeDocumentStart,
    $redAddonAssetInjection,
    'head'
);
?>
<?php $redThemeAdapter->renderHeaderBundle(); ?>


<!--==============================content================================-->

<?php 
// GET ALL CONTENT FOR THIS PAGE.
// 1. /class/class_build_page.asp -- get_page_query()
// 2. /class/class_build_query.php -- get_query()
// 3. /class/class_page_layout.php -- layout()
// 3a. /class/limit.php -- get_limit()
// 3b. /class/class_layout.php -- get_layout()
// 3c. /class/class_content.php -- cp_article()
$page=new Build_Page();
$page->get_page_query();
 ?>

<!--==============================footer=================================-->
<?php $redThemeAdapter->renderFooter(); ?>

<?php
$redPublicMutationPageDelivery =
    red_addon_public_mutation_page_delivery();
echo red_addon_public_mutation_page_controller_tag(
    $redPublicMutationPageDelivery
);
ob_start();
$redThemeAdapter->renderDocumentEnd();
$redThemeDocumentEnd = ob_get_clean();
echo red_addon_asset_injection_insert_document(
    $redThemeDocumentEnd,
    $redAddonAssetInjection,
    'body-end'
);
if (!empty($redPublicMutationPageDelivery['active'])) {
    red_addon_public_mutation_page_emit_cookie(
        $redPublicMutationPageDelivery
    );
}
ob_end_flush();
} catch (Throwable $exception) {
    while (ob_get_level() > $redThemeRequestBufferLevel) {
        ob_end_clean();
    }
    error_log('RED-CMS active theme render failed; using legacy-bootstrap: ' . $exception->getMessage());
    if (($redThemeRuntime['themeId'] ?? '') === 'legacy-bootstrap') {
        http_response_code(500);
        exit('Theme rendering is temporarily unavailable.');
    }

    try {
        http_response_code(200);
        $redThemeRuntime = red_theme_runtime_bootstrap('legacy-bootstrap', __DIR__);
        $redThemeAdapter = $redThemeRuntime['adapter'];
        ob_start();
        ob_start();
        $redThemeAdapter->renderDocumentStart();
        $redThemeDocumentStart = ob_get_clean();
        echo red_addon_asset_injection_insert_document(
            $redThemeDocumentStart,
            $redAddonAssetInjection,
            'head'
        );
        $redThemeAdapter->renderHeaderBundle();
        echo "\n\n<!--==============================content================================-->\n\n";
        $page = new Build_Page();
        $page->get_page_query();
        echo "\n\n<!--==============================footer=================================-->\n";
        $redThemeAdapter->renderFooter();
        $redPublicMutationPageDelivery =
            red_addon_public_mutation_page_delivery();
        echo red_addon_public_mutation_page_controller_tag(
            $redPublicMutationPageDelivery
        );
        ob_start();
        $redThemeAdapter->renderDocumentEnd();
        $redThemeDocumentEnd = ob_get_clean();
        echo red_addon_asset_injection_insert_document(
            $redThemeDocumentEnd,
            $redAddonAssetInjection,
            'body-end'
        );
        if (!empty($redPublicMutationPageDelivery['active'])) {
            red_addon_public_mutation_page_emit_cookie(
                $redPublicMutationPageDelivery
            );
        }
        ob_end_flush();
    } catch (Throwable $fallbackException) {
        while (ob_get_level() > $redThemeRequestBufferLevel) {
            ob_end_clean();
        }
        error_log('RED-CMS legacy theme recovery failed: ' . $fallbackException->getMessage());
        http_response_code(500);
        exit('Theme rendering is temporarily unavailable.');
    }
}
?>
