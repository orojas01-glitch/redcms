-- Disposable-only benchmark for Phase 2 query/index validation.
-- Run only after database/fixtures/phase2-query-index-scale.sql has been loaded.
-- Every candidate is added, measured, and removed before the next candidate.

SELECT 'route_hierarchy_last.before' AS benchmark;
EXPLAIN SELECT RecordID FROM RED_Articles
WHERE Active='Y' AND Language='sp'
  AND Sections='scale-section-1'
  AND Categories='scale-category-1'
  AND SubCategories='scale-subcategory-1'
  AND Alias='scale-article-49001'
LIMIT 1;
EXPLAIN ANALYZE SELECT RecordID FROM RED_Articles
WHERE Active='Y' AND Language='sp'
  AND Sections='scale-section-1'
  AND Categories='scale-category-1'
  AND SubCategories='scale-subcategory-1'
  AND Alias='scale-article-49001'
LIMIT 1;
ALTER TABLE RED_Articles ADD INDEX idx_bench_route_hierarchy
  (Language, Active, Sections, Categories, SubCategories, Alias(191));
SELECT 'route_hierarchy_last.after' AS benchmark;
EXPLAIN SELECT RecordID FROM RED_Articles
WHERE Active='Y' AND Language='sp'
  AND Sections='scale-section-1'
  AND Categories='scale-category-1'
  AND SubCategories='scale-subcategory-1'
  AND Alias='scale-article-49001'
LIMIT 1;
EXPLAIN ANALYZE SELECT RecordID FROM RED_Articles
WHERE Active='Y' AND Language='sp'
  AND Sections='scale-section-1'
  AND Categories='scale-category-1'
  AND SubCategories='scale-subcategory-1'
  AND Alias='scale-article-49001'
LIMIT 1;
ALTER TABLE RED_Articles DROP INDEX idx_bench_route_hierarchy;

SELECT 'route_alias_first.before' AS benchmark;
EXPLAIN SELECT RecordID FROM RED_Articles
WHERE Active='Y' AND Language='sp'
  AND Sections='scale-section-1'
  AND Alias='scale-article-49001'
LIMIT 1;
EXPLAIN ANALYZE SELECT RecordID FROM RED_Articles
WHERE Active='Y' AND Language='sp'
  AND Sections='scale-section-1'
  AND Alias='scale-article-49001'
LIMIT 1;
ALTER TABLE RED_Articles ADD INDEX idx_bench_route_alias
  (Language, Active, Alias(191), Sections, Categories, SubCategories);
SELECT 'route_alias_first.after' AS benchmark;
EXPLAIN SELECT RecordID FROM RED_Articles
WHERE Active='Y' AND Language='sp'
  AND Sections='scale-section-1'
  AND Alias='scale-article-49001'
LIMIT 1;
EXPLAIN ANALYZE SELECT RecordID FROM RED_Articles
WHERE Active='Y' AND Language='sp'
  AND Sections='scale-section-1'
  AND Alias='scale-article-49001'
LIMIT 1;
ALTER TABLE RED_Articles DROP INDEX idx_bench_route_alias;

SELECT 'section_content.before' AS benchmark;
EXPLAIN SELECT RecordID, Alias, Component, ExpDate, SmallPict, SectionPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND SectionPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1'
ORDER BY SectionPositionOrder ASC, StartDate DESC LIMIT 100;
EXPLAIN ANALYZE SELECT RecordID, Alias, Component, ExpDate, SmallPict, SectionPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND SectionPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1'
ORDER BY SectionPositionOrder ASC, StartDate DESC LIMIT 100;
ALTER TABLE RED_Articles ADD INDEX idx_bench_section_content
  (Language, Active, Sections, SectionPosition, SectionPositionOrder, StartDate);
SELECT 'section_content.after' AS benchmark;
EXPLAIN SELECT RecordID, Alias, Component, ExpDate, SmallPict, SectionPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND SectionPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1'
ORDER BY SectionPositionOrder ASC, StartDate DESC LIMIT 100;
EXPLAIN ANALYZE SELECT RecordID, Alias, Component, ExpDate, SmallPict, SectionPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND SectionPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1'
ORDER BY SectionPositionOrder ASC, StartDate DESC LIMIT 100;
ALTER TABLE RED_Articles DROP INDEX idx_bench_section_content;

