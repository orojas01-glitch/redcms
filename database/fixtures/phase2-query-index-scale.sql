-- Disposable-only scale fixture for Phase 2 query/index validation.
-- Never run this against the configured primary RED-CMS database.

SET SESSION cte_max_recursion_depth = 60000;

CREATE TEMPORARY TABLE red_scale_seq (
    n int unsigned NOT NULL,
    PRIMARY KEY (n)
) ENGINE=InnoDB;

INSERT INTO red_scale_seq
WITH RECURSIVE seq AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM seq WHERE n < 50000
)
SELECT n FROM seq;

INSERT INTO RED_Sections (
    RecordID, Sections, Title, Layout, QueryLimit, AccessLevel,
    Features, Active, Description, Tags, Language
)
SELECT
    3000000000 + n,
    CONCAT('scale-section-', n),
    CONCAT('Scale Section ', n),
    'index-1',
    '100',
    'Public',
    '',
    'Y',
    '',
    'scale',
    'sp'
FROM red_scale_seq
WHERE n <= 100;

INSERT INTO RED_Categories (
    RecordID, Categories, Title, Layout, QueryLimit, AccessLevel,
    Features, Active, Description, Tags, Language
)
SELECT
    3000000000 + n,
    CONCAT('scale-category-', n),
    CONCAT('Scale Category ', n),
    'index-1',
    '100',
    'Public',
    '',
    'Y',
    '',
    'scale',
    'sp'
FROM red_scale_seq
WHERE n <= 200;

INSERT INTO RED_SubCategories (
    RecordID, SubCategories, Title, Layout, QueryLimit, AccessLevel,
    Features, Active, Description, Tags, Language
)
SELECT
    3000000000 + n,
    CONCAT('scale-subcategory-', n),
    CONCAT('Scale SubCategory ', n),
    'index-1',
    '100',
    'Public',
    '',
    'Y',
    '',
    'scale',
    'sp'
FROM red_scale_seq
WHERE n <= 500;

INSERT INTO RED_Articles (
    RecordID, Title, Component, Alias, Sections,
    HomePosition, HomePositionOrder,
    SectionPosition, SectionPositionOrder,
    Categories, CategoryPosition, CategoryPositionOrder,
    SubCategories, SubCategoryPosition, SubCategoryPositionOrder,
    Layout, Article, PagePosition, PagePositionOrder, Tags,
    Active, HomeFeature,
    HomeFeatures, HomeFeatures_Order,
    SectionFeatures, SectionFeatures_Order,
    CategoryFeatures, CategoryFeatures_Order,
    SubCategoryFeatures, SubCategoryFeatures_Order,
    ArticleFeatures,
    StartDate, EventDate, ExpDate,
    ShortDesc, LongDesc, SliderDesc, Link, NewWindow,
    VideoSrc, AlbumSrc, BigPict, SmallPict, SmallPictAlign,
    SmallPict2, SmallPictAlign2, EditedBy, Language
)
SELECT
    3100000000 + n,
    CONCAT('Scale Article ', n),
    CASE
        WHEN MOD(n, 10) = 0 THEN 'Form'
        WHEN MOD(n, 10) = 1 THEN 'Gallery'
        ELSE 'Article'
    END,
    CONCAT('scale-article-', n),
    CONCAT('scale-section-', 1 + MOD(n - 1, 100)),
    1 + MOD(n - 1, 4),
    MOD(n - 1, 1000),
    1 + MOD(n - 1, 4),
    MOD(n - 1, 1000),
    CONCAT('scale-category-', 1 + MOD(n - 1, 200)),
    1 + MOD(n - 1, 4),
    MOD(n - 1, 1000),
    CONCAT('scale-subcategory-', 1 + MOD(n - 1, 500)),
    1 + MOD(n - 1, 4),
    MOD(n - 1, 1000),
    'index-1',
    '',
    1,
    MOD(n - 1, 1000),
    'scale',
    'Y',
    'N',
    CASE WHEN MOD(n, 50) = 0 THEN 'slider' ELSE '' END,
    MOD(n - 1, 1000),
    CASE WHEN MOD(n, 50) = 0 THEN 'slider' ELSE '' END,
    MOD(n - 1, 1000),
    CASE WHEN MOD(n, 50) = 0 THEN 'slider' ELSE '' END,
    MOD(n - 1, 1000),
    CASE WHEN MOD(n, 50) = 0 THEN 'slider' ELSE '' END,
    MOD(n - 1, 1000),
    '',
    '2000-01-01 00:00:00',
    '2000-01-01 00:00:00',
    '2099-12-31 23:59:59',
    'Scale fixture',
    'Scale fixture body',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    'scale',
    'sp'
FROM red_scale_seq;

INSERT INTO RED_C_Form (
    RecordID, RefID, Title, Alias, FormType, ShortDesc, LongDesc,
    Subject, Submitter, Destinatary, CC, BCC, Response, TableName
)
SELECT
    3200000000 + n,
    CAST(3100000000 + n AS CHAR),
    CONCAT('Scale Form ', n),
    CONCAT('scale-form-', n),
    'Contact',
    '',
    'scale form definition',
    'Scale subject',
    '',
    '',
    '',
    '',
    '',
    ''
FROM red_scale_seq
WHERE MOD(n, 10) = 0;

INSERT INTO RED_C_Gallery (
    RecordID, RefID, Title, Alias, GalleryType,
    ShortDesc, Link, LongDesc, NewWindow
)
SELECT
    3300000000 + n,
    CAST(3100000000 + n AS CHAR),
    CONCAT('Scale Gallery ', n),
    CONCAT('scale-gallery-', n),
    'Gallery',
    '',
    '',
    'scale-gallery.jpg',
    ''
FROM red_scale_seq
WHERE MOD(n, 10) = 1;

INSERT INTO RED_Menu (
    RecordID, RootOrder, Title, Label, Parent,
    Link, NewWindow, MenuOrder, Active, Language
)
SELECT
    1000000 + n,
    CASE
        WHEN MOD(n, 10) = 0 THEN '1'
        WHEN MOD(n, 5) = 0 THEN '3'
        ELSE '2'
    END,
    CONCAT('Scale Menu ', n),
    CONCAT('Scale ', n),
    CASE WHEN MOD(n, 10) = 0 THEN 0 ELSE 64 END,
    CONCAT('/scale-section-', 1 + MOD(n - 1, 100), '/'),
    '',
    n,
    CASE WHEN MOD(n, 10) = 9 THEN 'N' ELSE 'Y' END,
    CASE WHEN MOD(n, 5) = 4 THEN 'en' ELSE 'sp' END
FROM red_scale_seq
WHERE n <= 2000;

DROP TEMPORARY TABLE red_scale_seq;

SELECT 'RED_Sections', COUNT(*) FROM RED_Sections
UNION ALL SELECT 'RED_Categories', COUNT(*) FROM RED_Categories
UNION ALL SELECT 'RED_SubCategories', COUNT(*) FROM RED_SubCategories
UNION ALL SELECT 'RED_Articles', COUNT(*) FROM RED_Articles
UNION ALL SELECT 'RED_C_Form', COUNT(*) FROM RED_C_Form
UNION ALL SELECT 'RED_C_Gallery', COUNT(*) FROM RED_C_Gallery
UNION ALL SELECT 'RED_Menu', COUNT(*) FROM RED_Menu;
