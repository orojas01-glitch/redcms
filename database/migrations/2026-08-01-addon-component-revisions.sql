-- Immutable per-client snapshots for add-on component editor values.
--
-- Core owns this empty ledger. It stores no package PHP, settings, secrets,
-- media, or client package data in the starter distribution. Deliberately no
-- foreign key is used: future content/package removal policy must not erase
-- retained revision evidence implicitly.

CREATE TABLE IF NOT EXISTS `RED_Addon_Component_Revisions` (
  `RevisionID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ContentRecordID` int unsigned NOT NULL,
  `PackageID` varchar(127) NOT NULL,
  `ComponentID` varchar(160) NOT NULL,
  `RevisionNumber` int unsigned NOT NULL,
  `Operation` varchar(16) NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `ActorAlias` varchar(50) NOT NULL,
  `Snapshot` mediumtext NOT NULL,
  `StateHash` char(64) NOT NULL,
  `RestoredFromRevisionID` bigint unsigned DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RevisionID`),
  UNIQUE KEY `uq_red_addon_component_revision_number`
    (`ContentRecordID`,`RevisionNumber`),
  KEY `idx_red_addon_component_revision_timeline`
    (`PackageID`,`ComponentID`,`ContentRecordID`,`CreatedAt`,`RevisionID`),
  KEY `idx_red_addon_component_revision_actor`
    (`ActorAdminRecordID`,`CreatedAt`,`RevisionID`),
  KEY `idx_red_addon_component_revision_state`
    (`ContentRecordID`,`StateHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ROW_FORMAT=DYNAMIC;

SET @redcms_addon_component_revision_columns_valid = (
  SELECT COUNT(*) = 12
    AND SUM(COLUMN_NAME='RevisionID'
      AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ContentRecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='PackageID'
      AND COLUMN_TYPE='varchar(127)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ComponentID'
      AND COLUMN_TYPE='varchar(160)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='RevisionNumber'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='Operation'
      AND COLUMN_TYPE='varchar(16)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ActorAdminRecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ActorAlias'
      AND COLUMN_TYPE='varchar(50)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='Snapshot'
      AND DATA_TYPE='mediumtext' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='StateHash'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='RestoredFromRevisionID'
      AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='YES') = 1
    AND SUM(COLUMN_NAME='CreatedAt'
      AND DATA_TYPE='timestamp' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Component_Revisions'
);
SET @redcms_addon_component_revision_columns_sql = IF(
  @redcms_addon_component_revision_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Component_Revisions_Unexpected_Columns`'
);
PREPARE redcms_addon_component_revision_columns_statement
  FROM @redcms_addon_component_revision_columns_sql;
EXECUTE redcms_addon_component_revision_columns_statement;
DEALLOCATE PREPARE redcms_addon_component_revision_columns_statement;

SET @redcms_addon_component_revision_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Component_Revisions'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_addon_component_revision_number = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Component_Revisions'
    AND INDEX_NAME='uq_red_addon_component_revision_number'
);
SET @redcms_addon_component_revision_timeline = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Component_Revisions'
    AND INDEX_NAME='idx_red_addon_component_revision_timeline'
);
SET @redcms_addon_component_revision_indexes_sql = IF(
  @redcms_addon_component_revision_primary = 'RevisionID'
    AND @redcms_addon_component_revision_number
      = 'ContentRecordID,RevisionNumber'
    AND @redcms_addon_component_revision_timeline
      = 'PackageID,ComponentID,ContentRecordID,CreatedAt,RevisionID',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Component_Revisions_Unexpected_Indexes`'
);
PREPARE redcms_addon_component_revision_indexes_statement
  FROM @redcms_addon_component_revision_indexes_sql;
EXECUTE redcms_addon_component_revision_indexes_statement;
DEALLOCATE PREPARE redcms_addon_component_revision_indexes_statement;
