-- Phase 2 utf8mb4 normalization for administrator accounts.
-- Stored credentials, permissions, roles, indexes, table name, and engine stay unchanged.

ALTER TABLE `RED_Admin`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
