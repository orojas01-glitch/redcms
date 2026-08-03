-- Empty per-client storage for validated add-on setting values.
--
-- Core stores only normalized non-secret scalar JSON or an opaque config
-- reference. This migration does not add package data, resolve secrets,
-- authorize an actor, execute package code, or enable a package.

CREATE TABLE IF NOT EXISTS `RED_Addon_Settings` (
  `PackageID` varchar(127) NOT NULL,
  `SettingKey` varchar(160) NOT NULL,
  `ValueType` varchar(32) NOT NULL,
  `ValueJSON` text DEFAULT NULL,
  `SecretReference` varchar(160) DEFAULT NULL,
  `UpdatedByAdminRecordID` int unsigned NOT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`PackageID`,`SettingKey`),
  KEY `idx_red_addon_settings_updated`
    (`PackageID`,`UpdatedAt`,`SettingKey`),
  CONSTRAINT `fk_red_addon_settings_installation`
    FOREIGN KEY (`PackageID`) REFERENCES `RED_Addon_Installations` (`PackageID`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_addon_settings_columns_valid = (
  SELECT COUNT(*) = 7
    AND SUM(COLUMN_NAME='PackageID'
      AND COLUMN_TYPE='varchar(127)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='SettingKey'
      AND COLUMN_TYPE='varchar(160)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ValueType'
      AND COLUMN_TYPE='varchar(32)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ValueJSON'
      AND DATA_TYPE='text' AND IS_NULLABLE='YES') = 1
    AND SUM(COLUMN_NAME='SecretReference'
      AND COLUMN_TYPE='varchar(160)' AND IS_NULLABLE='YES') = 1
    AND SUM(COLUMN_NAME='UpdatedByAdminRecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='UpdatedAt'
      AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Addon_Settings'
);
SET @redcms_addon_settings_columns_sql = IF(
  @redcms_addon_settings_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Settings_Unexpected_Columns`'
);
PREPARE redcms_addon_settings_columns_statement
  FROM @redcms_addon_settings_columns_sql;
EXECUTE redcms_addon_settings_columns_statement;
DEALLOCATE PREPARE redcms_addon_settings_columns_statement;

SET @redcms_addon_settings_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Settings'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_addon_settings_updated_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Settings'
    AND INDEX_NAME='idx_red_addon_settings_updated'
);
SET @redcms_addon_settings_indexes_sql = IF(
  @redcms_addon_settings_primary = 'PackageID,SettingKey'
    AND @redcms_addon_settings_updated_index
      = 'PackageID,UpdatedAt,SettingKey',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Settings_Unexpected_Indexes`'
);
PREPARE redcms_addon_settings_indexes_statement
  FROM @redcms_addon_settings_indexes_sql;
EXECUTE redcms_addon_settings_indexes_statement;
DEALLOCATE PREPARE redcms_addon_settings_indexes_statement;

SET @redcms_addon_settings_foreign_key_valid = (
  SELECT COUNT(*) = 1
    AND SUM(CONSTRAINT_NAME='fk_red_addon_settings_installation'
      AND TABLE_NAME='RED_Addon_Settings'
      AND REFERENCED_TABLE_NAME='RED_Addon_Installations'
      AND DELETE_RULE='RESTRICT'
      AND UPDATE_RULE='RESTRICT') = 1
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND CONSTRAINT_NAME='fk_red_addon_settings_installation'
);
SET @redcms_addon_settings_foreign_key_sql = IF(
  @redcms_addon_settings_foreign_key_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Settings_Unexpected_Foreign_Key`'
);
PREPARE redcms_addon_settings_foreign_key_statement
  FROM @redcms_addon_settings_foreign_key_sql;
EXECUTE redcms_addon_settings_foreign_key_statement;
DEALLOCATE PREPARE redcms_addon_settings_foreign_key_statement;
