-- Bounded audit history for future add-on lifecycle actions.
--
-- The first consumer is the server-local package installer. The table stores
-- no SQL text, filesystem paths, secrets, request bodies, or package settings.

CREATE TABLE IF NOT EXISTS `RED_Addon_Activity_Log` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `EventName` varchar(64) NOT NULL,
  `PackageID` varchar(127) NOT NULL,
  `PackageVersion` varchar(120) NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `Result` varchar(16) NOT NULL,
  `DetailCode` varchar(64) NOT NULL,
  `OccurredAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_addon_activity_package` (`PackageID`,`OccurredAt`,`RecordID`),
  KEY `idx_red_addon_activity_event` (`EventName`,`OccurredAt`,`RecordID`),
  KEY `idx_red_addon_activity_actor` (`ActorAdminRecordID`,`OccurredAt`,`RecordID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_addon_activity_columns_valid = (
  SELECT COUNT(*) = 8
    AND SUM(COLUMN_NAME='RecordID' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='EventName' AND COLUMN_TYPE='varchar(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='PackageID' AND COLUMN_TYPE='varchar(127)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='PackageVersion' AND COLUMN_TYPE='varchar(120)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ActorAdminRecordID' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='Result' AND COLUMN_TYPE='varchar(16)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='DetailCode' AND COLUMN_TYPE='varchar(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='OccurredAt' AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Addon_Activity_Log'
);
SET @redcms_addon_activity_columns_sql = IF(
  @redcms_addon_activity_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Activity_Log_Unexpected_Columns`'
);
PREPARE redcms_addon_activity_columns_statement
  FROM @redcms_addon_activity_columns_sql;
EXECUTE redcms_addon_activity_columns_statement;
DEALLOCATE PREPARE redcms_addon_activity_columns_statement;

SET @redcms_addon_activity_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Activity_Log'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_addon_activity_package_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Activity_Log'
    AND INDEX_NAME='idx_red_addon_activity_package'
);
SET @redcms_addon_activity_event_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Activity_Log'
    AND INDEX_NAME='idx_red_addon_activity_event'
);
SET @redcms_addon_activity_actor_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Activity_Log'
    AND INDEX_NAME='idx_red_addon_activity_actor'
);
SET @redcms_addon_activity_indexes_sql = IF(
  @redcms_addon_activity_primary = 'RecordID'
    AND @redcms_addon_activity_package_index = 'PackageID,OccurredAt,RecordID'
    AND @redcms_addon_activity_event_index = 'EventName,OccurredAt,RecordID'
    AND @redcms_addon_activity_actor_index = 'ActorAdminRecordID,OccurredAt,RecordID',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Activity_Log_Unexpected_Indexes`'
);
PREPARE redcms_addon_activity_indexes_statement
  FROM @redcms_addon_activity_indexes_sql;
EXECUTE redcms_addon_activity_indexes_statement;
DEALLOCATE PREPARE redcms_addon_activity_indexes_statement;
