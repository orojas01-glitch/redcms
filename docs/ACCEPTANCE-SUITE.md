# RED-CMS Local Acceptance Suite

Date: 2026-07-26

## Purpose

`scripts/dev-acceptance.sh` creates and removes a disposable local database to prove that the checked-in installer and every migration still produce the expected RED-CMS schema.

This is a local development and controlled staging tool. It is not a HostGator deployment script and should not be uploaded into or run from the public web root.

Before it creates a database or grant, the runner executes
`scripts/clean-starter-boundary-self-test.php` and
the SEO and add-on trust contract self-tests. Those dependency-free checks require the
portable theme set, generic installer presets, server-local outbound-mail
configuration, deployment-neutral Apache rules, the shared SEO storage,
editor, validation, rendering, and cleanup contract, and a fail-closed
migration manifest with explicit reporting and transaction guards. They also
require closed, non-executing add-on discovery with exact path, compatibility,
dependency, route, settings, and SHA-256 inventory validation.
The runner also executes a temporary first-party runtime fixture that proves
fixed-entrypoint integrity, exact manifest registration, dependency ordering,
and fail-closed output or registration ambiguity without enabling a package.
After database migration, a separate disposable request fixture proves that
uninstalled and disabled packages remain unexecuted, enabled dependencies
register in order, core lookups resolve exact owners, and drift or missing code
fails before any package executes.

## Current Coverage

The current compatibility foundation performs these checks through one command:

