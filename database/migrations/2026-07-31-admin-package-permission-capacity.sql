-- Align per-client administrator capability storage with the existing
-- Manifest Version 1 permission limit. This adds no grants and changes no
-- administrator role or package lifecycle state.

ALTER TABLE `RED_Admin_Capabilities`
  MODIFY `Capability` varchar(160)
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
