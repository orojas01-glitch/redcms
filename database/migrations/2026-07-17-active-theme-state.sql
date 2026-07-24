-- Persistent singleton state for atomic file-based theme activation and rollback.
-- Empty Language marks these two rows as global system settings, outside the
-- language-specific Advanced editor inventory.

START TRANSACTION;

INSERT INTO `RED_Advanced` (`Item`, `Content`, `Language`)
SELECT 'System_Active_Theme', 'legacy-bootstrap', ''
WHERE NOT EXISTS (
  SELECT 1 FROM `RED_Advanced`
  WHERE `Item`='System_Active_Theme' AND `Language`=''
);

INSERT INTO `RED_Advanced` (`Item`, `Content`, `Language`)
SELECT 'System_Previous_Theme', 'legacy-bootstrap', ''
WHERE NOT EXISTS (
  SELECT 1 FROM `RED_Advanced`
  WHERE `Item`='System_Previous_Theme' AND `Language`=''
);

SET @redcms_theme_state_valid = (
  SELECT COUNT(*) = 2
    AND SUM(`Item`='System_Active_Theme') = 1
    AND SUM(`Item`='System_Previous_Theme') = 1
    AND SUM(`Content` REGEXP '^[a-z0-9]([a-z0-9-]{0,62}[a-z0-9])?$') = 2
  FROM `RED_Advanced`
  WHERE `Language`=''
    AND `Item` IN ('System_Active_Theme', 'System_Previous_Theme')
);
SET @redcms_theme_state_sql = IF(
  @redcms_theme_state_valid = 1,
  'SELECT 1',
  'SELECT * FROM `RED_Advanced_Invalid_Theme_State`'
);
PREPARE redcms_theme_state_statement FROM @redcms_theme_state_sql;
EXECUTE redcms_theme_state_statement;
DEALLOCATE PREPARE redcms_theme_state_statement;

COMMIT;

