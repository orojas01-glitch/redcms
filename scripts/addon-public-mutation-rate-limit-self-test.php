<?php
/**
 * Disposable checks for the core-only public-mutation rate-limit foundation.
 * No public request or package fixture is created here.
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
    '/includes/addon_public_mutation_rate_limit_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_public_mutation_rate_limit)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Public-mutation rate-limit self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$subjectIds = [];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_public_mutation_rate_limit_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_rate_limit_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_public_mutation_rate_limit_test_manifest(
    $packageId,
    $routeId,
    $mutationId,
    $path
) {
    return [
        'id' => $packageId,
        'routes' => [[
            'id' => $routeId,
            'scope' => 'public',
            'path' => $path,
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => $routeId,
            'mutation' => $mutationId,
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 1024,
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
                    'maximum' => 100,
                ],
            ],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => ['RED_Addon_Public_Rate_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_public_mutation_rate_limit_test_cleanup(
    $connection,
    array $subjectIds
) {
    $ids = array_values(array_filter(
        array_unique(array_map('intval', $subjectIds)),
        static function ($recordId) {
            return $recordId > 0;
        }
    ));
    if ($ids === []) {
        return;
    }
    $list = implode(',', $ids);
    try {
        mysqli_query(
            $connection,
            'DELETE FROM RED_Addon_Public_Mutation_Rate_Limits ' .
                "WHERE SubjectRecordID IN ($list)"
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Addon_Public_Mutation_CSRF_Tokens ' .
                "WHERE SubjectRecordID IN ($list)"
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Addon_Public_Mutation_Subjects ' .
                "WHERE RecordID IN ($list)"
        );
    } catch (Throwable $throwable) {
        error_log(
            'Public-mutation rate-limit cleanup failed: ' .
                $throwable->getMessage()
        );
    }
}

try {
    $policy = red_addon_public_mutation_rate_limit_policy();
    red_addon_public_mutation_rate_limit_test_assert(
        red_addon_public_mutation_subject_storage_available($connection)
            && red_addon_public_mutation_rate_limit_storage_available($connection)
            && $policy === [
                'windowSeconds' => 60,
                'maxRequests' => 12,
                'cleanupLimit' => 100,
            ],
        'the disposable client has exact short-lived core rate-limit storage and fixed policy'
    );
    red_addon_public_mutation_rate_limit_test_assert(
        red_addon_valid_public_mutation_table(
            'RED_Addon_Public_Rate_Fixture_Carts'
        )
            && !red_addon_valid_public_mutation_table(
                'RED_Addon_Public_Mutation_Rate_Limits'
            ),
        'package declarations cannot claim core-owned rate-limit storage'
    );

    $packageId = 'redcms.public-rate-fixture';
    $routeId = 'redcms.public-rate-fixture/cart-intent';
    $mutationId = 'redcms.public-rate-fixture/add-to-cart';
    $manifest = red_addon_public_mutation_rate_limit_test_manifest(
        $packageId,
        $routeId,
        $mutationId,
        '/addons/redcms/public-rate-fixture/cart-intent'
    );
    $plan = red_addon_public_mutation_declaration_preflight(
        $manifest,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_rate_limit_test_assert(
        red_addon_public_mutation_declaration_preflight_is_valid($plan),
        'rate-limit scope accepts only an already-valid closed declaration plan'
    );

    $cookieSnapshot = $_COOKIE;
    $sessionStatus = session_status();
    $headerSnapshot = headers_list();
    $subject = red_addon_public_mutation_subject_issue($connection);
    $subjectIds[] = $subject['subjectRecordId'] ?? 0;
    red_addon_public_mutation_rate_limit_test_assert(
        !empty($subject['valid'])
            && ($subject['subjectRecordId'] ?? 0) > 0,
        'an opaque active anonymous subject exists for the internal rate decision'
    );
    $scopeSha256 = red_addon_public_mutation_rate_limit_scope_sha256(
        $connection,
        $plan
    );
    red_addon_public_mutation_rate_limit_test_assert(
        red_addon_valid_sha256($scopeSha256)
            && $scopeSha256 !== red_addon_public_mutation_csrf_scope_sha256(
                $connection,
                $plan
            )
            && red_addon_public_mutation_rate_limit_scope_sha256(
                $connection,
                []
            ) === '',
        'rate evidence has a distinct declaration/database-bound opaque scope'
    );

    $invalidSubject = red_addon_public_mutation_rate_limit_claim(
        $connection,
        ['valid' => true, 'subjectRecordId' => 2147000999],
        $plan
    );
    $invalidPlan = red_addon_public_mutation_rate_limit_claim(
        $connection,
        $subject,
        []
    );
    red_addon_public_mutation_rate_limit_test_assert(
        empty($invalidSubject['valid'])
            && ($invalidSubject['reason'] ?? '') === 'rate_limit_subject_invalid'
            && empty($invalidPlan['valid'])
            && ($invalidPlan['reason'] ?? '') === 'rate_limit_scope_invalid'
            && red_addon_public_mutation_rate_limit_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits'
            ) === '0',
        'forged subject handles and undeclared scope data fail closed without a rate row'
    );

    $first = red_addon_public_mutation_rate_limit_claim(
        $connection,
        $subject,
        $plan
    );
    red_addon_public_mutation_rate_limit_test_assert(
        !empty($first['valid'])
            && !empty($first['allowed'])
            && ($first['subjectRecordId'] ?? 0)
                === $subject['subjectRecordId']
            && ($first['scopeSha256'] ?? '') === $scopeSha256
            && ($first['windowSeconds'] ?? 0) === 60
            && ($first['maxRequests'] ?? 0) === 12
            && ($first['retryAfterSeconds'] ?? 0) === 0
            && ($first['reason'] ?? '') === 'rate_limit_claimed'
            && array_keys($first) === [
                'valid', 'allowed', 'subjectRecordId', 'scopeSha256',
                'windowSeconds', 'maxRequests', 'retryAfterSeconds', 'reason',
            ],
        'the first bounded internal rate claim returns opaque policy evidence only'
    );
    red_addon_public_mutation_rate_limit_test_assert(
        $_COOKIE === $cookieSnapshot
            && session_status() === $sessionStatus
            && headers_list() === $headerSnapshot,
        'rate enforcement does not read or mutate browser cookie, session, or response state'
    );

    $claimsAllowed = true;
    for ($index = 1; $index < $policy['maxRequests']; $index++) {
        $claim = red_addon_public_mutation_rate_limit_claim(
            $connection,
            $subject,
            $plan
        );
        $claimsAllowed = $claimsAllowed
            && !empty($claim['valid'])
            && !empty($claim['allowed'])
            && ($claim['reason'] ?? '') === 'rate_limit_claimed';
    }
    $blocked = red_addon_public_mutation_rate_limit_claim(
        $connection,
        $subject,
        $plan
    );
    red_addon_public_mutation_rate_limit_test_assert(
        $claimsAllowed
            && !empty($blocked['valid'])
            && empty($blocked['allowed'])
            && ($blocked['reason'] ?? '') === 'rate_limited'
            && ($blocked['retryAfterSeconds'] ?? 0) >= 1
            && ($blocked['retryAfterSeconds'] ?? 0) <= $policy['windowSeconds']
            && red_addon_public_mutation_rate_limit_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*)
                     FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=" . (int) $subject['subjectRecordId'] . "
                       AND ScopeSHA256='$scopeSha256'),
                    (SELECT RequestCount
                     FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=" . (int) $subject['subjectRecordId'] . "
                       AND ScopeSHA256='$scopeSha256'
                     LIMIT 1)
                 )"
            ) === '1:12',
        'one scope cannot exceed the fixed 12-request window'
    );

    $alternateRoute = 'redcms.public-rate-fixture/cart-update';
    $alternateMutation = 'redcms.public-rate-fixture/update-cart';
    $alternatePlan = red_addon_public_mutation_declaration_preflight(
        red_addon_public_mutation_rate_limit_test_manifest(
            $packageId,
            $alternateRoute,
            $alternateMutation,
            '/addons/redcms/public-rate-fixture/cart-update'
        ),
        $alternateRoute,
        $alternateMutation
    );
    $alternateScope = red_addon_public_mutation_rate_limit_scope_sha256(
        $connection,
        $alternatePlan
    );
    $alternateClaim = red_addon_public_mutation_rate_limit_claim(
        $connection,
        $subject,
        $alternatePlan
    );
    red_addon_public_mutation_rate_limit_test_assert(
        red_addon_valid_sha256($alternateScope)
            && $alternateScope !== $scopeSha256
            && !empty($alternateClaim['allowed'])
            && red_addon_public_mutation_rate_limit_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits '
                    . 'WHERE SubjectRecordID=' .
                        (int) $subject['subjectRecordId']
            ) === '2',
        'a different declared route and mutation receives its own opaque rate window'
    );

    mysqli_begin_transaction($connection);
    $nested = red_addon_public_mutation_rate_limit_claim(
        $connection,
        $subject,
        $alternatePlan
    );
    mysqli_rollback($connection);
    red_addon_public_mutation_rate_limit_test_assert(
        empty($nested['valid'])
            && ($nested['reason'] ?? '') === 'transaction_already_active'
            && red_addon_public_mutation_rate_limit_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits '
                    . 'WHERE SubjectRecordID=' .
                        (int) $subject['subjectRecordId']
            ) === '2',
        'the standalone fixed-window claim refuses a caller-owned transaction before writing'
    );

    mysqli_query(
        $connection,
        'UPDATE RED_Addon_Public_Mutation_Rate_Limits
         SET ExpiresAt=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)
         WHERE SubjectRecordID=' . (int) $subject['subjectRecordId'] .
            " AND ScopeSHA256='$alternateScope'"
    );
    red_addon_public_mutation_rate_limit_test_assert(
        red_addon_public_mutation_rate_limit_cleanup($connection)
            && red_addon_public_mutation_rate_limit_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits '
                    . 'WHERE SubjectRecordID=' .
                        (int) $subject['subjectRecordId']
            ) === '1',
        'bounded cleanup removes expired opaque rate evidence without touching the active subject'
    );

    $subjectToken = $subject['cookie']['value'];
    mysqli_query(
        $connection,
        'UPDATE RED_Addon_Public_Mutation_Subjects
         SET ExpiresAt=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)
         WHERE RecordID=' . (int) $subject['subjectRecordId']
    );
    red_addon_public_mutation_rate_limit_test_assert(
        red_addon_public_mutation_subject_cleanup($connection)
            && red_addon_public_mutation_rate_limit_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*)
                     FROM RED_Addon_Public_Mutation_Subjects
                     WHERE SubjectTokenSHA256='$subjectToken'),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=" . (int) $subject['subjectRecordId'] . ')
                )'
            ) === '0:0',
        'subject expiry cascades the remaining short-lived rate evidence without retaining a raw token'
    );

    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_rate_limit_helpers.php'
    );
    red_addon_public_mutation_rate_limit_test_assert(
        is_string($source)
            && strpos($source, '$_') === false
            && strpos($source, 'setcookie') === false
            && strpos($source, 'session_') === false
            && strpos($source, 'header(') === false
            && strpos($source, 'addon.php') === false
            && strpos($source, 'register') === false,
        'the rate helper has no request-global, browser-response, package-load, or runtime-registration path'
    );

    red_addon_public_mutation_rate_limit_test_cleanup($connection, $subjectIds);
    red_addon_public_mutation_rate_limit_test_assert(
        red_addon_public_mutation_rate_limit_test_scalar(
            $connection,
            'SELECT CONCAT_WS(CHAR(58),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits)
             )'
        ) === '0:0:0',
        'self-test leaves no subject, CSRF, or rate-limit fixture row in the disposable database'
    );

    echo 'Public-mutation rate-limit self-test passed (' .
        $assertions . " assertions).\n";
} catch (Throwable $throwable) {
    red_addon_public_mutation_rate_limit_test_cleanup($connection, $subjectIds);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
