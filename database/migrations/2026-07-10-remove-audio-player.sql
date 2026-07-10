-- Remove the abandoned Audio Player component from existing RED-CMS installs.
-- Audio Store is removed separately by 2026-07-10-remove-audio-store.sql.

START TRANSACTION;

UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', AdminComponents, ','), ',121,', ','))
WHERE FIND_IN_SET('121', REPLACE(AdminComponents, ' ', '')) > 0;

DELETE FROM RED_Components
WHERE RecordID = 121
  AND UniqueName = 'AudioPlayer';

COMMIT;
