-- Phase 2 utf8mb4 normalization for form component data.
-- Stored definitions, parent references, keys, table name, and engine stay unchanged.
-- Match the connection collation before the later Contact-layout migration casts its numeric parent ID to text.

SET collation_connection = 'utf8mb4_unicode_ci';

ALTER TABLE `RED_C_Form`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
