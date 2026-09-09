<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/login_throttle_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/public_form_operation_helpers.php';

$loginContract = red_public_form_operation_contract('login');
$loginPayload = [
	'username' => array_key_exists('username', $_POST) ? $_POST['username'] : '',
	'password' => array_key_exists('password', $_POST) ? $_POST['password'] : '',
	'alias' => $loginContract['form']['alias'],
	'MySpamTrap' => '',
];

$db = null;
$dependencies = [
	'authenticate' => static function ($username, $password) use (&$db) {
		$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$usernameHash = red_login_username_hash($username);
		$clientAddress = red_login_client_address();
		red_login_throttle_cleanup($db->connection);

		if (red_login_is_throttled($db->connection, $usernameHash, $clientAddress)) {
			return 'throttled';
		}

		$stmt = mysqli_prepare(
			$db->connection,
			"SELECT RecordID, Username, Alias, AdminType, AdminComponents, AdminTools, Password FROM RED_Admin WHERE Username=? LIMIT 1"
		);
		if (!$stmt) {
			return 'unavailable';
		}

		mysqli_stmt_bind_param($stmt, 's', $username);
		if (!mysqli_stmt_execute($stmt)) {
			mysqli_stmt_close($stmt);
			return 'unavailable';
		}
		$result = mysqli_stmt_get_result($stmt);

		if ($result && $result->num_rows > 0) {
			$info = mysqli_fetch_assoc($result);
			$storedPassword = isset($info['Password']) ? (string) $info['Password'] : '';
			$passwordValid = red_admin_password_verify($password, $storedPassword);
			if (!red_admin_password_is_hash($storedPassword)) {
				password_verify($password, red_login_dummy_password_hash());
			}

			if ($passwordValid) {
				if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
					$updatedHash = red_update_admin_password_hash(
						$db->connection,
						(int) $info['RecordID'],
						$password,
						$storedPassword
					);
					if ($updatedHash === '') {
						mysqli_stmt_close($stmt);
						return 'unavailable';
					}
					$storedPassword = $updatedHash;
				}

				red_admin_session_establish();
				$_SESSION['alias'] = $info['Alias'];
				$_SESSION['AdminRecordID'] = (int) $info['RecordID'];
				$_SESSION['AdminUsername'] = (string) $info['Username'];
				$_SESSION['AdminType'] = $info['AdminType'];
				$_SESSION['AdminComponents'] = $info['AdminComponents'];
				$_SESSION['AdminTools'] = $info['AdminTools'];
				red_admin_addon_refresh_session_authorization(
					$db->connection,
					(int) $info['RecordID']
				);
				$_SESSION['AdminPasswordFingerprint'] = hash('sha256', $storedPassword);
				red_login_clear_username_failures($db->connection, $usernameHash);
				mysqli_stmt_close($stmt);

				return 'success';
			}

			red_login_record_failure($db->connection, $usernameHash, $clientAddress);
			mysqli_stmt_close($stmt);
			return 'invalid';
		}

		password_verify($password, red_login_dummy_password_hash());
		red_login_record_failure($db->connection, $usernameHash, $clientAddress);
		mysqli_stmt_close($stmt);
		return 'unknown';
	},
];

try {
	$result = red_public_form_operation_execute(
		'login',
		[
			'method' => $_SERVER['REQUEST_METHOD'] ?? '',
			'endpoint' => '/bin/login.php',
			'payload' => $loginPayload,
		],
		[],
		$dependencies
	);
} catch (InvalidArgumentException $exception) {
	if ($db instanceof connection) {
		$db->close();
	}
	echo 'no';
	exit;
}

if ($db instanceof connection) {
	$db->close();
}

http_response_code($result['httpStatus']);
foreach ($result['headers'] as $name => $value) {
	header($name . ': ' . $value);
}
echo $result['body'];
?>
