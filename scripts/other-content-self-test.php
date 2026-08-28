<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['REQUEST_URI'] = '/admin/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/class/class_connection.php';
require_once dirname(__DIR__) . '/includes/admin_other_content_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_content_revision_helpers.php';
require_once dirname(__DIR__) . '/includes/public_render_helpers.php';
require_once dirname(__DIR__) . '/includes/legacy_component_helpers.php';

if (!preg_match('/\Aredcms_(?:acceptance|other)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(STDERR, "Refusing to run: RED_DB_NAME must name a disposable redcms_acceptance_* or redcms_other_* database.\n");
    exit(64);
}

$_SESSION['AdminRecordID'] = 2147000860;
$_SESSION['alias'] = 'OtherQA';
$_SESSION['AdminType'] = 'webmaster';

$assertions = 0;
$assert = static function ($condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$normalId = 2147000861;
$legacyId = 2147000862;
$articleId = 2147000863;
$deniedCreateId = 2147000865;
$adminId = 2147000864;
$fixtureIds = [$normalId, $legacyId, $articleId, $deniedCreateId];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

$deleteFixtures = static function () use (&$connection, $fixtureIds): void {
    $ids = implode(',', array_map('intval', $fixtureIds));
    mysqli_query($connection, "DELETE FROM RED_Page_SEO WHERE OwnerType='article' AND OwnerRecordID IN ($ids)");
    mysqli_query($connection, "DELETE FROM RED_Content_Revisions WHERE ContentRecordID IN ($ids)");
    mysqli_query($connection, "DELETE FROM RED_Articles WHERE RecordID IN ($ids)");
    mysqli_query($connection, 'DELETE FROM RED_Admin WHERE RecordID=2147000864');
};

$revisionCount = static function (int $recordId) use (&$connection): int {
    $stmt = mysqli_prepare($connection, 'SELECT COUNT(*) FROM RED_Content_Revisions WHERE ContentRecordID=?');
    mysqli_stmt_bind_param($stmt, 'i', $recordId);
    mysqli_stmt_execute($stmt);
    $count = 0;
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return (int) $count;
};

$renderOther = static function (string $selectedAlias, array $rows): string {
    $redThemeOtherContext = [
        'article' => $selectedAlias,
        'dimensions' => [
            'Width' => 640,
            'WidthDivisor' => 1,
            'Height' => 360,
            'vWidth' => 640,
            'vHeight' => 360,
        ],
        'rows' => $rows,
    ];
    ob_start();
    include dirname(__DIR__) . '/themes/legacy-bootstrap/components/other.php';
    return (string) ob_get_clean();
};

$runEndpointChild = static function (string $endpoint, array $session, array $post): int {
    $pid = pcntl_fork();
    if ($pid === -1) {
        throw new RuntimeException('Could not fork protected endpoint check.');
    }
    if ($pid === 0) {
        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/admin/bin/' . basename($endpoint);
        $_POST = $post;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('redcms-other-' . getmypid());
        session_start();
        $_SESSION = $session;
        ob_start(static function (): string {
            return '';
        });
        include $endpoint;
        ob_end_clean();
        exit(0);
    }

    $status = 0;
    pcntl_waitpid($pid, $status);
    return pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 255;
};

try {
    $deleteFixtures();
    $assert(red_admin_content_revision_table_available($connection), 'content revision table is available');
    $assert(red_admin_other_registry_component($connection) === 'Other', 'Other component identity resolves from the database registry');

    $layoutResult = mysqli_query(
        $connection,
        "SELECT Layout FROM RED_Sections WHERE Sections='home' ORDER BY RecordID ASC LIMIT 1"
    );
    $layoutRow = $layoutResult ? mysqli_fetch_assoc($layoutResult) : null;
    if ($layoutResult) {
        mysqli_free_result($layoutResult);
    }
    $layout = (string) ($layoutRow['Layout'] ?? '');
    $assert($layout !== '', 'Home layout fixture is available');

    $advanced = "<section data-proof=\"byte-for-byte\">\n"
        . "  <template><x-card data-key='a&b'>Ω</x-card></template>\n"
        . "  <script type=\"application/ld+json\">{\"a\":\"<>&\"}</script>\n"
        . "</section>";
    $createPost = [
        'Component' => 'Article',
        'OtherContentAction' => 'create',
        'OtherContentBase64' => base64_encode($advanced),
    ];
    $createContent = red_admin_other_prepare_content($createPost, [], 'create');
    $assert(
        !empty($createContent['ok'])
            && ($createContent['data']['ShortDesc'] ?? null) === $advanced
            && ($createContent['data']['LongDesc'] ?? null) === $advanced,
        'Other create prepares one byte-exact canonical value for both fields'
    );

    $normalData = red_admin_article_default_insert_data($normalId);
    $normalData['Title'] = 'Other canonical fixture';
    $normalData['Alias'] = 'other-canonical-fixture';
    $normalData['Component'] = red_admin_other_registry_component($connection);
    $normalData['Sections'] = 'home';
    $normalData['Layout'] = $layout;
    $normalData['PagePosition'] = 1;
    $normalData['Active'] = 'Y';
    $normalData['Language'] = 'sp';
    $normalData['EditedBy'] = 'OtherQA';
    $normalData = array_merge($normalData, $createContent['data']);
    $assert(
        red_admin_content_revision_create_transaction(
            $connection,
            $normalId,
            static function () use ($connection, $normalId, $normalData): bool {
                return red_admin_article_insert($connection, $normalId, $normalData);
            },
            ['RED_Articles']
        ),
        'Other creation and its canonical revision commit together'
    );
    $normal = red_admin_article_full_record($connection, $normalId);
    $assert(
        ($normal['Component'] ?? '') === 'Other'
            && ($normal['ShortDesc'] ?? null) === $advanced
            && ($normal['LongDesc'] ?? null) === $advanced,
        'created Other ignores submitted Article identity and stores identical exact HTML'
    );

    $updated = "<!-- exact -->\n<div class=\"layout\" data-json='{\"x\":1}'>\n  <iframe srcdoc=\"<p>A &amp; B</p>\"></iframe>\n</div>";
    $normalHash = red_admin_content_revision_current_hash($connection, $normalId);
    $updateContent = red_admin_other_prepare_content([
        'Component' => 'Article',
        'LongDesc' => '<p>forged dedicated content</p>',
        'OtherContentAction' => 'update',
        'OtherContentBase64' => base64_encode($updated),
    ], $normal, 'update');
    $assert(!empty($updateContent['ok']), 'normal Other content update is accepted');
    $normalUpdate = red_admin_content_revision_guarded_transaction(
        $connection,
        $normalId,
        $normalHash,
        static function () use ($connection, $normalId, $updateContent): bool {
            return red_admin_article_update($connection, $normalId, $updateContent['data']);
        },
        ['RED_Articles'],
        'save'
    );
    $assert(!empty($normalUpdate['ok']), 'normal Other update commits under the stale-state guard');
    $normal = red_admin_article_full_record($connection, $normalId);
    $assert(
        ($normal['Component'] ?? '') === 'Other'
            && ($normal['ShortDesc'] ?? null) === $updated
            && ($normal['LongDesc'] ?? null) === $updated,
        'normal Other update mirrors exact HTML and ignores submitted component and LongDesc values'
    );

    // Establish one synthetic all-placement row directly for renderer coverage;
    // production placement endpoints continue to validate each real hierarchy.
    $placement = 1;
    $stmt = mysqli_prepare(
        $connection,
        'UPDATE RED_Articles SET HomePosition=?, SectionPosition=?, CategoryPosition=?, SubCategoryPosition=?, PagePosition=? WHERE RecordID=?'
    );
    mysqli_stmt_bind_param(
        $stmt,
        'iiiiii',
        $placement,
        $placement,
        $placement,
        $placement,
        $placement,
        $normalId
    );
    $placementSaved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $assert($placementSaved, 'canonical Other fixture can occupy every core placement column');
    $normal = red_admin_article_full_record($connection, $normalId);
    $assert(
        (int) $normal['HomePosition'] === 1
            && (int) $normal['SectionPosition'] === 1
            && (int) $normal['CategoryPosition'] === 1
            && (int) $normal['SubCategoryPosition'] === 1
            && (int) $normal['PagePosition'] === 1,
        'Home, Section, Category, Subcategory, and Article placement state is retained'
    );
    $publicRows = red_public_article_render_rows($connection, $normalId);
    $assert(
        $renderOther('', $publicRows) === $updated
            && $renderOther('other-canonical-fixture', $publicRows) === $updated,
        'embedded/list and selected dedicated-page render branches emit the newly saved identical bytes'
    );

    foreach (
        [
            ['Tags' => 'metadata-only'],
            ['HomePositionOrder' => 42],
            ['BigPict' => 'image-only.png'],
        ] as $unrelatedWrite
    ) {
        $before = red_admin_article_full_record($connection, $normalId);
        $prepared = red_admin_other_prepare_content(
            ['OtherContentAction' => 'preserve'],
            $before,
            'update'
        );
        $hash = red_admin_content_revision_current_hash($connection, $normalId);
        $result = red_admin_content_revision_guarded_transaction(
            $connection,
            $normalId,
            $hash,
            static function () use ($connection, $normalId, $unrelatedWrite, $prepared): bool {
                return $prepared['data'] === []
                    && red_admin_article_update($connection, $normalId, $unrelatedWrite);
            },
            ['RED_Articles'],
            'save'
        );
        $after = red_admin_article_full_record($connection, $normalId);
        $assert(
            !empty($result['ok'])
                && ($after['ShortDesc'] ?? null) === ($before['ShortDesc'] ?? null)
                && ($after['LongDesc'] ?? null) === ($before['LongDesc'] ?? null),
            'metadata, placement, and image-only writes preserve both Other content fields'
        );
    }

    $legacyData = red_admin_article_default_insert_data($legacyId);
    $legacyData['Title'] = 'Other legacy fixture';
    $legacyData['Alias'] = 'other-legacy-fixture';
    $legacyData['Component'] = 'Other';
    $legacyData['Sections'] = 'home';
    $legacyData['Layout'] = $layout;
    $legacyData['PagePosition'] = 1;
    $legacyData['Active'] = 'Y';
    $legacyData['Language'] = 'sp';
    $legacyData['ShortDesc'] = '<p>Initial</p>';
    $legacyData['LongDesc'] = '<p>Initial</p>';
    $legacyData['EditedBy'] = 'OtherQA';
    $assert(
        red_admin_content_revision_create_transaction(
            $connection,
            $legacyId,
            static function () use ($connection, $legacyId, $legacyData): bool {
                return red_admin_article_insert($connection, $legacyId, $legacyData);
            },
            ['RED_Articles']
        ),
        'legacy fixture begins as a normally revisioned Other record'
    );
    $legacyShort = "<div data-version=\"listing\">Listing bytes\n</div>";
    $legacyLong = "<section data-version=\"dedicated\">Dedicated bytes\n</section>";
    $stmt = mysqli_prepare($connection, 'UPDATE RED_Articles SET ShortDesc=?, LongDesc=? WHERE RecordID=?');
    mysqli_stmt_bind_param($stmt, 'ssi', $legacyShort, $legacyLong, $legacyId);
    $assert(mysqli_stmt_execute($stmt), 'legacy mismatch fixture is established with a prepared update');
    mysqli_stmt_close($stmt);

    $legacy = red_admin_article_full_record($connection, $legacyId);
    $revisionBeforeRefusal = $revisionCount($legacyId);
    $refused = red_admin_other_prepare_content([
        'ShortDesc' => '<p>silent overwrite</p>',
    ], $legacy, 'update');
    $unchanged = red_admin_article_full_record($connection, $legacyId);
    $assert(
        empty($refused['ok'])
            && ($refused['reason'] ?? '') === 'reconciliation_required'
            && ($unchanged['ShortDesc'] ?? null) === $legacyShort
            && ($unchanged['LongDesc'] ?? null) === $legacyLong
            && $revisionCount($legacyId) === $revisionBeforeRefusal,
        'mismatched legacy Other is unchanged without an explicit reconciliation selection'
    );

    $shortChoice = red_admin_other_prepare_content([
        'OtherContentAction' => 'reconcile',
        'OtherReconcileSource' => 'short',
    ], $legacy, 'update');
    $shortChoiceHash = red_admin_content_revision_current_hash($connection, $legacyId);
    $beforeShortChoice = $revisionCount($legacyId);
    $shortResult = red_admin_content_revision_reconciliation_transaction(
        $connection,
        $legacyId,
        $shortChoiceHash,
        static function () use ($connection, $legacyId, $shortChoice): bool {
            return red_admin_article_update($connection, $legacyId, $shortChoice['data']);
        },
        ['RED_Articles']
    );
    $afterShortChoice = red_admin_article_full_record($connection, $legacyId);
    $assert(
        !empty($shortResult['ok'])
            && ($afterShortChoice['ShortDesc'] ?? null) === $legacyShort
            && ($afterShortChoice['LongDesc'] ?? null) === $legacyShort
            && $revisionCount($legacyId) === $beforeShortChoice + 1,
        'ShortDesc reconciliation writes both fields and exactly one complete pre-change revision'
    );
    $preChangeRevision = red_admin_content_revision_latest($connection, $legacyId);
    $restore = red_admin_content_revision_restore(
        $connection,
        $legacyId,
        (int) ($preChangeRevision['RevisionID'] ?? 0),
        red_admin_content_revision_current_hash($connection, $legacyId)
    );
    $restoredLegacy = red_admin_article_full_record($connection, $legacyId);
    $assert(
        !empty($restore['ok'])
            && ($restoredLegacy['ShortDesc'] ?? null) === $legacyShort
            && ($restoredLegacy['LongDesc'] ?? null) === $legacyLong,
        'revision restore recovers the complete former mismatched state'
    );

    $longChoice = red_admin_other_prepare_content([
        'OtherContentAction' => 'reconcile',
        'OtherReconcileSource' => 'long',
    ], $restoredLegacy, 'update');
    $beforeLongChoice = $revisionCount($legacyId);
    $longResult = red_admin_content_revision_reconciliation_transaction(
        $connection,
        $legacyId,
        red_admin_content_revision_current_hash($connection, $legacyId),
        static function () use ($connection, $legacyId, $longChoice): bool {
            return red_admin_article_update($connection, $legacyId, $longChoice['data']);
        },
        ['RED_Articles']
    );
    $afterLongChoice = red_admin_article_full_record($connection, $legacyId);
    $assert(
        !empty($longResult['ok'])
            && ($afterLongChoice['ShortDesc'] ?? null) === $legacyLong
            && ($afterLongChoice['LongDesc'] ?? null) === $legacyLong
            && $revisionCount($legacyId) === $beforeLongChoice + 1,
        'LongDesc reconciliation writes both fields and exactly one complete pre-change revision'
    );

    $staleBefore = red_admin_article_full_record($connection, $normalId);
    $staleHash = red_admin_content_revision_current_hash($connection, $normalId);
    $assert(red_admin_article_update($connection, $normalId, ['Title' => 'Concurrent Other title']), 'concurrent fixture state changes');
    $staleRevisionCount = $revisionCount($normalId);
    $stale = red_admin_content_revision_guarded_transaction(
        $connection,
        $normalId,
        $staleHash,
        static function () use ($connection, $normalId): bool {
            return red_admin_article_update($connection, $normalId, [
                'ShortDesc' => '<p>stale</p>',
                'LongDesc' => '<p>stale</p>',
            ]);
        },
        ['RED_Articles'],
        'save'
    );
    $staleAfter = red_admin_article_full_record($connection, $normalId);
    $assert(
        empty($stale['ok'])
            && ($stale['reason'] ?? '') === 'conflict'
            && ($staleAfter['ShortDesc'] ?? null) === ($staleBefore['ShortDesc'] ?? null)
            && ($staleAfter['LongDesc'] ?? null) === ($staleBefore['LongDesc'] ?? null)
            && $revisionCount($normalId) === $staleRevisionCount,
        'stale Other request changes neither content nor revision state'
    );

    $rollbackHash = red_admin_content_revision_current_hash($connection, $normalId);
    $rollbackBefore = red_admin_article_full_record($connection, $normalId);
    $rollbackCount = $revisionCount($normalId);
    $rolledBack = red_admin_content_revision_guarded_transaction(
        $connection,
        $normalId,
        $rollbackHash,
        static function () use ($connection, $normalId): bool {
            return red_admin_article_update($connection, $normalId, [
                'ShortDesc' => '<p>must roll back</p>',
                'LongDesc' => '<p>must roll back</p>',
            ]) && false;
        },
        ['RED_Articles'],
        'save'
    );
    $rollbackAfter = red_admin_article_full_record($connection, $normalId);
    $assert(
        empty($rolledBack['ok'])
            && ($rollbackAfter['ShortDesc'] ?? null) === ($rollbackBefore['ShortDesc'] ?? null)
            && ($rollbackAfter['LongDesc'] ?? null) === ($rollbackBefore['LongDesc'] ?? null)
            && $revisionCount($normalId) === $rollbackCount,
        'failed canonical write and attempted revision roll back together'
    );

    $articleData = red_admin_article_default_insert_data($articleId);
    $articleData['Title'] = 'Article independent fields fixture';
    $articleData['Alias'] = 'article-independent-fields';
    $articleData['Component'] = 'Article';
    $articleData['Sections'] = 'home';
    $articleData['Layout'] = $layout;
    $articleData['PagePosition'] = 1;
    $articleData['Active'] = 'Y';
    $articleData['Language'] = 'sp';
    $articleData['ShortDesc'] = '<p>Article short</p>';
    $articleData['LongDesc'] = '<p>Article long</p>';
    $articleData['EditedBy'] = 'OtherQA';
    $assert(red_admin_article_insert($connection, $articleId, $articleData), 'Article regression fixture is created');
    $assert(
        red_admin_content_revision_transaction(
            $connection,
            $articleId,
            static function () use ($connection, $articleId): bool {
                return red_admin_article_update($connection, $articleId, ['ShortDesc' => '<p>Article short updated</p>']);
            },
            ['RED_Articles'],
            'save'
        ),
        'Article update continues through its existing revision transaction'
    );
    $article = red_admin_article_full_record($connection, $articleId);
    $assert(
        ($article['ShortDesc'] ?? null) === '<p>Article short updated</p>'
            && ($article['LongDesc'] ?? null) === '<p>Article long</p>',
        'Article short and long descriptions remain intentionally independent'
    );

    $otherRegistry = red_admin_component_registry_row($connection, 'Other');
    $otherComponentId = (int) ($otherRegistry['RecordID'] ?? 0);
    $adminPassword = 'OtherQAPassword';
    $adminComponents = (string) $otherComponentId;
    $stmt = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin (RecordID, Username, Password, Administrator, Alias, AdminType, AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref, Donation_Form, Donation_Form_Pref) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $adminUsername = 'redcms_other_qa';
    $administrator = 'Admin';
    $adminAlias = 'OthrEnd';
    $adminType = 'webmaster';
    $adminTools = '';
    $adminEmail = 'other-endpoint@example.invalid';
    $no = 'N';
    $to = 'to';
    mysqli_stmt_bind_param(
        $stmt,
        'issssssssssss',
        $adminId,
        $adminUsername,
        $adminPassword,
        $administrator,
        $adminAlias,
        $adminType,
        $adminComponents,
        $adminTools,
        $adminEmail,
        $no,
        $to,
        $no,
        $to
    );
    $assert($otherComponentId > 0 && mysqli_stmt_execute($stmt), 'protected endpoint administrator fixture is created');
    mysqli_stmt_close($stmt);

    $protectedBefore = red_admin_content_revision_current_hash($connection, $normalId);
    $protectedRevisionCount = $revisionCount($normalId);
    $validSession = [
        'alias' => $adminAlias,
        'AdminRecordID' => $adminId,
        'AdminPasswordFingerprint' => hash('sha256', $adminPassword),
        'AdminType' => $adminType,
        'AdminComponents' => $adminComponents,
        'AdminTools' => '',
        'csrf_token' => str_repeat('a', 64),
    ];
    $db->close();
    $invalidCsrfExit = $runEndpointChild(
        dirname(__DIR__) . '/admin/bin/update_content.php',
        $validSession,
        [
            'RecordID' => (string) $normalId,
            'ShortDesc' => '<p>invalid csrf</p>',
            'OtherContentAction' => 'update',
            'OtherContentBase64' => base64_encode('<p>invalid csrf</p>'),
            'CurrentHash' => $protectedBefore,
            'csrf_token' => str_repeat('b', 64),
        ]
    );
    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
    $connection = $db->connection;
    $assert(
        $invalidCsrfExit === 0
            && red_admin_content_revision_current_hash($connection, $normalId) === $protectedBefore
            && $revisionCount($normalId) === $protectedRevisionCount,
        'invalid-CSRF Other update exits before any database or revision change'
    );

    $db->close();
    $unauthorizedExit = $runEndpointChild(
        dirname(__DIR__) . '/admin/bin/insert_other.php',
        [],
        [
            'RecordID' => (string) $deniedCreateId,
            'Title' => 'Unauthorized Other',
            'OtherContentAction' => 'create',
            'OtherContentBase64' => base64_encode('<p>unauthorized</p>'),
            'csrf_token' => str_repeat('a', 64),
        ]
    );
    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
    $connection = $db->connection;
    $assert(
        $unauthorizedExit === 0
            && red_admin_article_full_record($connection, $deniedCreateId) === null
            && $revisionCount($deniedCreateId) === 0,
        'unauthorized Other create exits before any database or revision change'
    );

    $insertEndpoint = file_get_contents(dirname(__DIR__) . '/admin/bin/insert_other.php');
    $updateEndpoint = file_get_contents(dirname(__DIR__) . '/admin/bin/update_content.php');
    $genericInsertEndpoint = file_get_contents(dirname(__DIR__) . '/admin/bin/insert_content.php');
    $assert(
        is_string($insertEndpoint)
            && is_string($updateEndpoint)
            && is_string($genericInsertEndpoint)
            && str_contains($insertEndpoint, 'red_require_admin(true)')
            && str_contains($updateEndpoint, 'red_require_admin(true)')
            && str_contains($insertEndpoint, 'red_admin_other_registry_component')
            && str_contains($insertEndpoint, 'red_admin_require_article_access')
            && str_contains($updateEndpoint, "\$databaseComponent === 'Other'")
            && str_contains($updateEndpoint, 'red_admin_content_revision_guarded_transaction')
            && str_contains($genericInsertEndpoint, "\$component === 'Other'")
            && !str_contains($insertEndpoint, "\$_POST['Component']"),
        'Other endpoints retain CSRF and authorization gates, derive identity server-side, and reject generic forged creation'
    );

    echo "Other content self-test passed: {$assertions} assertions.\n";
} finally {
    $deleteFixtures();
    $db->close();
}
?>
