-- Remove the retired Audio Store component, forms, and data from existing installs.

START TRANSACTION;

UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', AdminComponents, ','), ',122,', ','))
WHERE FIND_IN_SET('122', REPLACE(AdminComponents, ' ', '')) > 0;

DELETE FROM RED_C_Form
WHERE FormType IN ('Register_StoreLogin', 'StoreLogin');

DELETE FROM RED_Components
WHERE RecordID IN (119, 120, 122)
  AND (
    UniqueName = 'AudioStore'
    OR (UniqueName = 'Form' AND Layout IN ('Register_StoreLogin', 'StoreLogin'))
  );

DROP TABLE IF EXISTS RED_C_AudioStore_Purchases;
DROP TABLE IF EXISTS RED_C_AudioStore_Users;
DROP TABLE IF EXISTS RED_C_AudioStore;

COMMIT;
