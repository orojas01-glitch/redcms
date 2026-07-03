# Phase 1 Modernization Plan

Date: 2026-07-02

## Goal

Make the current CMS safer and compatible with modern PHP while preserving existing websites, URLs, and database table names.

## Non-Negotiables

- Keep existing public URLs working.
- Keep existing database table names working.
- Keep existing entry points such as `index.php`, `bin/login.php`, `admin/mainnav.php`, and `admin/bin/*.php`.
- Do not introduce a full framework in Phase 1.
- Document any unavoidable compatibility break before making it.

## Priority Order

### 1. Runtime Safety

- Replace direct `session_start()` calls with a safe session-start guard.
- Replace remaining removed `mysql_*` calls.
- Stop displaying raw database errors to public users.
- Add a minimal local syntax-check strategy once a PHP CLI is available.

### 2. Configuration Safety

- Move database credentials and API keys out of `includes/config.php`.
- Add a sample config file and documented environment/server-variable contract.
- Keep current `DBHOST`, `DBUSER`, `DBPASS`, and `DBNAME` constants so existing classes keep working.

### 3. Authentication

- Expand the admin password column.
- Add `password_hash()` / `password_verify()` support.
- Keep temporary backward compatibility for old plain-text passwords only long enough to upgrade accounts on first successful login.
- Regenerate session IDs after login.

### 4. Admin Request Protection

- Add a CSRF token helper.
- Add CSRF tokens to admin forms/AJAX endpoints in batches.
- Centralize admin-session checks for every `admin/bin/` endpoint.

### 5. Upload Hardening

- Validate MIME/content, not only extension.
- Add file-size limits.
- Normalize or randomize stored filenames.
- Restrict upload destinations to known directories.
- Prevent path traversal.

### 6. Request/Input Cleanup

- Add helpers for URL aliases, integer IDs, booleans, language codes, and optional strings.
- Use helpers first in login, uploads, image resizing, and admin update endpoints.
- Move toward prepared statements, starting with high-risk write endpoints.

## First Code Batch

The safest first batch should be small:

1. Update `class/class_connection.php` to remove PHP 8-incompatible `mysql_*` calls.
2. Add a session guard helper.
3. Replace `session_start()` in the main entry points with that helper.
4. Add a compatibility note to docs.

This batch should not change URLs, tables, admin UI behavior, or content rendering.

## Verification

Local verification is limited because this machine currently does not have `php` on PATH. Once PHP CLI is available, run:

