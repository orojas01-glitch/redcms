# RED-CMS Security Notes

Date: 2026-07-25

## Configuration Secrets

Runtime secrets should not live in `includes/config.php`.

Supported configuration sources:

1. Environment or server variables.
2. `includes/config.local.php` on each server.

`includes/config.local.php` is intentionally ignored by Git and blocked by `.htaccess`.

Use `includes/config.local.example.php` as the template for server-specific values.

Supported environment variables:

- `RED_DB_HOST`
- `RED_DB_USER`
- `RED_DB_PASS`
- `RED_DB_NAME`
- `RED_IPSTACK_ACCESS_KEY`
- `RED_LEGACY_MAIL_OWNER`
- `RED_PAYPAL_PDT_HOSTNAME`
- `RED_PAYPAL_PDT_AUTH_TOKEN`
- `RED_PAYPAL_CONFIRMATION_FROM_EMAIL`
- `RED_PAYPAL_CONFIRMATION_FROM_NAME`

The existing constants `DBHOST`, `DBUSER`, `DBPASS`, and `DBNAME` are preserved so current CMS classes continue to work.

## Admin Passwords

`bin/login.php` now supports modern password hashes using PHP `password_hash()` and `password_verify()`.

Backward compatibility is deliberate:

- Existing plain-text passwords can still log in temporarily.
- After a successful login, the password is upgraded to a hash only if the database column is large enough.
- Run the migration below before relying on automatic upgrades.

```sql
ALTER TABLE `RED_Admin`
  MODIFY `Password` varchar(255) NOT NULL;
```

Migration file:

`database/migrations/2026-07-02-red-admin-password-hash.sql`

Administrator Users now creates and resets passwords with `password_hash()` only. The manager never renders stored password values. Password resets invalidate existing sessions for that account on its next protected admin request.

Only accounts whose `AdminType` is `webmaster` or `superadmin` can open or submit Administrator Users actions. Self-deletion and deletion of the final manager are rejected. Component permissions come from `AdminComponents`; utility-tool permissions come from `AdminTools` after applying:

`database/migrations/2026-07-10-red-admin-user-tools.sql`

Administrator email is required for every create/update request and duplicate non-empty email values are rejected by the application. The migration widens `RED_Admin.Email` to `varchar(254)`. Existing blank legacy emails are shown as repair rows in Edit User and must be completed before those accounts can be saved.

## Portable Starter Data

The tracked `db-structure.sql` is the portable starter installer. Its
administrator rows use unavailable password hashes and generic identities; its
Form presets contain no retained client identity, legal copy, recipient, or
payment form.

Client database exports must never replace this file. Keep every client
database, theme, media archive, local configuration, and rollback point outside
the starter repository. Before distribution, run:

```bash
php scripts/clean-starter-boundary-self-test.php
```

The same dependency-free check runs before `scripts/dev-acceptance.sh` creates
its disposable database.

## Legacy Mail And Payment Configuration

The CMS-owned Contact, Response, and Register behavior remains governed by
`docs/OPERATIONAL-FORM-BOUNDARY.md`.

The compatibility paths `/bin/MailHandler.php` and `/bat/MailHandler.php`
retain their URLs but send only to `RED_LEGACY_MAIL_OWNER` or the matching
server-local configuration value. With no valid configured owner, they return
`mail failed` and invoke no mail transport. The obsolete ASP.NET handler is
inert.

`/bin/paypal_response.php` remains default-inert. It requires a server-local PDT
token and a valid `RED_PAYPAL_CONFIRMATION_FROM_EMAIL` before contacting
PayPal. Confirmation mail uses only that configured sender and the verified
payer address; it has no fixed BCC or fallback recipient. This compatibility
route does not activate the Version 5.1 member, entitlement, or payment model.

## Database Backup Security

Full database dumps contain administrator hashes, email addresses, site content, and configuration data stored in tables. Keep backups outside the public web root with access limited to the operator, record their SHA-256 checksums, use encrypted storage for production copies, and remove expired archives according to the site's retention policy.

Use `scripts/db-backup.sh` and `scripts/db-restore.sh` as documented in `docs/DATABASE-MIGRATIONS.md`. The restore command protects the configured primary database and nonempty targets by default.

## Add-On Trust Boundary

