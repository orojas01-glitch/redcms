<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php'; ?>
<?php red_start_session(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php
function red_admin_password_column_supports_hash($connection)
{
	$stmt = mysqli_prepare(
		$connection,
		"SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1"
	);
	if (!$stmt) {
		return false;
	}

	$tableName = 'RED_Admin';
	$columnName = 'Password';
	mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
	if (!mysqli_stmt_execute($stmt)) {
		mysqli_stmt_close($stmt);
		return false;
	}

	$result = mysqli_stmt_get_result($stmt);
	$column = $result ? mysqli_fetch_assoc($result) : null;
	mysqli_stmt_close($stmt);

	if (!$column || empty($column['DATA_TYPE'])) {
		return false;
	}

	$dataType = strtolower((string) $column['DATA_TYPE']);
	$maxLength = isset($column['CHARACTER_MAXIMUM_LENGTH']) ? (int) $column['CHARACTER_MAXIMUM_LENGTH'] : 0;

	if (in_array($dataType, ['varchar', 'char'], true)) {
		return $maxLength >= 255;
	}

	return in_array($dataType, ['text', 'mediumtext', 'longtext'], true);
}

function red_update_admin_password_hash($connection, $recordId, $password)
{
	if (!red_admin_password_column_supports_hash($connection)) {
		return;
	}

	$passwordHash = password_hash($password, PASSWORD_DEFAULT);
	if ($passwordHash === false) {
		return;
	}

	$stmt = mysqli_prepare($connection, "UPDATE RED_Admin SET Password=? WHERE RecordID=?");
	if (!$stmt) {
		return;
	}

	mysqli_stmt_bind_param($stmt, 'si', $passwordHash, $recordId);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($username === '' || strlen($username) > 255 || $password === '') {
	echo "no";
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$stmt = mysqli_prepare(
	$db->connection,
	"SELECT RecordID, Alias, AdminType, AdminComponents, Password FROM RED_Admin WHERE Username=? LIMIT 1"
);
if (!$stmt) {
	echo "no";
	$db->close();
	exit;
}

mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && $result->num_rows > 0) {
	$info = mysqli_fetch_assoc($result);
	$storedPassword = isset($info['Password']) ? (string) $info['Password'] : '';
	$passwordInfo = password_get_info($storedPassword);
	$isPasswordHash = isset($passwordInfo['algoName']) && $passwordInfo['algoName'] !== 'unknown';
	$passwordValid = $isPasswordHash
		? password_verify($password, $storedPassword)
		: hash_equals($storedPassword, $password);

	if ($passwordValid) {
		if (!$isPasswordHash || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
			red_update_admin_password_hash($db->connection, (int) $info['RecordID'], $password);
		}

		session_regenerate_id(true);
		$_SESSION['alias'] = $info['Alias'];
		$_SESSION['AdminType'] = $info['AdminType'];
		$_SESSION['AdminComponents'] = $info['AdminComponents'];

		echo "yes";
	} else {
		echo "no";
	}
} else {
	echo "no";
}

mysqli_stmt_close($stmt);
$db->close();
?>
