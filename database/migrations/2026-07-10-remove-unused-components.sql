-- Remove unused/non-working RED-CMS component choices from existing installs.
-- Back up RED_Components, matching RED_Articles/RED_C_Menu rows, and
-- RED_Admin.AdminComponents before running this migration on another database.

START TRANSACTION;

-- SubMenu has a paired RED_C_Menu row; remove it before its RED_Articles shell.
DELETE menu
FROM RED_C_Menu AS menu
INNER JOIN RED_Articles AS article ON article.RecordID = menu.RefID
WHERE article.Component = 'SubMenu';

-- These component types have no separate component table beyond RED_Articles.
DELETE FROM RED_Articles
WHERE Component IN ('News', 'Event', 'Testimonial', 'ContentBox', 'SubMenu', 'ShortArticle');

-- Keep the seeded Instructions article aligned with the available component set.
UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, '&nbsp;or&nbsp;<strong>Submenu</strong>', '')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, 'Top Navigation or Submenu.', 'Top Navigation.')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, 'How to Edit Top Navigation or Submenu(s)', 'How to Edit Top Navigation')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(
  LongDesc,
  '&nbsp;&nbsp;<strong>Submenu</strong>&nbsp;is present only in selected pages.&nbsp; Follow the instructions for both:',
  '&nbsp; Follow these instructions:'
)
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(
  LongDesc,
  '&nbsp;<br />or Locate the&nbsp;<strong>Submenu &gt; Edit</strong><br />',
  '<br />'
)
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(
  LongDesc,
  '&nbsp;&nbsp;<strong>Submenus</strong>&nbsp;include only 1 (one) level. (image 18)',
  ''
)
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(
  LongDesc,
  '<p id="instructions-img"><img src="../admin/images/red-cms-instructions-manual_files/image040.png" alt="" width="999" height="748" border="0" /></p>',
  ''
)
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, '<p id="instructions-ref">image 18</p>', '')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, ', Sub-Menu(s)', '')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, 'SubMenus, ', '')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

-- Remove the retired component IDs from every administrator permission list.
UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', REPLACE(AdminComponents, ' ', ''), ','), ',101,', ','))
WHERE FIND_IN_SET('101', REPLACE(AdminComponents, ' ', '')) > 0;

UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', REPLACE(AdminComponents, ' ', ''), ','), ',110,', ','))
WHERE FIND_IN_SET('110', REPLACE(AdminComponents, ' ', '')) > 0;

UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', REPLACE(AdminComponents, ' ', ''), ','), ',112,', ','))
WHERE FIND_IN_SET('112', REPLACE(AdminComponents, ' ', '')) > 0;

UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', REPLACE(AdminComponents, ' ', ''), ','), ',113,', ','))
WHERE FIND_IN_SET('113', REPLACE(AdminComponents, ' ', '')) > 0;

UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', REPLACE(AdminComponents, ' ', ''), ','), ',114,', ','))
WHERE FIND_IN_SET('114', REPLACE(AdminComponents, ' ', '')) > 0;

UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', REPLACE(AdminComponents, ' ', ''), ','), ',115,', ','))
WHERE FIND_IN_SET('115', REPLACE(AdminComponents, ' ', '')) > 0;

UPDATE RED_Admin
SET AdminComponents = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', REPLACE(AdminComponents, ' ', ''), ','), ',118,', ','))
WHERE FIND_IN_SET('118', REPLACE(AdminComponents, ' ', '')) > 0;

DELETE FROM RED_Components
WHERE (RecordID = 101 AND UniqueName = 'Event')
   OR (RecordID = 110 AND UniqueName = 'News')
   OR (RecordID = 112 AND UniqueName = 'ShortArticle')
   OR (RecordID = 113 AND UniqueName = 'SubMenu' AND Layout = 'Vertical')
   OR (RecordID = 114 AND UniqueName = 'SubMenu' AND Layout = 'Horizontal')
   OR (RecordID = 115 AND UniqueName = 'Testimonial')
   OR (RecordID = 118 AND UniqueName = 'ContentBox');

COMMIT;