1. Capture a non-secret primary-database isolation snapshot and normalized schema manifest.
2. Generate a unique database name beginning with `redcms_acceptance_`.
3. Refuse the configured primary database, unsafe names, or any database that already exists.
4. Create the disposable database and grant the existing application database account access only to it.
5. Import `db-structure.sql`.
6. Verify 25 InnoDB tables, utf8mb4 collations, an empty migration ledger, sanitized administrator seed placeholders, empty administrator role/capability tables, and empty add-on installation/migration/audit storage.
7. Apply every checked-in migration and require the ledger count to match the migration-file count.
8. Run migrations again and require `No pending migrations.` plus zero checksum drift. Then run the 16-assertion Owner authorization lifecycle, 14-assertion add-on registry lifecycle, 11-assertion request-bootstrap lifecycle, 17-assertion safe component-persistence/dispatch lifecycle, 19-assertion install lifecycle, 22-assertion read-only enablement-preflight lifecycle, 23-assertion atomic-enable lifecycle, and 18-assertion atomic-disable lifecycle. Component persistence/dispatch requires non-executing discovery, disabled non-execution, enabled request-local registration, full manifest component-id storage, a package-owned table with only the exact numeric article-parent foreign key, orphan refusal, read-only parent/runtime-owner agreement, fixed non-executable placement data, core-owned escaped default markup, static fail-closed fallbacks for emitted output, malformed view models, handler exceptions, and output-buffer tampering, inactive non-rendering, unchanged legacy contexts, and exact cleanup. Enablement preflight requires exact Owner authority, current installed-disabled evidence, deterministic plans, dependency and namespace checks, declarative readiness for constrained registration-only service, core-rendered default public component, and combined default-component plus registration-only-service profiles, explicit richer-surface blockers, zero state mutation or package execution, CLI-only boundaries, drift refusal, and exact cleanup. Atomic enablement separately requires exact Owner authority and plan evidence, registrar validation under the shared lifecycle lock and package lock, atomic compare-and-swap state plus audit, lifecycle reach from newly enabled standalone and combined default components to the safe core renderer, injected-failure rollback, later registration of every declared combined-package identifier, repeat refusal, and exact cleanup. Atomic disablement requires exact Owner authority, deterministic current-registry and enabled-dependent evidence, cross-connection lifecycle-lock exclusion, dependent refusal, stale-plan refusal, atomic state/audit rollback and commit, zero package or migration execution, removal of both component and service registrations from later requests, repeat refusal, and exact cleanup. Then run the 38-assertion disposable SEO lifecycle, 29-assertion content-revision lifecycle, 21-assertion page-layout distribution lifecycle, and 36-assertion custom-layout lifecycle with their existing rollback and cleanup requirements.
9. Verify the final 26-table InnoDB/utf8mb4 state, canonical clean-installer row counts including zero SEO, administrator authorization, add-on installation, migration, and lifecycle-audit rows, and zero Form, Gallery, area, layout, and component relationship errors, then run the 14-assertion two-connection theme-contract suite against the disposable database. That suite proves database-scoped locking, reentrancy, cross-connection exclusion, exception-safe release, effective-theme agreement, safe inactive upload placeholders, and reserved active/previous theme rows.
10. Compare a normalized table/column/index manifest with the configured primary schema while ignoring data and auto-increment counters.
11. Confirm the primary isolation snapshot is unchanged.
12. Select an unused high local port while explicitly reserving port `8055` for the normal development site.
13. Start FrankenPHP against the disposable database and wait for an HTTP 200 readiness response.
14. Require HTTP 200, a nontrivial response size, route-specific content markers, and no PHP/runtime error markers for `/`, `/contacto/`, `/administracion/`, `/administracion/instructions`, and the clean-installer seed `/administracion/test-vimeo`.
15. Insert one disposable plaintext-password Webmaster and require matching generic HTTP 200 `no` responses for wrong-password and unknown-user logins.
16. Require a successful login to upgrade the legacy password to bcrypt, clear only that username's failed attempts, and preserve the upgraded hash on a second login.
17. Require the authenticated homepage overlay, a valid 64-character CSRF token, the same token in a protected Administrator Users form, and HTTP 403 `csrf` for a protected write without the token.
18. Require logout to redirect to `/` and make the old session return HTTP 403 `no` on a protected endpoint.
19. Log in again with the upgraded password, delete the temporary administrator directly, and require the still-open session to return HTTP 403 `no` because its database-backed fingerprint can no longer be validated.
20. Remove and verify zero temporary administrator, failed-login, and activity-audit rows. Login/logout intentionally remain outside the minimal activity-audit event allowlist.
21. Insert and log in one narrowly assigned Guest with only Article component `#100` and Move Content tool `#1`.
22. Require the Guest to render the assigned Article form and Move Content tool with their expected form/CSRF markers.
23. Require HTTP 403 `no` for a valid-CSRF site-layout write, Administrator Users, unassigned Video component `#107`, and unassigned Filter Areas tool `#2`.
24. Compare full checksums for all 26 tables before and after the allowed/denied permission requests and require no data changes.
25. Require Guest logout and account deletion to independently invalidate the formerly allowed Article render, then remove and verify zero Guest administrator, throttle, and activity rows.
26. Insert and authenticate one separate disposable Webmaster plus one disposable active Section for the Section-delete lifecycle, upgrade its fixture password to bcrypt, and extract its valid CSRF token.
27. Create one active and one already-inactive Article through the real protected endpoint, both assigned to that Section, and require exact relationship/count state.
28. Render **Move Content** and require its selectable Article, destination fields, CSRF token, and Update control inside one form. Move the active Article Section → Home and require exact `HomePosition=1`/`SectionPosition=0` plus public Home rendering; move it Home → Section and require exact `HomePosition=0`/`SectionPosition=2` plus public Section rendering; reject undeclared destination position `99` with unchanged state. Then render the protected Section editor and require its exact two-Article archive confirmation plus matching CSRF token.
29. Submit the Section delete without CSRF; require HTTP 403 `csrf` and unchanged Section/Article active-state counts.
30. Submit the protected delete; require legacy response `yes`, exact `X-RED-Archived-Articles: 2`, and atomic state of zero Sections plus two preserved inactive Articles and zero active Articles for that alias.
31. Require both rows in the authenticated **Inactive Articles** panel and require the deleted route to return the active-theme HTTP 404 without its old content.
32. Remove and verify zero Section-delete administrator, Section, Article, failed-login, and activity-audit artifacts.
33. Insert and authenticate one separate disposable Webmaster for the Article lifecycle, upgrade its fixture password to bcrypt, and extract its valid CSRF token.
34. Capture a filename/SHA-256 manifest of every pre-existing `images/articles` file, generate a valid 68-byte PNG, and upload it under a database-specific filename longer than the legacy 50-character limit through protected `post_file.php` before metadata exists. Require HTTP 200, the exact stored name and source/stored/served hashes, plus one complete inactive strict-schema placeholder at hidden position `0`.
35. Create that Article through `insert_content.php`; require the placeholder to be promoted to a renderable manifest position, the first long filename to remain attached, the exact saved fields, canonical count increase, and valid Article-component, active-section, and layout relationships.
36. Upload a second long filename through the existing-Article path, require both database fields/files/hashes, then render the protected editor with both names, saved metadata, form ID, and matching CSRF token; require the new public route to render its route-specific title and body marker.
37. Update the Article through `update_content.php`; require both image names and all relationships to remain, the updated editor and public route to render, and the former alias to stop rendering. Delete through `delete_label.php` and require the canonical four-Article count, zero fixture IDs/aliases, and absence from the deleted public route.
38. Remove only the two acceptance-owned images and generated source, require the full pre-existing Article media manifest to match, and verify zero Article-lifecycle administrator, Article, failed-login, activity-audit, or file artifacts. General Article CRUD intentionally remains outside the minimal Administrator Users activity-audit allowlist.
39. Insert and authenticate one separate disposable Webmaster for the Form lifecycle, upgrade its fixture password to bcrypt, and extract its valid CSRF token.
40. Create one Contact Form through `insert_form.php`; require the exact `RED_Articles` parent shell, exact `RED_C_Form` child, canonical count increases, and valid `RefID`, subtype `#102`, active-section, and layout relationships.
41. Render the paired protected Form editor with its parent/child IDs, saved values, form ID, and matching CSRF token, then require the new public route to render its exact title and generated field marker without PHP/runtime warnings.
42. Update both Form rows through `update_form.php`; require exact new database values, preserved parent/child/component/area relationships, updated editor, updated public route, and absence from the former alias.
43. Delete the child and parent through the paired `delete_label.php` `T=form` transaction; require the canonical four-Article/two-Form counts, zero fixture IDs/aliases, and absence from the deleted public route.
44. Remove and verify zero Form-lifecycle administrator, parent Article, child Form, failed-login, and activity-audit rows. General Form CRUD intentionally remains outside the minimal Administrator Users activity-audit allowlist.
45. Insert and authenticate one separate disposable Webmaster for the Gallery lifecycle, upgrade its fixture password to bcrypt, and extract its valid CSRF token.
46. Create one Video Gallery through `insert_gallery.php`; require the exact `RED_Articles` parent shell, exact `RED_C_Gallery` child, canonical count increases, and valid `RefID`, subtype `#107`, active-section, and layout relationships.
47. Render the paired protected Gallery editor with its parent/child IDs, saved values, form ID, and matching CSRF token, then require the new public route to render its exact title and generated YouTube embed without PHP/runtime warnings.
48. Update both Gallery rows through `update_gallery.php`; require exact new database values, preserved parent/child/component/area relationships, updated editor, updated public route, and absence from the former alias.
49. Delete the child and parent through the paired `delete_label.php` `T=gal` transaction; require the canonical four-Article/one-Gallery counts, zero fixture IDs/aliases, and absence from the deleted public route.
50. Remove and verify zero Gallery-lifecycle administrator, parent Article, child Gallery, failed-login, and activity-audit rows. General Gallery CRUD intentionally remains outside the minimal Administrator Users activity-audit allowlist.
51. Capture a filename/SHA-256 manifest of every pre-existing `images/gallery` file, reserve one database-specific acceptance filename, and refuse a pre-existing file, symbolic link, or non-file at that exact path.
52. Insert and authenticate a separate disposable Webmaster, then create one subtype `#106` Gallery parent/child metadata fixture through `insert_gallery.php` before uploading any media.
53. Generate a valid 68-byte 1×1 PNG, upload it through the protected multipart `post_file.php` endpoint with the session CSRF header, and require the exact database-specific stored name plus matching source/stored/served SHA-256 values.
54. Require the protected Gallery editor and public route to render the stored filename and caption without PHP/runtime warnings.
55. Delete the paired metadata through `delete_label.php`, remove only the exact acceptance-owned image, require the public route to stop rendering it, and require the complete pre-existing Gallery media manifest to match its starting state.
56. Remove and verify zero upload-lifecycle administrator, parent Article, child Gallery, failed-login, activity-audit, generated-source, and uploaded-file artifacts.
57. Insert and authenticate one final disposable Webmaster, then create an exact subtype `#107` Video Gallery parent/child fixture through the real protected endpoint.
58. Capture checksums for all 26 tables, install a `BEFORE UPDATE` trigger only in the disposable database, and submit a real protected Gallery update whose parent write is attempted before the trigger rejects the later child write.
59. Require HTTP 200 with the legacy response `no`, remove the trigger, require the complete 26-table checksum snapshot to match, require the exact initial parent/child values to remain, and require zero updated aliases.
60. Submit the same protected update after trigger removal and require `yes` plus exact updated parent/child values, then delete the pair through `delete_label.php` and restore canonical counts.
61. Remove and verify zero rollback-lifecycle administrator, parent Article, child Gallery, failed-login, activity-audit, and trigger artifacts. An injected failure immediately after trigger creation must also remove the trigger, fixtures, isolated server, scoped grant, and database.
62. Scan the isolated server log for PHP/runtime error markers.
63. Stop the isolated server, clean authentication/permission/Section-delete/Article/Form/Gallery/upload/rollback fixtures, remove the temporary response/cookie directory, revoke and verify removal of the temporary grant, and drop the disposable database through an exit trap on success, failure, `INT`, or `TERM`.

