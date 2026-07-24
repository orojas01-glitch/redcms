-- Phase 2 transaction foundation for layout registry metadata.
-- This changes only the storage engine; columns, collation, table name, and current key state stay unchanged.

ALTER TABLE `RED_Layouts` ENGINE=InnoDB;
