<?php
/**
 * Disposable checks for the core-only public-mutation idempotency foundation.
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
    '/includes/addon_public_mutation_idempotency_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_public_mutation_idempotency)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Public-mutation idempotency self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$subjectIds = [];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_public_mutation_idempotency_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_idempotency_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_public_mutation_idempotency_test_manifest(
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
            'tables' => ['RED_Addon_Public_Idempotency_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_public_mutation_idempotency_test_cleanup(
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
            'DELETE FROM RED_Addon_Public_Mutation_Idempotency_Keys ' .
                "WHERE SubjectRecordID IN ($list)"
        );
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
            'Public-mutation idempotency cleanup failed: ' .
                $throwable->getMessage()
        );
    }
}

try {
    $policy = red_addon_public_mutation_idempotency_policy();
    red_addon_public_mutation_idempotency_test_assert(
        red_addon_public_mutation_subject_storage_available($connection)
            && red_addon_public_mutation_rate_limit_storage_available(
                $connection
            )
            && red_addon_public_mutation_idempotency_storage_available(
                $connection
            )
            && $policy === [
                'lifetimeSeconds' => 600,
                'cleanupLimit' => 100,
            ],
        'the disposable client has exact hash-only idempotency storage and fixed policy'
    );
    red_addon_public_mutation_idempotency_test_assert(
        red_addon_valid_public_mutation_table(
            'RED_Addon_Public_Idempotency_Fixture_Carts'
        )
            && !red_addon_valid_public_mutation_table(
                'RED_Addon_Public_Mutation_Idempotency_Keys'
            ),
        'package declarations cannot claim core-owned idempotency storage'
    );

    $packageId = 'redcms.public-idempotency-fixture';
    $routeId = 'redcms.public-idempotency-fixture/cart-intent';
    $mutationId = 'redcms.public-idempotency-fixture/add-to-cart';
    $manifest = red_addon_public_mutation_idempotency_test_manifest(
        $packageId,
        $routeId,
        $mutationId,
        '/addons/redcms/public-idempotency-fixture/cart-intent'
    );
    $plan = red_addon_public_mutation_declaration_preflight(
        $manifest,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_idempotency_test_assert(
        red_addon_public_mutation_declaration_preflight_is_valid($plan),
        'idempotency scope accepts only an already-valid closed declaration plan'
    );

    $cookieSnapshot = $_COOKIE;
    $sessionStatus = session_status();
    $headerSnapshot = headers_list();
    $subject = red_addon_public_mutation_subject_issue($connection);
    $subjectIds[] = $subject['subjectRecordId'] ?? 0;
    red_addon_public_mutation_idempotency_test_assert(
        !empty($subject['valid'])
            && ($subject['subjectRecordId'] ?? 0) > 0,
        'an opaque active anonymous subject exists for core idempotency evidence'
    );
    $scopeSha256 = red_addon_public_mutation_idempotency_scope_sha256(
        $connection,
        $plan
    );
    red_addon_public_mutation_idempotency_test_assert(
        red_addon_valid_sha256($scopeSha256)
            && $scopeSha256 !== red_addon_public_mutation_csrf_scope_sha256(
                $connection,
                $plan
            )
            && $scopeSha256 !== red_addon_public_mutation_rate_limit_scope_sha256(
                $connection,
                $plan
            )
            && red_addon_public_mutation_idempotency_scope_sha256(
                $connection,
                []
            ) === '',
        'idempotency evidence has a distinct declaration/database-bound opaque scope'
    );

    $invalidSubject = red_addon_public_mutation_idempotency_issue(
        $connection,
        ['valid' => true, 'subjectRecordId' => 2147000999],
        $plan
    );
    $invalidPlan = red_addon_public_mutation_idempotency_issue(
        $connection,
        $subject,
        []
    );
    red_addon_public_mutation_idempotency_test_assert(
        empty($invalidSubject['valid'])
            && ($invalidSubject['reason'] ?? '') === 'idempotency_subject_invalid'
            && empty($invalidPlan['valid'])
            && ($invalidPlan['reason'] ?? '') === 'idempotency_scope_invalid'
            && red_addon_public_mutation_idempotency_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys'
            ) === '0',
        'forged subject handles and undeclared scope data fail closed without an idempotency row'
    );

    $issued = red_addon_public_mutation_idempotency_issue(
        $connection,
        $subject,
        $plan
    );
    $keySha256 = red_addon_public_mutation_opaque_token_sha256(
        $issued['key'] ?? ''
    );
    red_addon_public_mutation_idempotency_test_assert(
        !empty($issued['valid'])
            && !empty($issued['issued'])
            && ($issued['idempotencyRecordId'] ?? 0) > 0
            && ($issued['subjectRecordId'] ?? 0) === $subject['subjectRecordId']
            && ($issued['scopeSha256'] ?? '') === $scopeSha256
            && red_addon_public_mutation_valid_opaque_token($issued['key'] ?? '')
            && red_addon_valid_sha256($keySha256)
            && ($issued['key'] ?? '') !== $keySha256
            && ($issued['maxAgeSeconds'] ?? 0) === 600
            && ($issued['reason'] ?? '') === 'idempotency_issued'
            && array_keys($issued) === [
                'valid', 'issued', 'idempotencyRecordId', 'subjectRecordId',
                'scopeSha256', 'key', 'maxAgeSeconds', 'reason',
            ],
        'the first internal idempotency issue returns only opaque future-dispatch evidence'
    );
    red_addon_public_mutation_idempotency_test_assert(
        red_addon_public_mutation_idempotency_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*)
                 FROM RED_Addon_Public_Mutation_Idempotency_Keys
                 WHERE SubjectRecordID=" . (int) $subject['subjectRecordId'] . "
                   AND ScopeSHA256='$scopeSha256'),
                (SELECT COUNT(*)
                 FROM RED_Addon_Public_Mutation_Idempotency_Keys
                 WHERE KeySHA256='" . ($issued['key'] ?? '') . "'),
                (SELECT COUNT(*)
                 FROM RED_Addon_Public_Mutation_Idempotency_Keys
                 WHERE KeySHA256='$keySha256'),
                (SELECT TIMESTAMPDIFF(SECOND, CreatedAt, ExpiresAt)
                 FROM RED_Addon_Public_Mutation_Idempotency_Keys
                 WHERE RecordID=" . (int) ($issued['idempotencyRecordId'] ?? 0) . ')
            )'
        ) === '1:0:1:600',
        'only the SHA-256 digest and exact bounded expiry are persisted'
    );
    red_addon_public_mutation_idempotency_test_assert(
        $_COOKIE === $cookieSnapshot
            && session_status() === $sessionStatus
            && headers_list() === $headerSnapshot,
        'idempotency issuance does not read or mutate browser cookie, session, or response state'
    );

    $resolved = red_addon_public_mutation_idempotency_resolve(
        $connection,
        $subject,
        $plan,
        $issued['key']
    );
    red_addon_public_mutation_idempotency_test_assert(
        !empty($resolved['valid'])
            && ($resolved['idempotencyRecordId'] ?? 0)
                === $issued['idempotencyRecordId']
            && ($resolved['subjectRecordId'] ?? 0)
                === $subject['subjectRecordId']
            && ($resolved['scopeSha256'] ?? '') === $scopeSha256
            && ($resolved['reason'] ?? '') === 'idempotency_resolved'
            && array_keys($resolved) === [
                'valid', 'idempotencyRecordId', 'subjectRecordId',
                'scopeSha256', 'reason',
            ],
        'only the issuing subject and declaration can resolve one active opaque key'
    );

    $issuedSecond = red_addon_public_mutation_idempotency_issue(
        $connection,
        $subject,
        $plan
    );
    red_addon_public_mutation_idempotency_test_assert(
        !empty($issuedSecond['valid'])
            && ($issuedSecond['idempotencyRecordId'] ?? 0)
                !== $issued['idempotencyRecordId']
            && ($issuedSecond['key'] ?? '') !== $issued['key']
            && !empty(red_addon_public_mutation_idempotency_resolve(
                $connection,
                $subject,
                $plan,
                $issuedSecond['key']
            )['valid']),
        'separate same-scope core keys remain independently resolvable before the later runner'
    );

    $alternateRoute = 'redcms.public-idempotency-fixture/cart-update';
    $alternateMutation = 'redcms.public-idempotency-fixture/update-cart';
    $alternatePlan = red_addon_public_mutation_declaration_preflight(
        red_addon_public_mutation_idempotency_test_manifest(
            $packageId,
            $alternateRoute,
            $alternateMutation,
            '/addons/redcms/public-idempotency-fixture/cart-update'
        ),
        $alternateRoute,
        $alternateMutation
    );
    $otherSubject = red_addon_public_mutation_subject_issue($connection);
    $subjectIds[] = $otherSubject['subjectRecordId'] ?? 0;
    red_addon_public_mutation_idempotency_test_assert(
        red_addon_public_mutation_declaration_preflight_is_valid($alternatePlan)
            && !empty($otherSubject['valid'])
            && empty(red_addon_public_mutation_idempotency_resolve(
                $connection,
                $subject,
                $alternatePlan,
                $issued['key']
            )['valid'])
            && empty(red_addon_public_mutation_idempotency_resolve(
                $connection,
                $otherSubject,
                $plan,
                $issued['key']
            )['valid']),
        'wrong declaration scope and a different opaque subject cannot resolve the key'
    );

    $resolvedAgain = red_addon_public_mutation_idempotency_resolve(
        $connection,
        $subject,
        $plan,
        $issued['key']
    );
    red_addon_public_mutation_idempotency_test_assert(
        !empty($resolvedAgain['valid'])
            && ($resolvedAgain['idempotencyRecordId'] ?? 0)
                === $issued['idempotencyRecordId']
            && red_addon_public_mutation_idempotency_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys '
                    . 'WHERE SubjectRecordID=' .
                        (int) $subject['subjectRecordId']
            ) === '2',
        'resolution is deliberately non-consuming; only the separate atomic transaction runner may consume it'
    );

    mysqli_begin_transaction($connection);
    $nested = red_addon_public_mutation_idempotency_issue(
        $connection,
        $subject,
        $plan
    );
    mysqli_rollback($connection);
    red_addon_public_mutation_idempotency_test_assert(
        empty($nested['valid'])
            && ($nested['reason'] ?? '') === 'transaction_already_active'
            && red_addon_public_mutation_idempotency_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys '
                    . 'WHERE SubjectRecordID=' .
                        (int) $subject['subjectRecordId']
            ) === '2',
        'the standalone key issuer refuses a caller-owned transaction before writing'
    );

    mysqli_query(
        $connection,
        'UPDATE RED_Addon_Public_Mutation_Idempotency_Keys
         SET ExpiresAt=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)
         WHERE RecordID=' . (int) $issued['idempotencyRecordId']
    );
    $expired = red_addon_public_mutation_idempotency_resolve(
        $connection,
        $subject,
        $plan,
        $issued['key']
    );
    red_addon_public_mutation_idempotency_test_assert(
        empty($expired['valid'])
            && ($expired['reason'] ?? '') === 'idempotency_invalid'
            && red_addon_public_mutation_idempotency_cleanup($connection)
            && red_addon_public_mutation_idempotency_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys '
                    . 'WHERE SubjectRecordID=' .
                        (int) $subject['subjectRecordId']
            ) === '1',
        'expired opaque key evidence is refused and removed in bounded cleanup'
    );

    $otherIssued = red_addon_public_mutation_idempotency_issue(
        $connection,
        $otherSubject,
        $plan
    );
    mysqli_query(
        $connection,
        'UPDATE RED_Addon_Public_Mutation_Subjects
         SET ExpiresAt=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)
         WHERE RecordID=' . (int) $otherSubject['subjectRecordId']
    );
    red_addon_public_mutation_idempotency_test_assert(
        !empty($otherIssued['valid'])
            && red_addon_public_mutation_subject_cleanup($connection)
            && red_addon_public_mutation_idempotency_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*)
                     FROM RED_Addon_Public_Mutation_Subjects
                     WHERE RecordID=" . (int) $otherSubject['subjectRecordId'] . '),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Public_Mutation_Idempotency_Keys
                     WHERE SubjectRecordID=' .
                        (int) $otherSubject['subjectRecordId'] . ')
                )'
            ) === '0:0',
        'subject expiry cascades its remaining opaque key evidence'
    );

    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_idempotency_helpers.php'
    );
    red_addon_public_mutation_idempotency_test_assert(
        is_string($source)
            && strpos($source, '$_') === false
            && strpos($source, 'setcookie') === false
            && strpos($source, 'session_') === false
            && strpos($source, 'header(') === false
            && strpos($source, 'addon.php') === false
            && strpos($source, 'register') === false
            && strpos($source, 'ConsumedAt') === false,
        'the key helper has no request-global, browser-response, package-load, runtime-binding, or consumption path'
    );

    red_addon_public_mutation_idempotency_test_cleanup($connection, $subjectIds);
    red_addon_public_mutation_idempotency_test_assert(
        red_addon_public_mutation_idempotency_test_scalar(
            $connection,
            'SELECT CONCAT_WS(CHAR(58),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits),
                (SELECT COUNT(*)
                 FROM RED_Addon_Public_Mutation_Idempotency_Keys)
             )'
        ) === '0:0:0:0',
        'self-test leaves no subject, CSRF, rate-limit, or idempotency fixture row in the disposable database'
    );

    echo 'Public-mutation idempotency self-test passed (' .
        $assertions . " assertions).\n";
} catch (Throwable $throwable) {
    red_addon_public_mutation_idempotency_test_cleanup($connection, $subjectIds);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
