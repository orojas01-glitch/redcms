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
- Optional component editor metadata is data-only: it may declare fixed field
  types, bounds, labels, and already-requested permissions, but no table,
  column, class, callback, template, SQL, or persistence handler.
- Submitted component editor values pass a core-owned closed-schema validator
  before any future package handler can receive them. Unknown/nested values,
  invalid encodings or controls, non-canonical numbers, values outside bounds,
  and malformed choice, URL, email, date, datetime, or media references return
  no normalized payload.
- The display-only administrator renderer accepts only an empty state or the
  validator's exact closed result. It maps fixed field types to core-owned
  namespaced controls, escapes every manifest label, help string, option, and
  value, and renders only core-owned error messages. It emits no form, submit
  action, script, style, package template, or rejected raw value.
- Component-editor authorization resolves only the six fixed operations to
  permissions already declared by the validated manifest, then performs a
  fresh exact, case-sensitive lookup in the current client's administrator
  capability table. Package access is not inherited from Owner, Webmaster,
  legacy Superadmin, add-on lifecycle grants, or another package permission.
  The decision writes no grant, role, package, or audit state and is necessary
  but not sufficient for a future protected write endpoint.
- Component-editor data loading is available only from the exact enabled
  runtime owner after that view decision and the persisted numeric placement
  parent agree. Each declared editor has one registrar-bound loader. Core
  contains output and failures, rejects foreign ownership, disabled state,
  drift, and invalid returned fields, and revalidates the complete value object
  before exposing it with a core-owned state hash. The helper opens no endpoint
  and core performs no package, content, authorization, lifecycle, or audit
  write. The trusted first-party loader contract is read-only; it is not a PHP
  sandbox for untrusted package code.
- Existing package-record updates require one registrar-bound writer owned by
  the same enabled package. The writer declares one to eight package-owned
  `RED_Addon_*` transaction tables; core requires those tables and the locked
  placement parent to be InnoDB, requires current view and edit grants plus the
  exact state hash, supplies only normalized values, contains callback output
  and failures, and reloads the values before commit. Stale state, drift,
  revocation, disabled ownership, unsupported tables, false returns, and
  incomplete writes fail closed with rollback. Core records normalized
  baseline and saved snapshots in `RED_Addon_Component_Revisions` inside the
  same transaction; revision insertion failure rolls back the package write,
  and unchanged submissions create no revision. This trusted first-party
  callback is not a PHP sandbox and must not issue transaction controls, DDL,
  or writes outside its declared package tables. Core exposes no operational
  form or endpoint and adds no create, revision history UI, restore, delete,
  or activation path.
- Read-only revision history requires the exact view grant and enabled binding,
  validates every stored snapshot, and returns bounded metadata without values.
  Restore preflight separately requires the exact restore grant, current state
  hash, and one exact integrity-valid target revision before returning a
  deterministic plan hash. Neither helper invokes the writer or applies a
  restore.
- The core-owned revision-history renderer accepts only that bounded,
  value-free metadata and a current state hash whose value matches the newest
  entry. It revalidates exact keys, types, operations, hashes, uniqueness, and
  newest-first ordering; escapes displayed actor and timestamp metadata; and
  exposes no stored package values or hashes. Empty, stale, reordered,
  malformed, duplicate, or value-bearing input fails closed. Its status text
  never substitutes for restore preflight, and it emits no form, button, link,
  package markup, authorization lookup, package execution, or write.
- Component deletion planning requires the exact delete grant before any
  package loader can execute, then reuses the exact view-authorized inactive
  parent boundary. It requires caller-supplied current parent/package hashes,
  the latest validated immutable package revision, exact enabled runtime
  component/loader/deleter ownership, and declared package-owned InnoDB tables.
  The deterministic plan contains no package values. Preflight never invokes
  the deleter, opens a transaction, deletes data, writes revision/audit state,
  or exposes an endpoint or control.
- Atomic component deletion is activation-blocked and requires that exact plan
  again under the shared lifecycle/theme and enabled-binding locks. Core first
  records explicit-actor `delete` snapshots in both immutable ledgers, contains
  the registrar-bound deleter, verifies every declared package table has no
  matching row, removes SEO metadata, and deletes only the exact inactive
  hidden parent. Callback output/failure, partial deletion, stale evidence,
  transaction loss, ledger failure, or any postcondition failure rolls back
  everything. The ledgers remain after success; no endpoint, control, audit
  event, media deletion, uninstall/purge, public placement, or activation is
  authorized.
- Atomic restore execution is a separate activation-blocked helper. It locks
  the exact enabled placement parent, re-runs the restore preflight, matches
  the caller's current-state and plan hashes, invokes only the registered
  writer with the integrity-validated target values, reloads the package state,
  and commits one immutable `restore` snapshot linked to the source revision.
  Revoked view/restore grants, stale or substituted plans, binding drift,
  callback/output/buffer failures, postcondition mismatch, lost transactions,
  and revision-ledger failure roll back. The helper exposes no endpoint,
  restore UI action, audit workflow, package activation, or parent/delete mutation.
- Component creation preflight is separately read-only. An optional
  registrar-bound creator must belong to the exact enabled manifest owner and
  declare only package-owned transaction tables. Core requires the exact
  create grant, component and loader ownership, InnoDB support, an unused
  numeric record id with no parent/revision/SEO evidence, a valid active-theme
  layout, closed title/layout/language metadata, and normalized package values.
  The resulting hash fixes the parent as inactive, hidden, and unrouted. The
  preflight never invokes the creator or loader, reserves no identifier, opens
  no transaction, and writes no state. Creator PHP remains trusted
  first-party code for the separate atomic runner, not a sandboxed preflight
  hook.
