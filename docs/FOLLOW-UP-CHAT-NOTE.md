# Follow-Up Chat Note

Date: 2026-07-10

Project: RED-CMS modernization in `/Users/oscarrojas/Documents/demo.red-sphere.com`.

Current phase:

- Phase 1 closeout passed on 2026-07-10 and Phase 1 is complete.
- Phase 2 has not started; do not begin it without an explicit user decision.
- Phase 0 still has non-code ops items open: credential rotation, confirming production PHP/MySQL versions, and completing GitHub push authentication.
- Preserve public URLs and database table names exactly.
- Keep batches small and run `scripts/dev-php-lint.sh` plus focused HTTP smoke checks after each batch.

Git/checkpoint status:

- Local Git repository is initialized on branch `main`.
- Initial checkpoint commit exists: `073ce8b` (`Initial RED-CMS modernization checkpoint`).
- GitHub remote points at `orojas01-glitch/redcms`, but push is blocked until local GitHub auth is configured.
- Before the checkpoint commit, plaintext seed admin passwords in `db-structure.sql` were replaced with inert bcrypt placeholders.
- Current work is uncommitted and unstaged. Current modified/untracked files are expected to include:
  - `admin/bin/insert_content.php`
  - `admin/bin/update_content.php`
  - `includes/admin_article_helpers.php`
  - `admin/bin/update_section.php`
  - `admin/bin/update_category.php`
  - `admin/bin/update_subcategory.php`
  - `includes/admin_area_helpers.php`
  - `admin/bin/edit_section.php`
  - `admin/bin/edit_category.php`
  - `admin/bin/edit_subcategory.php`
  - `admin/bin/edit_article.php`
  - `admin/bin/edit_form.php`
  - `admin/bin/edit_other.php`
  - `admin/bin/edit_gallery.php`
  - `admin/bin/insert_gallery.php`
  - `admin/bin/update_gallery.php`
  - `includes/admin_gallery_helpers.php`
  - `admin/bin/insert_form.php`
  - `admin/bin/update_form.php`
  - `includes/admin_form_helpers.php`
  - `admin/bin/update_main_menu.php`
  - `admin/bin/update_sub_menu.php`
  - `includes/admin_menu_helpers.php`
  - `admin/bin/update_feature_slider.php`
  - `admin/bin/update_feature_template.php`
  - `admin/bin/edit_feature_slider.php`
  - `admin/bin/edit_feature_template.php`
  - `includes/admin_feature_helpers.php`
  - `admin/bin/new_article.php`
  - `admin/bin/new_gallery.php`
  - `admin/bin/new_form.php`
  - `admin/bin/new_other.php`
  - `admin/bin/new_ftp.php`
  - `admin/bin/delete_label.php`
  - `admin/bin/run_tool_movecontent.php`
  - `admin/bin/run_tool_filterareas.php`
  - `admin/bin/update_layout.php`
  - `includes/admin_tool_helpers.php`
  - `admin/bin/insert_advanced.php`
  - `admin/bin/update_advanced.php`
  - `admin/bin/edit_advanced.php`
  - `admin/class/class_add_menu.php`
  - `admin/class/class_add_tools.php`
  - `admin/class/class_edit_advanced.php`
  - `admin/class/class_edit_category.php`
  - `admin/class/class_edit_hiddenarticles.php`
  - `admin/class/class_edit_layout.php`
  - `admin/class/class_edit_section.php`
  - `admin/class/class_edit_subcategory.php`
  - `admin/class/class_new_advanced.php`
  - `admin/class/class_new_category.php`
  - `admin/class/class_new_section.php`
  - `admin/class/class_new_subcategory.php`
  - `includes/admin_advanced_helpers.php`
  - `class/class_layout.php`
  - `class/class_limit.php`
  - `class/class_metatags.php`
  - `class/class_pagetitle.php`
  - `class/class_content.php`
  - `class/class_main_menu.php`
  - `class/class_article.php`
  - `class/class_gallery.php`
  - `class/class_forms.php`
  - `class/class_other.php`
  - `class/class_component_template.php`
  - `class/class_build_breadcrumb.php`
  - `class/class_feature_slider.php`
  - `class/class_feature_template.php`
  - `bin/contact.php`
  - `bin/register.php`
  - `bin/response.php`
  - `bin/storelogin.php`
  - `bin/register_storelogin.php`
  - `includes/header.php`
  - `includes/footer.php`
  - `includes/public_render_helpers.php`
  - `includes/public_form_helpers.php`
  - `docs/FOLLOW-UP-CHAT-NOTE.md`

Completed Phase 1 work so far:

- Removed active `mysql_*` usage and added config.local support, guarded sessions, local PHP/MySQL dev runtimes, login password-hash migration, PHP 8.2+ compatibility bridge, CSRF helpers, and several admin protection batches.
- Protected section/category/subcategory create/update endpoints plus `post_file.php` and `post_ftp.php`; added upload validation helpers; hardened upload extension/MIME/size/destination/name handling; and started prepared insert cleanup for section/category/subcategory create endpoints.
- Protected `insert_advanced.php`, `update_advanced.php`, `insert_content.php`, and `update_content.php` with `red_require_admin(true)` and minimal empty-payload guards.
- Protected `insert_gallery.php`, `update_gallery.php`, `insert_form.php`, `update_form.php`, `update_main_menu.php`, and `update_sub_menu.php` with `red_require_admin(true)` and minimal empty/no-op payload guards; replaced PHP 8-incompatible `each()` loops in touched form/menu handlers.
- Protected `update_feature_slider.php` and `update_feature_template.php` with `red_require_admin(true)`.
- Tightened `update_section.php`, `update_category.php`, and `update_subcategory.php` with minimal empty/no-op payload guards, initialized rename/update state variables, removed an obsolete commented PHP `each()` block from `update_section.php`, and later replaced their raw dynamic write SQL with allowlisted prepared helpers.

Recent prepared-write batches:

