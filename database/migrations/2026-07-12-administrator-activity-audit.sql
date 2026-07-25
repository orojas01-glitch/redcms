-- Minimal persistent administrator activity audit.
-- Records only allowlisted successful user-management events and numeric identifiers.

CREATE TABLE IF NOT EXISTS `RED_Admin_Activity_Log` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `EventName` varchar(64) NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `TargetType` varchar(32) NOT NULL,
  `TargetRecordID` bigint unsigned NOT NULL,
  `OccurredAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_admin_activity_time` (`OccurredAt`),
  KEY `idx_red_admin_activity_actor_time` (`ActorAdminRecordID`,`OccurredAt`),
  KEY `idx_red_admin_activity_target_time` (`TargetType`,`TargetRecordID`,`OccurredAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_admin_activity_valid_columns = (
  SELECT COUNT(*) = 6
    AND SUM(COLUMN_NAME='RecordID' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='NO' AND EXTRA='auto_increment') = 1
    AND SUM(COLUMN_NAME='EventName' AND COLUMN_TYPE='varchar(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ActorAdminRecordID' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='TargetType' AND COLUMN_TYPE='varchar(32)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='TargetRecordID' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='OccurredAt' AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Activity_Log'
);
SET @redcms_admin_activity_column_sql = IF(
  @redcms_admin_activity_valid_columns = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Admin_Activity_Log_Unexpected_Columns`'
);
PREPARE redcms_admin_activity_column_statement FROM @redcms_admin_activity_column_sql;
EXECUTE redcms_admin_activity_column_statement;
DEALLOCATE PREPARE redcms_admin_activity_column_statement;

SET @redcms_admin_activity_primary_key = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Activity_Log' AND INDEX_NAME='PRIMARY'
);
SET @redcms_admin_activity_primary_sql = IF(
  @redcms_admin_activity_primary_key = 'RecordID',
  'SELECT 1',
  'SELECT * FROM `RED_Admin_Activity_Log_Unexpected_Primary_Key`'
);
PREPARE redcms_admin_activity_primary_statement FROM @redcms_admin_activity_primary_sql;
EXECUTE redcms_admin_activity_primary_statement;
DEALLOCATE PREPARE redcms_admin_activity_primary_statement;

SET @redcms_admin_activity_time_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Activity_Log'
    AND INDEX_NAME='idx_red_admin_activity_time'
);
SET @redcms_admin_activity_time_index_sql = CASE
  WHEN @redcms_admin_activity_time_index IS NULL THEN
    'ALTER TABLE `RED_Admin_Activity_Log` ADD INDEX `idx_red_admin_activity_time` (`OccurredAt`)'
  WHEN @redcms_admin_activity_time_index = 'OccurredAt' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Admin_Activity_Log_Unexpected_Time_Index`'
END;
PREPARE redcms_admin_activity_time_index_statement FROM @redcms_admin_activity_time_index_sql;
EXECUTE redcms_admin_activity_time_index_statement;
DEALLOCATE PREPARE redcms_admin_activity_time_index_statement;

SET @redcms_admin_activity_actor_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Activity_Log'
    AND INDEX_NAME='idx_red_admin_activity_actor_time'
);
SET @redcms_admin_activity_actor_index_sql = CASE
  WHEN @redcms_admin_activity_actor_index IS NULL THEN
    'ALTER TABLE `RED_Admin_Activity_Log` ADD INDEX `idx_red_admin_activity_actor_time` (`ActorAdminRecordID`,`OccurredAt`)'
  WHEN @redcms_admin_activity_actor_index = 'ActorAdminRecordID,OccurredAt' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Admin_Activity_Log_Unexpected_Actor_Index`'
END;
PREPARE redcms_admin_activity_actor_index_statement FROM @redcms_admin_activity_actor_index_sql;
EXECUTE redcms_admin_activity_actor_index_statement;
DEALLOCATE PREPARE redcms_admin_activity_actor_index_statement;

SET @redcms_admin_activity_target_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Activity_Log'
    AND INDEX_NAME='idx_red_admin_activity_target_time'
);
SET @redcms_admin_activity_target_index_sql = CASE
  WHEN @redcms_admin_activity_target_index IS NULL THEN
    'ALTER TABLE `RED_Admin_Activity_Log` ADD INDEX `idx_red_admin_activity_target_time` (`TargetType`,`TargetRecordID`,`OccurredAt`)'
  WHEN @redcms_admin_activity_target_index = 'TargetType,TargetRecordID,OccurredAt' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Admin_Activity_Log_Unexpected_Target_Index`'
END;
PREPARE redcms_admin_activity_target_index_statement FROM @redcms_admin_activity_target_index_sql;
EXECUTE redcms_admin_activity_target_index_statement;
DEALLOCATE PREPARE redcms_admin_activity_target_index_statement;
