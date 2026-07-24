-- Phase 2 stable key for layout registry metadata.
-- Preserve the table name and row data while making the existing unique layout name the primary key.

SET @redcms_layout_primary_key = (
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'RED_Layouts'
      AND INDEX_NAME = 'PRIMARY'
);

SET @redcms_layout_primary_key_sql = CASE
    WHEN @redcms_layout_primary_key IS NULL THEN
        'ALTER TABLE `RED_Layouts` ADD PRIMARY KEY (`UniqueName`)'
    WHEN @redcms_layout_primary_key = 'UniqueName' THEN
        'SELECT 1'
    ELSE
        'SELECT * FROM `RED_Layouts_Unexpected_Primary_Key`'
END;

PREPARE redcms_layout_primary_key_statement FROM @redcms_layout_primary_key_sql;
EXECUTE redcms_layout_primary_key_statement;
DEALLOCATE PREPARE redcms_layout_primary_key_statement;