Representative behavior coverage is complete through the generic Version 5.1
SEO, read-only add-on trust, persisted Owner authorization, read-only registry,
guarded disabled-install/recovery, read-only enablement-preflight, atomic
constrained service/default-component/combined-profile enablement, and
non-destructive atomic disablement foundations, fail-closed enabled-package
request bootstrap, plus
Milestone 5 content-version, direct page-structure, and custom Layout Builder
foundations.
The latest complete 2026-07-30 run passed the
22-assertion clean starter boundary, 92-assertion SEO contract, 17-assertion SEO
metadata migration contract, 40-assertion add-on trust contract, and
17-assertion non-activating runtime contract, imported the 25-table installer,
applied all 37 migrations to the expected 26-table schema with zero pending or
drifted files, and completed the 16-assertion Owner
authorization, 14-assertion add-on registry, 19-assertion disabled
installation/recovery, 22-assertion read-only enablement preflight,
23-assertion atomic enablement, 11-assertion enabled-package request bootstrap,
18-assertion atomic disablement, 17-assertion safe component
persistence/dispatch,
38-assertion SEO database, 29-assertion content-revision, 21-assertion
layout-distribution, and 36-assertion custom-layout lifecycles. The normalized
schema matched signature
`345b40b7956ffc72f0fe66ecc4d619374c3f14f487d9c1fb12a1acfc360eeabd`.
It also passed every authentication, permission, Move Content, Section archive,
Article upload/CRUD, Form CRUD, Gallery CRUD/upload, and forced-rollback
lifecycle with clean logs, preserved the tracked empty-media manifests, and
removed its fixtures, server, databases, grants, authorization rows, registry
rows, and add-on audit rows.

