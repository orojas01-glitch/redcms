<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';

red_require_admin(true);

$allowedPositionColumns = [
    'HomePosition',
    'SectionPosition',
    'CategoryPosition',
    'SubCategoryPosition',
    'PagePosition',
];

if (
    empty($_POST['RecordID']) || !is_array($_POST['RecordID']) ||
    empty($_POST['VarPosition']) || !is_array($_POST['VarPosition']) ||
    empty($_POST['PosOrder']) || !is_array($_POST['PosOrder'])
) {
    echo 'no';
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$success = false;

foreach ($_POST['RecordID'] as $a => $b) {
    $recordId = isset($_POST['RecordID'][$a]) ? (int) $_POST['RecordID'][$a] : 0;
    $positionColumn = isset($_POST['VarPosition'][$a]) ? (string) $_POST['VarPosition'][$a] : '';
    $positionOrder = isset($_POST['PosOrder'][$a]) ? (int) $_POST['PosOrder'][$a] : 0;

    if ($recordId <= 0 || !in_array($positionColumn, $allowedPositionColumns, true)) {
        continue;
    }

    $orderColumn = $positionColumn . 'Order';
    $stmt = mysqli_prepare($db->connection, "UPDATE RED_Articles SET `$orderColumn`=? WHERE RecordID=?");
    if (!$stmt) {
        continue;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $positionOrder, $recordId);
    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        $success = true;
    }
    mysqli_stmt_close($stmt);
}

echo $success ? 'yes' : 'no';
$db->close();
?>
