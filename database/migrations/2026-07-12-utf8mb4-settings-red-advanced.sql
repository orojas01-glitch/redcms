-- Phase 2 utf8mb4 normalization for Advanced settings metadata and content.
-- Stored values, table name, engine, primary key, and transaction behavior stay unchanged.

ALTER TABLE `RED_Advanced`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
