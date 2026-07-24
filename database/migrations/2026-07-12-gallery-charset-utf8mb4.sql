-- Phase 2 utf8mb4 normalization for Gallery, Video, Banner, and Carrousel component data.
-- Stored media values, parent references, key, table name, and engine stay unchanged.

ALTER TABLE `RED_C_Gallery`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