## Requirements

- The documented local MySQL service must be running.
- `includes/config.local.php` must point to the normal local application database.
- The configured application database account must already exist.
- A local MySQL administrative account must be able to create databases, grant/revoke access, and create/drop the suite's disposable trigger.
- The documented FrankenPHP runtime and `curl` must be available.

The local default administrative account is `root` with an empty password. Override it without placing the password on the command line:

```bash
RED_ACCEPTANCE_DB_ADMIN_USER=local_admin \
RED_ACCEPTANCE_DB_ADMIN_PASS='local-admin-password' \
scripts/dev-acceptance.sh
```

The script writes administrative credentials only to a mode-`0600` temporary MySQL options file and removes it during cleanup.

## Run

From the project root:

```bash
scripts/dev-acceptance.sh
```

A successful run ends with messages similar to:

```text
Acceptance database, Owner authorization, add-on registry reconciliation, enabled add-on request bootstrap, disabled add-on installation/recovery, read-only add-on enablement preflight, atomic add-on enablement/disablement, theme-contract serialization, public runtime, authentication, permission, Move Content, Section archive/delete, Article upload/CRUD, Form CRUD, Gallery CRUD, Gallery upload, and forced transaction rollback checks passed.
Cleanup complete: stopped the isolated server and removed database/grant redcms_acceptance_....
```