- Content handlers: added `includes/admin_article_helpers.php`, replaced legacy string-built article writes in `admin/bin/insert_content.php` and `admin/bin/update_content.php` with allowlisted prepared insert/update helpers, normalized aliases/tags, converted zero/empty dates to strict-safe values, preserved article `HomeFeature`/position behavior, and allowed existing component values including `Article`, `Other`, `Gallery`, `Form`, `MainMenu`, and `SubMenu`.
- Gallery handlers: added `includes/admin_gallery_helpers.php`, replaced legacy string-built writes in `admin/bin/insert_gallery.php` and `admin/bin/update_gallery.php` with prepared multi-table writes for `RED_Articles` and `RED_C_Gallery`, normalized aliases/tags/dates, preserved gallery photo-list/delete handling, kept gallery `NewWindow` separate from the article shell, and added a `RecordID`/`RefID` pairing guard so a gallery row cannot be updated through a mismatched article id.
- Gallery UI compatibility: updated `admin/bin/edit_gallery.php` so the edit form treats the new single `yes` success response as success while still tolerating legacy response strings.
- Form handlers: added `includes/admin_form_helpers.php`, replaced legacy string-built writes in `admin/bin/insert_form.php` and `admin/bin/update_form.php` with prepared multi-table writes for `RED_Articles` and `RED_C_Form`, normalized aliases/tags through shared article helpers, preserved register-form table creation with allowlisted table/field identifiers, and added a `RecordID`/`RefID` pairing guard so a form row cannot be updated through a mismatched article id.
- Menu handlers: added `includes/admin_menu_helpers.php`, replaced legacy string-built writes in `admin/bin/update_main_menu.php` and `admin/bin/update_sub_menu.php` with allowlisted prepared helpers, preserved public table names and existing POST field names, and kept empty/no-op requests returning `no`.
- Feature toggle handlers: added `includes/admin_feature_helpers.php`, replaced legacy string-built dynamic-column updates in `admin/bin/update_feature_slider.php` and `admin/bin/update_feature_template.php` with allowlisted feature columns plus prepared `RecordID` updates, preserved feature-list ordering behavior, and fixed the helper name collision with the existing `red_admin_feature_list()` helper by using `red_admin_feature_toggle_list()`.
- Admin tool/delete/layout handlers: added `includes/admin_tool_helpers.php`, replaced `delete_label.php` raw deletes with allowlisted prepared deletes plus component/article `RecordID`/`RefID` pairing guards, replaced duplicated `run_tool_movecontent.php` and `run_tool_filterareas.php` dynamic article updates with allowlisted prepared helpers, preserved filter dash-clearing behavior, and replaced `update_layout.php` string-built layout updates with prepared updates against allowed RED tables.
- Advanced handlers: added `includes/admin_advanced_helpers.php`, replaced `insert_advanced.php` language creation with prepared fixed-item inserts, replaced `update_advanced.php` dynamic request-name SQL with explicit `Content` updates, constrained CSS reload/save targets to existing `.css` files under `/css`, preserved duplicate-language `error` behavior, and updated `admin/class/class_new_advanced.php` to treat the new single `yes` success response as success while tolerating the legacy concatenated `yes` responses.
- Area update handlers: extended `includes/admin_area_helpers.php` with shared scalar/text normalization, current-language lookup, dynamic prepared bind helpers, allowlisted area update helpers, and prepared rename-cascade helpers for `RED_Articles`, `RED_Menu`, and `RED_C_Menu`; replaced raw string-built writes in `admin/bin/update_section.php`, `admin/bin/update_category.php`, and `admin/bin/update_subcategory.php` with those helpers while preserving legacy `yes`, `updateyes`, `updateupdateyes`, `updateupdateupdateyes`, `error`, `error2`, and `error3` response shapes.
- Area edit render handlers: extended `includes/admin_area_helpers.php` with shared prepared/read helpers for area record lookup, layout/feature option loading, related-article counts, and HTML escaping; replaced raw request-interpolated reads in `admin/bin/edit_section.php`, `admin/bin/edit_category.php`, and `admin/bin/edit_subcategory.php`; added direct `red_require_admin()` guards; cast `RecordID` to integers; returned clean `no` for missing/invalid records; and preserved the existing rendered form IDs/JavaScript update/delete behavior.
- Article/form/gallery/other edit render handlers: extended article/form/gallery helpers with full prepared render lookups, layout-position lookup, position-column allowlisting, and form/gallery component `RecordID`/`RefID` pairing guards; replaced request-interpolated article/component/layout reads in `admin/bin/edit_article.php`, `admin/bin/edit_form.php`, `admin/bin/edit_gallery.php`, and `admin/bin/edit_other.php`; added direct `red_require_admin()` guards; cast `RecordID`/`ArtRecordID` to integers; returned clean `no` for invalid IDs, mismatched component/article pairs, invalid position columns, and missing records; and preserved existing rendered form IDs/hidden IDs.
- Article/form/gallery/other edit render polish: replaced the remaining legacy static option-list queries in `admin/bin/edit_article.php`, `admin/bin/edit_form.php`, `admin/bin/edit_gallery.php`, and `admin/bin/edit_other.php` with shared prepared option helpers; escaped rendered input, textarea, image filename, and link values; added hidden CSRF fields to edit save forms; tokenized edit-upload `post_file.php` URLs and `delete_label.php` AJAX payloads; removed stale LinkNavigator comment fragments; and preserved existing form IDs/hidden IDs plus gallery `NewWindow` state.
- Advanced/feature edit render handlers: extended `includes/admin_advanced_helpers.php` and `includes/admin_feature_helpers.php` with prepared render lookups, CSS file allowlisting/listing, feature-column allowlisting, component group lookup, component `RecordID` lookup through allowed component tables, and HTML escaping; replaced request-interpolated reads in `admin/bin/edit_advanced.php`, `admin/bin/edit_feature_slider.php`, and `admin/bin/edit_feature_template.php`; added direct `red_require_admin()` guards; returned clean `no` for invalid records/feature columns/languages; propagated CSRF fields/tokens for the protected save, CSS reload, and logo upload paths; and preserved existing rendered form IDs/JavaScript update behavior.
- Main menu edit render handler: extended `includes/admin_menu_helpers.php` with prepared render/read helpers for main-menu title/items/children and generated page-link options; replaced raw menu/link queries and repeated per-query database connections in `admin/bin/edit_main_menu.php`; added a direct `red_require_admin()` guard; escaped rendered menu values; propagated CSRF into the protected save form and delete-label AJAX path; and preserved existing field names/form IDs/update/delete JavaScript behavior.
- Tool render handlers: extended `includes/admin_tool_helpers.php` with prepared/read helpers for layout positions, area/layout options, active area/article dropdowns, tool article lists, allowlisted sort/position columns, and component authorization/component-record lookup; replaced request-interpolated reads in `admin/bin/tool_movecontent.php` and `admin/bin/tool_filterareas.php`; added direct `red_require_admin()` guards; propagated CSRF into the protected run-tool forms; encoded generated AJAX parameters; and added PHP 8-compatible `$This` initialization for the legacy renderer variables.
- New content/gallery/form/other/FTP render handlers: extended `includes/admin_article_helpers.php` with shared prepared fetch, option-list, gallery-type, and form-template helpers; added direct `red_require_admin()` guards to `admin/bin/new_article.php`, `admin/bin/new_gallery.php`, `admin/bin/new_form.php`, `admin/bin/new_other.php`, and `admin/bin/new_ftp.php`; normalized POST context values; allowlisted dynamic position field names; replaced request-interpolated layout/component/link-navigator/area/article option reads; propagated CSRF into save forms and `post_file.php`/`post_ftp.php` upload URLs; escaped rendered hidden admin values; and removed stale gallery comments/undefined `$row` output.
- Admin class grid/list renderers: extended `includes/admin_area_helpers.php`, `includes/admin_advanced_helpers.php`, and `includes/admin_tool_helpers.php` with prepared multi-row/list helpers, route-safe component/tool identifiers, and prepared component/tool button lookups; added direct `red_require_admin()` guards to `admin/class/class_edit_section.php`, `admin/class/class_edit_category.php`, `admin/class/class_edit_subcategory.php`, `admin/class/class_edit_advanced.php`, `admin/class/class_edit_hiddenarticles.php`, `admin/class/class_add_menu.php`, and `admin/class/class_add_tools.php`; replaced area/advanced/admin-component/tool list reads with prepared helpers; escaped rendered row values; changed add/tool AJAX payload generation to object data; and replaced the inactive-article authorization loop with the existing allowlisted component-access helper while preserving grouped component `ArtRecordID` behavior.
- Admin class static option/form renderers: added direct `red_require_admin()` guards to `admin/class/class_new_section.php`, `admin/class/class_new_category.php`, `admin/class/class_new_subcategory.php`, and `admin/class/class_edit_layout.php`; reused `red_admin_area_layouts()` and `red_admin_area_features()` for layout/feature option lists; removed the remaining active `$db->query()` calls from `admin/class`; escaped layout selector hidden context values and new-area hidden language values; and kept the existing form IDs, field names, and AJAX behavior.
- Admin class guard audit: added the missing direct `red_require_admin()` guard to `admin/class/class_new_advanced.php`, propagated a hidden CSRF field into its `insert_advanced` form, and confirmed the focused direct-access guard scan is now clean for `admin/bin` and `admin/class`.
- Public page chrome/layout render batch: added `includes/public_render_helpers.php` with prepared public fetch helpers, RED table/column allowlists, route-based area/article lookups, public HTML/title/meta helpers, and advanced-item readers; replaced raw public reads in `includes/header.php`, `includes/footer.php`, `class/class_layout.php`, `class/class_limit.php`, `class/class_metatags.php`, and `class/class_pagetitle.php`; escaped title/meta attribute output while preserving stored CMS HTML for header/footer content; and fixed CMS entity handling so title/meta text no longer double-encodes entities such as `&eacute;`.
- Public content/menu render batch: extended `includes/public_render_helpers.php` with allowlisted article position/order helpers, route-derived prepared article list filters, and prepared menu/root readers; replaced raw public/admin renderer reads in `class/class_content.php` and `class/class_main_menu.php`; preserved component dispatch, public menu hierarchy, admin `cp_menu()` form IDs, and `cp_articles()` order forms; escaped public menu labels/links and the admin main-menu edit trigger values.
- Public breadcrumb/feature render batch: extended `includes/public_render_helpers.php` with prepared breadcrumb title lookups, area feature checks, and allowlisted feature-article list helpers; replaced raw public/admin reads in `class/class_build_breadcrumb.php`, `class/class_feature_slider.php`, and `class/class_feature_template.php`; escaped breadcrumb labels/links, slider/template image/link attributes, and feature edit-form hidden context values; preserved the public slider output, admin `cp_slider()` edit form, and CSRF propagation into feature edit requests.
- Public component render batch: extended `includes/public_render_helpers.php` with prepared article/component row lookups, layout dimension helpers matching the existing `RED_Layouts` schema, admin component authorization helpers, JavaScript identifier normalization, and decoded-safe display text escaping; replaced raw public/admin component reads in `class/class_article.php`, `class/class_gallery.php`, `class/class_forms.php`, `class/class_other.php`, and `class/class_component_template.php`; preserved stored CMS body HTML, public article/gallery/form rendering, and admin `cp_article`/`cp_gallery`/`cp_form`/`cp_other` edit-control forms.
- Public form submission batch: added `includes/public_form_helpers.php`, replaced public form/login endpoint `RED_C_Form` lookups in `bin/contact.php`, `bin/register.php`, `bin/response.php`, `bin/storelogin.php`, and `bin/register_storelogin.php` with prepared `RecordID` lookups, constrained CMS-generated table and field identifiers, replaced dynamic registration-table inserts and store-login/email lookups with prepared helpers, honored `MySpamTrap` even when it is appended outside the stored `LongDesc` definition, escaped submitted values before rendering email/response placeholders, and switched the public mail endpoints to the bundled namespaced PHPMailer class.

Verification from advanced batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Public homepage returned HTTP 200 on the local FrankenPHP server.
- Guard checks: unauthenticated advanced write returned `403 no`; valid session without CSRF returned `403 csrf`; invalid CSS reload path `../includes/config.php` returned clean `HTTP 200 no`; invalid CSS save path `../style.css` returned clean `HTTP 200 no`.
- CSS reload smoke: valid `style.css` reload returned HTTP 200 with a non-empty stylesheet response.
- Advanced smoke checks used only temporary `codex-smoke-*` admin data and temporary RED_Advanced language `zz`: `insert_advanced.php` returned `yes` and created 6 rows; duplicate language insert returned `error`; `update_advanced.php` returned `yes` for a temp `ShortLine`/`Content` update; unexpected dynamic field `Title` returned clean `HTTP 200 no` and left content unchanged.
- Temporary smoke admin/advanced rows were deleted; cleanup probes returned `0` for `RED_Admin` username `codex-smoke-advanced` and `0` for `RED_Advanced` language `zz`.
- Quick `admin/bin` audit after this batch found remaining files without `red_require_admin(true)` are edit/new/tool render endpoints (`edit_*`, `new_*`, `tool_*`), not the obvious write/update endpoints; they still deserve a follow-up read/input-hardening pass.

Verification from area-update batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Public homepage returned HTTP 200 on the local FrankenPHP server.
- Guard checks for `update_section.php`: unauthenticated write returned `403 no`; valid temp admin session without CSRF returned `403 csrf`.
- Valid CSRF-authenticated temp updates returned `HTTP 200 yes` for `update_section.php`, `update_category.php`, and `update_subcategory.php`.
- Database probes confirmed normalized temp values after the endpoint calls: aliases became `codex-smoke-section-renamed`, `codex-smoke-category-renamed`, and `codex-smoke-subcategory-renamed`; layouts/query limits/features/tags matched the submitted values.
- Conflict smoke preserved legacy behavior: renaming the temp section to the temp category alias returned `HTTP 200 error2`.
- Temporary smoke admin/area rows were deleted; cleanup probes returned `0` for `RED_Admin` username `codex-smoke-areas`, `0` matching temp sections, `0` matching temp categories, and `0` matching temp subcategories.