The Version 5.1 trust foundation can inspect separately deployed first-party
add-on packages without executing them.

- Discovery reads only `addon.json` beneath a fixed `addons/vendor/package`
  path.
- Package, vendor, manifest, and declared file symbolic links are rejected.
- Every package file except `addon.json` must appear in one exact SHA-256
  inventory; undeclared, missing, or changed files invalidate the package.
- Package-supplied SHA-256 values prove internal inventory consistency, not
  publisher identity. This filesystem-deployed phase depends on
  operator-reviewed provenance and does not claim signed-package verification.
- The fixed `addon.php` entry point must be declared but is never included by
  discovery or validation.
- Manifest fields cannot select PHP files, classes, methods, callbacks, or SQL
  text.
- Compatibility, dependencies, routes, unsafe-method CSRF policy, settings,
  migrations, assets, and outbound hosts fail closed.
- Current Guest, Webmaster, and legacy Superadmin roles receive no implicit
  install, enable, disable, upgrade, uninstall, or purge capability.

This is not a PHP sandbox and does not authorize untrusted packages. There is
no package upload, extraction, web installer, or web enable/disable
transition. The separate server-local installer, enable/disable commands, and
request loader accept only packages that pass this operator-reviewed
first-party trust boundary.

### Add-On Owner Authorization

Migration `2026-07-25-admin-addon-owner-authorization.sql` adds empty
`RED_Admin_Roles` and `RED_Admin_Capabilities` tables to each client database.
The migration assigns no Owner and grants no capability. The portable starter
also contains no authorization rows.

The first Owner is a server-operator bootstrap, not an administrator web form:

```bash
php scripts/admin-addon-owner.php --status
php scripts/admin-addon-owner.php \
  --bootstrap-owner=ADMIN_ID \
  --actor-admin=ADMIN_ID \
  --confirm-database=CLIENT_DATABASE \
  --confirm-username=EXACT_USERNAME
```

The second command is a dry run unless `--apply` is added. The target and
recorded actor must already be a Webmaster or legacy Superadmin, the database
and username confirmations must match exactly, and bootstrap refuses once any
Owner exists. The role, six fixed lifecycle grants, and
`administrator.owner_bootstrapped` audit event commit atomically. Owner
accounts cannot be demoted to Guest or deleted through Administrator Users.

Login and every protected administrator request reload the Owner role and
grants from the current client database. Unknown capability values are ignored,
and a capability row without the Owner role authorizes nothing. Only
`addons.install` has a server-local lifecycle consumer. `addons.enable` gates
both the read-only enablement preflight and the separate dry-run-first atomic
enable command. `addons.disable` gates the dry-run-first atomic disable
command. The other grants remain dormant because no upgrade, uninstall, or
purge transition exists.

### Add-On Registry Reconciliation

Migration `2026-07-26-addon-registry-foundation.sql` adds empty
`RED_Addon_Installations` and `RED_Addon_Migrations` tables to each client
database. The portable starter contains no installed package, lifecycle state,
or applied package migration row.

The registry foundation is read-only:

- Package identity records use only the validated stable id, semantic version,
  type, raw-manifest SHA-256, deterministic declared-file inventory SHA-256,
  actor ids, timestamps, and a closed lifecycle-state vocabulary.
- Migration evidence binds each package migration id and path to the exact
  manifest checksum. Duplicate ids and duplicate paths are rejected.
- Applied migration history prevents silent deletion of its installation
  record.
- Reconciliation fails closed on invalid packages, unknown lifecycle states,
  changed identity hashes, pending or changed migrations, orphaned migration
  rows, and installed packages whose deployed code is missing.
- A recorded `enabled` state with exact current evidence is eligible for the
  request loader; runtime registration still performs its own fail-closed
  checks before package execution.
- The read-only status command opens the current client database and validated
  filesystem packages but never includes `addon.php`, executes package SQL, or
  changes registry rows.

### Guarded Add-On Installation

Migration `2026-07-26-addon-install-activity-audit.sql` adds an empty bounded
`RED_Addon_Activity_Log` to each client database. The portable starter contains
no add-on activity rows.

`scripts/admin-addon-install.php` is dry-run-first and CLI-only. Apply requires:

- a database-backed Owner with the exact `addons.install` grant;
- exact database, package id, version, deterministic plan SHA-256, nonzero
  SHA-256 from a separately verified backup, and `installed_disabled`
  confirmations;
- a database-scoped advisory lock and a second trust/catalog preflight;
- all required dependencies to be installed, compatible, current, and enabled;
  and
- checksum-revalidated migrations confined to reviewed package files and
  package-owned `RED_Addon_*` tables.

The SQL guard refuses oversized/binary files, explicit transaction controls,
database/user/privilege/plugin/routine/trigger/event changes, file I/O, system
schemas, core or registry tables, and obvious unnamespaced table writes. It is
defense-in-depth for reviewed first-party SQL, not a complete SQL parser or an
untrusted-code sandbox.

MySQL DDL may commit implicitly. The installer therefore never promises a
rollback for DDL that the server already applied. It records each completed
migration immediately; a later failure records `installation_failed` plus a
bounded audit event and leaves the package non-loadable. Recovery requires
`--resume-failed`, a new dry run and plan digest, and all exact apply
confirmations. Successful installation ends `installed_disabled` and never
includes `addon.php`.

The separate Owner-authorized enablement preflight remains CLI-only and
read-only. It never includes package PHP and has no apply mode. Its activation
gate evaluator supports only two constrained profiles: a registration-only
service with no component, and a default public component with no service.
Both exclude migrations, settings, routes, jobs, public or administrator
assets, administrator tools, adapters, and outbound hosts. The component
profile clears theme compatibility only because core owns its escaped default
renderer. Every richer surface fails closed with explicit theme, settings, or
live-data evidence. The package registrar remains unexecuted during preflight.
`enableReady`, state mutation, and runtime loading remain false there. The
separate CLI-only Owner enable command requires exact plan and backup
confirmations, revalidates under the database-wide lifecycle lock and
per-client package lock, validates the fixed registrar, and commits the state
compare-and-swap plus bounded audit fact in one transaction. It accepts no
richer package surface.

The separate CLI-only Owner disable command is also dry-run first. It requires
the exact `addons.disable` capability, current `enabled` package and registry
evidence, a deterministic plan, a nonzero verified-backup SHA-256, and exact
enabled-state confirmations. Under the same lifecycle and package locks it
rechecks every enabled package and refuses the transition when one declares
the target as a required dependency. It never includes `addon.php`, runs a
migration, removes code, or deletes settings, media, or package data. The
`enabled` to `installed_disabled` compare-and-swap and
`addon.disable.completed` audit fact commit in one transaction. Later request
bootstrap excludes the disabled package.

No web endpoint consumes the installer, enable, or disable command. Component
dispatch is limited to the bounded core-rendered contract described below.
Service, administrator-tool, adapter, and route dispatch, upgrades, uninstall,
purge, and client business packages require separate reviewed implementations
with backup, dependency, live-data, and rollback or recovery gates.

### Add-On Runtime Registration Contract

The runtime-registration helper is connected only to the front-controller page
request bootstrap, public or authenticated, not to a lifecycle apply command.
It may execute only the fixed
`addon.php` entry point of an already validated first-party package recorded
as enabled in the current client database.

- Core rechecks the real path, symlink boundary, and declared `addon.php`
  checksum immediately before inclusion.
- The entry point must return one registrar callable. Neither inclusion nor
  registrar invocation may emit output, and the registrar must return null.
- The registrar can bind only identifiers declared by the validated manifest,
  and every declared runtime identifier must bind exactly once.
- Required enabled dependencies load before dependents.
- All enabled registry evidence and namespace ownership is checked before the
  first package executes.
- Missing code, checksum drift, undeclared or duplicate registration, output,
  and incomplete registration fail closed.

This remains trusted in-process PHP, not a sandbox. The current self-test
executes only temporary fixtures outside the starter. Uninstalled and disabled
packages never execute. Current enabled packages register into a request-local
lookup context. Core may invoke an enabled manifest-declared component only
through its fixed text view model and escaped default renderer; malformed
values, emitted output, handler exceptions, and output-buffer tampering fail
closed to static fallback content. Service, administrator-tool, adapter, and
route handlers remain lookup-only. The clean starter contains no package
directory or enabled state. The implemented enable command accepts only the
constrained registration-only service and default public component profiles.
The implemented disable command is non-executing and data-retaining for any
current enabled package with no enabled dependent. Settings, package assets,
migrations, live data, recovery, and every richer enablement gate remain
separate work.

