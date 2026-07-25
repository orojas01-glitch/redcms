CREATE TABLE IF NOT EXISTS `RED_Custom_Layouts` (
  `LayoutID` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DraftLabel` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DraftDefinition` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DraftHash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PublishedLabel` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PublishedDefinition` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `PublishedHash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `RevisionNumber` int unsigned NOT NULL DEFAULT 1,
  `Archived` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `CreatedByAdminRecordID` int unsigned NOT NULL,
  `UpdatedByAdminRecordID` int unsigned NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `PublishedAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`LayoutID`),
  KEY `idx_red_custom_layout_status` (`Archived`,`PublishedAt`),
  KEY `idx_red_custom_layout_updated` (`UpdatedAt`,`LayoutID`),
  CONSTRAINT `chk_red_custom_layout_archived` CHECK (`Archived` IN ('Y','N'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `RED_Custom_Layout_Revisions` (
  `RevisionID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `LayoutID` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RevisionNumber` int unsigned NOT NULL,
  `Operation` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `ActorAlias` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Snapshot` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `SnapshotHash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RestoredFromRevisionID` bigint unsigned DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RevisionID`),
  UNIQUE KEY `uniq_red_custom_layout_revision` (`LayoutID`,`RevisionNumber`),
  KEY `idx_red_custom_layout_timeline` (`LayoutID`,`CreatedAt`,`RevisionID`),
  KEY `idx_red_custom_layout_revision_actor` (`ActorAdminRecordID`,`CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
