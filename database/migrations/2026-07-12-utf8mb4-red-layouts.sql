-- Phase 2 utf8mb4 normalization for layout registry metadata.
-- Stored values, table name, engine, and current no-key state stay unchanged.

ALTER TABLE `RED_Layouts`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
