-- Phase 2 transaction foundation for the component, feature, and utility registries.
-- This changes only storage engines; columns, collations, keys, and table names stay unchanged.

ALTER TABLE `RED_Components` ENGINE=InnoDB;
ALTER TABLE `RED_Features` ENGINE=InnoDB;
ALTER TABLE `RED_Tools` ENGINE=InnoDB;
