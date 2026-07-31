-- Persist the full manifest capability id on the existing component-placement
-- parent without adding package business fields to core storage.

ALTER TABLE `RED_Articles`
  MODIFY `Component` varchar(160)
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
