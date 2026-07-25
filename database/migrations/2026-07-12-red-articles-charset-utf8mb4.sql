-- Phase 2 utf8mb4 normalization for the central content table.
-- Stored content, relationships, positions, key, table name, and engine stay unchanged.

SET @red_articles_original_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = '';

ALTER TABLE `RED_Articles`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET SESSION sql_mode = @red_articles_original_sql_mode;
