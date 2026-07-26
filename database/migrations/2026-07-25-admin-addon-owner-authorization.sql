-- Additive per-client Owner identity and normalized add-on lifecycle grants.
--
-- Existing Guest, Webmaster, and legacy Superadmin accounts receive no rows
-- and therefore no add-on lifecycle authority. Initial Owner assignment is an
-- explicit server-local bootstrap action, never an automatic migration.

CREATE TABLE IF NOT EXISTS `RED_Admin_Roles` (
  `AdminRecordID` int unsigned NOT NULL,
  `RoleName` varchar(32) NOT NULL,
  `AssignedByAdminRecordID` int unsigned NOT NULL,
  `AssignedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AdminRecordID`),
  KEY `idx_red_admin_roles_name` (`RoleName`,`AdminRecordID`),
  CONSTRAINT `fk_red_admin_roles_admin`
    FOREIGN KEY (`AdminRecordID`) REFERENCES `RED_Admin` (`RecordID`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `RED_Admin_Capabilities` (
  `AdminRecordID` int unsigned NOT NULL,
  `Capability` varchar(64) NOT NULL,
  `GrantedByAdminRecordID` int unsigned NOT NULL,
  `GrantedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AdminRecordID`,`Capability`),
  KEY `idx_red_admin_capabilities_capability` (`Capability`,`AdminRecordID`),
  CONSTRAINT `fk_red_admin_capabilities_admin`
    FOREIGN KEY (`AdminRecordID`) REFERENCES `RED_Admin` (`RecordID`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_admin_role_columns_valid = (
  SELECT COUNT(*) = 4
    AND SUM(COLUMN_NAME='AdminRecordID' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='RoleName' AND COLUMN_TYPE='varchar(32)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='AssignedByAdminRecordID' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='AssignedAt' AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Roles'
);
SET @redcms_admin_role_columns_sql = IF(
  @redcms_admin_role_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Admin_Roles_Unexpected_Columns`'
);
PREPARE redcms_admin_role_columns_statement FROM @redcms_admin_role_columns_sql;
EXECUTE redcms_admin_role_columns_statement;
DEALLOCATE PREPARE redcms_admin_role_columns_statement;

SET @redcms_admin_capability_columns_valid = (
  SELECT COUNT(*) = 4
    AND SUM(COLUMN_NAME='AdminRecordID' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='Capability' AND COLUMN_TYPE='varchar(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='GrantedByAdminRecordID' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='GrantedAt' AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Capabilities'
);
SET @redcms_admin_capability_columns_sql = IF(
  @redcms_admin_capability_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Admin_Capabilities_Unexpected_Columns`'
);
PREPARE redcms_admin_capability_columns_statement FROM @redcms_admin_capability_columns_sql;
EXECUTE redcms_admin_capability_columns_statement;
DEALLOCATE PREPARE redcms_admin_capability_columns_statement;

SET @redcms_admin_role_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Roles' AND INDEX_NAME='PRIMARY'
);
SET @redcms_admin_role_name_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Admin_Roles'
    AND INDEX_NAME='idx_red_admin_roles_name'
);
SET @redcms_admin_capability_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Capabilities' AND INDEX_NAME='PRIMARY'
);
SET @redcms_admin_capability_name_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Admin_Capabilities'
    AND INDEX_NAME='idx_red_admin_capabilities_capability'
);
SET @redcms_admin_authorization_indexes_sql = IF(
  @redcms_admin_role_primary = 'AdminRecordID'
    AND @redcms_admin_role_name_index = 'RoleName,AdminRecordID'
    AND @redcms_admin_capability_primary = 'AdminRecordID,Capability'
    AND @redcms_admin_capability_name_index = 'Capability,AdminRecordID',
  'SELECT 1',
  'SELECT * FROM `RED_Admin_Authorization_Unexpected_Indexes`'
);
PREPARE redcms_admin_authorization_indexes_statement FROM @redcms_admin_authorization_indexes_sql;
EXECUTE redcms_admin_authorization_indexes_statement;
DEALLOCATE PREPARE redcms_admin_authorization_indexes_statement;

SET @redcms_admin_authorization_foreign_keys_valid = (
  SELECT COUNT(*) = 2
    AND SUM(CONSTRAINT_NAME='fk_red_admin_roles_admin'
      AND TABLE_NAME='RED_Admin_Roles'
      AND REFERENCED_TABLE_NAME='RED_Admin'
      AND DELETE_RULE='RESTRICT'
      AND UPDATE_RULE='RESTRICT') = 1
    AND SUM(CONSTRAINT_NAME='fk_red_admin_capabilities_admin'
      AND TABLE_NAME='RED_Admin_Capabilities'
      AND REFERENCED_TABLE_NAME='RED_Admin'
      AND DELETE_RULE='CASCADE'
      AND UPDATE_RULE='RESTRICT') = 1
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND CONSTRAINT_NAME IN (
      'fk_red_admin_roles_admin',
      'fk_red_admin_capabilities_admin'
    )
);
SET @redcms_admin_authorization_foreign_keys_sql = IF(
  @redcms_admin_authorization_foreign_keys_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Admin_Authorization_Unexpected_Foreign_Keys`'
);
PREPARE redcms_admin_authorization_foreign_keys_statement
  FROM @redcms_admin_authorization_foreign_keys_sql;
EXECUTE redcms_admin_authorization_foreign_keys_statement;
DEALLOCATE PREPARE redcms_admin_authorization_foreign_keys_statement;
