-- Match persisted layout references to the validated theme-id contract.
-- Theme packages remain file based; RED_Layouts stays the legacy sizing
-- registry and is not populated from standard-theme manifests.

SET @red_theme_layout_original_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = '';

ALTER TABLE `RED_Articles`
  MODIFY `Layout` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  NOT NULL DEFAULT 'Full-Width' COMMENT 'Validated theme layout id';

ALTER TABLE `RED_Sections`
  MODIFY `Layout` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE `RED_Categories`
  MODIFY `Layout` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE `RED_SubCategories`
  MODIFY `Layout` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE `RED_Layouts`
  MODIFY `UniqueName` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

SET SESSION sql_mode = @red_theme_layout_original_sql_mode;
