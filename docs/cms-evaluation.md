# RED-CMS Engineering Evaluation

Date: 2026-07-02

## Scope

This review started with the `UPDATES PROCEDURES` Word documents, the project folder structure, `db-structure.sql`, and the main PHP execution path from `index.php` through the public classes and admin area.

After the initial evaluation, a small Phase 1 compatibility batch was applied. Details are tracked in `docs/phase-1-modernization-plan.md`.

## Modernization Decisions

These decisions came from the first review conversation and should guide future work:

- Preserve all existing public URLs by default.
- Preserve all existing database table names by default.
- If a URL, route, table, or field name must change, document it in a migration list before implementation.
- Keep the CMS lightweight for now; do not introduce a full framework as part of the first modernization phase.
- Composer/autoloading/namespaces can be evaluated as a later step after compatibility, security, and documentation are stabilized.
- Prioritize PHP compatibility and security together where they touch the same code, especially database access, authentication, sessions, uploads, and admin actions.
- `history/2015/` has been archived outside the web root at `/Users/oscarrojas/Documents/red-cms-archive/history-2015`.

## Project Map

| Path | Role | Notes |
| --- | --- | --- |
| `UPDATES PROCEDURES/` | Installation and extension instructions | Mostly short Word docs with screenshot-heavy guidance. Some old live credentials are present and should be removed or redacted before publishing. |
| `index.php` | Front controller and page shell | Starts session, loads config/classes, renders metadata/header/content/footer, and includes the admin panel when an admin session exists. |
| `includes/` | Shared header, footer, config | `config.php` parses URL segments, defines constants, stores DB/API configuration, and handles language session state. |
| `class/` | Public CMS runtime classes | Builds URL-derived queries, resolves layouts, renders content positions, menus, features, components, metadata, and breadcrumbs. |
| `admin/mainnav.php` | Admin panel composition | Injected into the public page for authenticated sessions; loads admin edit/add/tool classes. |
| `admin/class/` | Admin panel grid/form helpers | Builds admin controls for layout, sections, categories, subcategories, advanced settings, content buttons, and tools. |
| `admin/bin/` | Admin AJAX/action endpoints | Handles add/edit/update/upload operations. These are the highest-risk PHP modernization area. |
| `bin/` | Public form/login/payment/mail endpoints | Contains login, contact/register handlers, payment responses, download helpers, and bundled PHPMailer-era classes. |
| `images/resize.php` | Runtime image resizing endpoint | Reads image paths from query parameters and resizes dynamically. Needs path validation and input constraints. |
| `css/`, `js/`, `img/`, `images/` | Frontend assets/content assets | Bootstrap 5.3.3 assets appear present; TinyMCE is bundled under admin assets. |
| `/Users/oscarrojas/Documents/red-cms-archive/history-2015/` | Historical snapshot archive | Moved outside the deployable web root; useful for archaeology only. |

## Documentation Findings

The install docs describe a practical template-driven CMS workflow:

1. Create live and dev domains/databases, usually on cPanel.
2. Upload/copy a template package into the CMS structure.
3. Update `includes/config.php` database settings.
4. Adapt `index.php`, `includes/header.php`, `includes/footer.php`, `class/class_main_menu.php`, feature classes, and `class/class_page_layout.php`.
5. Access the admin area and create sections, categories, subcategories, and content.

The extension docs explain the intended mental model:

- Pages are assembled from sections, categories, subcategories, articles, layouts, positions, features, and components.
- Components are declared in `RED_Components`, rendered by public classes, and managed by admin `new/edit/insert/update` endpoints.
- Layouts are declared in `RED_Layouts` and implemented as PHP switch cases in `class/class_page_layout.php`.
- Admin permissions are component-record based through `RED_Admin.AdminComponents`.

Documentation gaps to address:

- Replace screenshot-only steps with text procedures that can be followed without images.
- Remove real usernames/passwords from docs and DB seed files.
- Split documentation into installer guide, operator/admin guide, developer extension guide, database reference, and security/deployment guide.
- Update host-specific language so cPanel/HostGator is one option, not the only installation model.
- Document the canonical request lifecycle and database table responsibilities.

## Runtime Architecture

Current public request flow:

