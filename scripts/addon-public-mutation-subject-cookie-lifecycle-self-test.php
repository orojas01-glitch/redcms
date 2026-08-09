<?php
/**
 * Disposable checks for the core-owned browser subject-cookie lifecycle.
 * No public endpoint or package fixture is created here.
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
    '/includes/addon_public_mutation_subject_cookie_lifecycle_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_public_mutation_cookie)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Subject-cookie lifecycle self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$subjectIds = [];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_public_mutation_cookie_lifecycle_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_cookie_lifecycle_test_scalar(
    $connection,
    $sql
) {
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_public_mutation_cookie_lifecycle_test_cleanup(
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
}

function red_addon_public_mutation_cookie_lifecycle_test_manifest()
{
    return [
        'id' => 'redcms.cookie-lifecycle-fixture',
        'routes' => [[
            'id' => 'redcms.cookie-lifecycle-fixture/cart-intent',
            'scope' => 'public',
            'path' => '/addons/redcms/cookie-lifecycle-fixture/cart-intent',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => 'redcms.cookie-lifecycle-fixture/cart-intent',
            'mutation' => 'redcms.cookie-lifecycle-fixture/add-to-cart',
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 128,
            'requestFields' => [[
                'key' => 'product',
                'type' => 'identifier',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 120,
            ]],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => ['RED_Addon_Cookie_Lifecycle_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

try {
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        red_addon_public_mutation_subject_storage_available($connection),
        'subject and CSRF storage is available'
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        red_addon_public_mutation_cookie_lifecycle_test_scalar(
            $connection,
            'SELECT CONCAT_WS(CHAR(58),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens)
             )'
        ) === '0:0',
        'lifecycle fixture starts with empty subject and CSRF storage'
    );

    $serverBefore = $_SERVER;
    $cookieBefore = $_COOKIE;
    $headersBefore = headers_list();
    $sessionBefore = session_status();
    $issued = red_addon_public_mutation_subject_cookie_lifecycle(
        $connection,
        'ensure',
        ''
    );
    $subjectIds[] = $issued['subjectRecordId'] ?? 0;
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
            $issued
        )
            && $issued['state'] === 'issued'
            && $issued['reason'] === 'subject_cookie_issued'
            && $issued['previousSubjectRecordId'] === 0
            && $issued['clearCookieValue'] === '',
        'ensure issues one fixed cookie descriptor for an absent browser cookie'
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        preg_match(
            '/\Aredcms_public_mutation_subject=([a-f0-9]{64});/',
            $issued['setCookieValue'],
            $matches
        ) === 1,
        'issued response contains only one opaque subject cookie value'
    );
    $subjectToken = $matches[1];
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        $_SERVER === $serverBefore
            && $_COOKIE === $cookieBefore
            && headers_list() === $headersBefore
            && session_status() === $sessionBefore,
        'ensure emits no header and changes no request, cookie, or session state'
    );

    $resolved = red_addon_public_mutation_subject_cookie_lifecycle(
        $connection,
        'ensure',
        $subjectToken
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
            $resolved
        )
            && $resolved['state'] === 'resolved'
            && $resolved['subjectRecordId'] === $issued['subjectRecordId']
            && $resolved['previousSubjectRecordId']
                === $issued['subjectRecordId']
            && $resolved['setCookieValue'] === ''
            && $resolved['clearCookieValue'] === '',
        'ensure resolves a valid cookie without reissuing or clearing it'
    );

    $manifest = red_addon_public_mutation_cookie_lifecycle_test_manifest();
    $plan = red_addon_public_mutation_declaration_preflight(
        $manifest,
        'redcms.cookie-lifecycle-fixture/cart-intent',
        'redcms.cookie-lifecycle-fixture/add-to-cart'
    );
    $subject = [
        'valid' => true,
        'subjectRecordId' => $issued['subjectRecordId'],
    ];
    $csrf = red_addon_public_mutation_csrf_issue(
        $connection,
        $subject,
        $plan
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        !empty($csrf['valid']),
        'old subject can receive one scoped CSRF value before rotation'
    );

    $rotated = red_addon_public_mutation_subject_cookie_lifecycle(
        $connection,
        'rotate',
        $subjectToken
    );
    $subjectIds[] = $rotated['subjectRecordId'] ?? 0;
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
            $rotated
        )
            && $rotated['state'] === 'rotated'
            && $rotated['reason'] === 'subject_cookie_rotated'
            && $rotated['subjectRecordId'] > 0
            && $rotated['subjectRecordId'] !== $issued['subjectRecordId']
            && $rotated['previousSubjectRecordId']
                === $issued['subjectRecordId'],
        'rotation creates a new subject and records the previous internal id'
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        red_addon_public_mutation_subject_cookie_clear_serialized_valid(
            $rotated['clearCookieValue']
        )
            && preg_match(
                '/\Aredcms_public_mutation_subject=([a-f0-9]{64});/',
                $rotated['setCookieValue'],
                $rotatedMatches
            ) === 1
            && $rotatedMatches[1] !== $subjectToken,
        'rotation returns one fixed deletion cookie and one distinct new cookie'
    );
    $rotatedToken = $rotatedMatches[1];
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        empty(red_addon_public_mutation_subject_resolve(
            $connection,
            $subjectToken
        )['valid'])
            && !empty(red_addon_public_mutation_subject_resolve(
                $connection,
                $rotatedToken
            )['valid']),
        'rotation invalidates the old browser token before the new one resolves'
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        empty(red_addon_public_mutation_csrf_verify(
            $connection,
            $subject,
            $plan,
            $csrf['token']
        )['valid']),
        'rotation invalidates old subject-bound CSRF evidence'
    );

    $resolvedRotated = red_addon_public_mutation_subject_cookie_lifecycle(
        $connection,
        'ensure',
        $rotatedToken
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        $resolvedRotated['state'] === 'resolved'
            && $resolvedRotated['subjectRecordId']
                === $rotated['subjectRecordId'],
        'the new rotated cookie remains the only active browser identity'
    );

    $cleared = red_addon_public_mutation_subject_cookie_lifecycle(
        $connection,
        'clear',
        $rotatedToken
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
            $cleared
        )
            && $cleared['state'] === 'cleared'
            && $cleared['reason'] === 'subject_cookie_cleared'
            && $cleared['previousSubjectRecordId']
                === $rotated['subjectRecordId']
            && red_addon_public_mutation_subject_cookie_clear_serialized_valid(
                $cleared['clearCookieValue']
            ),
        'clear expires the active subject and returns the fixed deletion cookie'
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        empty(red_addon_public_mutation_subject_resolve(
            $connection,
            $rotatedToken
        )['valid']),
        'cleared browser tokens fail closed on the next request'
    );

    $reissued = red_addon_public_mutation_subject_cookie_lifecycle(
        $connection,
        'ensure',
        'malformed'
    );
    $subjectIds[] = $reissued['subjectRecordId'] ?? 0;
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        $reissued['valid']
            && $reissued['state'] === 'issued'
            && $reissued['reason'] === 'subject_cookie_reissued'
            && $reissued['subjectRecordId'] > 0,
        'malformed browser input is replaced with a fresh opaque cookie'
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        red_addon_public_mutation_subject_cookie_lifecycle(
            $connection,
            'clear',
            'malformed'
        )['valid'],
        'clear remains safe and deterministic for an already-invalid cookie'
    );

    mysqli_begin_transaction($connection);
    $transactionRefusal = red_addon_public_mutation_subject_cookie_lifecycle(
        $connection,
        'rotate',
        $rotatedToken
    );
    mysqli_rollback($connection);
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        !$transactionRefusal['valid']
            && $transactionRefusal['reason'] === 'transaction_already_active',
        'rotation refuses an active caller transaction before changing state'
    );

    red_addon_public_mutation_cookie_lifecycle_test_cleanup(
        $connection,
        $subjectIds
    );
    red_addon_public_mutation_cookie_lifecycle_test_assert(
        red_addon_public_mutation_cookie_lifecycle_test_scalar(
            $connection,
            'SELECT CONCAT_WS(CHAR(58),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens)
             )'
        ) === '0:0',
        'lifecycle self-test leaves no subject or CSRF rows'
    );

    echo 'Public-mutation subject-cookie lifecycle self-test passed (' .
        $assertions . " assertions).\n";
} catch (Throwable $throwable) {
    red_addon_public_mutation_cookie_lifecycle_test_cleanup(
        $connection,
        $subjectIds
    );
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