```bash
php -l index.php
find . -path './history' -prune -o -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Manual smoke test list after deploying to a dev copy:

- Home page renders.
- Section URL renders.
- Category URL renders.
- Subcategory URL renders.
- Article URL renders.
- Admin login works.
- Admin panel appears only after login.
- Edit article opens.
- Add article opens.
- Upload image works.
- Update order works.
- Logout destroys the admin session.

## Compatibility Log

No URL or database table-name changes have been made.

## 2026-07-02 Code Batch 1

Completed:

- Added `includes/bootstrap.php` with `red_start_session()`.
- Updated `index.php`, `includes/config.php`, `bin/login.php`, and `bin/logout.php` to use the guarded session helper.
- Added `includes/config.local.example.php` and local/env override support.
- Moved install-specific config values out of `includes/config.php`; this workspace now uses gitignored `includes/config.local.php`.
- Declared properties in `class/class_connection.php` to avoid PHP 8.2 dynamic-property deprecations.
- Replaced removed `mysql_*` calls in active PHP files with `mysqli` equivalents.
- Changed shared database error handling to log detailed errors server-side and show generic failure messages to users.
- Updated `bin/login.php` to use a prepared statement, `password_verify()`, automatic hash migration when the schema allows it, and session ID regeneration.
- Added `database/migrations/2026-07-02-red-admin-password-hash.sql`.
- Updated `db-structure.sql` so `RED_Admin.Password` can hold modern hashes.
- Archived the legacy `history/` folder outside the web root at `/Users/oscarrojas/Documents/red-cms-archive/history-2015`.
- Installed local MySQL Community Server 8.4.10 LTS for dev use outside the web root.
- Created and imported the `redcms_dev` local database.
- Added local MySQL start/stop/status scripts under `scripts/`.

Verification:

- `rg -n "mysql_" --glob "*.php" --glob "!history/**"` returns no active-code matches.
- `includes/config.php` no longer contains install-specific database credentials or the IPStack key.
- The project web root no longer contains `history/`.
- Local database verification: 14 `RED_*` tables, 154 articles, 2 admin rows, and `RED_Admin.Password` is `varchar(255)`.
- PHP syntax linting could not be run because `php` is not currently available on PATH.

Compatibility:

- No existing public URLs changed.
- No existing database table names changed.
- `RED_Admin.Password` was expanded in width only; the field name is unchanged.

## 2026-07-03 Code Batch 2

Completed:

- Installed local PHP development runtimes outside the web root:
  - PHP CLI 8.5.8 for syntax checks.
  - FrankenPHP 1.12.4 with PHP 8.5.8, `mysqli`, `mysqlnd`, and `pdo_mysql` for browser-style smoke tests.
- Added `scripts/dev-php-lint.sh` and `scripts/dev-php-server.sh`.
- Added `docs/LOCAL-DEV-PHP.md`.
- Replaced remaining direct `session_start()` calls in active PHP files with `red_start_session()`.
- Made `admin/mainnav.php` safe to request directly by loading the guarded session and assumed dependencies with `require_once`.
- Added `#[\AllowDynamicProperties]` to legacy CMS classes under `class/` and `admin/class/` as a PHP 8.2+ compatibility bridge.
- Replaced deprecated `case 'value';` switch labels with `case 'value':`.

Verification:

- `scripts/dev-php-lint.sh` passes for all active PHP files.
- Public homepage returned HTTP 200 from the local FrankenPHP server.
- Public section URL `/clases/` returned HTTP 200 from the local FrankenPHP server.
- Invalid admin login returned `no`.
- Successful admin login returned `yes`.
- Both local dev `RED_Admin` passwords were upgraded to bcrypt-style hashes after successful login smoke tests.
- Authenticated homepage rendered the admin overlay.
- Direct `admin/mainnav.php` request with an authenticated session returned the admin UI.
- Captured public/admin smoke-test responses did not contain `Deprecated`, `Warning`, `Fatal error`, `Database connection failed`, or `Database query failed` text.

Compatibility:

- No existing public URLs changed.
- No existing database table names changed.
- The local dev database changed only by upgrading plaintext admin passwords to hashes through the intended login path.

## 2026-07-03 Code Batch 3

Completed:

- Added shared CSRF helpers to `includes/bootstrap.php`:
  - `red_csrf_token()`
  - `red_csrf_input()`
  - `red_verify_csrf()`
  - `red_require_admin()`
- Added a global authenticated-admin AJAX CSRF header in `admin/mainnav.php`.
- Added hidden CSRF tokens to the admin content-order forms generated by `class/class_content.php`.
- Protected `admin/bin/update_order.php` with centralized admin-session and CSRF checks.
- Protected `admin/bin/delete_label.php` with centralized admin-session and CSRF checks.
- Tightened `admin/bin/update_order.php` by whitelisting supported position columns and using a prepared update statement for order values.
- Cast `admin/bin/delete_label.php` record IDs to integers before delete queries.

Verification:

- `scripts/dev-php-lint.sh` passes for all active PHP files.
- Authenticated admin HTML includes `RED_CSRF_TOKEN` and hidden `csrf_token` fields for order forms.
- `admin/bin/update_order.php` without an admin session returns HTTP 403 and `no`.
- `admin/bin/update_order.php` with an admin session but no CSRF token returns HTTP 403 and `csrf`.
- `admin/bin/update_order.php` with an admin session and valid CSRF token passes CSRF and returns `no` for an intentionally invalid record ID.
- `admin/bin/delete_label.php` with an admin session but no CSRF token returns HTTP 403 and `csrf`.
- `admin/bin/delete_label.php` with an admin session and valid CSRF token passes CSRF and returns `no` for an intentionally invalid record ID.
- The temporary local dev admin password hash used to create a smoke-test session was restored.

