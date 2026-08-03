-- Immutable one-time execution evidence for enabled add-on administrator actions.
--
-- Core owns this empty per-client ledger. It retains only identifiers and
-- SHA-256 evidence; package target values, request bodies, tokens, and secrets
-- never enter this table. A successful action id may execute only once for one
-- numeric target in one client database.

CREATE TABLE IF NOT EXISTS `RED_Addon_Admin_Action_Executions` (
  `PackageID` varchar(127) NOT NULL,
  `ActionID` varchar(160) NOT NULL,
  `TargetRecordID` int unsigned NOT NULL,
  `PlanSHA256` char(64) NOT NULL,
  `ContractSHA256` char(64) NOT NULL,
  `PreviousStateSHA256` char(64) NOT NULL,
  `StateSHA256` char(64) NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `CompletedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`PackageID`,`ActionID`,`TargetRecordID`),
  KEY `idx_red_addon_admin_action_execution_package`
    (`PackageID`,`CompletedAt`,`TargetRecordID`),
  CONSTRAINT `fk_red_addon_admin_action_execution_installation`
    FOREIGN KEY (`PackageID`) REFERENCES `RED_Addon_Installations` (`PackageID`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_addon_action_execution_columns_valid = (
  SELECT COUNT(*) = 9
    AND SUM(COLUMN_NAME='PackageID'
      AND COLUMN_TYPE='varchar(127)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ActionID'
      AND COLUMN_TYPE='varchar(160)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='TargetRecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='PlanSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ContractSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='PreviousStateSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='StateSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ActorAdminRecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='CompletedAt'
      AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Admin_Action_Executions'
);
SET @redcms_addon_action_execution_columns_sql = IF(
  @redcms_addon_action_execution_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Admin_Action_Executions_Unexpected_Columns`'
);
PREPARE redcms_addon_action_execution_columns_statement
  FROM @redcms_addon_action_execution_columns_sql;
EXECUTE redcms_addon_action_execution_columns_statement;
DEALLOCATE PREPARE redcms_addon_action_execution_columns_statement;

SET @redcms_addon_action_execution_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Admin_Action_Executions'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_addon_action_execution_package_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Admin_Action_Executions'
    AND INDEX_NAME='idx_red_addon_admin_action_execution_package'
);
SET @redcms_addon_action_execution_indexes_sql = IF(
  @redcms_addon_action_execution_primary = 'PackageID,ActionID,TargetRecordID'
    AND @redcms_addon_action_execution_package_index
      = 'PackageID,CompletedAt,TargetRecordID',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Admin_Action_Executions_Unexpected_Indexes`'
);
PREPARE redcms_addon_action_execution_indexes_statement
  FROM @redcms_addon_action_execution_indexes_sql;
EXECUTE redcms_addon_action_execution_indexes_statement;
DEALLOCATE PREPARE redcms_addon_action_execution_indexes_statement;

SET @redcms_addon_action_execution_foreign_key_valid = (
  SELECT COUNT(*) = 1
    AND SUM(CONSTRAINT_NAME='fk_red_addon_admin_action_execution_installation'
      AND TABLE_NAME='RED_Addon_Admin_Action_Executions'
      AND REFERENCED_TABLE_NAME='RED_Addon_Installations'
      AND DELETE_RULE='RESTRICT'
      AND UPDATE_RULE='RESTRICT') = 1
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND CONSTRAINT_NAME='fk_red_addon_admin_action_execution_installation'
);
SET @redcms_addon_action_execution_foreign_key_sql = IF(
  @redcms_addon_action_execution_foreign_key_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Admin_Action_Executions_Unexpected_Foreign_Key`'
);
PREPARE redcms_addon_action_execution_foreign_key_statement
  FROM @redcms_addon_action_execution_foreign_key_sql;
EXECUTE redcms_addon_action_execution_foreign_key_statement;
DEALLOCATE PREPARE redcms_addon_action_execution_foreign_key_statement;
