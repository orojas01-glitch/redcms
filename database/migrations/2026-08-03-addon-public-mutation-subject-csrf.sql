-- Core-owned anonymous-subject and CSRF storage for a future add-on public
-- mutation boundary.
--
-- Both tables stay empty in the portable starter. They contain only SHA-256
-- hashes of random core-issued values, expiration facts, and an opaque
-- subject relationship. They do not contain a package record, cart, order,
-- request body, cookie value, CSRF token, secret, or client business data.

CREATE TABLE IF NOT EXISTS `RED_Addon_Public_Mutation_Subjects` (
  `RecordID` int unsigned NOT NULL AUTO_INCREMENT,
  `SubjectTokenSHA256` char(64) NOT NULL,
  `CreatedAt` datetime NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_red_addon_public_mutation_subject_token`
    (`SubjectTokenSHA256`),
  KEY `idx_red_addon_public_mutation_subject_expiry`
    (`ExpiresAt`,`RecordID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `RED_Addon_Public_Mutation_CSRF_Tokens` (
  `RecordID` int unsigned NOT NULL AUTO_INCREMENT,
  `SubjectRecordID` int unsigned NOT NULL,
  `ScopeSHA256` char(64) NOT NULL,
  `TokenSHA256` char(64) NOT NULL,
  `CreatedAt` datetime NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_red_addon_public_mutation_csrf_token`
    (`SubjectRecordID`,`ScopeSHA256`,`TokenSHA256`),
  KEY `idx_red_addon_public_mutation_csrf_expiry`
    (`ExpiresAt`,`RecordID`),
  CONSTRAINT `fk_red_addon_public_mutation_csrf_subject`
    FOREIGN KEY (`SubjectRecordID`)
    REFERENCES `RED_Addon_Public_Mutation_Subjects` (`RecordID`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_public_mutation_subject_columns_valid = (
  SELECT COUNT(*) = 4
    AND SUM(COLUMN_NAME='RecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO'
      AND EXTRA='auto_increment') = 1
    AND SUM(COLUMN_NAME='SubjectTokenSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='CreatedAt'
      AND DATA_TYPE='datetime' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ExpiresAt'
      AND DATA_TYPE='datetime' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Subjects'
);
SET @redcms_public_mutation_subject_columns_sql = IF(
  @redcms_public_mutation_subject_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Subjects_Unexpected_Columns`'
);
PREPARE redcms_public_mutation_subject_columns_statement
  FROM @redcms_public_mutation_subject_columns_sql;
EXECUTE redcms_public_mutation_subject_columns_statement;
DEALLOCATE PREPARE redcms_public_mutation_subject_columns_statement;

SET @redcms_public_mutation_csrf_columns_valid = (
  SELECT COUNT(*) = 6
    AND SUM(COLUMN_NAME='RecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO'
      AND EXTRA='auto_increment') = 1
    AND SUM(COLUMN_NAME='SubjectRecordID'
      AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ScopeSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='TokenSHA256'
      AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='CreatedAt'
      AND DATA_TYPE='datetime' AND IS_NULLABLE='NO') = 1
    AND SUM(COLUMN_NAME='ExpiresAt'
      AND DATA_TYPE='datetime' AND IS_NULLABLE='NO') = 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'
);
SET @redcms_public_mutation_csrf_columns_sql = IF(
  @redcms_public_mutation_csrf_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_CSRF_Tokens_Unexpected_Columns`'
);
PREPARE redcms_public_mutation_csrf_columns_statement
  FROM @redcms_public_mutation_csrf_columns_sql;
EXECUTE redcms_public_mutation_csrf_columns_statement;
DEALLOCATE PREPARE redcms_public_mutation_csrf_columns_statement;

SET @redcms_public_mutation_subject_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Subjects'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_public_mutation_subject_token_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Subjects'
    AND INDEX_NAME='uq_red_addon_public_mutation_subject_token'
);
SET @redcms_public_mutation_subject_expiry_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Subjects'
    AND INDEX_NAME='idx_red_addon_public_mutation_subject_expiry'
);
SET @redcms_public_mutation_subject_indexes_sql = IF(
  @redcms_public_mutation_subject_primary = 'RecordID'
    AND @redcms_public_mutation_subject_token_index = 'SubjectTokenSHA256'
    AND @redcms_public_mutation_subject_expiry_index = 'ExpiresAt,RecordID',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Subjects_Unexpected_Indexes`'
);
PREPARE redcms_public_mutation_subject_indexes_statement
  FROM @redcms_public_mutation_subject_indexes_sql;
EXECUTE redcms_public_mutation_subject_indexes_statement;
DEALLOCATE PREPARE redcms_public_mutation_subject_indexes_statement;

SET @redcms_public_mutation_csrf_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_public_mutation_csrf_token_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'
    AND INDEX_NAME='uq_red_addon_public_mutation_csrf_token'
);
SET @redcms_public_mutation_csrf_expiry_index = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'
    AND INDEX_NAME='idx_red_addon_public_mutation_csrf_expiry'
);
SET @redcms_public_mutation_csrf_indexes_sql = IF(
  @redcms_public_mutation_csrf_primary = 'RecordID'
    AND @redcms_public_mutation_csrf_token_index
      = 'SubjectRecordID,ScopeSHA256,TokenSHA256'
    AND @redcms_public_mutation_csrf_expiry_index = 'ExpiresAt,RecordID',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_CSRF_Tokens_Unexpected_Indexes`'
);
PREPARE redcms_public_mutation_csrf_indexes_statement
  FROM @redcms_public_mutation_csrf_indexes_sql;
EXECUTE redcms_public_mutation_csrf_indexes_statement;
DEALLOCATE PREPARE redcms_public_mutation_csrf_indexes_statement;

SET @redcms_public_mutation_csrf_foreign_key_valid = (
  SELECT COUNT(*) = 1
    AND SUM(CONSTRAINT_NAME='fk_red_addon_public_mutation_csrf_subject'
      AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'
      AND REFERENCED_TABLE_NAME='RED_Addon_Public_Mutation_Subjects'
      AND DELETE_RULE='CASCADE'
      AND UPDATE_RULE='RESTRICT') = 1
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND CONSTRAINT_NAME='fk_red_addon_public_mutation_csrf_subject'
);
SET @redcms_public_mutation_csrf_foreign_key_sql = IF(
  @redcms_public_mutation_csrf_foreign_key_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_CSRF_Tokens_Unexpected_Foreign_Key`'
);
PREPARE redcms_public_mutation_csrf_foreign_key_statement
  FROM @redcms_public_mutation_csrf_foreign_key_sql;
EXECUTE redcms_public_mutation_csrf_foreign_key_statement;
DEALLOCATE PREPARE redcms_public_mutation_csrf_foreign_key_statement;
