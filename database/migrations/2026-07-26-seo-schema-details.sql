-- Typed structured-data details for RED-CMS 5.1.
--
-- These nullable values extend the existing per-route SEO record without
-- introducing arbitrary JSON. Existing rows and rendered output remain
-- unchanged until an authorized editor or migration supplies values.

ALTER TABLE `RED_Page_SEO`
  ADD COLUMN `SchemaIdentityType` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `SchemaType`,
  ADD COLUMN `SchemaIdentityName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `SchemaIdentityType`,
  ADD COLUMN `SchemaIdentityURL` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `SchemaIdentityName`,
  ADD COLUMN `SchemaMainEntityName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `SchemaIdentityURL`,
  ADD COLUMN `SchemaEducationalLevel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `SchemaMainEntityName`,
  ADD COLUMN `SchemaCourseMode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `SchemaEducationalLevel`,
  ADD COLUMN `SchemaCourseWorkload` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `SchemaCourseMode`,
  ADD COLUMN `SchemaInstructorName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `SchemaCourseWorkload`,
  ADD COLUMN `SchemaTeaches` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `SchemaInstructorName`,
  ADD COLUMN `SchemaServiceType` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `SchemaTeaches`;
