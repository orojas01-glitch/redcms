-- Phase 2 indexes proven against the disposable 50,000-article hierarchy fixture.
-- Preserve table/column names and legacy query contracts while avoiding full scans.

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(CONCAT(COLUMN_NAME, IF(SUB_PART IS NULL, '', CONCAT('(', SUB_PART, ')')))
                      ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Articles'
    AND INDEX_NAME='idx_red_articles_public_route'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_Articles` ADD INDEX `idx_red_articles_public_route` (`Language`, `Active`, `Alias`(191), `Sections`, `Categories`, `SubCategories`)'
  WHEN @redcms_index_columns = 'Language,Active,Alias(191),Sections,Categories,SubCategories' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Articles_Unexpected_idx_red_articles_public_route`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Articles'
    AND INDEX_NAME='idx_red_articles_section_content'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_Articles` ADD INDEX `idx_red_articles_section_content` (`Language`, `Active`, `Sections`, `SectionPosition`, `SectionPositionOrder`, `StartDate`)'
  WHEN @redcms_index_columns = 'Language,Active,Sections,SectionPosition,SectionPositionOrder,StartDate' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Articles_Unexpected_idx_red_articles_section_content`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Articles'
    AND INDEX_NAME='idx_red_articles_category_content'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_Articles` ADD INDEX `idx_red_articles_category_content` (`Language`, `Active`, `Sections`, `Categories`, `CategoryPosition`, `CategoryPositionOrder`, `StartDate`)'
  WHEN @redcms_index_columns = 'Language,Active,Sections,Categories,CategoryPosition,CategoryPositionOrder,StartDate' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Articles_Unexpected_idx_red_articles_category_content`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Articles'
    AND INDEX_NAME='idx_red_articles_subcategory_content'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_Articles` ADD INDEX `idx_red_articles_subcategory_content` (`Language`, `Active`, `Sections`, `Categories`, `SubCategories`, `SubCategoryPosition`, `SubCategoryPositionOrder`, `StartDate`)'
  WHEN @redcms_index_columns = 'Language,Active,Sections,Categories,SubCategories,SubCategoryPosition,SubCategoryPositionOrder,StartDate' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Articles_Unexpected_idx_red_articles_subcategory_content`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Sections'
    AND INDEX_NAME='idx_red_sections_public_alias'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_Sections` ADD INDEX `idx_red_sections_public_alias` (`Language`, `Active`, `Sections`)'
  WHEN @redcms_index_columns = 'Language,Active,Sections' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Sections_Unexpected_idx_red_sections_public_alias`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Categories'
    AND INDEX_NAME='idx_red_categories_public_alias'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_Categories` ADD INDEX `idx_red_categories_public_alias` (`Language`, `Active`, `Categories`)'
  WHEN @redcms_index_columns = 'Language,Active,Categories' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Categories_Unexpected_idx_red_categories_public_alias`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_SubCategories'
    AND INDEX_NAME='idx_red_subcategories_public_alias'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_SubCategories` ADD INDEX `idx_red_subcategories_public_alias` (`Language`, `Active`, `SubCategories`)'
  WHEN @redcms_index_columns = 'Language,Active,SubCategories' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_SubCategories_Unexpected_idx_red_subcategories_public_alias`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Menu'
    AND INDEX_NAME='idx_red_menu_public_order'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_Menu` ADD INDEX `idx_red_menu_public_order` (`Language`, `Active`, `MenuOrder`)'
  WHEN @redcms_index_columns = 'Language,Active,MenuOrder' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_Menu_Unexpected_idx_red_menu_public_order`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_C_Form'
    AND INDEX_NAME='idx_red_c_form_refid'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_C_Form` ADD INDEX `idx_red_c_form_refid` (`RefID`)'
  WHEN @redcms_index_columns = 'RefID' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_C_Form_Unexpected_idx_red_c_form_refid`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;

SET @redcms_index_columns = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_C_Gallery'
    AND INDEX_NAME='idx_red_c_gallery_refid'
);
SET @redcms_index_sql = CASE
  WHEN @redcms_index_columns IS NULL THEN
    'ALTER TABLE `RED_C_Gallery` ADD INDEX `idx_red_c_gallery_refid` (`RefID`)'
  WHEN @redcms_index_columns = 'RefID' THEN 'SELECT 1'
  ELSE 'SELECT * FROM `RED_C_Gallery_Unexpected_idx_red_c_gallery_refid`'
END;
PREPARE redcms_index_statement FROM @redcms_index_sql;
EXECUTE redcms_index_statement;
DEALLOCATE PREPARE redcms_index_statement;
