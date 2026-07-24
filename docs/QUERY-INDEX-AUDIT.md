# RED-CMS Query And Index Audit

Date: 2026-07-12

## Scope

The original audit was read-only: it inventoried active public and administrator query shapes, recorded the pre-migration table/index state, measured representative plans and real public-route table reads, and identified candidates for disposable-first validation. The follow-up validation batch is now complete and its final migration outcome is recorded below. No PHP query, public URL, table name, column, or row data changed.

## Pre-Migration Database Scale

All 15 local tables are InnoDB. Application row counts are currently small:

| Table | Exact rows | Current indexes |
| --- | ---: | --- |
| `RED_Admin` | 2 | primary `RecordID`; unique `Username` |
| `RED_Advanced` | 6 | primary `RecordID` |
| `RED_Articles` | 4 | primary `RecordID` |
| `RED_C_Form` | 2 | primary `RecordID` |
| `RED_C_Gallery` | 1 | primary `RecordID` |
| `RED_C_Menu` | 0 | primary `RecordID` |
| `RED_Categories` | 0 | primary `RecordID` |
| `RED_Components` | 12 | primary `RecordID` |
| `RED_Features` | 2 | primary `RecordID` |
| `RED_Layouts` | 4 | primary `UniqueName` |
| `RED_Menu` | 2 | primary `RecordID` |
| `RED_Sections` | 3 | primary `RecordID` |
| `RED_SubCategories` | 0 | primary `RecordID` |
| `RED_Tools` | 2 | primary `RecordID` |

`RED_Schema_Migrations` is the fifteenth table and is not an application-query index target.

## Active Query-Shape Inventory

Counting table references in active prepared/read query definitions found the largest concentrations in:

| Table | Active code references |
| --- | ---: |
| `RED_Articles` | 21 |
| `RED_Menu` | 9 |
| `RED_Admin` | 9 |
| `RED_Components` | 7 |
| `RED_Advanced` | 7 |
| `RED_C_Gallery` | 6 |
| `RED_C_Form` | 5 |
| `RED_Layouts` | 4 |

The most important shapes are:

- Public area resolution by `Active`, `Language`, and the section/category/subcategory alias.
- Public article route resolution by `Active`, `Language`, hierarchy columns, and `Alias`.
- Public content lists by route hierarchy, a dynamic position column, its matching order column, and `StartDate`.
- Public menu reads by `Language`, `Active`, `RootOrder`/`Parent`, and `MenuOrder`.
- Advanced settings reads by `Language` and `Item` on every public page.
- Form and Gallery child resolution by `RefID`.
- Administrator article lists by hierarchy, component/active/language state, and `Updated` or a position column.
- Component authorization/template resolution by `UniqueName` and sometimes `Layout`.
- Administrator user uniqueness checks by `Username` or `Email`.

## Real Public-Route Reads

Performance Schema `COUNT_FETCH` deltas were captured around one request to each canonical route. These are rows fetched inside MySQL, not returned HTML rows.

| Route | HTTP result | Table rows fetched |
| --- | --- | --- |
| `/` | `200`, 6,414 bytes | Advanced 15; Articles 20; Menu 4; Sections 5 |
| `/contacto/` | `200`, 10,132 bytes | Advanced 15; Articles 17; Form 2; Menu 4; Sections 13 |
| `/administracion/` | `200`, 10,442 bytes | Advanced 15; Articles 12; Form 2; Gallery 1; Layouts 2; Menu 4; Sections 19 |
| `/administracion/instructions` | `200`, 26,977 bytes | Advanced 15; Articles 22; Layouts 1; Menu 4; Sections 4 |
| `/administracion/test-vimeo` | `200`, 6,073 bytes | Advanced 15; Articles 9; Menu 4 |

Across the five requests, the principal totals were Advanced 75, Articles 80, Sections 41, and Menu 20. No database writes occurred.

## Baseline Plans And Timings

Traditional `EXPLAIN` showed `type=ALL`, no possible secondary key, for public area, article route, content-list, feature-list, menu, advanced-item, child-component, administrator email, hierarchy-list, and related-count queries. Content/menu/hierarchy lists also reported `Using filesort`.

