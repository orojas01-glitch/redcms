<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php'; ?>
<?php red_start_session(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php
function red_admin_password_column_supports_hash($connection)
{
	$result = mysqli_query($connection, "SHOW COLUMNS FROM RED_Admin LIKE 'Password'");
	if (!$result) {
		return false;
	}

	$column = mysqli_fetch_assoc($result);
	if (!$column || empty($column['Type'])) {
		return false;
	}

	if (preg_match('/varchar\((\d+)\)/i', $column['Type'], $matches)) {
		return (int) $matches[1] >= 255;
	}

	return preg_match('/text/i', $column['Type']) === 1;
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

if ($username === '' || $password === '') {
	echo "no";
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$stmt = mysqli_prepare($db->connection, "SELECT * FROM RED_Admin WHERE Username=? LIMIT 1");
if (!$stmt) {
	echo "no";
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