## Multi-User Authorization

Administrator component and utility selections are now server-side authorization rules, not presentation-only settings.

- `AdminComponents` controls content creation, rendering of edit controls, updates, deletes, ordering, feature assignment, content uploads, and bulk-tool record selection.
- Group components resolve the exact child subtype through `RED_C_Form.FormType` or `RED_C_Gallery.GalleryType`. A user assigned Video cannot operate Gallery or Banner records.
- `AdminTools` controls both the render and write endpoints for each utility tool.
- Webmaster is the assignable site-manager role. Layout, navigation, sections, categories, subcategories, advanced settings, and administrator-account management are denied to Guest accounts at the endpoint. Legacy `superadmin` database values remain recognized as managers for compatibility but cannot be newly assigned.
- Add User and Edit User expose an allowlisted `AdminType` selector with only Guest and Webmaster. The signed-in manager cannot change their own role, and the final manager cannot be changed to Guest.
- Submitted component changes and subtype changes are reauthorized before a write. Mismatched parent/child component inserts are rejected.
- Authorization failures return HTTP 403 `no` and write a minimal denial entry to the server error log.

The shared contracts live in:

- `includes/bootstrap.php`
- `includes/admin_authorization_helpers.php`

## Failed Login Throttling

`database/migrations/2026-07-12-login-attempt-throttling.sql` adds `RED_Login_Attempts`, and `includes/login_throttle_helpers.php` applies the policy from `bin/login.php`.

- Only failed administrator login attempts are stored here. Successful login history is intentionally not stored by the minimal activity-audit batch; its initial scope is successful Administrator Users mutations only.
- Stored fields are a lowercase/trimmed username SHA-256 digest, the packed client network address supplied by `REMOTE_ADDR`, and the failure timestamp. Passwords, password hashes, submitted usernames, session IDs, CSRF tokens, and content are not stored.
- The application deliberately ignores client-supplied `X-Forwarded-For`. A reverse proxy must normalize the trusted client address at the web-server boundary before PHP if per-client throttling is required behind that proxy.
- Within a rolling 15-minute window, a new login is temporarily blocked after five failures for the same username/client pair, 15 failures for the same username across clients, or 30 failures from one client across usernames.
- Blocked and failed requests preserve the existing generic HTTP 200 `no` response so account existence and lock state are not exposed through a new response contract.
- A successful login clears failures for that normalized username. It does not erase failures for other usernames from the same client and does not invalidate already-authenticated sessions.
- Failed attempts older than 24 hours are removed in indexed batches of up to 500 during login traffic. The table is InnoDB so rollback behavior remains available.

## Administrator Activity Audit

`database/migrations/2026-07-12-administrator-activity-audit.sql` adds
`RED_Admin_Activity_Log`, and `includes/admin_audit_helpers.php` writes only
explicitly allowlisted administrator events.

- Events are successful `administrator.created`, `administrator.updated`, and
  `administrator.deleted` operations plus the server-local
  `administrator.owner_bootstrapped` event.
- Each row contains the event name, numeric actor administrator ID, target type, numeric target record ID, and timestamp. No foreign key is used so a deletion event remains attributable after the target account is removed.
- The table does not contain usernames, aliases, emails, IP addresses, passwords or password hashes, session IDs, CSRF tokens, request bodies, component/tool permission lists, or content bodies.
- The administrator mutation and its audit insertion share one InnoDB transaction. If audit persistence fails, the user mutation rolls back and the existing endpoint returns `no`.
- Events older than 180 days are removed during audited writes in indexed batches of up to 500. Production retention requirements should be reviewed before deployment if a longer legal or operational history is required.
- Login/logout history and general content, layout, navigation, upload, and tool actions are outside this minimal first scope. Expand coverage only through separately reviewed, allowlisted event batches.

## Next Security Work

- Review whether additional allowlisted activity categories are needed after the repeatable acceptance suite is established; do not add request payload logging.
- Optionally replace immediate account/password creation with single-use, expiring email invitations.
- Rotate real production credentials and confirm production PHP/MySQL versions before deployment.

The active milestone order is maintained in `docs/ROADMAP.md`.
