-- Phase 2 transaction foundation for administrator account writes.
-- This changes only the storage engine; columns, indexes, collation, and table name stay unchanged.

ALTER TABLE `RED_Admin` ENGINE=InnoDB;
