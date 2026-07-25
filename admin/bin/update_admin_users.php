<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
red_require_admin_user_manager(true); ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/config.php'; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php'; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_user_helpers.php'; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_audit_helpers.php'; ?>
<?php
$action = red_admin_user_text($_POST['action'] ?? '');
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$status = 'invalid';
$eventNames = [
    'create' => 'administrator.created',
    'update' => 'administrator.updated',
    'delete' => 'administrator.deleted',
];
$targetRecordId = in_array($action, ['update', 'delete'], true)
    ? (int) red_admin_user_scalar($_POST['RecordID'] ?? 0)
    : 0;

if (isset($eventNames[$action])) {
    $transactionStarted = false;
    try {
        if (!mysqli_begin_transaction($db->connection)) {
            throw new RuntimeException('Could not start administrator audit transaction.');
        }
        $transactionStarted = true;

        switch ($action) {
            case 'create':
                $status = red_admin_user_create($db->connection, $_POST);
                if ($status === 'yes') {
                    $targetRecordId = (int) mysqli_insert_id($db->connection);
                }
                break;
            case 'update':
                $status = red_admin_user_update($db->connection, $_POST);
                break;
            case 'delete':
                $status = red_admin_user_delete($db->connection, $_POST);
                break;
        }

        if (
            $status === 'yes'
            && red_admin_audit_record(
                $db->connection,
                $eventNames[$action],
                'administrator',
                $targetRecordId
            )
        ) {
            if (!mysqli_commit($db->connection)) {
                throw new RuntimeException('Could not commit administrator audit transaction.');
            }
            $transactionStarted = false;
        } else {
            mysqli_rollback($db->connection);
            $transactionStarted = false;
            if ($status === 'yes') {
                $status = 'no';
            }
        }
    } catch (Throwable $e) {
        if ($transactionStarted) {
            try {
                mysqli_rollback($db->connection);
            } catch (Throwable $rollbackError) {
                error_log('RED administrator audited write rollback failed: ' . $rollbackError->getMessage());
            }
        }
        error_log('RED administrator audited write failed: ' . $e->getMessage());
        $status = 'no';
    }
}

$selfPasswordReset = $status === 'yes'
    && $action === 'update'
    && (int) red_admin_user_scalar($_POST['RecordID'] ?? 0) === (int) ($_SESSION['AdminRecordID'] ?? 0)
    && red_admin_user_scalar($_POST['Password'] ?? '') !== '';

$db->close();
echo $selfPasswordReset ? 'reauth' : $status;
?>