1. `index.php` starts session and loads `class_connection.php` plus `includes/config.php`.
2. `includes/config.php` derives constants such as `section`, `category`, `subcategory`, `article`, `countpage`, `language`, `URL`, and `BASE_URL`.
3. `Build_Query::get_query()` turns URL depth into SQL fragments and identifies the relevant position/feature/table fields.
4. `Build_Page::get_page_query()` passes query metadata to `page_layout::layout()`.
5. `page_layout` asks `limit` and `layout` for page limits/layout name, then renders hardcoded Bootstrap layout positions.
6. Each position calls `content::articles()`.
7. `content::articles()` selects active articles for that position and dispatches by `Component`, currently including `Article`, `Form`, `Gallery`, and `Other`.

Admin flow:

1. `bin/login.php` validates admin credentials and sets session variables.
2. If `$_SESSION['alias']` exists, `index.php` includes `admin/mainnav.php`.
3. `admin/mainnav.php` loads admin class helpers and renders the control panel.
4. Admin actions call endpoint scripts in `admin/bin/` by AJAX.

## Database Findings

`db-structure.sql` contains 14 CMS tables:

| Table | Role |
| --- | --- |
| `RED_Admin` | Admin users and component permissions |
| `RED_Advanced` | Site-wide editable content/settings |
| `RED_Articles` | Central content table, 49 columns |
| `RED_Sections` | Top-level sections |
| `RED_Categories` | Category pages |
| `RED_SubCategories` | Subcategory pages |
| `RED_Components` | Component registry |
| `RED_C_Form` | Form component data |
| `RED_C_Gallery` | Gallery component data |
| `RED_C_Menu` | Component-level menus |
| `RED_Menu` | Top navigation |
| `RED_Features` | Feature registry |
| `RED_Layouts` | Layout metadata, image/video dimensions |
| `RED_Tools` | Admin tools registry |

Schema concerns:

- All tables use `MyISAM`; this prevents foreign keys and transactions.
- Character sets are mixed between `latin1`, `utf8`, and `utf8_unicode_ci`.
- `RED_Layouts` has no primary key.
- `RED_Admin.Password` is `varchar(14)`, which cannot hold modern password hashes.
- `RED_Articles.RecordID` is not auto-increment even though it is the primary key.
- There are no foreign keys between articles, components, sections, categories, or users.

## Compatibility And Security Findings

Initial codebase counts from the active PHP files, excluding `history/2015`:

- 98 PHP files, about 33,160 lines.
- 34 legacy `mysql_*` calls remained across 12 files at initial review.
- 532 `mysqli_*` calls across 72 files.
- 979 direct superglobal references across 67 files.
- 0 uses of `password_hash()` or `password_verify()`.
- 54 plain `http://` redirects across 47 files.
- 65 files call `session_start()`.

Current Phase 1 status:

- Active PHP files now have no `mysql_*` matches outside `history/2015`.
- A guarded session helper has been added for the main entry points.
- The shared connection class no longer uses removed `mysql_*` error handling.
- `includes/config.php` now reads secrets from environment variables or gitignored `includes/config.local.php`.
- `bin/login.php` now uses a prepared statement, modern password verification, session ID regeneration, and automatic hash migration when the database column supports it.
- `RED_Admin.Password` has been widened to `varchar(255)` in `db-structure.sql`, with a matching migration in `database/migrations/`.
- Shared CSRF/admin helpers are in place, with enforced checks on 19 admin write/upload endpoints, including order/delete, layout/features/tools, section/category/subcategory create/update, content/advanced writes, and upload handlers.
- `admin/bin/post_file.php` and `admin/bin/post_ftp.php` now validate upload extension, detected MIME, file size, destination directory, and stored filename before moving files.
- The section/category/subcategory create endpoints now use shared input cleanup helpers, prepared duplicate checks, and prepared insert statements.

Highest-priority risks:

- Rotate any database, API, and admin credentials that have appeared in old configs, docs, or dumps.
- Existing plain-text admin passwords still need to be converted by running the migration and logging in once per admin, or by manually setting hashes.
- SQL strings are assembled in many places from request/session/database values.
- Several remaining admin write endpoints still need centralized admin-session and CSRF checks.
- Upload hardening has started for `post_file.php` and `post_ftp.php`; continue checking any additional upload paths and media-specific edge cases.
- `images/resize.php` accepts an image path from the query string without enough path validation.
- Several older public/admin scripts still need deeper input validation and prepared statements even after the removed `mysql_*` calls were replaced.
- Several redirects use `http://` even though `.htaccess` pushes HTTPS.
- Mixed output rendering and data access make isolated testing difficult.

## PHP Version Target

