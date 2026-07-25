-- Align the recovered Contact form shell with the registered contacto section layout.
-- Preserve the RED_Articles / RED_C_Form parent-child pair.

START TRANSACTION;

UPDATE RED_Articles AS article
INNER JOIN RED_C_Form AS form
  ON form.RefID = CAST(article.RecordID AS CHAR)
  AND form.RecordID = 93039112
INNER JOIN RED_Layouts AS layout_registry
  ON layout_registry.UniqueName = 'index-1'
SET article.Layout = 'index-1'
WHERE article.RecordID = 459269660
  AND article.Component = 'Form'
  AND article.Alias = 'contact'
  AND article.Sections = 'contacto'
  AND article.Layout = 'Two-Columns';

COMMIT;
