-- Additive per-client add-on installation state and immutable migration ledger.
--
-- This migration creates empty generic storage only. It does not discover,
-- install, enable, execute, migrate, disable, uninstall, or purge a package.

CREATE TABLE IF NOT EXISTS `RED_Addon_Installations` (
  `PackageID` varchar(127) NOT NULL,
  `PackageVersion` varchar(120) NOT NULL,
  `PackageType` varchar(32) NOT NULL,
  `ManifestSHA256` char(64) NOT NULL,
  `InventorySHA256` char(64) NOT NULL,
  `LifecycleState` varchar(32) NOT NULL,
  `InstalledByAdminRecordID` int unsigned NOT NULL,
  `InstalledAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedByAdminRecordID` int unsigned NOT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`PackageID`),
  KEY `idx_red_addon_installations_state` (`LifecycleState`,`PackageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `RED_Addon_Migrations` (
  `PackageID` varchar(127) NOT NULL,
  `MigrationID` varchar(120) NOT NULL,
  `MigrationPath` varchar(255) NOT NULL,
  `Checksum` char(64) NOT NULL,
  `AppliedByAdminRecordID` int unsigned NOT NULL,
  `AppliedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ExecutionMs` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`PackageID`,`MigrationID`),
  UNIQUE KEY `uq_red_addon_migrations_path` (`PackageID`,`MigrationPath`),
  CONSTRAINT `fk_red_addon_migrations_installation`
    FOREIGN KEY (`PackageID`) REFERENCES `RED_Addon_Installations` (`PackageID`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_addon_installation_columns_valid = (
  SELECT COUNT(*) = 10
    AND SUM(COLUMN_NAME='PackageID' AND COLUMN_TYPE='varchar(127)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='PackageVersion' AND COLUMN_TYPE='varchar(120)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='PackageType' AND COLUMN_TYPE='varchar(32)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ManifestSHA256' AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='InventorySHA256' AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='LifecycleState' AND COLUMN_TYPE='varchar(32)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='InstalledByAdminRecordID' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='InstalledAt' AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='UpdatedByAdminRecordID' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='UpdatedAt' AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Addon_Installations'
);
SET @redcms_addon_installation_columns_sql = IF(
  @redcms_addon_installation_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Installations_Unexpected_Columns`'
);
PREPARE redcms_addon_installation_columns_statement
  FROM @redcms_addon_installation_columns_sql;
EXECUTE redcms_addon_installation_columns_statement;
DEALLOCATE PREPARE redcms_addon_installation_columns_statement;

SET @redcms_addon_migration_columns_valid = (
  SELECT COUNT(*) = 7
    AND SUM(COLUMN_NAME='PackageID' AND COLUMN_TYPE='varchar(127)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='MigrationID' AND COLUMN_TYPE='varchar(120)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='MigrationPath' AND COLUMN_TYPE='varchar(255)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='Checksum' AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='AppliedByAdminRecordID' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='AppliedAt' AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ExecutionMs' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Addon_Migrations'
);
SET @redcms_addon_migration_columns_sql = IF(
  @redcms_addon_migration_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Migrations_Unexpected_Columns`'
);
PREPARE redcms_addon_migration_columns_statement
  FROM @redcms_addon_migration_columns_sql;
EXECUTE redcms_addon_migration_columns_statement;
DEALLOCATE PREPARE redcms_addon_migration_columns_statement;

SET @redcms_addon_installation_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Installations'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_addon_installation_state_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Installations'
    AND INDEX_NAME='idx_red_addon_installations_state'
);
SET @redcms_addon_migration_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Migrations'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_addon_migration_path_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Migrations'
    AND INDEX_NAME='uq_red_addon_migrations_path'
);
SET @redcms_addon_registry_indexes_sql = IF(
  @redcms_addon_installation_primary = 'PackageID'
    AND @redcms_addon_installation_state_index = 'LifecycleState,PackageID'
    AND @redcms_addon_migration_primary = 'PackageID,MigrationID'
    AND @redcms_addon_migration_path_index = 'PackageID,MigrationPath',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Registry_Unexpected_Indexes`'
);
PREPARE redcms_addon_registry_indexes_statement
  FROM @redcms_addon_registry_indexes_sql;
EXECUTE redcms_addon_registry_indexes_statement;
DEALLOCATE PREPARE redcms_addon_registry_indexes_statement;

SET @redcms_addon_registry_foreign_key_valid = (
  SELECT COUNT(*) = 1
    AND SUM(CONSTRAINT_NAME='fk_red_addon_migrations_installation'
      AND TABLE_NAME='RED_Addon_Migrations'
      AND REFERENCED_TABLE_NAME='RED_Addon_Installations'
      AND DELETE_RULE='RESTRICT'
      AND UPDATE_RULE='RESTRICT') = 1
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND CONSTRAINT_NAME='fk_red_addon_migrations_installation'
);
SET @redcms_addon_registry_foreign_key_sql = IF(
  @redcms_addon_registry_foreign_key_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Registry_Unexpected_Foreign_Key`'
);
PREPARE redcms_addon_registry_foreign_key_statement
  FROM @redcms_addon_registry_foreign_key_sql;
EXECUTE redcms_addon_registry_foreign_key_statement;
DEALLOCATE PREPARE redcms_addon_registry_foreign_key_statement;
