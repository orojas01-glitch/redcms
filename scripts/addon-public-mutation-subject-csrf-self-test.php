<?php
/**
 * Disposable checks for the core-only public-mutation anonymous subject and
 * CSRF foundation. No public request or package fixture is created here.
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
    '/includes/addon_public_mutation_subject_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_public_mutation_subject)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Public-mutation subject/CSRF self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$subjectIds = [];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_public_mutation_subject_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_subject_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_public_mutation_subject_test_manifest(
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
            'tables' => ['RED_Addon_Public_Subject_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_public_mutation_subject_test_cleanup(
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
            'Public-mutation subject/CSRF cleanup failed: ' .
                $throwable->getMessage()
        );
    }
}

try {
    red_addon_public_mutation_subject_test_assert(
        red_addon_public_mutation_subject_storage_available($connection),
        'exact empty core-owned subject and CSRF storage is available'
    );
    red_addon_public_mutation_subject_test_assert(
        red_addon_valid_public_mutation_table(
            'RED_Addon_Public_Subject_Fixture_Carts'
        )
            && !red_addon_valid_public_mutation_table(
                'RED_Addon_Public_Mutation_Subjects'
            )
            && !red_addon_valid_public_mutation_table(
                'RED_Addon_Public_Mutation_CSRF_Tokens'
            ),
        'package declarations cannot claim core-owned subject or CSRF storage'
    );

    $packageId = 'redcms.public-subject-fixture';
    $routeId = 'redcms.public-subject-fixture/cart-intent';
    $mutationId = 'redcms.public-subject-fixture/add-to-cart';
    $manifest = red_addon_public_mutation_subject_test_manifest(
        $packageId,
        $routeId,
        $mutationId,
        '/addons/redcms/public-subject-fixture/cart-intent'
    );
    $plan = red_addon_public_mutation_declaration_preflight(
        $manifest,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_subject_test_assert(
        red_addon_public_mutation_declaration_preflight_is_valid($plan),
        'CSRF scope accepts only an already-valid closed declaration plan'
    );

    $cookieSnapshot = $_COOKIE;
    $sessionStatus = session_status();
    $headerSnapshot = headers_list();
    $subjectA = red_addon_public_mutation_subject_issue($connection);
    $subjectIds[] = $subjectA['subjectRecordId'] ?? 0;
    red_addon_public_mutation_subject_test_assert(
        !empty($subjectA['valid'])
            && !empty($subjectA['issued'])
            && is_int($subjectA['subjectRecordId'] ?? null)
            && ($subjectA['subjectRecordId'] ?? 0) > 0
            && ($subjectA['reason'] ?? '') === 'subject_issued'
            && red_addon_public_mutation_valid_opaque_token(
                $subjectA['cookie']['value'] ?? null
            )
            && ($subjectA['cookie']['name'] ?? '')
                === 'redcms_public_mutation_subject'
            && ($subjectA['cookie']['path'] ?? '') === '/'
            && ($subjectA['cookie']['secure'] ?? null) === true
            && ($subjectA['cookie']['httpOnly'] ?? null) === true
            && ($subjectA['cookie']['sameSite'] ?? '') === 'Strict'
            && ($subjectA['cookie']['maxAgeSeconds'] ?? 0) === 1800,
        'subject issuance returns only a core cookie descriptor and opaque value'
    );
    red_addon_public_mutation_subject_test_assert(
        $_COOKIE === $cookieSnapshot
            && session_status() === $sessionStatus
            && headers_list() === $headerSnapshot,
        'foundation does not read or mutate cookie, session, or response state'
    );

    $subjectToken = $subjectA['cookie']['value'];
    $subjectTokenHash = hash('sha256', $subjectToken);
    red_addon_public_mutation_subject_test_assert(
        red_addon_public_mutation_subject_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*)
                 FROM RED_Addon_Public_Mutation_Subjects
                 WHERE SubjectTokenSHA256='$subjectTokenHash'),
                (SELECT COUNT(*)
                 FROM RED_Addon_Public_Mutation_Subjects
                 WHERE SubjectTokenSHA256='$subjectToken'),
                (SELECT COUNT(*)
                 FROM RED_Addon_Public_Mutation_CSRF_Tokens)
            )"
        ) === '1:0:0',
        'subject storage retains a hash only and no CSRF record'
    );

    $resolvedA = red_addon_public_mutation_subject_resolve(
        $connection,
        $subjectToken
    );
    red_addon_public_mutation_subject_test_assert(
        !empty($resolvedA['valid'])
            && ($resolvedA['subjectRecordId'] ?? 0)
                === $subjectA['subjectRecordId']
            && ($resolvedA['reason'] ?? '') === 'subject_resolved'
            && array_keys($resolvedA) === [
                'valid', 'subjectRecordId', 'reason',
            ],
        'subject resolution returns an opaque internal handle without cookie data'
    );
    red_addon_public_mutation_subject_test_assert(
        empty(red_addon_public_mutation_subject_resolve(
            $connection,
            str_repeat('a', 64)
        )['valid'])
            && empty(red_addon_public_mutation_subject_resolve(
                $connection,
                'malformed'
            )['valid']),
        'forged or malformed subject cookie values fail closed'
    );

    $subjectB = red_addon_public_mutation_subject_issue($connection);
    $subjectIds[] = $subjectB['subjectRecordId'] ?? 0;
    red_addon_public_mutation_subject_test_assert(
        !empty($subjectB['valid'])
            && ($subjectB['subjectRecordId'] ?? 0)
                !== ($subjectA['subjectRecordId'] ?? 0),
        'new anonymous subjects receive distinct opaque identities'
    );

    $csrfA = red_addon_public_mutation_csrf_issue($connection, $resolvedA, $plan);
    red_addon_public_mutation_subject_test_assert(
        !empty($csrfA['valid'])
            && !empty($csrfA['issued'])
            && ($csrfA['subjectRecordId'] ?? 0)
                === $subjectA['subjectRecordId']
            && red_addon_valid_sha256($csrfA['scopeSha256'] ?? null)
            && red_addon_public_mutation_valid_opaque_token(
                $csrfA['token'] ?? null
            )
            && ($csrfA['maxAgeSeconds'] ?? 0) === 600
            && ($csrfA['reason'] ?? '') === 'csrf_issued',
        'CSRF issuance is bounded to an active subject and valid declaration'
    );
    $csrfToken = $csrfA['token'];
    $csrfTokenHash = hash('sha256', $csrfToken);
    red_addon_public_mutation_subject_test_assert(
        red_addon_public_mutation_subject_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*)
                 FROM RED_Addon_Public_Mutation_CSRF_Tokens
                 WHERE SubjectRecordID=" . (int) $subjectA['subjectRecordId'] . "
                   AND ScopeSHA256='" . $csrfA['scopeSha256'] . "'
                   AND TokenSHA256='$csrfTokenHash'),
                (SELECT COUNT(*)
                 FROM RED_Addon_Public_Mutation_CSRF_Tokens
                 WHERE TokenSHA256='$csrfToken')
            )"
        ) === '1:0',
        'CSRF storage retains only the token hash and scope hash'
    );

    $verifiedA = red_addon_public_mutation_csrf_verify(
        $connection,
        $resolvedA,
        $plan,
        $csrfToken
    );
    red_addon_public_mutation_subject_test_assert(
        !empty($verifiedA['valid'])
            && ($verifiedA['subjectRecordId'] ?? 0)
                === $subjectA['subjectRecordId']
            && ($verifiedA['scopeSha256'] ?? '') === $csrfA['scopeSha256']
            && ($verifiedA['reason'] ?? '') === 'csrf_verified'
            && array_keys($verifiedA) === [
                'valid', 'subjectRecordId', 'scopeSha256', 'reason',
            ],
        'valid CSRF verification returns no token, cookie, request, or package data'
    );
    red_addon_public_mutation_subject_test_assert(
        empty(red_addon_public_mutation_csrf_verify(
            $connection,
            $subjectB,
            $plan,
            $csrfToken
        )['valid'])
            && empty(red_addon_public_mutation_csrf_verify(
                $connection,
                $resolvedA,
                $plan,
                str_repeat('b', 64)
            )['valid']),
        'CSRF tokens cannot cross anonymous subjects or survive forgery'
    );

    $alternateRoute = 'redcms.public-subject-fixture/cart-update';
    $alternateMutation = 'redcms.public-subject-fixture/update-cart';
    $alternatePlan = red_addon_public_mutation_declaration_preflight(
        red_addon_public_mutation_subject_test_manifest(
            $packageId,
            $alternateRoute,
            $alternateMutation,
            '/addons/redcms/public-subject-fixture/cart-update'
        ),
        $alternateRoute,
        $alternateMutation
    );
    red_addon_public_mutation_subject_test_assert(
        red_addon_public_mutation_declaration_preflight_is_valid($alternatePlan)
            && red_addon_public_mutation_csrf_scope_sha256(
                $connection,
                $alternatePlan
            ) !== $csrfA['scopeSha256']
            && empty(red_addon_public_mutation_csrf_verify(
                $connection,
                $resolvedA,
                $alternatePlan,
                $csrfToken
            )['valid']),
        'CSRF values are bound to one trusted declaration scope'
    );

    mysqli_query(
        $connection,
        'UPDATE RED_Addon_Public_Mutation_CSRF_Tokens
         SET ExpiresAt=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)
         WHERE SubjectRecordID=' . (int) $subjectA['subjectRecordId'] .
            " AND TokenSHA256='$csrfTokenHash'"
    );
    red_addon_public_mutation_subject_test_assert(
        empty(red_addon_public_mutation_csrf_verify(
            $connection,
            $resolvedA,
            $plan,
            $csrfToken
        )['valid']),
        'expired CSRF values fail without a mutation path'
    );

    $csrfBeforeSubjectExpiry = red_addon_public_mutation_csrf_issue(
        $connection,
        $resolvedA,
        $plan
    );
    red_addon_public_mutation_subject_test_assert(
        !empty($csrfBeforeSubjectExpiry['valid']),
        'an active subject may receive a later core-owned CSRF value'
    );
    mysqli_query(
        $connection,
        'UPDATE RED_Addon_Public_Mutation_Subjects
         SET ExpiresAt=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)
         WHERE RecordID=' . (int) $subjectA['subjectRecordId']
    );
    red_addon_public_mutation_subject_test_assert(
        empty(red_addon_public_mutation_subject_resolve(
            $connection,
            $subjectToken
        )['valid'])
            && empty(red_addon_public_mutation_csrf_verify(
                $connection,
                $resolvedA,
                $plan,
                $csrfBeforeSubjectExpiry['token']
            )['valid']),
        'subject expiry invalidates both the cookie reference and every CSRF value'
    );
    red_addon_public_mutation_subject_test_assert(
        red_addon_public_mutation_subject_cleanup($connection)
            && red_addon_public_mutation_subject_test_scalar(
                $connection,
                'SELECT CONCAT_WS(CHAR(58),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Public_Mutation_Subjects
                     WHERE RecordID=' . (int) $subjectA['subjectRecordId'] . '),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Public_Mutation_CSRF_Tokens
                     WHERE SubjectRecordID=' .
                        (int) $subjectA['subjectRecordId'] . ')
                 )'
            ) === '0:0',
        'bounded cleanup removes expired subject rows and cascading CSRF rows'
    );

    red_addon_public_mutation_subject_test_cleanup($connection, $subjectIds);
    red_addon_public_mutation_subject_test_assert(
        red_addon_public_mutation_subject_test_scalar(
            $connection,
            'SELECT CONCAT_WS(CHAR(58),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens)
             )'
        ) === '0:0',
        'self-test leaves no subject or CSRF fixture row in the disposable database'
    );

    echo 'Public-mutation anonymous subject/CSRF self-test passed (' .
        $assertions . " assertions).\n";
} catch (Throwable $throwable) {
    red_addon_public_mutation_subject_test_cleanup($connection, $subjectIds);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