The current `.htaccess` config says cPanel is using `ea-php82`. That should be the minimum compatibility target unless the production server is different.

As of this review date, php.net lists PHP 8.2, 8.3, 8.4, and 8.5 as supported branches, with PHP 8.2 receiving security fixes only until 2026-12-31. The modernization should therefore make PHP 8.2 safe first, then avoid patterns that block PHP 8.4/8.5.

Official reference: https://www.php.net/supported-versions.php

## Recommended Phases

### Phase 0: Safety And Baseline

- Create a Git repository or equivalent backup/checkpoint process before code edits.
- Remove or rotate exposed credentials from docs, DB dumps, and config.
- Confirm target production PHP/MySQL versions.
- Create a redacted sample config and keep real credentials out of the codebase.
- Add a basic local verification path: PHP syntax check, a database import smoke test, and a small list of URLs/admin actions to manually verify.
- Completed: archived `history/2015/` outside the deployable web root.

### Phase 1: PHP 8.2 Compatibility And Security Foundation

- Replace remaining `mysql_*` calls with `mysqli` equivalents or safe exception handling.
- Update `connection` to use typed properties where safe, `mysqli_report()`, charset setup, and non-public error messages.
- Add configuration helpers for environment/server variables.
- Introduce input helpers for URL segments, POST/GET values, integer IDs, booleans, and aliases.
- Add output helpers for escaping HTML attributes/text where content is not intentionally rich HTML.
- Start password migration with `password_hash()`/`password_verify()` and an expanded password column.
- Add CSRF tokens to admin forms and AJAX endpoints.
- Harden uploads with MIME checks, size limits, normalized/randomized filenames, and allowed target directories.

Compatibility rule: Phase 1 should not intentionally rename public URLs, database tables, or core database fields. New helper files/classes are acceptable as long as existing entry points continue to work.

### Phase 2: Database And Data-Access Stabilization

- Convert tables from `MyISAM` to `InnoDB` after backup/testing.
- Normalize to `utf8mb4`.
- Add primary key to `RED_Layouts`.
- Add indexes for frequent lookups: active/language/section/category/subcategory/article/component/position/order fields.
- Move query-building toward prepared statements, starting with login/admin endpoints and upload/update scripts.
- Create migration scripts rather than editing live schemas by hand.

### Phase 3: Admin Area Refactor

- Centralize session/auth checks for every admin endpoint.
- Extract repeated admin form rendering and AJAX response patterns.
- Add authorization checks by component and action, not only by rendering or hiding buttons.
- Replace inline generated JavaScript where practical with reusable admin JS modules.
- Update TinyMCE deliberately after testing plugin compatibility.

### Phase 4: Public Rendering Refactor

- Convert component dispatch from switch statements to a component registry.
- Separate content fetching from HTML rendering.
- Introduce small templates/partials for `Article`, `Other`, `Gallery`, and `Form`.
- Add an explicit router/request object to replace global constants over time.
- Preserve the existing URL model while cleaning internals.

### Phase 5: Documentation Package

- `INSTALL.md`: server requirements, config, database import, first login, deployment checklist.
- `ADMIN-GUIDE.md`: managing sections/categories/subcategories/articles/features/layouts/navigation.
- `DEVELOPER-GUIDE.md`: request lifecycle, adding components/features/layouts/admin tools.
- `DATABASE.md`: table map, relationships, important fields, migration notes.
- `SECURITY.md`: credentials, password policy, uploads, backups, update checklist.

## Open Questions

1. What production PHP and database versions are actually running now? `.htaccess` says PHP 8.2, while the schema dump came from Percona/MySQL 5.7-era tooling.
2. Is the admin UI meant only for trusted site owners, or will multiple client users use it with strict permission boundaries?

## Composer, Autoloading, And Namespaces: Later Benefits

The current decision is to keep the CMS lightweight. That is the right near-term choice because several websites already depend on the current folder, URL, and database conventions.

Later, Composer/autoloading/namespaces could still help without changing public URLs:

- Less manual `require` boilerplate in `index.php` and admin endpoints.
- Clearer separation between public runtime classes, admin services, database helpers, and components.
- Safer dependency management for libraries like PHPMailer.
- Easier testing because classes can be loaded predictably.
- Reduced chance of class/function name collisions as the CMS grows.
- A smoother path to PSR-style coding standards without adopting a full framework.

A conservative future path would be to add Composer only for autoloading and vendor libraries first, while leaving current URLs and table names untouched.
