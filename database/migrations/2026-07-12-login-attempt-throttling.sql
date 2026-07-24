-- Persistent failed-login throttling storage.
-- Stores no passwords, session identifiers, CSRF tokens, or successful activity history.

CREATE TABLE IF NOT EXISTS `RED_Login_Attempts` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `UsernameHash` binary(32) NOT NULL,
  `ClientAddress` varbinary(16) NOT NULL,
  `AttemptedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_login_attempt_username_time` (`UsernameHash`,`AttemptedAt`),
  KEY `idx_red_login_attempt_client_time` (`ClientAddress`,`AttemptedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_login_attempt_valid_columns = (
  SELECT COUNT(*) = 4
    AND SUM(COLUMN_NAME='RecordID' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='NO' AND EXTRA='auto_increment') = 1
    AND SUM(COLUMN_NAME='UsernameHash' AND COLUMN_TYPE='binary(32)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ClientAddress' AND COLUMN_TYPE='varbinary(16)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='AttemptedAt' AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Login_Attempts'
);
SET @redcms_login_attempt_column_sql = IF(
  @redcms_login_attempt_valid_columns = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Login_Attempts_Unexpected_Columns`'
);
PREPARE redcms_login_attempt_column_statement FROM @redcms_login_attempt_column_sql;
EXECUTE redcms_login_attempt_column_statement;
DEALLOCATE PREPARE redcms_login_attempt_column_statement;

SET @redcms_login_attempt_primary_key = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Login_Attempts' AND INDEX_NAME='PRIMARY'
);
SET @redcms_login_attempt_primary_sql = IF(
  @redcms_login_attempt_primary_key = 'RecordID',
  'SELECT 1',
  'SELECT * FROM `RED_Login_Attempts_Unexpected_Primary_Key`'
);
PREPARE redcms_login_attempt_primary_statement FROM @redcms_login_attempt_primary_sql;
EXECUTE redcms_login_attempt_primary_statement;
DEALLOCATE PREPARE redcms_login_attempt_primary_statement;

SET @redcms_login_attempt_username_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Login_Attempts'
    AND INDEX_NAME='idx_red_login_attempt_username_time'
);
SET @redcms_login_attempt_username_index_sql = CASE
  WHEN @redcms_login_attempt_username_index IS NULL THEN
    'ALTER TABLE `RED_Login_Attempts` ADD INDEX `idx_red_login_attempt_username_time` (`UsernameHash`,`AttemptedAt`)'
  WHEN @redcms_login_attempt_username_index = 'UsernameHash,AttemptedAt' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Login_Attempts_Unexpected_Username_Index`'
END;
PREPARE redcms_login_attempt_username_index_statement FROM @redcms_login_attempt_username_index_sql;
EXECUTE redcms_login_attempt_username_index_statement;
DEALLOCATE PREPARE redcms_login_attempt_username_index_statement;

SET @redcms_login_attempt_client_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Login_Attempts'
    AND INDEX_NAME='idx_red_login_attempt_client_time'
);
SET @redcms_login_attempt_client_index_sql = CASE
  WHEN @redcms_login_attempt_client_index IS NULL THEN
    'ALTER TABLE `RED_Login_Attempts` ADD INDEX `idx_red_login_attempt_client_time` (`ClientAddress`,`AttemptedAt`)'
  WHEN @redcms_login_attempt_client_index = 'ClientAddress,AttemptedAt' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Login_Attempts_Unexpected_Client_Index`'
END;
PREPARE redcms_login_attempt_client_index_statement FROM @redcms_login_attempt_client_index_sql;
EXECUTE redcms_login_attempt_client_index_statement;
DEALLOCATE PREPARE redcms_login_attempt_client_index_statement;
