-- Restartable evidence for future add-on component destination coordination.
--
-- Core owns this empty per-client ledger. It stores package/component
-- identities, server-derived record identifiers, bounded stage names, and
-- SHA-256 evidence only. Package values, request bodies, tokens, secrets, and
-- rendered content never enter this table.

CREATE TABLE IF NOT EXISTS `RED_Addon_Component_Destination_Executions` (
  `PackageID` varchar(127) NOT NULL,
  `PlanSHA256` char(64) NOT NULL,
  `ComponentID` varchar(160) NOT NULL,
  `PackagePlanSHA256` char(64) NOT NULL,
  `RouteRecordID` int unsigned NOT NULL,
  `ComponentRecordID` int unsigned NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `Stage` varchar(32) NOT NULL,
  `RouteStateSHA256` char(64) DEFAULT NULL,
  `ComponentStateSHA256` char(64) DEFAULT NULL,
  `PlacementStateSHA256` char(64) DEFAULT NULL,
  `SearchNotification` varchar(16) NOT NULL DEFAULT 'pending',
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`PackageID`,`PlanSHA256`),
  UNIQUE KEY `idx_red_addon_destination_route` (`RouteRecordID`),
  UNIQUE KEY `idx_red_addon_destination_component` (`ComponentRecordID`),
  KEY `idx_red_addon_destination_stage`
    (`PackageID`,`Stage`,`UpdatedAt`),
  CONSTRAINT `fk_red_addon_destination_installation`
    FOREIGN KEY (`PackageID`) REFERENCES `RED_Addon_Installations` (`PackageID`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_addon_destination_columns_valid = (
  SELECT COUNT(*) = 14
    AND SUM(COLUMN_NAME='PackageID'
      AND COLUMN_TYPE='varchar(127)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='PlanSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ComponentID'
      AND COLUMN_TYPE='varchar(160)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='PackagePlanSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='RouteRecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ComponentRecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ActorAdminRecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='Stage'
      AND COLUMN_TYPE='varchar(32)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='RouteStateSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='YES') = 1
    AND SUM(COLUMN_NAME='ComponentStateSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='YES') = 1
    AND SUM(COLUMN_NAME='PlacementStateSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='YES') = 1
    AND SUM(COLUMN_NAME='SearchNotification'
      AND COLUMN_TYPE='varchar(16)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='CreatedAt'
      AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='UpdatedAt'
      AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Component_Destination_Executions'
);
SET @redcms_addon_destination_columns_sql = IF(
  @redcms_addon_destination_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Component_Destination_Executions_Unexpected_Columns`'
);
PREPARE redcms_addon_destination_columns_statement
  FROM @redcms_addon_destination_columns_sql;
EXECUTE redcms_addon_destination_columns_statement;
DEALLOCATE PREPARE redcms_addon_destination_columns_statement;

SET @redcms_addon_destination_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Component_Destination_Executions'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_addon_destination_route_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Component_Destination_Executions'
    AND INDEX_NAME='idx_red_addon_destination_route'
);
SET @redcms_addon_destination_component_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Component_Destination_Executions'
    AND INDEX_NAME='idx_red_addon_destination_component'
);
SET @redcms_addon_destination_stage_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Component_Destination_Executions'
    AND INDEX_NAME='idx_red_addon_destination_stage'
);
SET @redcms_addon_destination_indexes_sql = IF(
  @redcms_addon_destination_primary = 'PackageID,PlanSHA256'
    AND @redcms_addon_destination_route_index = 'RouteRecordID'
    AND @redcms_addon_destination_component_index = 'ComponentRecordID'
    AND @redcms_addon_destination_stage_index = 'PackageID,Stage,UpdatedAt',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Component_Destination_Executions_Unexpected_Indexes`'
);
PREPARE redcms_addon_destination_indexes_statement
  FROM @redcms_addon_destination_indexes_sql;
EXECUTE redcms_addon_destination_indexes_statement;
DEALLOCATE PREPARE redcms_addon_destination_indexes_statement;

SET @redcms_addon_destination_foreign_key_valid = (
  SELECT COUNT(*) = 1
    AND SUM(CONSTRAINT_NAME='fk_red_addon_destination_installation'
      AND TABLE_NAME='RED_Addon_Component_Destination_Executions'
      AND REFERENCED_TABLE_NAME='RED_Addon_Installations'
      AND DELETE_RULE='RESTRICT'
      AND UPDATE_RULE='RESTRICT') = 1
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND CONSTRAINT_NAME='fk_red_addon_destination_installation'
);
SET @redcms_addon_destination_foreign_key_sql = IF(
  @redcms_addon_destination_foreign_key_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Component_Destination_Executions_Unexpected_Foreign_Key`'
);
PREPARE redcms_addon_destination_foreign_key_statement
  FROM @redcms_addon_destination_foreign_key_sql;
EXECUTE redcms_addon_destination_foreign_key_statement;
DEALLOCATE PREPARE redcms_addon_destination_foreign_key_statement;
