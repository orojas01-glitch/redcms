-- Phase 2 utf8mb4 normalization for low-risk registry tables.
-- Stored values, keys, table names, and storage engines stay unchanged.

ALTER TABLE `RED_Components`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `RED_Features`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `RED_Tools`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
