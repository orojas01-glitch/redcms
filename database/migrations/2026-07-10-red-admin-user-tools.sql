-- Add explicit utility-tool permissions, widen required administrator email storage,
-- and enforce unique administrator usernames.
-- Existing administrators retain access to both current RED_Tools records.

SET @red_admin_tools_column_sql = IF(
  EXISTS(
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'RED_Admin'
      AND COLUMN_NAME = 'AdminTools'
  ),
  'SELECT 1',
  'ALTER TABLE `RED_Admin` ADD COLUMN `AdminTools` varchar(50) NOT NULL DEFAULT ''1,2'' COMMENT ''RecordID from RED_Tools'' AFTER `AdminComponents`'
);
PREPARE red_admin_tools_column_stmt FROM @red_admin_tools_column_sql;
EXECUTE red_admin_tools_column_stmt;
DEALLOCATE PREPARE red_admin_tools_column_stmt;

SET @red_admin_username_index_sql = IF(
  EXISTS(
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'RED_Admin'
      AND INDEX_NAME = 'uniq_red_admin_username'
  ),
  'SELECT 1',
  'ALTER TABLE `RED_Admin` ADD UNIQUE KEY `uniq_red_admin_username` (`Username`)'
);
PREPARE red_admin_username_index_stmt FROM @red_admin_username_index_sql;
EXECUTE red_admin_username_index_stmt;
DEALLOCATE PREPARE red_admin_username_index_stmt;

ALTER TABLE `RED_Admin`
  MODIFY `Email` varchar(254) NOT NULL;