SELECT 'category_content.before' AS benchmark;
EXPLAIN SELECT RecordID, Alias, Component, ExpDate, SmallPict, CategoryPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND CategoryPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1' AND Categories='scale-category-1'
ORDER BY CategoryPositionOrder ASC, StartDate DESC LIMIT 100;
EXPLAIN ANALYZE SELECT RecordID, Alias, Component, ExpDate, SmallPict, CategoryPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND CategoryPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1' AND Categories='scale-category-1'
ORDER BY CategoryPositionOrder ASC, StartDate DESC LIMIT 100;
ALTER TABLE RED_Articles ADD INDEX idx_bench_category_content
  (Language, Active, Sections, Categories, CategoryPosition, CategoryPositionOrder, StartDate);
SELECT 'category_content.after' AS benchmark;
EXPLAIN SELECT RecordID, Alias, Component, ExpDate, SmallPict, CategoryPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND CategoryPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1' AND Categories='scale-category-1'
ORDER BY CategoryPositionOrder ASC, StartDate DESC LIMIT 100;
EXPLAIN ANALYZE SELECT RecordID, Alias, Component, ExpDate, SmallPict, CategoryPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND CategoryPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1' AND Categories='scale-category-1'
ORDER BY CategoryPositionOrder ASC, StartDate DESC LIMIT 100;
ALTER TABLE RED_Articles DROP INDEX idx_bench_category_content;

SELECT 'subcategory_content.before' AS benchmark;
EXPLAIN SELECT RecordID, Alias, Component, ExpDate, SmallPict, SubCategoryPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND SubCategoryPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1'
  AND Categories='scale-category-1' AND SubCategories='scale-subcategory-1'
ORDER BY SubCategoryPositionOrder ASC, StartDate DESC LIMIT 100;
EXPLAIN ANALYZE SELECT RecordID, Alias, Component, ExpDate, SmallPict, SubCategoryPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND SubCategoryPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1'
  AND Categories='scale-category-1' AND SubCategories='scale-subcategory-1'
ORDER BY SubCategoryPositionOrder ASC, StartDate DESC LIMIT 100;
ALTER TABLE RED_Articles ADD INDEX idx_bench_subcategory_content
  (Language, Active, Sections, Categories, SubCategories, SubCategoryPosition,
   SubCategoryPositionOrder, StartDate);
SELECT 'subcategory_content.after' AS benchmark;
EXPLAIN SELECT RecordID, Alias, Component, ExpDate, SmallPict, SubCategoryPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND SubCategoryPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1'
  AND Categories='scale-category-1' AND SubCategories='scale-subcategory-1'
ORDER BY SubCategoryPositionOrder ASC, StartDate DESC LIMIT 100;
EXPLAIN ANALYZE SELECT RecordID, Alias, Component, ExpDate, SmallPict, SubCategoryPositionOrder
FROM RED_Articles
WHERE Active='Y' AND Language='sp' AND SubCategoryPosition=1
  AND StartDate <= NOW() AND Sections='scale-section-1'
  AND Categories='scale-category-1' AND SubCategories='scale-subcategory-1'
ORDER BY SubCategoryPositionOrder ASC, StartDate DESC LIMIT 100;
ALTER TABLE RED_Articles DROP INDEX idx_bench_subcategory_content;

SELECT 'section_alias.before' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID FROM RED_Sections
WHERE Active='Y' AND Language='sp' AND Sections='scale-section-100' LIMIT 1;
ALTER TABLE RED_Sections ADD INDEX idx_bench_section_alias (Language, Active, Sections);
SELECT 'section_alias.after' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID FROM RED_Sections
WHERE Active='Y' AND Language='sp' AND Sections='scale-section-100' LIMIT 1;
ALTER TABLE RED_Sections DROP INDEX idx_bench_section_alias;

