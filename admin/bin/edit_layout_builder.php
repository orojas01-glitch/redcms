<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
red_require_admin_site_manager();

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin_custom_layout_ui_helpers.php';

$layoutId = red_custom_layout_scalar($_POST['LayoutID'] ?? '');
if ($layoutId !== '' && !red_custom_layout_valid_id($layoutId)) {
    $layoutId = '';
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_render_custom_layout_builder($db->connection, $layoutId);
$db->close();
?>
