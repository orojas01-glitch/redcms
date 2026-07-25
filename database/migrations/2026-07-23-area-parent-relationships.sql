-- Give Categories and Subcategories explicit, stable parent records.
--
-- The legacy Article route strings remain intact for URL compatibility.
-- Existing parentless rows are backfilled only when their Article paths
-- identify exactly one valid parent; ambiguous or unused legacy rows remain
-- NULL until an administrator assigns them.

SET @redcms_category_parent_column = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Categories'
    AND COLUMN_NAME='SectionRecordID'
);
SET @redcms_category_parent_sql = IF(
  @redcms_category_parent_column=0,
  'ALTER TABLE `RED_Categories` ADD COLUMN `SectionRecordID` int unsigned DEFAULT NULL COMMENT ''Parent RED_Sections.RecordID'' AFTER `RecordID`',
  'SELECT 1'
);
PREPARE redcms_category_parent_statement FROM @redcms_category_parent_sql;
EXECUTE redcms_category_parent_statement;
DEALLOCATE PREPARE redcms_category_parent_statement;

SET @redcms_subcategory_parent_column = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_SubCategories'
    AND COLUMN_NAME='CategoryRecordID'
);
SET @redcms_subcategory_parent_sql = IF(
  @redcms_subcategory_parent_column=0,
  'ALTER TABLE `RED_SubCategories` ADD COLUMN `CategoryRecordID` int unsigned DEFAULT NULL COMMENT ''Parent RED_Categories.RecordID'' AFTER `RecordID`',
  'SELECT 1'
);
PREPARE redcms_subcategory_parent_statement FROM @redcms_subcategory_parent_sql;
EXECUTE redcms_subcategory_parent_statement;
DEALLOCATE PREPARE redcms_subcategory_parent_statement;

UPDATE RED_Categories AS category_area
JOIN (
  SELECT
    article.Categories,
    article.Language,
    MIN(section_area.RecordID) AS SectionRecordID
  FROM RED_Articles AS article
  JOIN RED_Sections AS section_area
    ON section_area.Sections=article.Sections
   AND section_area.Language=article.Language
  WHERE TRIM(article.Categories)<>''
  GROUP BY article.Categories, article.Language
  HAVING COUNT(DISTINCT section_area.RecordID)=1
) AS inferred_category_parent
  ON inferred_category_parent.Categories=category_area.Categories
 AND inferred_category_parent.Language=category_area.Language
SET category_area.SectionRecordID=inferred_category_parent.SectionRecordID
WHERE category_area.SectionRecordID IS NULL;

UPDATE RED_SubCategories AS subcategory_area
JOIN (
  SELECT
    article.SubCategories,
    article.Language,
    MIN(category_area.RecordID) AS CategoryRecordID
  FROM RED_Articles AS article
  JOIN RED_Categories AS category_area
    ON category_area.Categories=article.Categories
   AND category_area.Language=article.Language
  JOIN RED_Sections AS section_area
    ON section_area.RecordID=category_area.SectionRecordID
   AND section_area.Sections=article.Sections
   AND section_area.Language=article.Language
  WHERE TRIM(article.SubCategories)<>''
  GROUP BY article.SubCategories, article.Language
  HAVING COUNT(DISTINCT category_area.RecordID)=1
) AS inferred_subcategory_parent
  ON inferred_subcategory_parent.SubCategories=subcategory_area.SubCategories
 AND inferred_subcategory_parent.Language=subcategory_area.Language
SET subcategory_area.CategoryRecordID=inferred_subcategory_parent.CategoryRecordID
WHERE subcategory_area.CategoryRecordID IS NULL;

UPDATE RED_Categories AS category_area
LEFT JOIN RED_Sections AS section_area
  ON section_area.RecordID=category_area.SectionRecordID
 AND section_area.Language=category_area.Language
SET category_area.SectionRecordID=NULL
WHERE category_area.SectionRecordID IS NOT NULL
  AND section_area.RecordID IS NULL;

UPDATE RED_SubCategories AS subcategory_area
LEFT JOIN RED_Categories AS category_area
  ON category_area.RecordID=subcategory_area.CategoryRecordID
 AND category_area.Language=subcategory_area.Language
SET subcategory_area.CategoryRecordID=NULL
WHERE subcategory_area.CategoryRecordID IS NOT NULL
  AND category_area.RecordID IS NULL;

SET @redcms_category_parent_index = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Categories'
    AND INDEX_NAME='idx_red_categories_parent'
);
SET @redcms_category_parent_index_sql = IF(
  @redcms_category_parent_index=0,
  'ALTER TABLE `RED_Categories` ADD INDEX `idx_red_categories_parent` (`SectionRecordID`,`Language`,`Active`,`Categories`)',
  'SELECT 1'
);
PREPARE redcms_category_parent_index_statement FROM @redcms_category_parent_index_sql;
EXECUTE redcms_category_parent_index_statement;
DEALLOCATE PREPARE redcms_category_parent_index_statement;

SET @redcms_subcategory_parent_index = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_SubCategories'
    AND INDEX_NAME='idx_red_subcategories_parent'
);
SET @redcms_subcategory_parent_index_sql = IF(
  @redcms_subcategory_parent_index=0,
  'ALTER TABLE `RED_SubCategories` ADD INDEX `idx_red_subcategories_parent` (`CategoryRecordID`,`Language`,`Active`,`SubCategories`)',
  'SELECT 1'
);
PREPARE redcms_subcategory_parent_index_statement FROM @redcms_subcategory_parent_index_sql;
EXECUTE redcms_subcategory_parent_index_statement;
DEALLOCATE PREPARE redcms_subcategory_parent_index_statement;

SET @redcms_category_parent_fk = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Categories'
    AND CONSTRAINT_NAME='fk_red_categories_section'
);
SET @redcms_category_parent_fk_sql = IF(
  @redcms_category_parent_fk=0,
  'ALTER TABLE `RED_Categories` ADD CONSTRAINT `fk_red_categories_section` FOREIGN KEY (`SectionRecordID`) REFERENCES `RED_Sections` (`RecordID`) ON DELETE RESTRICT ON UPDATE RESTRICT',
  'SELECT 1'
);
PREPARE redcms_category_parent_fk_statement FROM @redcms_category_parent_fk_sql;
EXECUTE redcms_category_parent_fk_statement;
DEALLOCATE PREPARE redcms_category_parent_fk_statement;

SET @redcms_subcategory_parent_fk = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_SubCategories'
    AND CONSTRAINT_NAME='fk_red_subcategories_category'
);
SET @redcms_subcategory_parent_fk_sql = IF(
  @redcms_subcategory_parent_fk=0,
  'ALTER TABLE `RED_SubCategories` ADD CONSTRAINT `fk_red_subcategories_category` FOREIGN KEY (`CategoryRecordID`) REFERENCES `RED_Categories` (`RecordID`) ON DELETE RESTRICT ON UPDATE RESTRICT',
  'SELECT 1'
);
PREPARE redcms_subcategory_parent_fk_statement FROM @redcms_subcategory_parent_fk_sql;
EXECUTE redcms_subcategory_parent_fk_statement;
DEALLOCATE PREPARE redcms_subcategory_parent_fk_statement;
