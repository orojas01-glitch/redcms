CREATE TABLE IF NOT EXISTS `RED_Content_Revisions` (
  `RevisionID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ContentRecordID` int unsigned NOT NULL,
  `ContentType` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RevisionNumber` int unsigned NOT NULL,
  `Operation` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `ActorAlias` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Snapshot` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `SnapshotHash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RestoredFromRevisionID` bigint unsigned DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RevisionID`),
  UNIQUE KEY `uniq_red_content_revision_number` (`ContentRecordID`,`RevisionNumber`),
  KEY `idx_red_content_revision_timeline` (`ContentRecordID`,`CreatedAt`,`RevisionID`),
  KEY `idx_red_content_revision_actor_time` (`ActorAdminRecordID`,`CreatedAt`),
  KEY `idx_red_content_revision_hash` (`ContentRecordID`,`SnapshotHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
