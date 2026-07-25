<?php
/** Live, data-free checks for theme activation/content-write serialization. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/config.php';
require_once $repositoryRoot . '/class/class_connection.php';
require_once $repositoryRoot . '/includes/admin_transaction_helpers.php';
require_once $repositoryRoot . '/includes/theme_activation_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';
require_once $repositoryRoot . '/includes/admin_article_helpers.php';
require_once $repositoryRoot . '/includes/admin_advanced_helpers.php';

$assertions = 0;
$assert = static function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$first = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$second = new connection(DBHOST, DBUSER, DBPASS, DBNAME);

try {
    $firstName = red_admin_theme_contract_lock_name($first->connection);
    $secondName = red_admin_theme_contract_lock_name($second->connection);
    $assert($firstName !== '' && $firstName === $secondName, 'both connections derive one database-scoped lock');
    $assert(strlen($firstName) <= 64, 'advisory lock name stays within the MySQL limit');

    $state = red_theme_activation_read_state($first->connection, false, true);
    $requestedThemeId = !empty($state['persisted'])
        ? (string) $state['activeThemeId']
        : 'legacy-bootstrap';
    $runtime = red_theme_runtime_bootstrap(
        $requestedThemeId,
        $repositoryRoot,
        'legacy-bootstrap',
        true
    );
    $layoutContract = red_theme_active_layout_contract($first->connection, $repositoryRoot);
    $assert(
        $layoutContract['requestedThemeId'] === $requestedThemeId
            && $layoutContract['themeId'] === $runtime['themeId'],
        'active admin layout choices use the same effective theme as the public runtime'
    );
    $assert(
        ($layoutContract['manifest']['id'] ?? '') === ($runtime['manifest']['id'] ?? ''),
        'active layout manifest follows runtime fallback selection'
    );
    $placeholder = red_admin_article_upload_placeholder_contract($first->connection, 'sp');
    $assert(
        is_array($placeholder)
            && isset($layoutContract['catalog'][$placeholder['Layout']])
            && $placeholder['PagePosition'] === 0
            && $placeholder['Active'] === 'N'
            && $placeholder['Language'] === 'sp',
        'new-item uploads stage an inactive, hidden placeholder under an effective canonical layout'
    );
    if (!empty($state['persisted'])) {
        $activeRecordId = (int) $state['activeRecordId'];
        $assert(
            red_admin_advanced_update_content(
                $first->connection,
                $activeRecordId,
                'System_Active_Theme',
                'forged-theme-id'
            ) === false,
            'generic Advanced writes cannot mutate the reserved active-theme row'
        );
        $assert(
            red_admin_advanced_update_logo(
                $first->connection,
                $activeRecordId,
                'forged-logo-name.png'
            ) === false,
            'logo writes cannot target the reserved active-theme row'
        );
        $stateAfterDeniedWrites = red_theme_activation_read_state($first->connection);
        $assert(
            $stateAfterDeniedWrites === $state,
            'denied generic and logo writes leave paired theme state unchanged'
        );
    }

    $secondCallbackRan = false;
    $outer = red_admin_with_theme_contract_lock(
        $first->connection,
        function () use ($first, $second, &$secondCallbackRan, $assert) {
            $nested = red_admin_with_theme_contract_lock(
                $first->connection,
                static function () {
                    return 'nested';
                },
                0
            );
            $assert($nested === 'nested', 'same-connection acquisition is reentrant');

            $blocked = red_admin_with_theme_contract_lock(
                $second->connection,
                function () use (&$secondCallbackRan) {
                    $secondCallbackRan = true;
                    return true;
                },
                0
            );
            $assert($blocked === false, 'second connection fails closed while the mutex is held');
            return true;
        },
        0
    );
    $assert($outer === true && !$secondCallbackRan, 'blocked callbacks do not execute');

    $afterRelease = red_admin_with_theme_contract_lock(
        $second->connection,
        static function () {
            return 'released';
        },
        0
    );
    $assert($afterRelease === 'released', 'a second connection acquires after outer release');

    $caught = false;
    try {
        red_admin_with_theme_contract_lock(
            $first->connection,
            static function () {
                throw new LogicException('lock-release-fixture');
            },
            0
        );
    } catch (LogicException $exception) {
        $caught = $exception->getMessage() === 'lock-release-fixture';
    }
    $assert($caught, 'callback exceptions propagate to the caller');
    $assert(
        red_admin_with_theme_contract_lock(
            $second->connection,
            static function () {
                return true;
            },
            0
        ) === true,
        'callback exceptions still release the mutex'
    );

    echo 'Theme contract lock self-test passed: ' . $assertions . " assertions.\n";
} finally {
    $first->close();
    $second->close();
}