Verification from latest area-edit render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds raw `$db->query`/`$db->update`/`mysqli_real_escape_string`/legacy `queryset` patterns in `admin/bin/edit_section.php`, `admin/bin/edit_category.php`, or `admin/bin/edit_subcategory.php`.
- Public homepage returned HTTP 200 on the local FrankenPHP server.
- Guard check for `edit_section.php`: unauthenticated render returned `403 no`.
- Valid temp admin session returned `HTTP 200` and expected rendered forms for `edit_section.php` (`id="update_section"` with related-article count), `edit_category.php` (`id="update_category"` with related-article count), and `edit_subcategory.php` (`id="update_subcategory"` with related-article count).
- Invalid area lookup returned clean `HTTP 200 no`.
- Temporary smoke admin/subcategory rows were deleted; cleanup probes returned `0` for `RED_Admin` username `codex-smoke-edit-areas` and `0` for temp subcategory alias `codex-smoke-edit-subcategory`.
- Local FrankenPHP dev service was stopped. Local MySQL responded to cleanup queries and `scripts/dev-mysql-status.sh` reported `mysqld is alive`; MySQL is still listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest article/form/gallery/other edit render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds the old request-interpolated `SELECT * FROM RED_Articles WHERE RecordID='...'`, `SELECT * FROM RED_C_Form/Gallery WHERE RecordID='...'`, or `RED_Layouts WHERE UniqueName='...'` patterns in `admin/bin/edit_article.php`, `admin/bin/edit_form.php`, `admin/bin/edit_gallery.php`, or `admin/bin/edit_other.php`.
- Public homepage returned HTTP 200 on the local FrankenPHP server.
- Guard checks: unauthenticated POSTs to `edit_article.php`, `edit_other.php`, `edit_form.php`, and `edit_gallery.php` all returned `403 no`.
- Authenticated local smoke checks using temporary admin `codex-smoke-render` returned HTTP 200 and expected rendered forms for `edit_article.php` (`id="update_content"` with `RecordID=11955622`), `edit_other.php` (`id="update_content"` with `RecordID=44189216`), `edit_form.php` (`id="update_form"` with `ArtRecordID=120878710` and `RecordID=267734579`), and `edit_gallery.php` (`id="update_gallery"` with `ArtRecordID=33564015` and `RecordID=133649831`).
- Component/article mismatch probes for form and gallery renders returned clean `HTTP 200 no`; invalid `VarPosition=RecordID` for `edit_article.php` returned clean `HTTP 200 no`.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-render`.
- Local FrankenPHP dev service was stopped. `scripts/dev-mysql-status.sh` still reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest article/form/gallery/other edit render polish batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds raw `$db->query`, `->query(`, `mysqli_real_escape_string`, legacy `queryset`, `SELECT * FROM RED`, dynamic `RED_C_...`, legacy `resultNav`/`result3`, `LinkNavigator`, or `mysqli_fetch_assoc(...)` patterns in `admin/bin/edit_article.php`, `admin/bin/edit_form.php`, `admin/bin/edit_gallery.php`, or `admin/bin/edit_other.php`.
- Public homepage returned HTTP 200 on the local FrankenPHP server.
- Guard checks: unauthenticated POSTs to `edit_article.php`, `edit_other.php`, `edit_form.php`, and `edit_gallery.php` all returned `403 no`.
- Authenticated local smoke checks using temporary admin `codex-smoke-edit` returned HTTP 200 and expected rendered forms for `edit_article.php` (`id="update_content"`, `RecordID=11955622`, hidden `csrf_token`, tokenized `post_file.php` URLs, and `delete_label.php` AJAX carrying `csrf_token`), `edit_other.php` (`id="update_content"`, `RecordID=44189216`, hidden `csrf_token`, tokenized `post_file.php` URLs, and tokenized delete AJAX), `edit_form.php` (`id="update_form"`, `ArtRecordID=120878710`, `RecordID=267734579`, hidden `csrf_token`, tokenized `post_file.php` URLs, and tokenized form delete AJAX), and `edit_gallery.php` (`id="update_gallery"`, `ArtRecordID=33564015`, `RecordID=133649831`, hidden `csrf_token`, tokenized gallery/article upload URLs, and tokenized gallery delete AJAX).
- Saved render responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.
- CSRF checks: authenticated `update_content.php` and `delete_label.php` requests without CSRF returned `403 csrf`; token-backed no-op requests to `update_content.php`, `update_form.php`, `update_gallery.php`, and `delete_label.php` returned clean `HTTP 200 no`.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-edit`.
- Local FrankenPHP dev service was stopped after verification. `scripts/dev-mysql-status.sh` reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest advanced/feature edit render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds raw `$db->query` or the old request-interpolated `RED_Advanced WHERE RecordID='...'`, feature `Language='...$Language...'`, dynamic `$row[$VarFeatures.'_Order']`, or dynamic `RED_C_".$Component` patterns in `admin/bin/edit_advanced.php`, `admin/bin/edit_feature_slider.php`, or `admin/bin/edit_feature_template.php`.
- Public homepage returned HTTP 200 on the local FrankenPHP server.
- Guard checks: unauthenticated POSTs to `edit_feature_slider.php`, `edit_feature_template.php`, and `edit_advanced.php` all returned `403 no`.
- Authenticated local smoke checks using temporary admin `codex-smoke-render2` returned HTTP 200 and expected rendered forms for `edit_feature_slider.php` (`id="update_slider"` with `RecordID[0]` and `csrf_token`), `edit_feature_template.php` (`id="update_template"` with `RecordID[0]` and `csrf_token`), and `edit_advanced.php` (`id="update_advanced"` with `RecordID=1`, `Item=Website_Title`, and `csrf_token`).
- Invalid feature column probe `VarFeatures=RecordID` returned clean `HTTP 200 no`.
- Advanced CSS reload smoke with rendered CSRF token returned HTTP 200 with a non-empty `style.css` response.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-render2`.
- Local FrankenPHP dev service was stopped. `scripts/dev-mysql-status.sh` still reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest main-menu edit render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds raw `$db->query`, `mysqli_real_escape_string`, request-superglobal interpolation, legacy `queryset`, or `SELECT * FROM RED_Menu` patterns in `admin/bin/edit_main_menu.php` or `includes/admin_menu_helpers.php`; remaining `RED_Menu` hits are prepared helper SQL strings.
- Public homepage returned HTTP 200 on the local FrankenPHP server.
- Guard check: unauthenticated POST to `edit_main_menu.php` returned `403 no`.
- Authenticated local smoke check using temporary admin `codex-smoke-menu` returned HTTP 200 and expected rendered form output for `edit_main_menu.php` (`id="update_main_menu"`, `Menu Item Manager`, hidden `csrf_token`, and delete-label AJAX carrying `csrf_token`).
- CSRF checks: authenticated `update_main_menu.php` and `delete_label.php` requests without CSRF returned `403 csrf`; token-backed no-op requests returned clean `HTTP 200 no`.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-menu`.
- Local FrankenPHP dev service was stopped. `scripts/dev-mysql-status.sh` reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest tool render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds raw `$db->query`, request-superglobal interpolation, `Build_Query`, legacy result cursor variables, or dynamic `RED_C_".$Component` patterns in `admin/bin/tool_movecontent.php` or `admin/bin/tool_filterareas.php`; remaining hits are prepared helper SQL/fetch calls in `includes/admin_tool_helpers.php`.
- Public homepage returned HTTP 200 on the local FrankenPHP server.
- Guard checks: unauthenticated POSTs to `tool_movecontent.php` and `tool_filterareas.php` returned `403 no`.
- Authenticated local smoke checks using an existing local admin session returned HTTP 200 and expected rendered forms for `tool_movecontent.php` (`id="toolmove"`, `Move Content`, hidden `csrf_token`) and `tool_filterareas.php` (`id="toolfilter"`, `Filter Content`, hidden `csrf_token`).
- Saved render responses were checked for expected form markers and no `Fatal`, `Warning`, `Deprecated`, or `Notice` output.
- CSRF checks: authenticated `run_tool_movecontent.php` and `run_tool_filterareas.php` requests without CSRF returned `403 csrf`; token-backed no-op requests returned clean `HTTP 200 no`.
- Local FrankenPHP dev service was stopped after verification. `scripts/dev-mysql-status.sh` reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest new-render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds raw `$db->query`/`->query`, old `RED_Layouts WHERE UniqueName='...'`, request-superglobal interpolation, `SELECT * FROM RED...`, `mysqli_real_escape_string`, or stale `$row[...]` render patterns in `admin/bin/new_article.php`, `admin/bin/new_gallery.php`, `admin/bin/new_form.php`, `admin/bin/new_other.php`, or `admin/bin/new_ftp.php`.
- Public homepage returned HTTP 200 on the local FrankenPHP server before and after the render smokes.
- Guard checks: unauthenticated POSTs to `new_article.php`, `new_gallery.php`, `new_form.php`, `new_other.php`, and `new_ftp.php` returned `403 no`; the `new_other.php` guard smoke caught and the batch fixed leading whitespace before `<?php` that had previously caused header/session warnings.
- Authenticated local smoke checks using temporary admin `codex-smoke-new` returned HTTP 200 and expected rendered markers for `new_article.php` (`id="insert_content"`, `name="PagePosition"`, hidden `csrf_token`, tokenized `post_file.php` URLs), `new_other.php` (`id="insert_content"`, hidden `csrf_token`, tokenized `post_file.php` URLs), `new_form.php` (`id="insert_form"`, form template textarea, hidden `FormType`, hidden `csrf_token`, tokenized `post_file.php` URLs), `new_gallery.php` (`id="insert_gallery"`, `name="GalleryType"`, hidden `csrf_token`, tokenized `post_file.php` URLs), `new_gallery.php` with `Type=Banner` (`LinkNavigator` options), and `new_ftp.php` (`id="dropbox"`, tokenized `post_ftp.php` URL).
- Saved render responses were checked for expected markers and no `Fatal`, `Warning`, `Deprecated`, or `Notice` output.
- Invalid `VarPosition=RecordID` for `new_article.php` returned clean `HTTP 200 no`.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-new`.
- Local FrankenPHP dev service was stopped after verification. `scripts/dev-mysql-status.sh` reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest admin-class grid/list batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds raw `$db->query`, `mysqli_real_escape_string`, legacy `queryset`, dynamic `RED_C_...$Component`, request-interpolated `RecordID`, `CompGroup`, `Language='".language."'`, or `AdminComponents`-driven raw SQL patterns in `admin/class/class_edit_hiddenarticles.php`, `admin/class/class_add_menu.php`, `admin/class/class_add_tools.php`, `admin/class/class_edit_section.php`, `admin/class/class_edit_category.php`, `admin/class/class_edit_subcategory.php`, `admin/class/class_edit_advanced.php`, or their touched helpers.
- Public homepage returned HTTP 200 before and after the authenticated render smoke.
- Authenticated homepage smoke using temporary admin `codex-smoke-class` returned HTTP 200 and rendered expected admin overlay markers: `CodexClass`, add-content buttons such as `cp_article`, `cp_gallery`, `cp_other`, and `cp_ftp`, `tools_content_grid`, inactive article grid `id="edit_inactive_article"`, area grids `id="editsection"`, `id="editcategory"`, `id="editsubcategory"`, and advanced grid `id="editadvanced"`.
- Rendered HTML was checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.
- Rendered JavaScript showed add/tool actions using object-style POST payloads and grouped inactive article rows still carrying `{RecordID: CRecordID, ArtRecordID: RecordID, Layout: "index"}`.
- Direct unauthenticated requests to representative touched class files (`class_edit_section.php`, `class_edit_hiddenarticles.php`, `class_add_menu.php`, and `class_add_tools.php`) returned `403 no`.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-class`.
- Local FrankenPHP dev service was stopped after verification. `scripts/dev-mysql-status.sh` reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest admin-class static option/form batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds active `$db->query(...)` calls anywhere under `admin/class`.
- Public homepage returned HTTP 200 before and after the authenticated render smoke.
- Authenticated homepage smoke using temporary admin `codex-smoke-class` returned HTTP 200 and rendered expected markers for the current layout selector (`id="update_layout"` with escaped hidden context values and layout options), new section/category/subcategory forms (`id="insert_section"`, `id="insert_category"`, `id="insert_subcategory"`), layout selects, and `Features[]` multi-selects.
- Rendered HTML was checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.
- Direct unauthenticated requests to representative newly touched class files (`class_new_section.php` and `class_edit_layout.php`) returned `403 no`.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-class`.
- Local FrankenPHP dev service was stopped after verification. `scripts/dev-mysql-status.sh` reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest admin-class guard audit:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Focused scan `rg --files-without-match "red_require_admin" admin/bin admin/class -g "*.php"` returns no remaining unguarded PHP files.
- Focused scan for raw `$db->query`, `->query(`, `mysqli_real_escape_string`, `queryset`, `SELECT * FROM RED`, and request-superglobal interpolation patterns in `admin/bin` and `admin/class` returns no matches.
- Public homepage returned HTTP 200 on the local FrankenPHP server.
- Direct unauthenticated request to `admin/class/class_new_advanced.php` returned `403 no`.
- Authenticated homepage smoke using temporary admin `codex-smoke-guard` returned HTTP 200 and rendered `id="insert_advanced"` with a hidden `csrf_token`; rendered HTML was checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.
- Direct authenticated request to `admin/class/class_new_advanced.php` returned HTTP 200 with an empty class-definition response.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-guard`.
- Local FrankenPHP dev service was stopped after verification. `scripts/dev-mysql-status.sh` reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest public page chrome/layout render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds raw `$db->query`, `SELECT * FROM RED`, `Language='".language."'`, dynamic `RED_".$this->Table`, `$this->metaquery`, `$this->otherquery`, or `mysqli_real_escape_string` patterns in `class/class_layout.php`, `class/class_limit.php`, `class/class_metatags.php`, `class/class_pagetitle.php`, `includes/header.php`, `includes/footer.php`, or `includes/public_render_helpers.php`.
- `scripts/dev-mysql-status.sh` reported `mysqld is alive` before HTTP verification.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/`, `/clases/`, `/clases/canto/`, and `/clases/canto/clases-canto-adultos`.
- Saved route responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.
- Title/meta snippets were checked after the helper update: homepage title renders `Adriana Granobles | Clases de Música y Producción Musical, Servicio personalizado y a domicilio`; article meta description renders decoded Spanish accents such as `técnicas`, `vocalización`, and `interpretación` rather than double-encoded entities.
- Local FrankenPHP dev service was stopped after verification. MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest public content/menu render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds active raw `$db->query`, `->query(`, `mysqli_fetch_assoc`, `num_rows`, `mysqli_real_escape_string`, legacy `queryset`, or request-interpolated public `RED_Articles`/`RED_Menu` reads in `class/class_content.php`, `class/class_main_menu.php`, or the touched helper code; remaining `RED_Menu` hit is a prepared helper query.
- `scripts/dev-mysql-status.sh` initially reported MySQL was down; `scripts/dev-mysql-start.sh` started the local MySQL service on `127.0.0.1:3307`.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/`, `/clases/`, `/clases/canto/`, and `/clases/canto/clases-canto-adultos`.
- Saved public responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output; menu/content markers such as `tm_navbar`, `Clases`, `Canto`, and `Adriana Granobles` rendered as expected.
- Authenticated homepage and `/clases/` overlay smokes used temporary admin `codex-smoke-menu-content`; login returned `yes`, both routes returned `HTTP 200`, and rendered expected markers including `CodexSmoke`, `main_menu_Top_Navigation`, `update_order_*`, `cp_article`, `cp_gallery`, `cp_other`, `cp_ftp`, `edit_content_grid`, `tools_content_grid`, and `tm_navbar`.
- Saved authenticated responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-menu-content`.
- Local FrankenPHP dev service was stopped after verification. Local MySQL was started for this batch, the TCP stop script failed while ping still succeeded, then socket shutdown succeeded; final status check reported MySQL unreachable on `127.0.0.1:3307`.

Verification from latest public breadcrumb/feature render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds raw `$db->query`, `->query(`, `mysqli_fetch_assoc`, `mysqli_real_escape_string`, `SELECT * FROM RED`, dynamic `RED_".$this->Table`, `$this->metaquery`, or feature-column `LIKE` string-build patterns in `class/class_build_breadcrumb.php`, `class/class_feature_slider.php`, `class/class_feature_template.php`, or the touched helper code.
- `scripts/dev-mysql-status.sh` initially reported MySQL was down; `scripts/dev-mysql-start.sh` started the local MySQL service on `127.0.0.1:3307`.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/`, `/clases/`, `/clases/canto/`, and `/clases/canto/clases-canto-adultos`.
- Saved public responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output; rendered markers included `camera_wrap`, `btn-default btn2`, `tm_navbar`, `Clases`, `Canto`, `Adriana Granobles`, and decoded slider text such as `Composición` and `Técnicas`.
- Authenticated homepage overlay smoke used temporary admin `codex-smoke-feature`; login returned `yes`, the homepage returned `HTTP 200`, and rendered expected markers including `CodexFeature`, `main_menu_Top_Navigation`, `edit_content_grid`, `edit_feature_slider`, `slider_canto`, decoded thumbnail title text, and hidden `csrf_token`.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-feature`.
- Local FrankenPHP dev service was stopped after verification. The MySQL TCP shutdown attempts failed while `scripts/dev-mysql-status.sh` still reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest public component render batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds active raw `$db->query`, `->query(`, `mysqli_fetch_assoc`, `mysqli_real_escape_string`, legacy `queryset`, `SELECT * FROM RED`, request-superglobal reads, or request-interpolated `RecordID`/`RefID` patterns in `class/class_article.php`, `class/class_gallery.php`, `class/class_forms.php`, `class/class_other.php`, `class/class_component_template.php`, or the touched helper code.
- `scripts/dev-mysql-status.sh` reported `mysqld is alive` before and after verification.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/`, `/clases/`, `/clases/canto/`, and `/clases/canto/clases-canto-adultos`.
- Saved public responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output; rendered markers included `camera_wrap`, `tm_navbar`, `thumb-pad`, `Clases`, `Canto`, `Adriana Granobles`, `Composición`, `Técnicas`, and component article/video/form content.
- Authenticated overlay smokes used temporary admin `codex-smoke-components`; login through `bin/login.php` returned `yes`, homepage and `/clases/canto/` returned `HTTP 200`, and rendered expected markers including `CodexComp`, `main_menu_Top_Navigation`, `edit_content_grid`, `tools_content_grid`, `cp_article`, `cp_gallery`, `cp_form`, `cp_other`, `content_*`, `gallery_*`, `forms_*`, `RecordID`, `ArtRecordID`, `VarPosition`, and hidden `csrf_token` fields.
- Saved authenticated responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.
- Title/entity smoke confirmed a stored entity title renders as `Clases de Canto para adultos y niños` instead of double-encoding as `&amp;ntilde;`.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-components`.
- Local FrankenPHP dev service was stopped after verification. MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest public form submission batch:

- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan no longer finds `mysql_*`, `mysqli_real_escape_string`, raw `$db->query`, `->query(`, legacy `queryset`, `SELECT * FROM`, dynamic `INSERT INTO $...`, dynamic `UPDATE $...`, dynamic `DELETE FROM $...`, or global `new PHPMailer()` patterns in `bin/contact.php`, `bin/register.php`, `bin/response.php`, `bin/storelogin.php`, `bin/register_storelogin.php`, or `includes/public_form_helpers.php`.
- FrankenPHP CLI temp-table probe exercised `red_public_form_insert_submission()` and `red_public_form_store_user()` against a temporary `codex_public_form_tmp` table; prepared insert returned `true` and prepared lookup returned `RecordID=12345`/`full_name=Test User`. The temporary table was connection-scoped only.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/`, `/clases/`, `/clases/canto/`, and `/clases/canto/clases-canto-adultos`.
- Saved public responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output; expected route markers such as `tm_navbar`, `Clases`, `Canto`, `Adriana Granobles`, and `Formulario de Contacto` rendered.
- Missing-session POST checks for rewritten endpoints returned legacy home redirects (`HTTP 302`) for `register.php` and `storelogin.php`.
- Session-backed no-mail spam-trap checks returned clean responses for `contact.php` (`HTTP 200` with escaped email table), `register.php` (`HTTP 200` empty), and `response.php` (`HTTP 200` empty); invalid local store-login/register-storelogin form IDs returned legacy home redirects with empty bodies.
- Local FrankenPHP dev service was stopped after verification. `scripts/dev-mysql-status.sh` reported `mysqld is alive`; MySQL remains listening on `127.0.0.1:3307` and should be checked manually before the next session.

Verification from latest public PayPal callback batch:

- Added `includes/public_paypal_helpers.php` and rewrote `bin/paypal_response.php` to preserve the public callback URL and legacy redirects while moving PayPal PDT parsing, email rendering, store custom parsing, duplicate confirmation checks, and audio-store purchase writes into helpers.
- Removed the hard-coded PayPal PDT auth token from `bin/paypal_response.php`; the endpoint now reads `PAYPAL_PDT_AUTH_TOKEN`/`RED_PAYPAL_PDT_AUTH_TOKEN` or `includes/config.local.php`, and `includes/config.local.example.php` documents `PAYPAL_PDT_HOSTNAME` and `PAYPAL_PDT_AUTH_TOKEN`.
- Replaced raw string-built `RED_C_AudioStore_Purchases` insert and `RED_C_AudioStore_Users` update with prepared statements inside a transaction; store callbacks now require numeric `audio_id`/`user_id` values from the `custom=store,...` payload and skip duplicate `confirmation` inserts.
- Updated PayPal confirmation email output to escape PDT-provided item, amount, and transaction values and use the bundled namespaced PHPMailer class.
- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan found no hard-coded legacy PDT token, raw `$db->insert`/`$db->update`/`$db->query`, `mysqli_real_escape_string`, `SELECT * FROM`, or global `new PHPMailer()` patterns in `bin/paypal_response.php` or `includes/public_paypal_helpers.php`.
- Local no-token callback smokes returned clean empty `HTTP 200` responses for `/bin/paypal_response.php` and `/bin/paypal_response.php?tx=codex-smoke`, confirming local config does not make external PayPal calls without a configured token.
- FrankenPHP CLI helper probe confirmed PDT parsing and `custom=store,123,456` extraction; invalid custom IDs such as `store,abc,456` returned `null`.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/` and `/clases/`; saved responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.
- Local FrankenPHP dev service was stopped after verification. MySQL was started for public route smokes; later TCP shutdown attempts failed, and the final `scripts/dev-mysql-status.sh` check still reported `mysqld is alive` on `127.0.0.1:3307`.

Verification from latest public route-state adapter batch:

- Replaced `class/class_build_query.php` legacy SQL-fragment builder with a deterministic route-state adapter that preserves the existing return shapes for `get_query()` and `cp_get_query()` while no longer opening a database connection, calling `mysqli_real_escape_string`, or returning request-derived SQL fragments.
- Preserved legacy route metadata behavior for home, section, category, subcategory, article, and the special `countpage === 6` article-context branch; downstream prepared helpers continue to own actual article/content filtering.
- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Focused scan no longer finds `mysqli_real_escape_string`, raw query calls, legacy `queryset`, `SELECT * FROM`, or request-built `Sections`/`Categories`/`SubCategories`/`Alias`/`Article LIKE` SQL fragments in `class/class_build_query.php`, `class/class_build_page.php`, `class/class_page_layout.php`, or `class/class_content.php`.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/`, `/clases/`, `/clases/canto/`, and `/clases/canto/clases-canto-adultos`.
- Saved public responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output; rendered markers included `tm_navbar`, `Clases`, `Canto`, `Adriana Granobles`, `camera_wrap`, and the article title/meta/body for `clases-canto-adultos`.

Verification from latest public download endpoint batch:

- Hardened `bin/download.php` while preserving the public URL and legacy unauthenticated JavaScript redirect behavior.
- Added an immediate `exit` after the unauthenticated store-user redirect so requests cannot continue into file handling without `$_SESSION['StoreUser']`.
- Normalized `f` to a single basename, stripped null bytes, constrained filenames to a conservative safe-name pattern, and added `realpath()` containment checks so downloads must resolve to an existing regular file directly under `/images/store`.
- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Unauthenticated traversal probe `/bin/download.php?f=../../includes/config.php` returned HTTP 200 with only the legacy redirect script.
- Authenticated include-level traversal probe with a fake active `StoreUser` session and `f=../../includes/config.php` returned an empty response, confirming the path guard fails closed.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/` and `/clases/canto/clases-canto-adultos`; saved responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.

Verification from latest public login/logout endpoint batch:

- Hardened `bin/login.php` while preserving the public URL and legacy `yes`/`no` response shape.
- Replaced the raw `SHOW COLUMNS` password-width check with a prepared `INFORMATION_SCHEMA.COLUMNS` lookup, replaced `SELECT * FROM RED_Admin` with explicit selected columns, kept legacy plaintext-password migration behavior for old rows, and added an empty/overlong username guard.
- Hardened `bin/logout.php` by exiting immediately after `session_destroy()` and the legacy `Location: /` redirect.
- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Focused scan no longer finds `mysqli_query`, `SELECT * FROM`, `SHOW COLUMNS`, raw `$db->query`, `mysqli_real_escape_string`, or legacy `queryset` patterns in `bin/login.php` or `bin/logout.php`.
- Missing-field and invalid-credential login POSTs returned clean `HTTP 200 no`; `logout.php?logout` returned `HTTP 302 Location: /`.
- Valid hashed-password login smoke used temporary admin `codex-smoke-login`; `bin/login.php` returned `HTTP 200 yes`, authenticated homepage rendered expected admin overlay markers including `CodexLogin`, `main_menu_Top_Navigation`, `edit_content_grid`, `tools_content_grid`, `cp_article`, and hidden `csrf_token`.
- Temporary smoke admin row was deleted; cleanup probe returned `0` for `RED_Admin` username `codex-smoke-login`.
- Public/authenticated responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.

Verification from latest legacy mail-handler batch:

- Hardened `bin/MailHandler.php` and `bat/MailHandler.php` while preserving their public URLs and legacy `mail sent` / `mail failed` response shape.
- Added scalar POST readers, CR/LF stripping for header-bound values, `FILTER_VALIDATE_EMAIL` checks for sender/recipient addresses, HTML escaping for rendered message fields, and fail-closed validation before calling `mail()`.
- Kept `bin/MailHandler.php` on its fixed `oscar@red-sphere.com` recipient and kept `bat/MailHandler.php` compatible with its legacy `owner_email`, `nope`, and `stripHTML` fields while validating `owner_email` before any send attempt.
- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Targeted scan confirms the handlers no longer build `From:` directly from `$_POST`, no longer trust `owner_email` without validation, and escape dynamic message fields before HTML output.
- Fail-closed HTTP smokes avoided sending email: invalid header-injection sender to `/bin/MailHandler.php` returned `HTTP 200 mail failed`; invalid `owner_email` to `/bat/MailHandler.php` returned `HTTP 200 mail failed`.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/` and `/clases/canto/clases-canto-adultos`; saved responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.

Verification from latest gallery/form transaction batch:

- Added shared `red_admin_write_transaction()` in `includes/admin_article_helpers.php` to wrap related admin writes in `mysqli_begin_transaction()`, commit only when the callback succeeds, and roll back/log on false returns or thrown failures.
- Wrapped `admin/bin/insert_gallery.php` and `admin/bin/update_gallery.php` so `RED_Articles` and `RED_C_Gallery` writes commit or roll back as one unit while preserving the legacy single `yes` / `no` response shape.
- Wrapped `admin/bin/insert_form.php` and `admin/bin/update_form.php` so `RED_Articles` and `RED_C_Form` writes commit or roll back as one unit while preserving response shapes.
- Kept registration-form table creation outside the row-write transaction with an inline note because MySQL `CREATE TABLE` can implicitly commit; the batch preflights table creation before the article/form row transaction for registration forms.
- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.
- Authenticated CSRF-backed smokes used temporary admin `codex-smoke-transaction`; `insert_gallery.php`, `update_gallery.php`, `insert_form.php`, and `update_form.php` each returned `HTTP 200 yes`.
- Database probes confirmed the temporary gallery and form article shells plus component rows all reflected the updated values after endpoint calls.
- Temporary gallery/form/article/admin rows were deleted; cleanup probe returned `0` for temporary `RED_C_Gallery`, `RED_C_Form`, `RED_Articles`, and `RED_Admin` rows.
- Public route smokes on the local FrankenPHP server returned non-empty `HTTP 200` responses for `/` and `/clases/canto/clases-canto-adultos`; saved public/authenticated responses were checked for no `Fatal`, `Warning`, `Deprecated`, `Notice`, `Parse error`, `Database query failed`, `Undefined`, or `Uncaught` output.

Recommended next Phase 1 work:

- Consider whether remaining multi-step admin operations such as tools should get targeted transaction wrapping; keep batches narrow and smoke with disposable rows.
- Complete GitHub upload after configuring local GitHub authentication; do not put tokens or passwords in chat.

Verification from latest component-delete transaction batch:

- Added the shared article transaction helper to the admin tool helper include graph.
- Wrapped paired component/article deletion in `red_admin_tool_delete_component_article()` so component cleanup commits or rolls back as one unit while preserving the legacy `yesyes` / `no` response shape.
- Kept single-table label and article deletes outside the transaction because each operation has only one database write in this path.
- `scripts/dev-php-lint.sh` passes.
- `git diff --check` passes.

Transaction storage-engine prerequisite discovered during rollback verification:

- The local schema showed `RED_Articles`, `RED_C_Form`, `RED_C_Gallery`, and `RED_C_Menu` using MyISAM; MySQL accepts transaction calls for those tables but cannot roll back their writes.
- Added `database/migrations/2026-07-10-red-content-transactions.sql` to convert the article/component write tables to InnoDB after a backup, and aligned the same four table definitions in `db-structure.sql`.
- The local migration must be applied before relying on multi-table atomicity in gallery, form, or paired component-delete writes.
- Applied the migration to the local dev database; `RED_Articles`, `RED_C_Form`, `RED_C_Gallery`, and `RED_C_Menu` now report `InnoDB`.
- Added a shared InnoDB preflight to transaction calls so an optional or unmigrated component table fails closed before any paired write/delete begins.
- Success smoke returned `yesyes` and removed both temporary gallery/article rows; forced second-delete failure returned `no` and preserved the temporary component row, confirming rollback.
- Repeated the success/rollback smoke after the preflight change with the same results: `yesyes`/`0 0` on success and `no`/`1 0` after the forced failure.
- Temporary smoke rows/admins were deleted; cleanup counts returned `0 0 0`.
- Public route smokes for `/`, `/clases/`, and `/clases/canto/clases-canto-adultos` returned HTTP 200, and saved responses had no runtime-error markers.
- `scripts/dev-php-lint.sh` passes and `git diff --check` passes.

Verification from latest Move Content compatibility batch:

- Compared the current `admin/bin/tool_movecontent.php` with the confirmed-working reference at `/Users/oscarrojas/Documents/red-cms-archive/tool_movecontent.php`.
- Restored the generic `Content` scope used by `admin/mainnav.php`; the current renderer had incorrectly required `red_admin_tool_area_config('Content')`, which only supports `Sections`, `Categories`, and `SubCategories`, causing the tool to return `no` before rendering.
- Fixed the Move Content AJAX callback so its error message is an actual `else` branch instead of running after a successful update.
- Preserved the current admin guard, prepared article reads, output escaping, CSRF field, and allowlisted position handling.
- Archived-style render smoke (`CountPage=3`, `Section=clases`, `Layout=index-2`, `cparea=Content`) returned HTTP 200, rendered `id="toolmove"` and `Move Content`, and had no PHP runtime-error markers.
- `scripts/dev-php-lint.sh` passes and `git diff --check` passes.

Local clean-install content cleanup:

- At the user's direction, deleted local `RED_Articles` rows `968105996`, `1427456022`, `1833316387`, `896025657`, and `76013402`; local `RED_C_Form` row `267734579`; and local `RED_Menu` rows `55`, `59`, `60`, `61`, and `62`.
- Created targeted restore files before deletion under `/Users/oscarrojas/Documents/red-cms-archive/`: `redcms-cleanup-backup-2026-07-10-articles.sql`, `redcms-cleanup-backup-2026-07-10-form.sql`, and `redcms-cleanup-backup-2026-07-10-menu.sql`.
- Verified all requested IDs now have zero remaining rows.
- The user later deleted gallery row `599622305` through the admin; the paired delete also removed linked article `1275161057`.
- Backed up and deleted orphan article `120878710` after the user confirmed that the removed form/article pair was no longer needed. The form row `267734579` was already absent.
- Recovered Contact form `93039112` by moving its linked article `459269660` from the removed `contact` section to the surviving `contacto` section. Backed up both rows first in `/Users/oscarrojas/Documents/red-cms-archive/redcms-contact-form-recovery-2026-07-10.sql`.
- `/contacto/` now returns HTTP 200 and renders Contact form `93039112` with its expected fields.
- Final local counts are `RED_Articles=5`, `RED_C_Form=2`, `RED_C_Gallery=1`, and `RED_Menu=2`; the complete schema contains the canonical 14 tables.
- Relationship audit returns zero missing article/component pairs, zero missing form/gallery articles, zero missing sections, and zero missing menu parents.
- Archived the previous installer dump as `/Users/oscarrojas/Documents/red-cms-archive/db-structure-pre-clean-regeneration-2026-07-10.sql` before regenerating `db-structure.sql`.
- Regenerated `db-structure.sql` from a sanitized temporary clone of the cleaned local database. It preserves the current content, schema, and InnoDB engines for `RED_Articles`, `RED_C_Form`, `RED_C_Gallery`, and `RED_C_Menu`, while replacing seed admin passwords with disabled placeholders and blanking seed admin emails.
- Imported the regenerated installer into a fresh temporary database and verified all 14 tables, exact row counts, storage engines, sanitized admin seeds, and zero relationship errors. Removed both temporary databases afterward; only `redcms_dev` remains.
- `scripts/dev-php-lint.sh` and `git diff --check` pass after regeneration.

Verification from latest area-rename transaction batch:

- Created a rollback checkpoint at `/Users/oscarrojas/Documents/red-cms-archive/phase1-area-transactions-2026-07-10/`, including the pre-change handlers/helpers/schema files and `redcms-area-menu-before.sql` for `RED_Sections`, `RED_Categories`, `RED_SubCategories`, and `RED_Menu`.
- Extracted the generic InnoDB preflight and write transaction functions from `includes/admin_article_helpers.php` into `includes/admin_transaction_helpers.php`; existing gallery, form, and paired-delete calls retain the same helper contract.
- Added `red_admin_area_rename()` to `includes/admin_area_helpers.php` and wrapped section, category, and subcategory rename cascades so the area record, `RED_Articles`, `RED_Menu`, and `RED_C_Menu` commit or roll back together.
- Preserved the successful legacy response shapes such as `updateyes`, `updateupdateyes`, and `updateupdateupdateyes`; a failed area update now returns `no` and rolls back earlier cascade writes instead of leaving a partially renamed route.
- Expanded `database/migrations/2026-07-10-red-content-transactions.sql` and `db-structure.sql` so `RED_Menu`, `RED_Sections`, `RED_Categories`, and `RED_SubCategories` use InnoDB in addition to the article/component transaction tables.
- Applied the expanded migration locally. `RED_Articles`, `RED_C_Form`, `RED_C_Gallery`, `RED_C_Menu`, `RED_Menu`, `RED_Sections`, `RED_Categories`, and `RED_SubCategories` all report `InnoDB`.
- Disposable helper smoke returned `updateupdateupdateyes` and updated a temporary section, article, main-menu link, and component-menu link together. A forced final area-write failure returned `false` and preserved all four original values, confirming rollback. Cleanup counts were zero for every temporary row.
- Imported the updated `db-structure.sql` into a fresh temporary database. Verification returned 14 tables, 5 articles, 2 forms, 1 gallery, 2 main-menu entries, 2 sanitized admin seeds, the expected eight InnoDB tables, and zero missing section/form/gallery relationships. The temporary database was removed afterward.
- Public route smokes for `/`, `/contacto/`, and `/administracion/` returned non-empty HTTP 200 responses with no PHP/runtime error markers; `/contacto/` still renders recovered form `93039112`.
- Unauthenticated POSTs to `update_section.php`, `update_category.php`, and `update_subcategory.php` returned `HTTP 403 no`.
- `scripts/dev-php-lint.sh` passes, and `git diff --check` passes for every file touched by this batch. The global whitespace check currently reports pre-existing trailing whitespace in modified `css/forms.css`, which this batch did not change.

Clear Phase 1 continuation plan:

1. Inventory the remaining admin write endpoints and classify each as single-table or multi-table. Add transactions only where multiple related writes can leave inconsistent state; do not wrap the Move/Filter tools merely because they are called tools if they execute one atomic `UPDATE`.
2. Run a Phase 1 closeout security audit across admin guards/CSRF, prepared reads and writes, upload boundaries, public submission endpoints, transaction coverage, and direct-access behavior. Fix findings in small file-family batches.
3. Re-run the clean-install acceptance suite: fresh `db-structure.sql` import, canonical table/count/engine audit, relationship checks, public routes, authenticated admin overlays, and focused create/edit/delete workflows with disposable rows.
4. Record any remaining Phase 1 risks and decide whether Phase 1 can close. Do not start Phase 2 until this acceptance pass is complete and documented.
5. Keep the remaining Phase 0 operational tasks separate: rotate real production credentials, confirm production PHP/MySQL versions, and complete local GitHub authentication/push without placing credentials in chat.

Recommended next Phase 1 slice:

- Perform the remaining admin write endpoint inventory and transaction-need audit first. This should be a read-heavy audit followed by one small implementation batch only if a genuine multi-table gap is found.

Admin write transaction inventory and linked submenu batch:

- Inventory confirmed that transaction coverage is already in place for gallery/article saves, form/article saves, paired component/article deletes, and section/category/subcategory rename cascades.
- Confirmed several endpoints are single-statement or single-record writes and do not need transaction wrapping solely for consistency: content insert/update, area insert, individual label/article deletes, advanced content update, and layout update.
- Confirmed `admin/bin/update_sub_menu.php` was a genuine two-table gap: one form submission can update multiple `RED_C_Menu` rows and then its linked `RED_Articles` row.
- Created a rollback checkpoint at `/Users/oscarrojas/Documents/red-cms-archive/phase1-submenu-transactions-2026-07-10/`, including the pre-change endpoint, handoff note, and a SQL backup of `RED_C_Menu` plus `RED_Articles`.
- Wrapped the complete linked submenu/article save in `red_admin_write_transaction()` with an InnoDB preflight for `RED_C_Menu` and `RED_Articles`.
- Preserved the existing POST field names and single `yes` / `no` response shape. Valid unchanged-field updates remain successful executions, while an invalid attempted row update or linked article failure now rolls back the complete save.
- Rechecked the component-menu `RecordID`/article `RefID` pairing inside the transaction before writes begin.
- Authenticated success smoke used a disposable linked submenu/article pair. The real endpoint returned `yes` and updated menu title, label, link, order, and linked article title together.
- Forced-rollback smoke sent an overlong article section after valid menu changes. The article helper failed closed, the endpoint returned `no`, and all menu/article values remained at their original state, confirming rollback.
- Guard smokes returned `HTTP 403 no` without an admin session and `HTTP 403 csrf` with an admin session but an invalid token.
- Temporary article and submenu cleanup counts both returned zero.
- `scripts/dev-php-lint.sh` passes; the scoped `git diff --check` for `admin/bin/update_sub_menu.php` passes.
- Public route smokes for `/` and `/contacto/` returned non-empty HTTP 200 responses with no runtime-error markers; recovered Contact form `93039112` still renders.

Remaining transaction candidates, in recommended order:

1. `admin/bin/update_main_menu.php`: one save performs multiple `RED_Menu` title/item inserts and updates. `RED_Menu` is already InnoDB, so this is the next narrow transaction batch and needs no schema migration.
2. `admin/bin/update_feature_slider.php`, `admin/bin/update_feature_template.php`, and `admin/bin/update_order.php`: each form can update multiple `RED_Articles` rows and should commit or roll back as one ordered set.
3. `admin/bin/run_tool_movecontent.php` and `admin/bin/run_tool_filterareas.php`: inventory corrected the earlier assumption that these are necessarily one atomic update. Their helper can update several selected article rows across multiple location/position columns, so strict batch failure handling and transaction wrapping should be evaluated together.
4. `admin/bin/insert_advanced.php`: creating a language inserts six related `RED_Advanced` rows. Transaction support requires converting `RED_Advanced` from MyISAM to InnoDB first, so keep this as a separate schema-backed batch.
5. Upload handlers combine filesystem and database effects, which a MySQL transaction alone cannot make atomic. Review them separately for compensating file cleanup rather than grouping them into the database-only transaction work.

Recommended next Phase 1 slice:

- Transaction-wrap the complete `update_main_menu.php` save, preserve `yes` / `no`, and verify success plus forced rollback using disposable `RED_Menu` rows.

Verification from latest main-menu transaction batch:

- Created a rollback checkpoint at `/Users/oscarrojas/Documents/red-cms-archive/phase1-main-menu-transactions-2026-07-10/`, including the pre-change endpoint, handoff note, and a SQL backup of `RED_Menu`.
- Wrapped the complete `admin/bin/update_main_menu.php` save in `red_admin_write_transaction()` with an InnoDB preflight for `RED_Menu`.
- Title renames, existing root/child/grandchild updates, and new root/child/grandchild inserts now commit only when every attempted operation succeeds. Invalid attempted record IDs fail the full save instead of allowing earlier rows to remain changed.
- Preserved all existing POST field names and the single `yes` / `no` response shape. Valid unchanged-field updates remain successful statement executions.
- Authenticated success smoke used disposable root/child rows and added a third child through the real endpoint. It returned `yes`, and all three title/label/link/order/parent values committed together.
- Forced-rollback smoke allowed the title/root update to run before supplying an invalid later child `RecordID`. The endpoint returned `no`, and both original rows retained their old title, labels, links, and orders.
- Guard smokes returned `HTTP 403 no` without an admin session and `HTTP 403 csrf` with an admin session but an invalid token.
- Smoke cleanup removed all temporary menu rows. A checksum comparison confirmed the retained and active menu/area table copies were identical before cleanup replacement.
- Disposable high IDs exposed a MySQL 8.4 metadata nuance: `information_schema.TABLES.AUTO_INCREMENT` can continue reporting the historical in-memory counter after table cleanup. The final schema-only dump and `SHOW CREATE TABLE` are authoritative here: `RED_Menu` reports `AUTO_INCREMENT=68`, `RED_Sections` reports `AUTO_INCREMENT=26`, and empty `RED_C_Menu`, `RED_Categories`, and `RED_SubCategories` definitions have clean default counters.
- Final database audit returned 5 articles, 2 forms, 1 gallery, 2 main-menu rows, 0 component-menu rows, 3 sections, 0 categories, 0 subcategories, 0 temporary tables/rows, and 0 missing section relationships.
- `scripts/dev-php-lint.sh` passes; scoped `git diff --check` passes for `admin/bin/update_main_menu.php` and the handoff note.
- Public `/` and `/contacto/` routes returned non-empty HTTP 200 responses after database cleanup with no runtime-error markers. Navigation still renders Home/Contacto, and Contact form `93039112` remains present.

Remaining transaction candidates after the main-menu batch:

1. `admin/bin/update_feature_slider.php`, `admin/bin/update_feature_template.php`, and `admin/bin/update_order.php`: transaction-wrap the multi-row `RED_Articles` saves, ideally through one strict shared batch helper that distinguishes SQL failure from valid unchanged rows.
2. `admin/bin/run_tool_movecontent.php` and `admin/bin/run_tool_filterareas.php`: update several selected article rows and potentially several location/position columns; revise their helper return contract so one failed selected-row write rolls back the full tool action.
3. `admin/bin/insert_advanced.php`: make the six-row language seed atomic after a separate `RED_Advanced` InnoDB migration and clean-installer alignment.
4. Upload handlers: audit filesystem/database compensation separately because MySQL transactions cannot roll back moved files.

Recommended next Phase 1 slice:

- Add a strict shared `RED_Articles` batch transaction helper and use it for feature slider/template ordering plus `update_order.php`; verify multi-row success and a late-row forced rollback with disposable articles.

Verification from latest feature/order transaction batch:

- Created a rollback checkpoint at `/Users/oscarrojas/Documents/red-cms-archive/phase1-feature-order-transactions-2026-07-10/`, including the pre-change endpoints/helpers, handoff note, and a SQL backup of `RED_Articles`.
- Added `red_admin_article_batch_transaction()` in `includes/admin_article_helpers.php` as the shared InnoDB transaction boundary for strict multi-row `RED_Articles` operations.
- Added `red_admin_feature_update_batch()` in `includes/admin_feature_helpers.php`; it validates the feature/selection-field pairing and rolls back the whole batch when any requested article lookup or update fails.
- Updated `admin/bin/update_feature_slider.php` and `admin/bin/update_feature_template.php` to use the strict shared feature batch while preserving their POST fields and single `yes` / `no` response shape.
- Added `red_admin_article_update_order_batch()` and moved `admin/bin/update_order.php` onto the shared prepared article helper/transaction path. Position columns remain allowlisted, requested rows must exist, and valid unchanged rows remain successful statement executions.
- Real authenticated endpoint smokes used two disposable articles for each handler. Slider, template, and position-order success cases each returned `yes` and committed both rows with the expected feature/order values.
- A late missing article was supplied after one valid row for each handler. Slider, template, and position-order rollback cases each returned `no`; the first valid row retained its original feature/order values, confirming full rollback.
- Guard checks for all three endpoints returned `HTTP 403 no` without an admin session and `HTTP 403 csrf` with an admin session but an invalid token.
- Temporary article cleanup count returned zero; the canonical `RED_Articles` count remains 5 and the missing-section relationship count remains zero.
- `scripts/dev-php-lint.sh` passes. Scoped `git diff --check` passes for both helpers and all three endpoints.
- Public `/` and `/contacto/` routes returned non-empty HTTP 200 responses with no runtime-error markers. Home/Contacto navigation and recovered Contact form `93039112` still render.

Remaining transaction/consistency candidates after the feature/order batch:

1. `admin/bin/run_tool_movecontent.php` and `admin/bin/run_tool_filterareas.php`: revise the tool helper return contract so invalid/missing selected rows and failed column writes are distinguishable from valid unchanged values, then wrap the full multi-row/multi-column action in the shared `RED_Articles` transaction boundary.
2. `admin/bin/insert_advanced.php`: convert `RED_Advanced` to InnoDB in the migration and clean installer, then make all six language seed rows one transaction.
3. Upload handlers: audit moved-file/database-write compensation separately; a database transaction cannot undo filesystem moves.
4. After those targeted batches, run the Phase 1 closeout audit and clean-install acceptance suite before deciding whether Phase 1 can close.

Recommended next Phase 1 slice:

- Harden `red_admin_tool_apply_article_updates()` for strict batch failure reporting and transaction-wrap both Move Content and Filter Areas actions. Preserve their current `yes` / `no` responses and verify multi-row success plus late-row rollback with disposable articles.

Next task chat starting point:

- Read this note first and continue in Phase 1 from the Move Content / Filter Areas transaction slice above. Do not start Phase 2, do not regenerate the clean installer unless a schema batch requires it, and keep public URLs/table names unchanged.

Audio Player / Audio Store retirement:

- Removed abandoned component records 121 (`AudioPlayer`) and 122 (`AudioStore`) from the clean installer and local database; removed store-only form component records 119/120 as well.
- Removed Audio Player/Store admin button styling, Audio Store MP3 upload handling, store registration/login endpoints and form-render branches, the authenticated store download handler/class, and store-specific PayPal purchase bookkeeping. The generic PayPal confirmation path remains.
- Added idempotent migrations `database/migrations/2026-07-10-remove-audio-player.sql` and `database/migrations/2026-07-10-remove-audio-store.sql`. Local verification returned zero retired component rows, form rows, Audio Store tables, and admin references.
- `scripts/dev-php-lint.sh` and the scoped `git diff --check` pass.

Verification from latest Move Content / Filter Areas transaction batch:

- Created a rollback checkpoint at `/Users/oscarrojas/Documents/red-cms-archive/phase1-tool-transactions-2026-07-10/`, including the pre-change endpoints/helpers, handoff note, and a data-only SQL backup of `RED_Articles`.
- Reworked `red_admin_tool_apply_article_updates()` to reject invalid selected IDs and invalid position columns, require at least one requested field update, verify every selected article exists, and execute the full selected-row/multi-column action through `red_admin_article_batch_transaction()`.
- Removed the two duplicated per-column tool update helpers. Valid unchanged values remain successful prepared statement executions, and Filter Areas still converts `-` category/subcategory/article selections to empty strings.
- Authenticated two-row Move Content success returned `yes`; both disposable rows committed `Sections=tool-moved` and `SectionPosition=2`. Authenticated two-row Filter Areas success returned `yes`; both rows cleared Categories and committed `SubCategories=tool-filtered`.
- Forced late-row failures supplied one valid disposable article followed by a missing positive ID. Both endpoints returned `no`; the valid row retained its earlier `tool-moved` / `tool-filtered` values, confirming full rollback.
- Guard checks for both endpoints returned `HTTP 403 no` without an admin session and `HTTP 403 csrf` with an admin session but an invalid token.
- Smoke cleanup removed both disposable articles and the temporary admin. The canonical article count remains 5, missing-section relationships remain zero, and public `/` plus `/contacto/` returned non-empty HTTP 200 responses without runtime-error markers.
- `scripts/dev-php-lint.sh` and the scoped `git diff --check` pass.

Remaining Phase 1 transaction/consistency candidates:

1. `admin/bin/insert_advanced.php`: convert `RED_Advanced` to InnoDB in a migration and the clean installer, then make the complete six-row language seed atomic.
2. Upload handlers: audit moved-file/database-write compensation separately; a database transaction cannot undo filesystem moves.
3. Run the Phase 1 closeout audit and clean-install acceptance suite before deciding whether Phase 1 can close.

Recommended next Phase 1 slice:

- Convert `RED_Advanced` to InnoDB and transaction-wrap the complete `insert_advanced.php` language seed. Preserve the current duplicate-language `error` and success response behavior, then verify success plus forced late-row rollback.

Next task chat starting point:

- Read this note first and continue in Phase 1 from the `RED_Advanced` language-seed transaction slice above. Do not start Phase 2; keep the CMS lightweight and preserve public URLs and database table names.

Verification from latest `RED_Advanced` language-seed transaction batch:

- Created a rollback checkpoint at `/Users/oscarrojas/Documents/red-cms-archive/phase1-advanced-transactions-2026-07-10/`, including the pre-change endpoint/helper, installer, handoff note, and a full schema/data dump of `RED_Advanced`.
- Added `database/migrations/2026-07-10-red-advanced-transaction.sql`, converted the local `RED_Advanced` table to InnoDB, and aligned the clean installer table engine. The original six Spanish advanced rows remained intact.
- Updated `red_admin_advanced_create_language()` to run the complete six-item insert through `red_admin_write_transaction()` with an InnoDB preflight while preserving endpoint responses: `yes` for a complete create, `error` for an existing language, and `no` for transaction/setup failure.
- Authenticated success created all six expected rows for a disposable language and returned `yes`. Repeating it returned `error` and did not add duplicates.
- Forced late-row failure used a temporary generated unique key so the first insert succeeded and the second failed. The endpoint returned `no`, and the disposable language row count remained zero, confirming rollback. The temporary column/index were removed immediately afterward.
- Guard checks returned `HTTP 403 no` without an admin session and `HTTP 403 csrf` with an authenticated session but invalid token.
- Cleanup removed both disposable languages and the temporary admin. `RED_Advanced` remains InnoDB with 6 canonical rows and no temporary test column. Public `/` and `/contacto/` returned non-empty HTTP 200 responses without runtime-error markers.
- `scripts/dev-php-lint.sh` and the scoped `git diff --check` pass.

Remaining Phase 1 work:

1. Audit upload handlers for filesystem/database compensation so a database failure after a successful file move does not leave an orphaned file. Keep this narrowly scoped because database transactions cannot roll back filesystem moves.
2. Run the Phase 1 closeout audit and clean-install acceptance suite before deciding whether Phase 1 can close.

Recommended next Phase 1 slice:

- Audit `admin/bin/post_file.php`, `admin/bin/post_ftp.php`, and shared upload helpers for moved-file/database-write failure compensation. Add narrow cleanup only where a newly moved file would otherwise be orphaned, preserve endpoint responses/paths, and verify success plus forced database failure.

Next task chat starting point:

- Read this note first and continue in Phase 1 from the upload compensation audit above. Do not start Phase 2; keep the CMS lightweight and preserve public URLs, upload paths, and database table names.

Verification from latest upload compensation batch:

- Created a rollback checkpoint at `/Users/oscarrojas/Documents/red-cms-archive/phase1-upload-compensation-2026-07-10/`, including pre-change `post_file.php`, `post_ftp.php`, `upload_helpers.php`, and the handoff note.
- Confirmed `post_ftp.php` has no database write and therefore needs no filesystem/database compensation; its existing successful upload behavior remains unchanged.
- Added `red_upload_move_and_persist()` and a containment-checked `red_upload_remove_stored_file()` helper. `post_file.php` now moves each uniquely named file, runs its database persistence callback, and removes only that newly moved file when the callback throws or fails.
- Changed the local `post_file.php` statement helper to throw on prepare/bind/execute failure so the compensation wrapper can run. Article/logo upload targets are now checked for existence where an update would otherwise succeed with zero matching rows; valid unchanged database values remain successful.
- Removed the obsolete explicit `finfo_close()` call after real PHP 8.5 multipart smokes exposed its deprecation output, which had caused headers/status codes to be emitted incorrectly. MIME detection now returns clean JSON responses without warnings.
- Authenticated logo upload success returned HTTP 200 JSON, stored a unique filename, and updated `RED_Advanced`. The original `logo.png` database value and uploaded test file were restored/removed afterward.
- Forced database failure used a nonexistent positive logo record after a successful move. It returned HTTP 500 `{"status":"Database query failed."}` and the matching upload-directory file count stayed unchanged, confirming orphan cleanup.
- Authenticated FTP compatibility smoke returned HTTP 200 JSON and its temporary uploaded file was removed. Guard checks for both upload endpoints returned `HTTP 403 no` without a session and `HTTP 403 csrf` with an invalid token.
- Cleanup removed the temporary admin and all test uploads; `RED_Advanced` logo content is back to `logo.png`. Public `/` and `/contacto/` returned non-empty HTTP 200 responses without runtime-error markers.
- `scripts/dev-php-lint.sh` and the scoped `git diff --check` pass.

Remaining Phase 1 work:

1. Run the Phase 1 closeout audit across schema engines, installer/migrations, authentication/CSRF guards, remaining legacy SQL patterns, public/admin smoke coverage, temporary artifacts, and documentation consistency.
2. Run a clean-install acceptance suite from `db-structure.sql` plus required migrations and verify canonical row counts/relationships and core public/admin flows.
3. Use the audit evidence to decide whether Phase 1 can close; do not start Phase 2 automatically.

Recommended next Phase 1 slice:

- Run the Phase 1 closeout audit and clean-install acceptance suite. Fix only narrow Phase 1 blockers discovered by evidence, then document a clear ready/not-ready decision without beginning Phase 2.

Next task chat starting point:

- Read this note first and perform the Phase 1 closeout audit/clean-install acceptance suite above. Keep the CMS lightweight, preserve public URLs/database table names, and do not start Phase 2 without an explicit user decision.

Phase 1 closeout decision:

- Created a rollback checkpoint at `/Users/oscarrojas/Documents/red-cms-archive/phase1-closeout-2026-07-10/` for the installer and closeout documentation.
- Full active-code PHP lint passes across 110 PHP files. Guard inventory found no unguarded `admin/bin` or `admin/class` PHP files, and every `insert_*`, `update_*`, `delete_*`, `run_*`, and `post_*` admin endpoint uses `red_require_admin(true)`.
- Active-code scans found no direct request-superglobal interpolation into SQL, no direct legacy `session_start()` outside the guarded bootstrap helper, and no active `mysql_*` calls. Remaining dynamic SQL is confined to prepared helpers with allowlisted table/column identifiers; `class_connection.php` retains its legacy generic mysqli wrapper for compatibility but active migrated paths no longer call raw `$db->query()` methods.
- The first fresh installer import exposed one malformed quote/comma sequence in the sanitized `RED_Admin` seed left by the Audio Player component-list removal. Corrected that single installer seed syntax blocker and reran the complete suite.
- A fresh disposable `redcms_phase1_acceptance` database imported `db-structure.sql` and all five migrations successfully. Rerunning every migration also succeeded. The resulting schema has 14 canonical tables, 9 InnoDB transaction tables, 5 articles, 2 forms, 1 gallery, 2 menu rows, 3 sections, 19 components, 2 sanitized admin seeds, zero retired Audio Player/Store components, and zero missing section/form/gallery relationships.
- Normalized schema-only dumps of the clean acceptance database and live `redcms_dev` were identical after ignoring auto-increment counters.
- Clean-install runtime acceptance on an isolated server returned non-empty HTTP 200 responses for `/` and `/contacto/` with no runtime-error markers. A disposable plaintext admin logged in successfully, upgraded to a 60-character bcrypt hash, rendered the authenticated overlay, add-article form, and edit-article form, and exposed CSRF tokens.
- Clean-install guards returned `HTTP 403 no` without a session and `HTTP 403 csrf` for an authenticated invalid token. A tokened impossible order update returned HTTP 200 `no`. Logout destroyed the admin session and removed overlay markers.
- Removed the disposable admin/database, revoked its temporary application-account grant, stopped isolated acceptance servers, and verified live `redcms_dev` remains at 14 tables/5 articles with zero `codex-*` admins, high-ID smoke articles, or disposable advanced languages.
- The only global `git diff --check` output remains the previously documented line-ending/trailing-whitespace condition in modified `css/forms.css`; no closeout batch touched that file, and all scoped checks pass.
- Decision: Phase 1 is complete. The CMS meets the Phase 1 runtime, configuration, authentication, request protection, upload, input/SQL, transaction, clean-installer, and compatibility goals without changing public URLs or database table names.

Remaining work outside Phase 1:

1. Phase 0 operations remain open: rotate real production credentials, confirm production PHP/MySQL versions, and complete GitHub authentication/push.
2. Decide explicitly whether and when to begin Phase 2. Do not begin Phase 2 automatically.

Next task chat starting point:

- Read this note first. Phase 1 is complete and Phase 2 is not started. Ask the user for an explicit Phase 2 decision, or handle the separate Phase 0 operational items they choose.