SELECT 'category_alias.before' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID FROM RED_Categories
WHERE Active='Y' AND Language='sp' AND Categories='scale-category-200' LIMIT 1;
ALTER TABLE RED_Categories ADD INDEX idx_bench_category_alias (Language, Active, Categories);
SELECT 'category_alias.after' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID FROM RED_Categories
WHERE Active='Y' AND Language='sp' AND Categories='scale-category-200' LIMIT 1;
ALTER TABLE RED_Categories DROP INDEX idx_bench_category_alias;

SELECT 'subcategory_alias.before' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID FROM RED_SubCategories
WHERE Active='Y' AND Language='sp' AND SubCategories='scale-subcategory-500' LIMIT 1;
ALTER TABLE RED_SubCategories ADD INDEX idx_bench_subcategory_alias (Language, Active, SubCategories);
SELECT 'subcategory_alias.after' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID FROM RED_SubCategories
WHERE Active='Y' AND Language='sp' AND SubCategories='scale-subcategory-500' LIMIT 1;
ALTER TABLE RED_SubCategories DROP INDEX idx_bench_subcategory_alias;

SELECT 'menu.before' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID, Parent, RootOrder, Title, Label, Link, NewWindow, MenuOrder
FROM RED_Menu WHERE Language='sp' AND Active='Y' ORDER BY MenuOrder ASC;
ALTER TABLE RED_Menu ADD INDEX idx_bench_menu_public (Language, Active, MenuOrder);
SELECT 'menu.after' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID, Parent, RootOrder, Title, Label, Link, NewWindow, MenuOrder
FROM RED_Menu WHERE Language='sp' AND Active='Y' ORDER BY MenuOrder ASC;
ALTER TABLE RED_Menu DROP INDEX idx_bench_menu_public;

SELECT 'form_ref.before' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID, RefID, Alias, Title, FormType, LongDesc
FROM RED_C_Form WHERE RefID='3100049000';
ALTER TABLE RED_C_Form ADD INDEX idx_bench_form_ref (RefID);
SELECT 'form_ref.after' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID, RefID, Alias, Title, FormType, LongDesc
FROM RED_C_Form WHERE RefID='3100049000';
ALTER TABLE RED_C_Form DROP INDEX idx_bench_form_ref;

SELECT 'gallery_ref.before' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID, RefID, Alias, Title, GalleryType, ShortDesc, LongDesc, Link, NewWindow
FROM RED_C_Gallery WHERE RefID='3100049001';
ALTER TABLE RED_C_Gallery ADD INDEX idx_bench_gallery_ref (RefID);
SELECT 'gallery_ref.after' AS benchmark;
EXPLAIN ANALYZE SELECT RecordID, RefID, Alias, Title, GalleryType, ShortDesc, LongDesc, Link, NewWindow
FROM RED_C_Gallery WHERE RefID='3100049001';
ALTER TABLE RED_C_Gallery DROP INDEX idx_bench_gallery_ref;

SELECT 'advanced.before' AS benchmark;
EXPLAIN ANALYZE SELECT Content FROM RED_Advanced
WHERE Language='sp' AND Item='Website_Header' LIMIT 1;
ALTER TABLE RED_Advanced ADD INDEX idx_bench_advanced_item (Language, Item);
SELECT 'advanced.after' AS benchmark;
EXPLAIN ANALYZE SELECT Content FROM RED_Advanced
WHERE Language='sp' AND Item='Website_Header' LIMIT 1;
ALTER TABLE RED_Advanced DROP INDEX idx_bench_advanced_item;

SELECT 'components.before' AS benchmark;
EXPLAIN ANALYZE SELECT Template, ResponseTemplate FROM RED_Components
WHERE UniqueName='Form' AND Layout='Contact' LIMIT 1;
ALTER TABLE RED_Components ADD INDEX idx_bench_components_lookup (UniqueName, Layout);
SELECT 'components.after' AS benchmark;
EXPLAIN ANALYZE SELECT Template, ResponseTemplate FROM RED_Components
WHERE UniqueName='Form' AND Layout='Contact' LIMIT 1;
ALTER TABLE RED_Components DROP INDEX idx_bench_components_lookup;
