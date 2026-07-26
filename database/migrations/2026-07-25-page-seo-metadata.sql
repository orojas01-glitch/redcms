-- Nullable per-route SEO overrides for RED-CMS 5.1.
--
-- The polymorphic owner is constrained by application allowlists to Article,
-- Section, Category, or Subcategory. Existing installations receive no rows,
-- so legacy title and metadata rendering remains unchanged until an authorized
-- editor or migration explicitly saves an override.

CREATE TABLE IF NOT EXISTS `RED_Page_SEO` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `OwnerType` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `OwnerRecordID` int unsigned NOT NULL,
  `SEO_Title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MetaDescription` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `CanonicalURL` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `RobotsIndex` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `RobotsFollow` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `OGTitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `OGDescription` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `OGImage` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `OGImageAlt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `OGType` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `OGLocale` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `XCard` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `XTitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `XDescription` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `XImage` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SchemaType` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UpdatedByAdminRecordID` int unsigned DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uniq_red_page_seo_owner` (`OwnerType`,`OwnerRecordID`),
  KEY `idx_red_page_seo_updated` (`UpdatedAt`,`RecordID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
