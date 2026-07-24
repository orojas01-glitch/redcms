<?php
declare(strict_types=1);

$_SERVER['REQUEST_URI'] = '/home/test-category/test-subcategory/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/class/class_connection.php';
require_once dirname(__DIR__) . '/includes/admin_area_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_article_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_tool_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_menu_helpers.php';
require_once dirname(__DIR__) . '/includes/public_render_helpers.php';

if (strpos((string) DBNAME, 'redcms_parent_migration_') !== 0) {
    fwrite(STDERR, "Refusing to run: RED_DB_NAME must name a redcms_parent_migration_ disposable database.\n");
    exit(64);
}

$assertions = 0;
$assert = static function ($condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

try {
    $home = red_admin_area_fetch_one(
        $connection,
        "SELECT RecordID FROM RED_Sections WHERE Sections='home' AND Language='sp' LIMIT 1",
        '',
        [],
        'Parent self-test Home lookup failed'
    );
    $about = red_admin_area_fetch_one(
        $connection,
        "SELECT RecordID FROM RED_Sections WHERE Sections='about' AND Language='sp' LIMIT 1",
        '',
        [],
        'Parent self-test About lookup failed'
    );
    $category = red_admin_area_fetch_one(
        $connection,
        "SELECT RecordID FROM RED_Categories WHERE Categories='test-category' AND Language='sp' LIMIT 1",
        '',
        [],
        'Parent self-test Category lookup failed'
    );
    $subcategory = red_admin_area_fetch_one(
        $connection,
        "SELECT RecordID FROM RED_SubCategories WHERE SubCategories='test-subcategory' AND Language='sp' LIMIT 1",
        '',
        [],
        'Parent self-test Subcategory lookup failed'
    );

    $assert(is_array($home) && (int) ($home['RecordID'] ?? 0) > 0, 'Home Section fixture exists');
    $assert(is_array($about) && (int) ($about['RecordID'] ?? 0) > 0, 'About Section fixture exists');
    $assert(is_array($category) && (int) ($category['RecordID'] ?? 0) > 0, 'test Category is preserved');
    $assert(is_array($subcategory) && (int) ($subcategory['RecordID'] ?? 0) > 0, 'test Subcategory is preserved');

    $homeId = (int) $home['RecordID'];
    $aboutId = (int) $about['RecordID'];
    $categoryId = (int) $category['RecordID'];
    $subcategoryId = (int) $subcategory['RecordID'];

    $assert(
        is_array(red_admin_area_save_existing(
            $connection,
            'RED_Categories',
            'Categories',
            $categoryId,
            ['SectionRecordID' => $homeId]
        )),
        'Category can be assigned to a Section'
    );
    $assert(
        is_array(red_admin_area_save_existing(
            $connection,
            'RED_SubCategories',
            'SubCategories',
            $subcategoryId,
            ['CategoryRecordID' => $categoryId]
        )),
        'Subcategory can be assigned to a Category'
    );

    $categoryContext = red_admin_area_record_route_context($connection, 'RED_Categories', $categoryId);
    $subcategoryContext = red_admin_area_record_route_context($connection, 'RED_SubCategories', $subcategoryId);
    $assert(($categoryContext['path'] ?? '') === '/home/test-category/', 'Category route includes its Home parent');
    $assert(
        ($subcategoryContext['path'] ?? '') === '/home/test-category/test-subcategory/',
        'Subcategory route includes its complete parent chain'
    );
    $homeMenuChoices = red_admin_main_menu_link_choices($connection, 'sp');
    $homeMenuPaths = array_column($homeMenuChoices, 'value');
    $assert(
        in_array('/home/test-category/', $homeMenuPaths, true)
            && in_array('/home/test-category/test-subcategory/', $homeMenuPaths, true),
        'menu choices include the Category and Subcategory under their stored Home parent'
    );
    $assert(
        !in_array('/about/test-category/', $homeMenuPaths, true)
            && !in_array('/admin/test-category/', $homeMenuPaths, true),
        'menu choices do not multiply a Category under unrelated Sections'
    );

    $validHierarchy = [
        'Sections' => 'home',
        'Categories' => 'test-category',
        'SubCategories' => 'test-subcategory',
        'Language' => 'sp',
    ];
    $assert(red_admin_article_hierarchy_valid($connection, $validHierarchy), 'valid Article hierarchy is accepted');
    $invalidHierarchy = $validHierarchy;
    $invalidHierarchy['Sections'] = 'about';
    $assert(!red_admin_article_hierarchy_valid($connection, $invalidHierarchy), 'mismatched Article hierarchy is rejected');

    $destination = red_admin_tool_move_destination_context(
        $connection,
        [
            'Sections' => 'home',
            'Categories' => 'test-category',
            'SubCategories' => 'test-subcategory',
            'Article' => '',
        ],
        'sp'
    );
    $assert(!empty($destination['valid']), 'Move Content accepts the assigned hierarchy');
    $assert(
        ($destination['path'] ?? '') === '/home/test-category/test-subcategory/',
        'Move Content reports the complete assigned path'
    );
    $invalidDestination = red_admin_tool_move_destination_context(
        $connection,
        [
            'Sections' => 'about',
            'Categories' => 'test-category',
            'SubCategories' => 'test-subcategory',
            'Article' => '',
        ],
        'sp'
    );
    $assert(empty($invalidDestination['valid']), 'Move Content rejects a mismatched parent chain');

    $publicCategory = red_public_area_row($connection, 'Categories', ['Categories', 'Title']);
    $publicSubcategory = red_public_area_row($connection, 'SubCategories', ['SubCategories', 'Title']);
    $assert(($publicCategory['Categories'] ?? '') === 'test-category', 'public Category lookup honors its parent');
    $assert(
        ($publicSubcategory['SubCategories'] ?? '') === 'test-subcategory',
        'public Subcategory lookup honors its parent chain'
    );

    $assert(
        !red_admin_area_delete_record($connection, 'RED_Categories', $categoryId),
        'Category deletion is blocked while a child Subcategory exists'
    );
    $sectionDelete = red_admin_section_archive_and_delete($connection, $homeId);
    $assert($sectionDelete === false, 'Section deletion is blocked while a child Category exists');

    $assert(
        is_array(red_admin_area_save_existing(
            $connection,
            'RED_Categories',
            'Categories',
            $categoryId,
            ['SectionRecordID' => $aboutId]
        )),
        'Category can be reparented'
    );
    $categoryContext = red_admin_area_record_route_context($connection, 'RED_Categories', $categoryId);
    $subcategoryContext = red_admin_area_record_route_context($connection, 'RED_SubCategories', $subcategoryId);
    $assert(($categoryContext['path'] ?? '') === '/about/test-category/', 'Category route changes after reparenting');
    $assert(
        ($subcategoryContext['path'] ?? '') === '/about/test-category/test-subcategory/',
        'child Subcategory inherits the Category reparenting'
    );
    $aboutMenuChoices = red_admin_main_menu_link_choices($connection, 'sp');
    $aboutMenuPaths = array_column($aboutMenuChoices, 'value');
    $assert(
        in_array('/about/test-category/', $aboutMenuPaths, true)
            && in_array('/about/test-category/test-subcategory/', $aboutMenuPaths, true),
        'menu choices follow Category reparenting and retain the inherited Subcategory route'
    );
    $assert(
        !in_array('/home/test-category/', $aboutMenuPaths, true),
        'menu choices remove the previous Category parent route after reparenting'
    );

    $assert(
        is_array(red_admin_area_save_existing(
            $connection,
            'RED_Categories',
            'Categories',
            $categoryId,
            ['SectionRecordID' => $homeId]
        )),
        'Category can be returned to Home'
    );

    $foreignKeyRejected = false;
    try {
        mysqli_query(
            $connection,
            'UPDATE RED_Categories SET SectionRecordID=4294967294 WHERE RecordID=' . $categoryId
        );
    } catch (mysqli_sql_exception $exception) {
        $foreignKeyRejected = true;
    }
    $assert($foreignKeyRejected, 'database foreign key rejects an unknown Section parent');

    $assert(
        is_array(red_admin_area_save_existing(
            $connection,
            'RED_SubCategories',
            'SubCategories',
            $subcategoryId,
            ['CategoryRecordID' => $categoryId]
        )),
        'valid Subcategory parent remains saveable after a rejected database write'
    );

    mysqli_begin_transaction($connection);
    try {
        $assert(
            red_admin_main_menu_insert_item(
                $connection,
                1,
                'Top Navigation',
                'Temporary navigation test',
                0,
                '/home/test-category/',
                '_blank',
                99,
                'sp'
            ),
            'new top-level menu item accepts its destination on first insert'
        );
        $temporaryMenuId = (int) mysqli_insert_id($connection);
        $temporaryMenuRow = red_admin_area_fetch_one(
            $connection,
            'SELECT Link, NewWindow FROM RED_Menu WHERE RecordID=? LIMIT 1',
            'i',
            [$temporaryMenuId],
            'Temporary menu insert lookup failed'
        );
        $assert(
            ($temporaryMenuRow['Link'] ?? '') === '/home/test-category/',
            'new top-level menu item stores its first-save destination'
        );
        $assert(
            ($temporaryMenuRow['NewWindow'] ?? '') === '_blank',
            'new top-level menu item stores its first-save window behavior'
        );
    } finally {
        mysqli_rollback($connection);
    }

    echo 'Area parent relationships self-test passed: ' . $assertions . " assertions.\n";
} finally {
    $db->close();
}