The command must return a nonzero status if installation, migration, schema, relationship, primary-isolation, runtime behavior, transaction rollback, or cleanup checks fail.

## Safety Boundaries

- Database names must match `redcms_acceptance_[A-Za-z0-9_]+`.
- The configured primary database name is always refused.
- A pre-existing disposable-looking database is refused and is not deleted.
- The cleanup trap drops a database only after this exact process successfully created it.
- Port `8055` is always refused; generated ports are checked for listeners before use.
- The isolated server receives the disposable database name through environment configuration and is stopped before database/grant removal.
- Route checks require content markers, not HTTP status alone.
- Theme-contract serialization uses two disposable-database connections, writes no content, requires a blocked connection's callback not to run, verifies exception-safe mutex release, and proves forged generic Advanced/logo writes leave the paired theme rows unchanged.
- The theme contract self-test requires the core-owned page-layout ellipsis menu to reset active-theme `details` and `summary` element styles while retaining its 32-by-30-pixel desktop geometry.
- The theme contract self-test requires structured position-`0` Article and Other cards to omit their retained legacy float wrappers, establishes a scoped editor flow context, and keeps the Hidden content grid out of the later visible-position block override.
- Authentication uses only hard-coded disposable fixture credentials inside the disposable database; it never reads or changes a real administrator password.
- The cookie jar and every authentication response live inside the suite's temporary response directory, which cleanup removes and verifies absent.
- Authentication fixture cleanup is safe to rerun and executes before grant/database removal after successful and injected-failure paths.
- Guest permission checks use only disposable account data and target the disposable database. The potentially mutating layout request uses a valid CSRF token but must be denied by the Guest role before any write.
- Owner authorization acceptance runs only in the uniquely named disposable database. It requires empty default storage, a manager-only one-time bootstrap under a database advisory lock, the exact six fixed capabilities, one allowlisted audit row, database-backed session refresh, refusal of a second Owner, refusal of Owner demotion/deletion, transactional rollback after an injected audit failure, CLI confirmation guards, and exact cleanup.
- Add-on registry acceptance runs only in the uniquely named disposable database and executes no package PHP or SQL. It requires empty default storage, deterministic identity snapshots, exact Owner capability mapping, pending/checksum/version/missing-code failure reports, enabled/current load eligibility, immutable migration identity, protected ledger ownership, and exact cleanup.
- Add-on request-bootstrap acceptance runs only in the uniquely named disposable database and uses temporary first-party packages outside the clean starter. It proves uninstalled and disabled packages never execute, enabled dependencies register first, exact handlers and owners remain lookup-only, lifecycle CLIs do not request-load packages, bootstrap writes no registry or audit state, drift and missing dependencies/code fail before execution, and every package/database/filesystem fixture is removed.
- Add-on install acceptance runs only in the uniquely named disposable database and uses a temporary validated first-party fixture outside the clean starter. It proves exact Owner authorization and dependency state, stale-plan and audit fail-closed behavior before SQL, resumable partial DDL, immutable migration evidence, bounded audit data, disabled/unloaded completion, local-only confirmations, and zero residual package, SQL, authorization, audit, or code-execution artifacts.
- Add-on enablement-preflight acceptance runs only in the uniquely named disposable database and uses temporary validated packages outside the clean starter. It requires exact Owner `addons.enable` authority, exact installed-disabled/current registry evidence, deterministic client-bound plans, required enabled dependencies, capability and route conflict reporting, registration-only service, core-rendered default component, and combined default-component plus registration-only-service profiles that clear their declarative gates, exact richer-surface theme/settings/live-data blockers, no apply path, identical pre/post registry and authorization fingerprints, no package execution, and exact cleanup.
- Atomic add-on enablement acceptance runs only in the uniquely named disposable database and uses temporary validated first-party packages outside the clean starter. It requires exact Owner authority, a stale-plan refusal before execution, registrar-failure refusal, audit and post-state-update injected-failure rollback, atomic enabled-state and bounded-audit commits for all three constrained profiles, lifecycle reach from standalone and combined default components to the safe core renderer, later runtime registration of every combined-package component and service identifier, repeat refusal, CLI-only confirmations, and exact cleanup.
- Atomic add-on disablement acceptance runs only in the uniquely named disposable database and uses temporary validated first-party packages outside the clean starter. It requires exact Owner `addons.disable` authority, deterministic current-registry evidence, an exact enabled-dependent blocker, database-wide lifecycle-lock exclusion across connections, stale-plan refusal, audit and post-state-update injected-failure rollback, an atomic `installed_disabled` state and bounded audit commit, zero registrar or migration execution, exclusion of both combined-package component and service registrations from later request bootstrap, dependent-first unblocking, repeat refusal, CLI-only confirmations, and exact cleanup.
- A full-table checksum comparison makes HTTP 403 alone insufficient: every allowed/denied permission request must also leave all 26 tables unchanged.
- The Move Content lifecycle requires one valid browser-parsable tool form, exact source/destination placement changes, real protected endpoint responses, matching public rendering after each move, destination-layout refusal for undeclared positions, and transaction-preserved state after refusal. Moving between contexts clears only the source position column; unrelated placements remain intact.
- The Section-delete lifecycle uses a disposable Webmaster, Section, and two Articles only inside the disposable database. It requires count-aware confirmation, CSRF refusal with unchanged state, one transaction that archives every related Article before deleting the Section, exact response reporting, recovery through **Inactive Articles**, an active-theme 404 at the old route, and zero targeted artifacts. Form/Gallery child rows and media are deliberately left attached to their preserved parent Articles.
- The Article lifecycle uses a hard-coded disposable Webmaster, Article ID, aliases, and body markers only inside the disposable database. Create, update, and delete must pass through the real protected CSRF endpoints; direct SQL is used only to install and remove the temporary administrator and as a cleanup backstop.
- Article acceptance requires a long-name upload before metadata, a complete inactive hidden placeholder, safe promotion to a renderable position on save, a second long-name edit upload, matching source/stored/served hashes, exact saved values, both filenames in protected editors, registered component/section/layout relationships, route-specific public markers, canonical count restoration, an unchanged pre-existing Article media manifest, and zero targeted Article/admin/throttle/activity/file artifacts.
- The Form lifecycle uses Contact subtype `#102` so it can verify the two-table parent/child transaction without creating a registration table or uploading files. Its definition deliberately omits a trailing empty-row delimiter so the legacy public parser renders without undefined-key warnings.
- Form acceptance requires exact parent and child values, numeric `RefID` coupling, registered subtype/section/layout relationships, paired editor IDs and CSRF, generated public fields, canonical count restoration, and zero targeted Form/Article/admin/throttle/activity artifacts.
- The Gallery lifecycle uses Video subtype `#107` so it can verify the two-table parent/child transaction and public component renderer without creating image files or contacting an external video service.
- Gallery acceptance requires exact parent and child values, numeric `RefID` coupling, registered subtype/section/layout relationships, paired editor IDs and CSRF, generated YouTube embed markers, canonical count restoration, and zero targeted Gallery/Article/admin/throttle/activity artifacts.
- The upload lifecycle uses Gallery subtype `#106` and a database-specific filename under `images/gallery`. It refuses to overwrite any existing path and will remove only the exact regular file whose name is returned by the protected upload endpoint.
- Upload acceptance requires a valid generated PNG, matching source/stored/served hashes, exact database persistence, protected-editor and public-route markers, paired metadata deletion, zero targeted database/file artifacts, and an unchanged manifest for every pre-existing Gallery image.
- The forced-rollback lifecycle uses hard-coded disposable IDs and Video subtype `#107`. Its trigger is created by the administrative test connection only after schema comparison and exists only in the uniquely named disposable database; the protected endpoint still runs under the normal application account.
- Rollback acceptance requires the legacy `no` response, removal of the trigger, identical pre/post checksums across all 26 tables, exact initial parent/child values, a successful control update after trigger removal, canonical count restoration, and zero targeted database/trigger artifacts.
- Cleanup failure converts an otherwise successful run into a failure.
- Cleanup re-reads the application account's grants after revoke/drop and fails if the disposable database grant is still present.
- The primary database is re-read after cleanup and must match its starting isolation snapshot.

The acceptance suite does not alter production or replace the separate backup, deployment, migration, and rollback procedures required for HostGator.
