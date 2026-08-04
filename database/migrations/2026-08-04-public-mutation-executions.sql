-- Core-owned completed public-mutation replay evidence.
-- Stores only a one-time idempotency-key relation, keyed SHA-256 command and
-- state evidence, a bounded outcome, and completion time. It stores no raw
-- subject, CSRF value, idempotency key, package id, route, request body,
-- secret, cart, order, or other business data.

CREATE TABLE IF NOT EXISTS `RED_Addon_Public_Mutation_Executions` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `IdempotencyRecordID` int unsigned NOT NULL,
  `CommandSHA256` char(64) NOT NULL,
  `Outcome` varchar(16) NOT NULL,
  `PreviousStateSHA256` char(64) NOT NULL,
  `StateSHA256` char(64) NOT NULL,
  `CompletedAt` datetime NOT NULL,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_red_addon_public_mutation_execution_idempotency`
    (`IdempotencyRecordID`),
  KEY `idx_red_addon_public_mutation_execution_completed`
    (`CompletedAt`,`RecordID`),
  CONSTRAINT `fk_red_addon_public_mutation_execution_idempotency`
    FOREIGN KEY (`IdempotencyRecordID`)
    REFERENCES `RED_Addon_Public_Mutation_Idempotency_Keys` (`RecordID`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @redcms_public_mutation_execution_engine_valid = (
  SELECT COUNT(*)=1 AND MAX(ENGINE)='InnoDB'
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'
);
SET @redcms_public_mutation_execution_engine_sql = IF(
  @redcms_public_mutation_execution_engine_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Executions_Unexpected_Engine`'
);
PREPARE redcms_public_mutation_execution_engine_statement
  FROM @redcms_public_mutation_execution_engine_sql;
EXECUTE redcms_public_mutation_execution_engine_statement;
DEALLOCATE PREPARE redcms_public_mutation_execution_engine_statement;

SET @redcms_public_mutation_execution_columns_valid = (
  SELECT COUNT(*)=7
    AND SUM(COLUMN_NAME='RecordID'
      AND COLUMN_TYPE='bigint unsigned'
      AND IS_NULLABLE='NO'
      AND EXTRA='auto_increment')=1
    AND SUM(COLUMN_NAME='IdempotencyRecordID'
      AND COLUMN_TYPE='int unsigned'
      AND IS_NULLABLE='NO')=1
    AND SUM(COLUMN_NAME='CommandSHA256'
      AND COLUMN_TYPE='char(64)'
      AND IS_NULLABLE='NO')=1
    AND SUM(COLUMN_NAME='Outcome'
      AND COLUMN_TYPE='varchar(16)'
      AND IS_NULLABLE='NO')=1
    AND SUM(COLUMN_NAME='PreviousStateSHA256'
      AND COLUMN_TYPE='char(64)'
      AND IS_NULLABLE='NO')=1
    AND SUM(COLUMN_NAME='StateSHA256'
      AND COLUMN_TYPE='char(64)'
      AND IS_NULLABLE='NO')=1
    AND SUM(COLUMN_NAME='CompletedAt'
      AND DATA_TYPE='datetime'
      AND IS_NULLABLE='NO')=1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'
);
SET @redcms_public_mutation_execution_columns_sql = IF(
  @redcms_public_mutation_execution_columns_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Executions_Unexpected_Columns`'
);
PREPARE redcms_public_mutation_execution_columns_statement
  FROM @redcms_public_mutation_execution_columns_sql;
EXECUTE redcms_public_mutation_execution_columns_statement;
DEALLOCATE PREPARE redcms_public_mutation_execution_columns_statement;

SET @redcms_public_mutation_execution_primary = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'
    AND INDEX_NAME='PRIMARY'
);
SET @redcms_public_mutation_execution_unique = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'
    AND INDEX_NAME='uq_red_addon_public_mutation_execution_idempotency'
);
SET @redcms_public_mutation_execution_completed = (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'
    AND INDEX_NAME='idx_red_addon_public_mutation_execution_completed'
);
SET @redcms_public_mutation_execution_indexes_sql = IF(
  @redcms_public_mutation_execution_primary = 'RecordID'
    AND @redcms_public_mutation_execution_unique = 'IdempotencyRecordID'
    AND @redcms_public_mutation_execution_completed = 'CompletedAt,RecordID',
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Executions_Unexpected_Indexes`'
);
PREPARE redcms_public_mutation_execution_indexes_statement
  FROM @redcms_public_mutation_execution_indexes_sql;
EXECUTE redcms_public_mutation_execution_indexes_statement;
DEALLOCATE PREPARE redcms_public_mutation_execution_indexes_statement;

SET @redcms_public_mutation_execution_foreign_key_valid = (
  SELECT COUNT(*)=1
    AND SUM(CONSTRAINT_NAME=
          'fk_red_addon_public_mutation_execution_idempotency'
      AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'
      AND REFERENCED_TABLE_NAME='RED_Addon_Public_Mutation_Idempotency_Keys'
      AND DELETE_RULE='CASCADE'
      AND UPDATE_RULE='RESTRICT')=1
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND CONSTRAINT_NAME=
      'fk_red_addon_public_mutation_execution_idempotency'
);
SET @redcms_public_mutation_execution_foreign_key_sql = IF(
  @redcms_public_mutation_execution_foreign_key_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Addon_Public_Mutation_Executions_Unexpected_Foreign_Key`'
);
PREPARE redcms_public_mutation_execution_foreign_key_statement
  FROM @redcms_public_mutation_execution_foreign_key_sql;
EXECUTE redcms_public_mutation_execution_foreign_key_statement;
DEALLOCATE PREPARE redcms_public_mutation_execution_foreign_key_statement;
