-- Phase 1 transaction prerequisite for article/component and area-rename writes.
-- Run after a database backup and verify the resulting table engines.
-- Legacy RED_Articles rows may contain zero dates, so relax only this migration session.

SET SESSION sql_mode='';

ALTER TABLE `RED_Articles` ENGINE=InnoDB;
ALTER TABLE `RED_C_Form` ENGINE=InnoDB;
ALTER TABLE `RED_C_Gallery` ENGINE=InnoDB;
ALTER TABLE `RED_C_Menu` ENGINE=InnoDB;
ALTER TABLE `RED_Menu` ENGINE=InnoDB;
ALTER TABLE `RED_Sections` ENGINE=InnoDB;
ALTER TABLE `RED_Categories` ENGINE=InnoDB;
ALTER TABLE `RED_SubCategories` ENGINE=InnoDB;
