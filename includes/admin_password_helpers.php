<?php
/** Administrator authentication accepts only password_hash-compatible hashes. */
function red_admin_password_is_hash(string $stored): bool
{
    return (password_get_info($stored)['algoName'] ?? 'unknown') !== 'unknown';
}

function red_admin_password_verify(string $password, string $stored): bool
{
    return red_admin_password_is_hash($stored) && password_verify($password, $stored);
}

function red_admin_password_migration_class(string $stored): string
{
    if (red_admin_password_is_hash($stored)) return 'hashed';
    // Do not accidentally turn an empty value or an unrecognized digest into a password.
    if ($stored === '' || strlen($stored) > 72 || str_contains($stored, "\0") || str_starts_with($stored, '$') || preg_match('/\A[0-9a-f]{32,128}\z/i', $stored)) return 'resetRequired';
    return 'legacy';
}

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

function red_update_admin_password_hash($connection, $recordId, $password, $expectedHash)
{
	if (!red_admin_password_is_hash($expectedHash) || !red_admin_password_column_supports_hash($connection)) {
		return '';
	}

	$passwordHash = password_hash($password, PASSWORD_DEFAULT);
	if ($passwordHash === false) {
		return '';
	}

	$stmt = mysqli_prepare($connection, "UPDATE RED_Admin SET Password=? WHERE RecordID=? AND CAST(Password AS BINARY)=CAST(? AS BINARY)");
	if (!$stmt) {
		return '';
	}

	mysqli_stmt_bind_param($stmt, 'sis', $passwordHash, $recordId, $expectedHash);
	$updated = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
	mysqli_stmt_close($stmt);

	return $updated ? $passwordHash : '';
}