Compatibility:

- No existing public URLs changed.
- No existing database table names changed.
- Nineteen high-risk admin write/upload endpoints enforce CSRF so far; the remaining admin write endpoints should be moved onto `red_require_admin(true)` in later batches.

## 2026-07-03 Code Batch 4

Completed:

- Protected another small group of admin write endpoints with centralized admin-session and CSRF checks:
  - `admin/bin/update_layout.php`
  - `admin/bin/update_feature_slider.php`
  - `admin/bin/update_feature_template.php`
  - `admin/bin/run_tool_filterareas.php`
  - `admin/bin/run_tool_movecontent.php`
- Kept the checks before each file's early PHP close tag so failed requests can return the intended HTTP 403 status and response body.

Verification:

- `scripts/dev-php-lint.sh` passes for all active PHP files.
- Public homepage returned HTTP 200 from the local FrankenPHP server.
- Each newly protected endpoint without an admin session returns HTTP 403 and `no`.
- Each newly protected endpoint with an admin session but no CSRF token returns HTTP 403 and `csrf`.
- `admin/bin/update_layout.php` with an admin session and valid CSRF token passes CSRF and returns HTTP 200 with `no` for intentionally impossible article criteria.

Compatibility:

- No existing public URLs changed.
- No existing database table names changed.
- Phase 1 admin request protection is still incomplete; continue moving remaining admin write endpoints onto `red_require_admin(true)` in small batches before starting Phase 2.

## 2026-07-03 Code Batch 5

Completed:

- Protected another admin request batch with centralized admin-session and CSRF checks:
  - `admin/bin/insert_section.php`
  - `admin/bin/insert_category.php`
  - `admin/bin/insert_subcategory.php`
  - `admin/bin/update_section.php`
  - `admin/bin/update_category.php`
  - `admin/bin/update_subcategory.php`
  - `admin/bin/post_file.php`
  - `admin/bin/post_ftp.php`
- Added `includes/upload_helpers.php` for admin upload validation:
  - extension allowlists
  - detected MIME checks via `finfo`
  - server-side file-size limits
  - upload destination allowlists under the document root
  - normalized, collision-safe stored filenames
- Updated the three `filedrop` upload helpers to send `X-CSRF-Token` from the global admin token.
- Replaced `admin/bin/post_file.php` and `admin/bin/post_ftp.php` with hardened handlers that preserve the existing endpoint URLs and upload case names.
- Added `includes/admin_area_helpers.php`.
- Reworked the section/category/subcategory create endpoints to use shared input cleanup helpers, prepared duplicate checks, and prepared insert statements.

Verification:

- `scripts/dev-php-lint.sh` passes for all active PHP files.
- Public homepage returned HTTP 200 from the local FrankenPHP server.
- The eight newly protected endpoints return HTTP 403 and `no` without an admin session.
- The eight newly protected endpoints return HTTP 403 and `csrf` with an admin session but no CSRF token.
- `admin/bin/insert_section.php` with an admin session and valid CSRF token returns HTTP 200 and `no` for an empty no-op payload.
- `admin/bin/insert_section.php` with a duplicate `Home` section payload returns HTTP 200 and `error`, exercising the prepared duplicate-check path without inserting.
- `admin/bin/post_file.php` and `admin/bin/post_ftp.php` with valid CSRF and no file return JSON validation errors.
- `admin/bin/post_file.php` rejects a `.txt` upload for the image-only `BigPict` case with HTTP 400 and a JSON validation error before moving the file.

Compatibility:

- No existing public URLs changed.
- No existing database table names changed.
- Upload handlers now normalize stored filenames; upload responses include `stored_name` for UI paths that display the uploaded URL.
- Phase 1 is still in progress. Continue remaining admin request protection and prepared-statement cleanup before starting Phase 2.

