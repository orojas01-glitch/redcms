-- Core-owned short-lived public-mutation idempotency-key evidence.
-- Stores no raw subject token, CSRF value, idempotency key, package id, route,
-- request body, secret, cart, order, or other business data. Key consumption
-- and replay recording remain the later transaction-runner responsibility.

CREATE TABLE IF NOT EXISTS `RED_Addon_Public_Mutation_Idempotency_Keys` (
  `RecordID` int unsigned NOT NULL AUTO_INCREMENT,
  `SubjectRecordID` int unsigned NOT NULL,
  `ScopeSHA256` char(64) NOT NULL,
  `KeySHA256` char(64) NOT NULL,
  `CreatedAt` datetime NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_red_addon_public_mutation_idempotency_key`
    (`SubjectRecordID`,`ScopeSHA256`,`KeySHA256`),
  KEY `idx_red_addon_public_mutation_idempotency_expiry`
    (`ExpiresAt`,`RecordID`),
  CONSTRAINT `fk_red_addon_public_mutation_idempotency_subject`
    FOREIGN KEY (`SubjectRecordID`)
    REFERENCES `RED_Addon_Public_Mutation_Subjects` (`RecordID`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_public_mutation_idempotency_engine_valid = (
  SELECT COUNT(*)=1 AND MAX(ENGINE)='InnoDB'
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Idempotency_Keys'
);
SET @redcms_public_mutation_idempotency_engine_sql = IF(
  @redcms_public_mutation_idempotency_engine_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Idempotency_Keys_Unexpected_Engine`'
);
PREPARE redcms_public_mutation_idempotency_engine_statement
  FROM @redcms_public_mutation_idempotency_engine_sql;
EXECUTE redcms_public_mutation_idempotency_engine_statement;
DEALLOCATE PREPARE redcms_public_mutation_idempotency_engine_statement;

SET @redcms_public_mutation_idempotency_columns_valid = (
  SELECT COUNT(*)=6
    AND SUM(COLUMN_NAME='RecordID'
      AND COLUMN_TYPE='int unsigned'
      AND IS_NULLABLE='NO'
      AND EXTRA='auto_increment')=1
    AND SUM(COLUMN_NAME='SubjectRecordID'
      AND COLUMN_TYPE='int unsigned'
      AND IS_NULLABLE='NO')=1
    AND SUM(COLUMN_NAME='ScopeSHA256'
      AND COLUMN_TYPE='char(64)'
      AND IS_NULLABLE='NO')=1
    AND SUM(COLUMN_NAME='KeySHA256'
      AND COLUMN_TYPE='char(64)'
      AND IS_NULLABLE='NO')=1
    AND SUM(COLUMN_NAME='CreatedAt'
      AND DATA_TYPE='datetime'
      AND IS_NULLABLE='NO')=1
    AND SUM(COLUMN_NAME='ExpiresAt'
      AND DATA_TYPE='datetime'
      AND IS_NULLABLE='NO')=1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Idempotency_Keys'
);
SET @redcms_public_mutation_idempotency_columns_sql = IF(
  @redcms_public_mutation_idempotency_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Idempotency_Keys_Unexpected_Columns`'
);
PREPARE redcms_public_mutation_idempotency_columns_statement
  FROM @redcms_public_mutation_idempotency_columns_sql;
EXECUTE redcms_public_mutation_idempotency_columns_statement;
DEALLOCATE PREPARE redcms_public_mutation_idempotency_columns_statement;

SET @redcms_public_mutation_idempotency_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Idempotency_Keys'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_public_mutation_idempotency_unique = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Idempotency_Keys'
    AND INDEX_NAME='uq_red_addon_public_mutation_idempotency_key'
);
SET @redcms_public_mutation_idempotency_expiry = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Idempotency_Keys'
    AND INDEX_NAME='idx_red_addon_public_mutation_idempotency_expiry'
);
SET @redcms_public_mutation_idempotency_indexes_sql = IF(
  @redcms_public_mutation_idempotency_primary = 'RecordID'
    AND @redcms_public_mutation_idempotency_unique =
      'SubjectRecordID,ScopeSHA256,KeySHA256'
    AND @redcms_public_mutation_idempotency_expiry = 'ExpiresAt,RecordID',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Idempotency_Keys_Unexpected_Indexes`'
);
PREPARE redcms_public_mutation_idempotency_indexes_statement
  FROM @redcms_public_mutation_idempotency_indexes_sql;
EXECUTE redcms_public_mutation_idempotency_indexes_statement;
DEALLOCATE PREPARE redcms_public_mutation_idempotency_indexes_statement;

SET @redcms_public_mutation_idempotency_foreign_key_valid = (
  SELECT COUNT(*)=1
    AND SUM(CONSTRAINT_NAME=
          'fk_red_addon_public_mutation_idempotency_subject'
      AND TABLE_NAME='RED_Addon_Public_Mutation_Idempotency_Keys'
      AND REFERENCED_TABLE_NAME='RED_Addon_Public_Mutation_Subjects'
      AND DELETE_RULE='CASCADE'
      AND UPDATE_RULE='RESTRICT')=1
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND CONSTRAINT_NAME=
      'fk_red_addon_public_mutation_idempotency_subject'
);
SET @redcms_public_mutation_idempotency_foreign_key_sql = IF(
  @redcms_public_mutation_idempotency_foreign_key_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Idempotency_Keys_Unexpected_Foreign_Key`'
);
PREPARE redcms_public_mutation_idempotency_foreign_key_statement
  FROM @redcms_public_mutation_idempotency_foreign_key_sql;
EXECUTE redcms_public_mutation_idempotency_foreign_key_statement;
DEALLOCATE PREPARE redcms_public_mutation_idempotency_foreign_key_statement;