`EXPLAIN ANALYZE` on the current 0–12-row tables measured approximately `0.003` to `0.092` ms inside the iterator plans. The slowest representative shape was the section content list at about `0.092` ms; it scanned all four Articles rows and sorted two matches. These values confirm there is no present latency incident, but they are too small to prove how the same plans behave with hundreds or thousands of content rows.

Administrator measurements used the exact prepared-query shapes directly. A synthetic authenticated session was intentionally not used; normal login credentials were not available to this read-only audit.

## Candidates For Disposable-Scale Validation

These are candidates to test, not approved migration contents.

1. `RED_Articles` route hierarchy:

   `Language, Active, Sections, Categories, SubCategories, Alias(prefix)`

   Articles is the most frequently referenced and fetched table. `Alias` is currently `text` even though the observed maximum is 12 characters, so an index requires an explicit safe prefix or a separately approved type normalization. Validation also compared hierarchy-first and alias-first column order without changing public URL behavior.

2. `RED_Articles` hierarchy content listings:

   - Section: `Language, Active, Sections, SectionPosition, SectionPositionOrder, StartDate`
   - Category: `Language, Active, Sections, Categories, CategoryPosition, CategoryPositionOrder, StartDate`
   - SubCategory: `Language, Active, Sections, Categories, SubCategories, SubCategoryPosition, SubCategoryPositionOrder, StartDate`

   The Section shape is exercised today and currently scans/filesorts. Category and SubCategory are required architectural growth paths even though their current tables contain no rows: the route builder maps deeper folders onto `CategoryPosition`/`CategoryFeatures` and `SubCategoryPosition`/`SubCategoryFeatures`. Test all three shapes independently so the final migration supports the intended three-level hierarchy without automatically cloning indexes for unrelated Home and Page paths.

3. `RED_Advanced` item lookup:

   `Language, Item`

   All five public routes repeatedly scan the six-row settings registry. The table is small, but the equality lookup is stable and high-frequency.

4. Public hierarchy alias lookups:

   - `RED_Sections (Language, Active, Sections)`
   - `RED_Categories (Language, Active, Categories)`
   - `RED_SubCategories (Language, Active, SubCategories)`

   Sections produced 4–19 fetched rows per canonical request from a three-row table. Categories and SubCategories are not non-candidates: they are currently unmeasured but required future hierarchy paths. The disposable scale fixture must populate and test both tables before deciding the final index set.

5. `RED_Menu` public ordered list:

   `Language, Active, MenuOrder`

   Each canonical request fetched four Menu rows from a two-row table, and the current plan scans then filesorts. A separate child-menu index such as `Language, Parent, MenuOrder` should be tested only when real child-menu rows exist.

6. Component parent links:

   - `RED_C_Form (RefID)`
   - `RED_C_Gallery (RefID)`

   These exact equality lookups are used by public rendering and administrator authorization. Current tables are too small for a timing gain, but the relationship pattern is stable and grows with component content.

7. Component registry lookup:

   `RED_Components (UniqueName, Layout)`

   Authorization and template resolution repeatedly filter these columns. The registry has only 12 rows and is expected to remain small, so this candidate should be retained only if scaled plan testing proves a benefit without complicating grouped-component behavior.

## Disposable-Scale Validation And Final Decision

The verified canonical backup was restored into disposable `redcms_phase2_indexes_20260712`. The disposable-only scale fixture added 100 Sections, 200 Categories, 500 SubCategories, 50,000 Articles, 5,000 Forms, 5,000 Galleries, and 2,000 Menu rows. Every candidate was added, measured, and removed independently before the final set was assembled.