## 2026-07-03 Code Batch 6

Completed:

- Protected admin request batch 4 with centralized admin-session and CSRF checks:
  - `admin/bin/insert_advanced.php`
  - `admin/bin/update_advanced.php`
  - `admin/bin/insert_content.php`
  - `admin/bin/update_content.php`
- Added minimal missing-payload guards to the same endpoints so valid-CSRF empty requests return `no` before legacy SQL/file-write paths run.
- Removed four local smoke-test `RED_Advanced` rows with blank `Language` that were created by an intentionally empty valid-token smoke payload before the guard was added.

Verification:

- `scripts/dev-php-lint.sh` passes for all active PHP files.
- Public homepage returned HTTP 200 from the local FrankenPHP server.
- The four newly protected endpoints return HTTP 403 and `no` without an admin session.
- The four newly protected endpoints return HTTP 403 and `csrf` with an admin session but no CSRF token.
- The four newly protected endpoints return HTTP 200 and `no` with an admin session, valid CSRF token, and empty no-op payload.
- Local `RED_Advanced` rows with blank `Language` were restored to zero after smoke-test cleanup.

Compatibility:

- No existing public URLs changed.
- No existing database table names changed.
- Phase 1 is still in progress. Continue remaining admin request protection and prepared-statement cleanup before starting Phase 2.

## 2026-07-03 Code Batch 7

Completed:

- Protected admin request batch 5 with centralized admin-session and CSRF checks:
  - `admin/bin/insert_gallery.php`
  - `admin/bin/update_gallery.php`
  - `admin/bin/insert_form.php`
  - `admin/bin/update_form.php`
  - `admin/bin/update_main_menu.php`
  - `admin/bin/update_sub_menu.php`
- Added minimal no-op payload guards to those endpoints so valid-CSRF empty requests return `no` before legacy SQL paths run.
- Replaced PHP 8-incompatible `each()` loops in the touched form/menu write handlers.
- Initialized menu update success flags and made menu label loops tolerate absent optional nested arrays.

Verification:

- `scripts/dev-php-lint.sh` passes for all active PHP files.
- Public homepage returned HTTP 200 from the local FrankenPHP server.
- The six newly protected endpoints return HTTP 403 and `no` without an admin session.
- The six newly protected endpoints return HTTP 403 and `csrf` with an admin session but no CSRF token.
- The six newly protected endpoints return HTTP 200 and `no` with an admin session, valid CSRF token, and empty no-op payload.
- Temporary smoke-test sessions were destroyed and the local PHP server was stopped.

Compatibility:

- No existing public URLs changed.
- No existing database table names changed.
- Phase 1 is still in progress. Continue prepared-statement/input cleanup on high-risk admin writes before starting Phase 2.

## 2026-07-03 Code Batch 8

Completed:

- Tightened the existing protected area update handlers:
  - `admin/bin/update_section.php`
  - `admin/bin/update_category.php`
  - `admin/bin/update_subcategory.php`
- Added minimal no-op payload guards so valid-CSRF empty requests return `no` before legacy SQL paths run.
- Initialized rename/update state variables before POST processing.
- Removed an obsolete commented PHP `each()` loop from `admin/bin/update_section.php`.

Verification:

- `scripts/dev-php-lint.sh` passes for all active PHP files.
- Public homepage returned HTTP 200 from the local FrankenPHP server.
- The three area update endpoints return HTTP 403 and `no` without an admin session.
- The three area update endpoints return HTTP 403 and `csrf` with an admin session but no CSRF token.
- The three area update endpoints return HTTP 200 and `no` with an admin session, valid CSRF token, and empty no-op payload.
- `rg -n "\beach\s*\(|while \(list" --glob "*.php"` now finds only jQuery `.each()` calls in active PHP files.
- Temporary smoke-test sessions were destroyed and the local PHP server was stopped.

Compatibility:

- No existing public URLs changed.
- No existing database table names changed.
- Phase 1 is still in progress. Continue prepared-statement/input cleanup on high-risk admin writes before starting Phase 2.
