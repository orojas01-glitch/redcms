-- Preserve readable article image names while keeping generated upload names
-- below the shared filesystem-safe limit enforced by upload_helpers.php.

SET @red_article_image_name_original_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = '';

ALTER TABLE `RED_Articles`
  MODIFY `BigPict` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  MODIFY `SmallPict` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  MODIFY `SmallPict2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

SET SESSION sql_mode = @red_article_image_name_original_sql_mode;