- Atomic component creation revalidates the exact plan under the shared add-on
  lifecycle lock, theme-contract lock, and enabled-installation row lock. Core
  inserts only the inactive hidden parent, passes the creator normalized values
  and bounded identity context, reloads through the exact registered loader,
  and commits only after both parent and package postconditions match. The
  parent, package row, core `create` revision, and package `baseline` revision
  share one transaction. Caller-owned transactions, stale plans, callback
  output/exceptions/buffer changes/false returns, partial writes, postcondition
  mismatch, and either ledger failure fail closed. The callback is still
  trusted in-process PHP and must not issue transaction controls, DDL, or
  writes outside declared package tables.
- Parent metadata uses a separate core-owned activation-blocked boundary.
  Read-only state requires the exact view grant, enabled manifest/runtime and
  persisted binding, a closed inactive hidden unrouted shell, a valid package
  loader result, and a current matching core revision. The atomic writer
  checks the exact edit grant before package loading, rejects caller-owned
  transactions, requires a caller state hash, and rechecks the complete state
  under lifecycle, theme, installation, and parent locks. Only title,
  active-theme layout, and language can change. The full remaining parent row
  and package state are postconditions, and one explicit-actor core `save`
  revision shares the transaction. Identical values add no revision; stale,
  revoked, public/placed, transaction, postcondition, and ledger failures roll
  back. No endpoint, public placement, activation, delete control, audit event,
  or package-value write is exposed; deletion remains available only through
  the separate exact-plan atomic helper.
- Public-placement planning is separately read-only. It requires the exact
  publish grant before package loading and then the complete view-authorized
  inactive parent, enabled binding, package-state, and current revision
  evidence. Core accepts only numeric source/target ids plus bounded position
  and order values, derives one unique active `Article` route, requires exact
  language agreement, validates hierarchy and the active-theme position
  contract, and hashes the actor, package, component, both states, and closed
  placement values. It performs no activation, update, package write, audit,
  endpoint, or transaction; the atomic runner remains a separate gate.
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
Migration `2026-07-31-admin-package-permission-capacity.sql` expands only the
capability name column from 64 to 160 characters so it matches the existing
Manifest Version 1 permission limit. It adds no role or grant and leaves every
client's existing authorization rows unchanged.

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
  package-owned `RED_Addon_*` tables. A package table may declare an exact
  foreign key to the core-owned numeric `RED_Articles(RecordID)` placement
  parent; this is the only permitted core-table reference.

The SQL guard refuses oversized/binary files, explicit transaction controls,
database/user/privilege/plugin/routine/trigger/event changes, file I/O, system
schemas, core or registry writes, alternate `RED_Articles` column references,
every other core-table reference, and obvious unnamespaced table writes. It is
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
gate evaluator supports only three constrained profiles: a registration-only
service, a default public component, or a default public component combined
with registration-only services. All exclude migrations, settings, routes,
jobs, public or administrator assets, administrator tools, adapters, and
outbound hosts. Either component profile clears theme compatibility only
because core owns its escaped default renderer. Every richer surface fails
closed with explicit theme, settings, or live-data evidence. The package
registrar remains unexecuted during preflight.
Packages declaring `componentEditors` fail closed with
`component_editor_contract_required`; schema/value validation, core rendering,
permission decisions, bounded data loading, the operational existing-record
form, and the activation-blocked update/restore helpers and read-only creation
preflight do not imply complete editor authority or activation support.
Revision restore UI actions, a create or delete endpoint/control, public
placement, and activation remain absent. The
activation-blocked atomic delete runner does not change those gates. The
parent-metadata prerequisite is
activation-blocked and does not complete the editor lifecycle. The renderer is
likewise non-authorizing and non-executing; it
does not open a form, provide a Save action, load a registrar, inspect a
package table, or make the package eligible for activation.
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
constrained registration-only service, default public component, and combined
default-component plus registration-only-service profiles.

For add-on components, `RED_Articles` remains the core-owned placement parent
and stores the complete validated component id. Production public dispatch
also requires a read-only exact match between that numeric parent, its
component id, the persisted enabled installation, and the request-local
runtime owner. Package-specific fields stay in package-owned tables; core does
not select a table, class, callback, or executable loader from database data.
Activation-blocked data loading, existing-record updates, immutable revision
snapshots, read-only inactive creation planning, and permission-enforced
inactive parent-metadata writes are implemented as separate prerequisites.
Atomic inactive creation is also implemented behind the exact plan. The
authenticated existing-record editor accepts only a core record id, current
state hash, CSRF token, and schema values; it derives package/component
ownership again and requires fresh exact view/edit grants before invoking the
atomic writer. Restore UI, atomic public placement/activation, and create/delete
controls/endpoints remain absent. Read-only public-placement planning and
atomic inactive deletion are implemented only
behind an exact preflight plan. The bounded component-editor data loader may
read package-owned values only through the exact enabled registrar owner and
returns nothing unless core validation accepts the complete result.

The implemented disable command is non-executing and data-retaining for any
current enabled package with no enabled dependent. Settings, package assets,
migrations, live data, recovery, and every richer enablement gate remain
separate work.

`docs/STORE-LITE-DIRECTION.md` defines the first optional package's security
boundary. It does not activate commerce. Combined component-plus-service
registration is implemented, but Store Lite remains blocked until generic
revision restore actions, typed service invocation,
routes, administrator tools, settings, assets, and live-data compatibility
pass separate disposable-fixture reviews. The activation-blocked parent
metadata prerequisite, numeric placement-parent
relationship and read-only public binding foundation are implemented.
Client-submitted totals and browser payment redirects are never authoritative,
and Store Lite data must remain package-owned in the current client's database.

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
