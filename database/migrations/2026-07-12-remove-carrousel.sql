-- Remove the retired Carrousel content option and its administrator permission.
-- Existing content rows are intentionally preserved for backup/recovery purposes.

START TRANSACTION;

UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', AdminComponents, ','), ',109,', ','))
WHERE FIND_IN_SET('109', AdminComponents) > 0;

DELETE FROM RED_Components
WHERE RecordID = 109
  AND UniqueName = 'Gallery'
  AND Layout = 'Carrousel';

COMMIT;