| Query shape | Before | Proven candidate | After | Repeated-query result |
| --- | --- | --- | --- | --- |
| Article route, section/article depth | 49,005 rows scanned; 12.7 ms | Alias-first route index | 1-row lookup; 0.0068 ms | 12.96 to 0.12 ms/query |
| Article route, deepest hierarchy | 49,005 rows scanned; 17.4 ms | Same alias-first route index | 1-row lookup; 0.0296 ms | Covered by the same key |
| Section content list | 50,004 rows scanned; 13.3 ms | Section content index | 500 rows; 0.706 ms | 13.08 to 0.94 ms/query |
| Category content list | 50,004 rows scanned; 14.3 ms | Category content index | 250 rows; 0.440 ms | 13.62 to 0.92 ms/query |
| SubCategory content list | 50,004 rows scanned; 14.4 ms | SubCategory content index | 50 rows; 0.172 ms | 14.30 to 0.60 ms/query |
| Section/Category/SubCategory alias | 103/200/500-row scans | Three area alias indexes | Three 1-row covering lookups | 0.18/0.16/0.24 to 0.16/0.12/0.14 ms/query |
| Public menu | 2,002-row scan plus filesort; 1.13 ms | Public menu order index | Ordered index lookup; 0.67 ms | 1.44 to 1.02 ms/query |
| Form/Gallery parent | 5,002/5,001-row scans; 0.92/1.01 ms | Two `RefID` indexes | Two 1-row lookups; about 0.0037 ms | 1.12/1.14 to 0.16/0.16 ms/query |

The content-list plans still report `Using filesort` because the legacy order is ascending position order plus descending start date. The accepted indexes bound that sort to 500, 250, or 50 matched rows instead of scanning 50,004 rows. A version-specific descending-key design was not required for the measured gain and was not introduced while the production MySQL version remains unconfirmed.

The final set contains ten indexes: four on `RED_Articles`, three public hierarchy alias indexes, one public Menu index, and one `RefID` index on each Form/Gallery table. At fixture scale the secondary indexes occupied about 26.6 MB, and loading all 62,800 scale rows with the indexes present took 0.82 seconds. This was accepted as a reasonable storage/write cost for the measured read gains and intended three-level hierarchy growth.

`RED_Advanced (Language, Item)` and `RED_Components (UniqueName, Layout)` were rejected. Both candidates produced an index lookup, but the tables remain fixed six- and twelve-row registries with only microsecond-scale differences; their extra write/storage surface did not justify permanent keys.

The selected route key is `Language, Active, Alias(191), Sections, Categories, SubCategories`. Putting `Alias` before hierarchy columns allows the same index to serve section/article routes, the deepest Section/Category/SubCategory route, and breadcrumb alias resolution. The hierarchy-first alternative was fast only when every deeper hierarchy predicate was present.

## Explicit Non-Candidates

- All `RecordID` lookups already use primary keys.
- `RED_Layouts.UniqueName` is already the primary key.
- `RED_Features` and `RED_Tools` are fixed two-row registries; extra indexes have no measured value.
- `RED_Admin.Email` scans only two rows and is used in low-frequency management actions. Revisit only after administrator counts grow; do not introduce uniqueness semantics while legacy blank emails remain valid.
- `FeatureColumn LIKE '%value%'` and legacy `Article LIKE '%value%'` predicates cannot use ordinary B-tree indexes because of the leading wildcard. Do not add misleading indexes for these shapes.
- Administrator sorts over two users or 0–3 area rows do not justify dedicated sort indexes.
- Category/SubCategory indexes must not be judged from the empty live tables; validate them with representative hierarchy rows in the disposable scale fixture.

## Completed Migration Gate

Before local application, the batch completed all required gates:

1. Verified the external rollback package and restored it into a disposable database.
2. Built and loaded the representative hierarchy scale fixture without touching `redcms_dev`.
3. Tested every candidate independently with before/after `EXPLAIN ANALYZE`, rows examined, filesort behavior, and repeated-query timing.
4. Rejected the two fixed-registry indexes and retained only the ten proven growth-path indexes.
5. Preserved canonical fingerprints and relationships; restored and clean-install migration replay, exact five-route output, normal login, authenticated Article/Form/Gallery editor rendering, and deleted-session rejection passed.
6. Applied `database/migrations/2026-07-12-public-query-indexes.sql` locally in 60 ms; final status is 21 applied migrations with zero pending or drifted files.
