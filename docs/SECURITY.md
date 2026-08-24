# RED-CMS Security Notes

Date: 2026-08-07

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
- `RED_PUBLIC_MUTATION_TRUSTED_ORIGIN` (one canonical HTTPS origin for a
  future core-owned public-mutation dispatcher; it is not a secret and must
  not be supplied by `Host`, a request header, or a request-projected server
  value)
- `RED_PUBLIC_MUTATION_INGRESS_PROFILE` (optional exact
  `frankenphp_attested` or `direct_php`; the attested profile remains the
  default and an unknown value disables the endpoint)
- `RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY` (one 64-lowercase-hex, 256-bit secret
  shared only by an optional operator-built Caddy ingress handler and its
  unlinked PHP verifier; never place it in a Caddyfile, `config.local.php`,
  request header, client package, log, or diagnostic output)
- `RED_ADDON_SECRET_REFERENCES` (comma-separated opaque `config:` references;
  contains no secret values)
- `RED_ADDON_SECRET_VALUES_JSON` (server-local JSON object mapping exact opaque
  `config:` references to values; read only from the operating-system
  environment and never returned or logged)

The existing constants `DBHOST`, `DBUSER`, `DBPASS`, and `DBNAME` are preserved so current CMS classes continue to work.

The future public-mutation origin uses the stricter
`red_server_config_value()` path. It accepts only an operating-system
environment value or `config.local.php`; unlike compatibility configuration,
it deliberately does not fall back to `$_ENV` or `$_SERVER`, because some
SAPIs project request headers into server arrays.

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
- Package setting values pass a separate non-executing closed-schema
  validator. Defaults must match the exact declared non-secret type; unknown,
  nested, loosely coerced, malformed, oversized, or missing values return no
  normalized configuration. Secret settings accept only bounded lowercase
  `config:` reference identifiers and are separated from ordinary values;
  core does not resolve or return secret material through this boundary. It
  opens no database, form, permission, package runtime, or activation path.
- Each client database now has empty generic package-setting storage. Ordinary
  JSON scalars and opaque secret-reference identifiers use separate nullable
  columns under an exact package foreign key. A read-only write preflight
  requires an exact trusted filesystem/registry identity, installed-disabled
  or enabled state, complete typed values, explicit package-declared setting
  permissions, fresh binary grants, and a current-state fingerprint. Unknown
  or malformed stored rows, identity drift, lifecycle drift, missing grants,
  or stale inputs fail closed. The boundary writes nothing, resolves no secret,
  invokes no package, renders no form, and changes no lifecycle state.
- The internal atomic setting writer consumes only that exact plan. It refuses
  caller-owned transactions; acquires lifecycle, package, installation, and
  setting-row locks; replaces the complete configuration; reloads the exact
  target hash/count; and commits one value-free `addon.settings.updated`
  audit fact. Ordinary JSON and opaque secret references remain physically
  separated. Exact no-ops add no audit. Any stale plan, drift, write,
  postcondition, audit, or injected failure rolls back all rows and audit state.
- The separate core-only current-setting reader repeats trusted package and
  lifecycle validation, then makes one fresh exact case-sensitive grant
  decision per declared setting. It exposes normalized ordinary stored/default/
  unset values only for the authorized subset; secret-reference entries reveal
  only whether a reference is configured. It returns no `config:` identifier,
  renders no control, exposes no endpoint, writes no state, executes no
  package, resolves no secret, or changes activation eligibility. Storage,
  identity, lifecycle, permission, or value drift returns no partial model.
- Server-local add-on secret availability is a separate non-executing
  attestation. `ADDON_SECRET_REFERENCES` in ignored local configuration and
  `RED_ADDON_SECRET_REFERENCES` may list only bounded lowercase `config:`
  identifiers. Core validates and fingerprints the complete inventory and
  typed configuration, then returns counts and missing setting keys without
  returning any reference identifier or secret value. Malformed or stale
  declarations fail closed. The boundary reads no secret or database, executes
  no package, and grants no activation eligibility.
- Core-internal secret resolution is a separate boundary. The operator must
  provide both the explicit opaque-reference allowlist and a server-local
  value inventory from ignored `ADDON_SECRET_VALUES` or the operating-system
  `RED_ADDON_SECRET_VALUES_JSON` object. Values are bounded, NUL-free, and
  rejected on malformed, nested, list-shaped, unknown, or conflicting input.
  Resolution returns only fixed status while the bytes travel through an
  internal by-reference value; they are never serialized, logged, audited,
  rendered, persisted, or sent to package PHP by this boundary.
- The core-owned secret-reference replacement endpoint accepts only exact
  `config:` identifiers for declared secret settings. It resolves proposed
  references server-locally, preserves ordinary values, and delegates a
  complete configuration to the existing locked atomic settings writer. It
  records only the value-free `secret_reference_replaced` detail, refuses
  stale or unavailable plans, and does not change lifecycle, enablement, or
  package execution. The endpoint remains unlinked while richer runtime
  consumption and the administrator secret-management UI are separately
  reviewed.
- The admitted `registration_only_service_with_secrets` profile is the narrow
  runtime-consumption exception. For a current enabled package with only
  secret-reference settings and no richer surfaces, core resolves that
  package's allowlisted server-local values into a private request object.
  `RED_Addon_Service_Request::secret()` exposes only a value-free status plus
  an internal by-reference value; core rejects any typed service result that
  contains a resolved secret. Missing configuration blocks bootstrap before
  package PHP runs, and no secret bytes enter context snapshots, plans, audits,
  responses, logs, or browser state.
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
- The operational component creator is core-owned. Add Content lists a
  component only after current enabled runtime/manifest/loader/creator
  ownership, declared package tables, and the actor's exact create grant are
  re-derived. The form endpoint requires the authenticated administrator and
  CSRF before accepting the closed component/layout/language request. The
  Create endpoint repeats those checks, accepts only core title metadata plus
  manifest-declared values, allocates an unused positive parent id with bounded
  cryptographic randomness on the server, and delegates only to the atomic
  inactive-creation runner. Browser input cannot select a package, handler,
  permission, table, plan hash, or record id.
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
  evidence. Core accepts only the numeric source id, the closed homepage
  sentinel or a positive Article target id, plus bounded position and order
  values. It derives either the one unique active homepage for the source
  language or one unique active `Article` route, requires exact language
  agreement, validates hierarchy and the active-theme position contract, and
  hashes the actor, package, component, both states, and closed placement
  values. It performs no activation, update, package write, audit, endpoint,
  or transaction.
- Atomic public placement revalidates the exact deterministic plan under the
  shared lifecycle and theme locks plus enabled-installation, source-parent,
  and destination row locks. Article placement may change only its seven
  derived page fields; homepage placement may change only `Sections`,
  `HomePosition`, `HomePositionOrder`, and `Active`. Package data and the
  destination must remain byte-for-byte equivalent to their planned states.
  Success requires one explicit-actor
  core `move` revision and one allowlisted `component.public_placed` audit fact
  containing only the numeric actor and component-parent identifiers. The
  revision, audit row, and seven parent fields share one transaction; an audit
  failure rolls everything back. Caller transactions, reused or stale plans, grant or
  route drift, unsupported positions, transaction loss, postcondition
  mismatch, revision failure, and audit failure roll back.
- The authenticated placement control is core-owned, POST-only, and protected
  by the existing administrator-session and CSRF checks. Browser input contains
  only the numeric component-parent id, numeric destination page/position/order,
  and current parent/package hashes. Core derives package, component, manifest,
  grants, theme positions, target ownership, and the exact plan again on the
  server; no package callback chooses placement or receives request data.
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
command. `addons.upgrade` gates the dry-run-first disabled-package upgrade and
explicit recovery command. The uninstall and purge grants remain dormant.

### Add-On Package Permission Administration

Migration `2026-08-14-addon-package-permission-audit.sql` adds an empty
`RED_Addon_Permission_Activity_Log` to each client database. It records only a
successful grant or revoke event, validated package identity, the exact
manifest permission, actor and target administrator ids, result, and
timestamp. It stores no credential, request body, package setting, or package
code.

Package access is managed by a server-local, dry-run-first command:

```bash
php scripts/admin-addon-permission.php \
  --package=PACKAGE_ID \
  --target-admin=TARGET_ADMIN_ID \
  --actor-owner=OWNER_ADMIN_ID \
  --permission=PACKAGE_PERMISSION \
  --grant \
  --confirm-database=CLIENT_DATABASE \
  --confirm-target-username=EXACT_TARGET_USERNAME \
  --confirm-actor-username=EXACT_OWNER_USERNAME
```

The command discovers an integrity-valid manifest without loading package PHP.
It accepts only an exactly declared permission, rechecks the Owner from the
current client database, and prints a deterministic plan SHA-256 without
writing. Apply requires repeating the exact command with `--apply` and
`--expected-plan=SHA256`. The runner locks the current actor, target, role, and
capability state, refuses stale or repeated plans, and commits the capability
change and its permission-specific audit fact in one transaction. `--revoke`
uses the same boundary, and the next package authorization decision sees the
revocation immediately. This command does not change package lifecycle,
settings, content, or any other client database.

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

### Guarded Add-On Upgrade And Recovery

`scripts/admin-addon-upgrade.php` is CLI-only and dry-run first. It requires a
database-backed Owner with `addons.upgrade`, a separately verified nonzero
backup checksum, exact database/package/current-version/target-version/state
and plan confirmations, and a package that is already `installed_disabled`.
The target must be a strictly higher trusted version of the same package type.
Every recorded migration must remain present with the identical id, path, and
checksum. Existing stored settings must retain their declared key, type, and
secret classification. Required dependencies must remain current and enabled.

Apply holds the database-wide lifecycle lock and package lock. It never
includes `addon.php`, invokes a registrar, or enables runtime code. It records
`upgrading`, applies only pending checksum-revalidated package migrations, and
records each completed migration immediately. Completion atomically replaces
the recorded version/manifest/inventory identity, restores
`installed_disabled`, and records one bounded target-version audit fact.

MySQL DDL is not transactionally reversible. A failed migration or completion
therefore preserves the old recorded package identity, marks the package
`upgrade_failed`, retains exact completed-migration evidence, and remains
non-loadable. Recovery requires `--resume-failed`, a new deterministic plan,
the same exact target package, and all apply confirmations. It runs only the
remaining migrations; if all migration SQL already completed, it performs only
the final registry/audit transaction. Package migrations must consequently be
reviewed as safe to resume after an execution/ledger boundary failure. This is
recovery from a known append-only target, not arbitrary downgrade or rollback.

`scripts/store-lite-upgrade-rehearsal.sh` is the opt-in real-package proof. It
requires the historical external Store Lite 0.1.28 commit and the exact merged
0.1.29 commit, stages both outside the starter, and permits only a bounded
`redcms_sl_upg_*` disposable database. It installs 0.1.28 disabled, stores five
non-secret settings and one order, then forces failure before the second of two
new order-list indexes. The first DDL remains recorded under the old identity
and `upgrade_failed`; runtime registration stays unavailable. Explicit resume
applies only the second index and finishes 0.1.29 disabled. Cleanup revokes the
exact grant, drops the exact database and staged projects, and rechecks the
configured primary boundary.

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

The operational content-package preflight is a separate read-only bridge for
the first richer package profile. It requires exact one-to-one component/editor
and public POST route/mutation coverage, complete administrator tool contracts,
at least one core-owned administrator form, current recorded migrations, fully
configured non-secret settings with no manifest default, declared InnoDB
package tables, and the existing public subject, CSRF, rate-limit, idempotency,
transaction, and response boundaries. Adapters, jobs, assets, outbound hosts,
administrator actions, secret settings, partial coverage, unsupported engines,
and forged counts fail closed. The result exposes only bounded counts and
hashes, leaves `enableReady` and `activationSupported` false, includes no
package entrypoint, executes no registrar, and writes no lifecycle or audit
state. The separate operational enable command can consume this evidence only
after revalidating it under both lifecycle locks.

Operational enablement remains CLI-only, dry-run first, and Owner-authorized.
Apply requires exact database, package, version, plan, backup SHA-256, and
installed-disabled confirmations. After current evidence and package integrity
are rechecked, core includes the trusted entry point and invokes its registrar
without invoking any registered handler. Registration must exactly cover the
manifest-declared components, services, tools, forms, routes, mutations,
loaders, writers, creators, deleters, and state loaders with no missing,
undeclared, or duplicate identifier. Each public-mutation handler's table list
must exactly equal its manifest declaration. Every additional table bound to a
component or form transaction must exist in the current client database as
InnoDB. Only after this validation succeeds does core commit the
`installed_disabled` to `enabled` compare-and-swap and one bounded audit fact
in a single database transaction.

The registrar is trusted in-process PHP, not a sandbox, and it runs before the
state/audit transaction. Database rollback therefore cannot reverse arbitrary
external effects from package PHP. The operational contract requires the entry
point and registrar to remain registration-only: no output, network activity,
handler invocation, migration, business-data mutation, or response/session
change. A package that cannot satisfy that boundary is not eligible for this
profile. Disable remains non-executing and non-destructive; re-enable must
reproduce the same exact registrar evidence while preserving package code,
migration evidence, settings, and business data.

No web endpoint consumes the installer, enable, or disable command. Component
dispatch is limited to the bounded core-rendered contract described below.
Typed internal service and adapter, exact static public-route, display-only
administrator-tool, and non-executing administrator-action-preflight
boundaries are separate reviewed implementations described below. Provider
transport, administrator write actions, upgrades, uninstall, purge, and client
business-package activation still require distinct backup, dependency,
registrar, atomic lifecycle, and rollback or recovery gates.

### Add-On Runtime Registration Contract

The runtime-registration helper is connected to front-controller request
bootstrap for enabled packages and to the separately guarded operational
enable registrar-validation step. It may execute only the fixed
`addon.php` entry point of an already validated first-party package recorded
as enabled in the current client database, except during the locked operational
transition where the exact current installed-disabled evidence is revalidated
before inclusion.

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
through its fixed text view model and escaped default renderer. The view may
add only a bounded closed list of plain-text label/value facts, which core
escapes and renders as semantic description-list markup; package HTML, links,
controls, and templates remain forbidden. Malformed values, emitted output,
handler exceptions, and output-buffer tampering fail closed to static fallback
content. This component path never automatically
invokes service, administrator-tool, administrator-action, adapter, or route
handlers. Services and adapters dispatch only through their separate typed
internal boundaries; routes, display tools, and action preflight can proceed
only through their separate bounded cores. The
clean starter contains no package directory or enabled state. The implemented
enable command accepts only the constrained registration-only service,
secret-capable registration-only service, default public component, and
combined default-component plus registration-only-service profiles. The
secret-capable profile still has no route, component, asset, migration,
administrator, adapter, or outbound-host surface.

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
atomic writer. Restore UI and delete controls/endpoints remain absent. The
core-owned create form/endpoint advertises only a current enabled runtime,
manifest schema, exact loader/creator/table ownership, and fresh create grant;
it allocates the numeric parent identifier on the server and delegates only to
the exact atomic inactive-creation runner before separate protected placement.
Read-only planning, atomic public placement/activation, and atomic inactive
deletion are implemented only
behind an exact preflight plan. The bounded component-editor data loader may
read package-owned values only through the exact enabled registrar owner and
returns nothing unless core validation accepts the complete result.

The separately distributed Store Lite 0.1.11 Product component keeps its
business relationship in a package-owned InnoDB table with restrictive foreign
keys to the numeric core parent and package Product parent. Its create, update,
and delete callbacks require the surrounding core transaction; its runtime
handler performs only a bound-product read, reconstructs and re-normalizes the
complete catalog graph, and returns the closed core fact model. It emits no
markup and cannot mutate cart, order, inventory, payment, lifecycle, or core
placement state.

Store Lite 0.1.12 adds a pure, unregistered cart-line resolver that accepts
only product, integer quantity 1–100, and an optional variant intent. The
caller must separately load the current complete product and installation
currency. The resolver repeats product normalization, requires current
published/available state, resolves one exact sellable simple record or
variable variant, checks tracked stock, and derives SKU, option labels,
integer unit price/total, currency, and product-state SHA-256. It rejects
browser-owned commercial fields and returns no partial line on any refusal.
It opens no database or request/session/cookie state, registers no commerce
service, reserves no inventory, and creates no route, response, cart, order,
checkout, or enablement path.

Store Lite 0.1.13 adds an internal, unregistered cart persistence boundary.
The package accepts only a positive core-issued numeric anonymous-subject
relation, never the raw subject token or cookie. One unique cart belongs to one
relation, but it deliberately has no foreign key to the expiring core subject
table so core rotation/cleanup cannot silently cascade-delete package business
data. An already-active caller-owned InnoDB transaction is mandatory. The
package locks cart and line state plus the current product/selected variant,
requires a fresh expected cart-state hash, re-resolves commercial facts from
server storage, verifies the complete postcondition, and records one value-free
before/after activity fact. It never begins, commits, or rolls back; core must
roll back every non-success result, including late activity failure. Product
and variant references restrict deletion. The class reads no request/session/
cookie state, registers no service, and creates no route, response, inventory
reservation, order, checkout, or enablement path.

The implemented disable command is non-executing and data-retaining for any
current enabled package with no enabled dependent. Migrations, live data,
recovery, and every richer enablement gate remain separate work; the narrow
secret-capable service path does not authorize those surfaces.

`docs/STORE-LITE-DIRECTION.md` defines the first optional package's security
boundary. It does not activate commerce. Combined component-plus-service
registration is implemented, but Store Lite remains blocked until operational
administrator actions, writable routes, protected tool UI/endpoints, settings,
and live-data compatibility pass separate
disposable-fixture reviews. The activation-blocked parent
metadata prerequisite, numeric placement-parent
relationship and read-only public binding foundation are implemented.
Client-submitted totals and browser payment redirects are never authoritative,
and Store Lite data must remain package-owned in the current client's database.
  The atomic setting helper, availability evidence, core-owned secret
  replacement boundary, and narrow secret-capable service profile do not
  supply Store Lite settings UI/endpoints or enablement readiness. The
CSS/JavaScript plan, read-only delivery preflight, static endpoint, and
core-owned document injection are complete, but do not make Store Lite
enablement-ready.

The first namespaced asset helper accepts only a trusted manifest's
package-owned CSS and JavaScript declarations and derives deterministic
checksum-versioned URLs and core-owned tags. It rejects malformed or forged
plans before generating markup. The separate delivery preflight claims only an
exact reserved checksum URL; it revalidates the complete package inventory,
enabled/current registry report, surface plan, package containment without
symlinks, and final file checksum before returning internal evidence.

The core-owned static endpoint reruns that preflight before theme, session, or
add-on runtime bootstrap. It serves only checksum-matching CSS/JavaScript
bytes up to 4 MiB through `GET` or `HEAD`, with fixed immutable-cache and
`nosniff` headers. Noncanonical, disabled, drifted, missing, and oversized
assets receive only a generic `404`; unavailable registry storage receives a
generic `503`; and other methods receive a fixed `405`. No response exposes a
filesystem path, preflight reason, package output, or package PHP execution.

The separate core-owned injection planner re-discovers trusted manifests and
current registry evidence without loading `addon.php`, then revalidates both
asset surfaces for every enabled package. It selects public CSS/JavaScript for
every document and selects administrator CSS/JavaScript only when the existing
signed-in administrator overlay is present. Core emits escaped tags only
immediately before one unambiguous closing `head` or `body` boundary; catalog,
registry, integrity, plan, or document-boundary failure suppresses every
package tag. The planner does not start a session, invoke a registrar, or write
state. The pre-existing request runtime registration remains separate, and the
asset endpoint independently revalidates every later browser asset request.

Internal typed service invocation is implemented without an HTTP or
administrator endpoint. Core requires exact request-local runtime ownership and
manifest declaration, passes a final request object containing only a bounded
operation identifier and JSON-compatible input, and accepts only a final result
object. Floating-point values, objects, resources, unsafe keys/control bytes,
excess depth/nodes/string size, and oversized encoded payloads fail before the
handler. Output, exceptions, output-buffer changes, and malformed results are
contained. No database connection, session, actor authority, route request, or
automatic service call is supplied by this boundary.

Internal typed adapter invocation is also implemented without an HTTP, route,
browser, administrator, database, or provider-transport endpoint. Core requires
exact request-local adapter ownership and manifest declaration, passes a final
request containing only the adapter id, a bounded operation id, JSON-compatible
input, and the owning package's private secret access, then accepts only a final
typed result. Output, exceptions, output-buffer changes, malformed results, and
resolved-secret disclosure fail closed. This boundary does not authorize an
outbound host or inspect future package network code; provider transport,
timeouts, redirects, response validation, persistence, and deployment remain
separately reviewed gates.

Public add-on routes have one deliberately narrow dispatch boundary. Core
matches only an exact unencoded static manifest path owned by the enabled
request-local registrar and permits only public `GET` with
`csrf: not-applicable`. The final request exposes only route id, method, path,
and bounded JSON-compatible query data. The final result is encoded by core as
JSON with `no-store` and `nosniff`; packages cannot emit HTML, redirects,
headers, or raw output. Invalid input, exceptions, buffer changes, malformed or
oversized results, and ownership drift fail closed. Member routes, unsafe
methods, placeholders, administrator routes, sessions, server variables,
uploads, and database connections are not exposed. Current enablement gates
still reject any route-bearing package, so this dispatcher does not activate a
richer package by itself.

P3A-1 permits `authentication: server-signature` only as a non-executing
manifest value for one exact static public `POST` with
`csrf: not-applicable`. It is not equivalent to anonymous browser access and
does not weaken the rule that ordinary unsafe browser methods require CSRF.
Neither the public GET dispatcher nor the browser public-mutation selector can
select this route. No signature parser, raw-body reader, response, handler,
endpoint, registrar execution, secret lookup, database query, or outbound
request is connected. At the P3A-1 checkpoint, the payment-adapter profile
remained activation-blocked until the separate database-bound, ingress,
registrar, and atomic lifecycle gates were implemented and approved.

P3A-2 connects only the database-bound readiness portion. The preflight
requires database-persisted Owner `addons.enable` authority, exact current
installed-disabled adapter registry identity, an exact current enabled Store
Lite dependency in that same selected database, immutable migration-ledger
agreement, and present InnoDB storage for every bounded table touched by the
guarded adapter migrations. It exposes only counts and hashes and performs no
write. Dependency disablement, ledger drift, missing tables, unsupported
engines, catalog drift, and absent Owner authority fail closed. It does not
read settings, resolve either secret reference, include package PHP, register
or expose the declared route, inspect a request, or open a network connection.
At the P3A-2 checkpoint, registrar validation, server-event ingress, and atomic
enablement remained explicit blockers.

P3A-3 closes only the registration-shape blocker. Its production entry point
reruns P3A-2 rather than accepting stale caller evidence, then uses the fixed
integrity-checked registrar loader. The temporary registry must contain exactly
the manifest-declared adapter and server-event route and no other registration.
Core invokes neither callback, exposes no route, publishes no request runtime,
and returns only package/registration identities, counts, and SHA-256 evidence.
Invalid database evidence fails before package PHP loads; output, duplicate,
undeclared, missing, checksum-drifted, or malformed registration fails closed.
The operation changes no lifecycle, migration, setting, authority, or business
row. Because registrar PHP is trusted in-process code rather than a sandbox,
first-party review must still enforce its registration-only no-network and
no-side-effect contract. At the P3A-3 checkpoint, ingress and atomic enablement
remained blocked.

P3A-4 closes only the core ingress-contract blocker. It requires a fresh valid
P3A-3 plan and accepts no ambient request state: callers must supply the exact
`POST` method, exact static path with no query, an explicitly complete and
ordered capture of only `Content-Type`, `Content-Length`, and
`Stripe-Signature`, the exact nonempty raw body, and an integer receipt time.
The RED-CMS body limit is 65,536 bytes; the complete signature header is
treated as opaque bounded verification material. Core does not parse the
provider header, validate its timestamp, resolve the endpoint secret, or parse
JSON. Those steps belong to the future separately distributed adapter and must
occur in that order before database or service access.

Raw body and signature bytes live only in a request-local `WeakMap` behind a
final value-free object. JSON, debug output, object casts, and capture evidence
contain only identities, byte count, receipt time, and SHA-256 metadata;
cloning and serialization are refused. The helper reads no `$_SERVER`,
`php://input`, cookie, or session; invokes no registered callback; emits no
response; accesses no database or network; and exposes no endpoint or route.
Malformed, incomplete, extra, duplicated, reordered, mismatched, empty, or
oversized transport evidence fails closed. Atomic adapter enablement remains
outside this ingress helper.

P3A-5 provides that lifecycle transition only through the separate CLI-only
payment-adapter runner. Its dry run refreshes the P3A-2 database evidence,
executes only the integrity-checked registration-only P3A-3 registrar,
validates the P3A-4 ingress plan, reconstructs every exact typed setting from
the selected client database, and requires value-free availability evidence
for both opaque secret references. It never resolves or returns the referenced
secret bytes. Apply requires exact database, package, version, plan, nonzero
backup checksum, and `installed_disabled` confirmations. Core then repeats the
complete plan under the database-wide lifecycle lock and target-package lock,
uses an exact state compare-and-swap, and commits that state with one bounded
`payment_adapter_enabled` audit fact in the same transaction.

Revoked Owner authority, disabled Store Lite, migration or table drift,
configuration drift, unavailable secret references, registrar or ingress
drift, a stale plan, repeat execution, audit failure, or an injected late
failure all fail closed. No registered adapter or route handler is invoked; no
secret value, request body, provider response, route publication, network
client, or Store Lite order transition enters the runner. P3A is therefore
complete, but no webhook or provider integration exists. P3B remains the next
separate gate.

P3E-7 is a later, separate CLI-only authorization boundary. It accepts only
the exact closed P3E-6 readiness and prepared-envelope objects, revalidates the
enabled trusted `redcms.store-lite-stripe-checkout` 0.1.1 package and enabled
same-database Store Lite dependency, and derives the expected operator subject
from the selected database plus numeric actor. A persisted Owner with the
exact `addons.enable` grant is required on every decision; an opaque caller
hash alone is never authority.

Apply holds the shared lifecycle/package locks, then locks the Owner role,
capability, adapter, and Store Lite rows in one InnoDB transaction. The
existing immutable administrator-action ledger uses a nonce-derived action id
as its unique key and stores only the plan, envelope, Owner-subject, and
authorization-state SHA-256 values with the numeric actor. The matching
value-free audit fact commits atomically. Duplicate nonce insertion, including
under a changed envelope, fails closed; audit failure rolls the reservation
back. The resulting `contactAuthorized=true` is only a pending single-attempt
permission for a future runner. Execution, credential resolution, environment
access, provider contact, Checkout, payment, webhook, Store Lite mutation, and
client deployment all remain false.

P3E-8A is a second CLI-only core boundary and is still non-networking. Dry run
recomputes the complete P3E-7 decision, requires the exact immutable
authorization row, and proves that no claim exists. Apply repeats lifecycle,
package, Owner-role, capability, adapter, and Store Lite locking, then inserts
one separate `provider-contact-attempt-claim` identity in the existing
administrator-action ledger. The claim row binds the original plan,
authorization, Owner subject, authorization state, nonce, actor, and original
expiry through SHA-256 evidence. One `provider_contact_attempt_claimed` audit
fact commits in the same transaction. Duplicate claim, changed or missing
authorization, expiry, revocation, disabled dependency, ledger drift, audit
failure, and active caller transaction fail closed; audit failure rolls the
claim back. A committed claim neither extends the authorization window nor
permits a retry.

The P3E-8A helper contains no secret resolver, environment reader, network
client, provider hostname, package callback, request global, or response body.
It sets `executionPerformed=false` and leaves secret resolution, DNS, TLS,
HTTP, Stripe contact, Checkout, payment, webhook, Store Lite mutation, browser
routes, client activation, and deployment outside the gate. A future P3E-8B
must be approved separately, revalidate the still-active exact claim, resolve
only the owning package's restricted sandbox key at the final boundary, make
at most one exact read-only request, discard the response body, and forbid any
retry or live credential.

P3E-8B2 is the first executing boundary, but only against a sealed in-process
loopback double. It revalidates the still-active exact authorization and claim,
then commits a nonce-derived execution-start row and bounded audit before
registrar execution, secret resolution, or handler invocation. Start-audit
failure rolls back before execution. Once the start commits, the attempt is
permanently spent: missing secret material, registrar/handler failure,
indeterminate output, interruption, result-ledger failure, or outcome-audit
failure cannot authorize replay.

Runtime secret access is restricted to exactly `stripe.secret-key`; the
webhook secret and unrelated values are unavailable. Core validates the
trusted registrar and invokes only the typed
`provider-contact.read-only-probe-loopback` operation with
`contactTarget=loopback`. Output, exceptions, buffer changes, malformed
results, and secret disclosure fail closed. Before persisting a result, core
locks and verifies every bounded field of the immutable start row. The result
ledger and audit contain only closed status facts and hashes—never credential
values, value hashes, response bodies, or response headers. This gate contains
no provider hostname, network client, DNS, TLS, HTTP, Stripe SDK, request
global, public route, payment, webhook, Store Lite mutation, browser, client,
or deployment primitive. Any real sandbox transport remains separately
approved P3E-8B3 work.

P3E-8B3B adds a second exact non-network profile rather than widening the
loopback runner. Authorization accepts only adapter `0.1.1` with provider
transport `disabled`, or adapter `0.1.3` with provider transport
`synthetic_only`. Version `0.1.2`, mode `enabled`, and every other pair are
refused. The synthetic runner uses distinct start/outcome hashes that bind its
operation and `synthetic-package` target while retaining the same immutable
authorization, claim, ledger, audit, expiry, and permanent no-retry rules.

After the committed start, core integrity-checks the `0.1.3` registrar,
resolves only `stripe.secret-key`, and invokes the registered typed synthetic
operation. The adapter explicitly requires the webhook secret to be absent.
Its fixed in-memory evidence is projected to a closed result; core independently
validates the exact keys, status classification, hashes, and false network,
provider, retry, mutation, body, header, and credential flags before result
persistence. Neither the core synthetic helper nor its fixture contains a
provider hostname, network client, DNS, TLS, HTTP, cURL, request global, public
route, payment, webhook, Store Lite mutation, browser, client, or deployment
primitive. Real provider transport remains separately approved P3E-8B3C work.

P3E-8B3C2 adds only the exact core runner for adapter
`0.1.4/provider_read_only`. The historical loopback and synthetic runners
refuse that profile. The provider runner commits a distinct operation- and
target-bound start before package or secret access, resolves exactly
`stripe.secret-key`, leaves the webhook secret unavailable, and invokes only
`provider-contact.read-only-probe-sandbox` with the complete plan and state
hashes. It accepts only the bounded status projection with body, headers,
credential, retry, and mutation absent. If a trusted handler was invoked but
returns missing or malformed output, core conservatively records possible
network/provider contact as indeterminate and never restores the attempt.

The core helper contains no hostname, DNS, TLS, HTTP, cURL, socket, request
global, route, scheduler, browser, payment, webhook, Store Lite mutation,
client, or deployment primitive. Its disposable acceptance package registers
an in-memory handler for the exact operation; therefore this gate performs no
provider contact. One real restricted-key sandbox GET remains separately
approved P3E-8B3C3 work, and no public or automatic caller exists.

P3E-8B3C3A adds the one explicit server-local command allowed to call the
B3C2 runner. Dry run revalidates the exact package, current Owner/capability,
same-database Store Lite, authorization, claim, expiry, and value-free secret
availability without resolving a value, executing the registrar, writing a
start, or contacting a provider. Apply requires every printed state/hash plus
a nonzero backup checksum and literal confirmations for the exact sandbox
operation, target, restricted-test mode, one attempt, no retry, and no
mutation. The command accepts no key value or key option.

The operator command contains one execution call site and no provider hostname,
network primitive, request global, web endpoint, job, scheduler, or automatic
caller. Any non-resource-miss result after a committed start is reported only
as bounded evidence and remains permanently non-retryable. The 40-assertion
command test uses source inspection only and performs no provider contact.
One real restricted-key GET remains separately approved B3C3B work.

P3E-8B3C3B executed that separately authorized boundary exactly once in the
dedicated RED-CMS Stripe sandbox. The restricted key had only Checkout Sessions
Read permission. Core committed the immutable start before secret resolution,
the adapter made one verified HTTPS GET to the fixed synthetic missing-session
target, and the bounded outcome was `404 resource_miss_observed`. Stripe logged
one matching GET and no write request. Response body/header content and the
credential were discarded; no retry or mutation was authorized.

The copied key and process value were cleared, the evidence archive contained
no credential pattern, and disposable database/grant/staged-project/process
cleanup reached exact zero with the configured primary unchanged. After
evidence review, the operator explicitly expired the one-purpose restricted
key. It no longer appears in the active restricted-key list and cannot be
reused for a second request.

P3E-9 must not reinterpret any of that read-only evidence as provider-mutation
authority. Checkout Session creation requires a new operation profile, plan,
authorization, claim, start, result, audit identity, and separately provisioned
least-privilege restricted sandbox key. P3E-9A is non-executing and may touch
none of those runtime surfaces. Later real creation remains limited to one
separately approved POST from a fresh disposable installation with synthetic
data, one exact idempotency relation, a short automatic expiry, no recovery,
no browser navigation, no payment, no webhook, no Store Lite transition, no
automatic retry, and no client deployment. See
[`PAYMENT-ADAPTER-P3E9-SANDBOX-CHECKOUT-CREATION-FRONTIER.md`](PAYMENT-ADAPTER-P3E9-SANDBOX-CHECKOUT-CREATION-FRONTIER.md).

The external P3E-9A source contract preserves that separation. Its future
effect profile names provider mutation and Checkout creation, but its current
execution facts keep authorization, network, provider contact/mutation,
Checkout creation, payment, webhook, browser navigation, order mutation,
retry, and client deployment false. It rejects the read-only credential mode,
accepts no credential value, adds no installable package file, and discards the
validated synthetic Checkout URL. Core does not copy or invoke the class.
P3E-9B must separately prove exact synthetic package/core ownership and
cross-profile refusal before any later authority or transport gate. See
[`PAYMENT-ADAPTER-P3E9A-CONTRACT-ADOPTION.md`](PAYMENT-ADAPTER-P3E9A-CONTRACT-ADOPTION.md).

P3E-9B preserves the pre-authority boundary. Adapter `0.1.5` exposes only a
synthetic operation, and core requires its exact integrity-current package,
closed P3E-9A input, one injected package-owned secret-access object with one
setting, and an unchanged plan hash. The helper has no secret resolver,
environment reader, request global, database primitive, provider hostname, or
network client. Existing typed invocation contains output, exceptions, buffer
drift, malformed results, and secret disclosure. The result gate requires the
Checkout URL, credential, body, headers, network, provider mutation, Checkout
creation, payment, webhook, browser, order mutation, retry, and deployment to
remain absent. P3E-8 authorization does not recognize adapter `0.1.5` or the
new operation. P3E-9C must add new authority rather than widening old evidence.
See
[`PAYMENT-ADAPTER-P3E9B2-SYNTHETIC-CORE-RUNNER.md`](PAYMENT-ADAPTER-P3E9B2-SYNTHETIC-CORE-RUNNER.md).

P3E-9C1 adds that distinct authority without adding execution. It requires a
fresh database-backed Owner, `addons.enable`, exact
`store.orders.manage`, enabled integrity-current adapter `0.1.5`, and enabled
Store Lite `0.1.35`. An at-most-fifteen-minute envelope binds one nonce, one
maximum attempt, and the exact P3E-9B plan/input hashes. Apply repeats the
decision under locks and atomically commits one nonce-derived administrator
action plus one value-free audit fact. Replay, changed evidence, expiry,
revocation, package drift, and audit failure fail closed. The helper resolves
no secret, invokes no package, opens no network, and does not claim or start an
attempt, create a Checkout Session, accept payment, process a webhook, mutate
Store Lite, authorize retry, or deploy a client. See
[`PAYMENT-ADAPTER-P3E9C1-MUTATION-AUTHORIZATION.md`](PAYMENT-ADAPTER-P3E9C1-MUTATION-AUTHORIZATION.md).

P3E-9C2 consumes only one exact persisted P3E-9C1 row. It recomputes the
authorization under fresh Owner, lifecycle, Store Lite permission, package,
expiry, input, and synthetic-plan checks; locks the authorization and missing
claim row; and atomically commits one distinct nonce-derived claim plus one
value-free audit. Missing, changed, tampered, expired, revoked, disabled, or
already-claimed evidence fails closed. Audit failure rolls the claim back.
The helper has no execution start/result, secret resolver, package invocation,
network/provider primitive, Checkout Session, payment, webhook, browser,
Store Lite mutation, retry, live-mode, client, migration, or table path. See
[`PAYMENT-ADAPTER-P3E9C2-MUTATION-CLAIM.md`](PAYMENT-ADAPTER-P3E9C2-MUTATION-CLAIM.md).

P3E-9C3A adds durable execution ordering around only a final core-owned
transport double. It recomputes authorization and verifies the exact claim,
then commits start and audit before invocation. A result or bounded
indeterminate outcome is recorded afterward. Start-audit failure prevents
invocation; any failure after committed start permanently refuses retry. The
helper exposes no arbitrary callable and contains no credential resolver,
package invocation, DNS/TLS/HTTP primitive, Stripe request, real Checkout
creation, payment, webhook, browser, Store Lite mutation, live mode, client,
migration, or table path. See
[`PAYMENT-ADAPTER-P3E9C3A-TRANSPORT-DOUBLE-RUNNER.md`](PAYMENT-ADAPTER-P3E9C3A-TRANSPORT-DOUBLE-RUNNER.md).

P3E-9C3B1 adds only a CLI command around C3A. Default dry run invokes no
double. Apply requires exact database/package/state/hash/nonzero-backup and
no-effect confirmations, constructs one final double, and contains one runner
call site. The command accepts no credential and has no secret, package,
network, shell, request, browser, payment, webhook, Store Lite, client,
migration, or table primitive. See
[`PAYMENT-ADAPTER-P3E9C3B1-OPERATOR-COMMAND.md`](PAYMENT-ADAPTER-P3E9C3B1-OPERATOR-COMMAND.md).

P3E-9C3B2 runs that exact command only against a staged project and fresh
disposable database. Dry run and incomplete-confirmation refusal write no
start/result. One exact apply invokes only the final in-memory double; replay
is refused. Exit-trap cleanup proves database/grant/project removal and an
unchanged configured primary. No credential, package handler, network,
provider mutation, real Checkout Session, payment, webhook, Store Lite
mutation, retry, or client effect occurs. See
[`PAYMENT-ADAPTER-P3E9C3B2-OPERATOR-REHEARSAL.md`](PAYMENT-ADAPTER-P3E9C3B2-OPERATOR-REHEARSAL.md).

P3E-9D0 is pure request planning only. It requires exact mutation-aware
synthetic evidence and emits a bounded form plan for one future Stripe Sandbox
Checkout create POST with deterministic idempotency. It contains no credential
value/header, resolver, database, package invocation, network/shell primitive,
response body/header, real Session, payment, webhook, browser, Store Lite,
retry, live-mode, or client path. See
[`PAYMENT-ADAPTER-P3E9D0-REAL-POST-PREFLIGHT.md`](PAYMENT-ADAPTER-P3E9D0-REAL-POST-PREFLIGHT.md).

P3E-9D2 accepts only corrected external adapter `0.1.7`, exact canonical D0
evidence, and the separately named
`checkout.create-sandbox-real-post-preflight` operation. Core removes the raw
provider form map from the typed input, invokes the integrity-checked handler
with `null` secret access, and accepts only an exact reconstructed name/value
list plus closed false-effect facts. Output, exceptions, malformed data,
changed operation identity, extra fields, and altered hashes fail closed.
Deterministic start/result SHA-256 values are identity contracts only: no
execution is ready or started and no result, authorization, claim, audit, or
database row is recorded. The helper contains no credential access, resolver,
request global, route, CLI, database, transport, provider, payment, Store Lite,
demo/client, or deployment path. See
[`PAYMENT-ADAPTER-P3E9D2-CORE-PREFLIGHT-RUNNER.md`](PAYMENT-ADAPTER-P3E9D2-CORE-PREFLIGHT-RUNNER.md).

P3E-9D3A exposes that contained preflight only through a CLI command. The
default dry run exits before registrar or handler invocation. Apply requires
exact package/source/integrity/request/start identities, the separately named
preflight/provider operations, one attempt, and nine explicit `no` effect
confirmations. The command accepts no actor, database, backup, setting, secret
reference, or credential value; loads no configuration; opens no database;
and contains no request-global, browser bridge, shell, resolver, transport, or
provider primitive. Its one accepted call can return only the non-persistent
D2 result identity with every execution/provider effect false. See
[`PAYMENT-ADAPTER-P3E9D3A-OPERATOR-COMMAND.md`](PAYMENT-ADAPTER-P3E9D3A-OPERATOR-COMMAND.md).

P3E-9D3B rehearses that command only in a guarded temporary project assembled
from exact committed core `f93d191`, adapter `a441588` (`0.1.7`), and Store
Lite `f7de77e` (`0.1.35`) package bytes. It runs PHP with URL streams and common
network functions disabled, removes common secret/proxy environment inputs,
opens no configuration or database, and accepts no credential value or
reference. Dry run exits before registrar/handler invocation; a changed plan
hash is refused; one exact contained apply returns only the D2 non-persistent
identity. Credential scans, staged-tree fingerprints, all three source-tree
fingerprints, and exit-trap cleanup pass with `provider-effects:0` and
`staged-project:0 evidence:0 source-repositories:unchanged
database:not-opened`. See
[`PAYMENT-ADAPTER-P3E9D3B-NO-CONTACT-REHEARSAL.md`](PAYMENT-ADAPTER-P3E9D3B-NO-CONTACT-REHEARSAL.md).

P3E-9D4 is split so provider-capable code, durable execution, operator
exposure, and the first provider effect cannot enter one approval. D4A may add
only the separately distributed adapter operation with offline/loopback
acceptance and no core caller. D4B must create fresh operation/version-bound
authorization, claim, durable start, and bounded result evidence; earlier
mutation rows cannot be reused. D4C adds the dry-run-first CLI command and a
network-disabled no-contact rehearsal but does not run the real apply. D4D is
the first real apply and separately requires key-creation, key-storage, apply,
and key-expiration approvals. Any post-start ambiguity consumes the attempt;
no retry, URL disclosure, payment, webhook, browser, Store Lite mutation,
hosted/client action, or live mode is included. See
[`PAYMENT-ADAPTER-P3E9D4-REAL-CREATION-PLAN.md`](PAYMENT-ADAPTER-P3E9D4-REAL-CREATION-PLAN.md).

D4A is now merged only in the separately distributed adapter at version
`0.1.8` (`562b8a9`). Its production-capable transport was never invoked during
acceptance; sealed doubles and local TLS loopback proved the bounded contract
without DNS, external TLS, HTTP, Stripe, Checkout creation, or payment. The
clean core starter contains no adapter package and still has no operator or
public provider-write caller. D4B must not reinterpret any P3E-9C row or
pre-D4 package identity as authority for the `0.1.8` operation.

P3E-9D4B1 now records only fresh D4 authorization and claim. Both bind the
exact `0.1.8` package, D0/D2 identities, database, order snapshot, Owner,
permissions, and value-free secret-reference availability. New action prefixes
and state hashes prevent every P3E-9C row from satisfying D4 checks.

P3E-9D4B2 now adds the durable core boundary. It commits start and audit before
registrar, secret, or handler access; resolves only the owning package's
`stripe.secret-key`; invokes one integrity-checked typed operation; records a
closed bounded result only after rechecking the start row; and permanently
refuses replay after any committed start. Fault, malformed output, missing
secret, or result-storage ambiguity becomes a consumed indeterminate attempt.
The 29-assertion fixture is final and in-memory with no network primitive. Core
still exposes no CLI, route, public endpoint, browser bridge, retry, or
automatic caller. D4C was implemented through separate command and no-contact
review gates.

P3E-9D4C1 adds only the CLI source contract around D4B2. The command defaults
to dry run, recomputes current D4 authority and identity evidence, requires one
nonzero backup hash and every printed confirmation, and has exactly one apply
call site. Three provider effects must be confirmed `yes`; payment, webhook,
browser, Store Lite mutation, Session expiration, retry, live mode, and client
deployment must be confirmed `no`. The 74-assertion source test executes no
command and proves credential, network, shell, secret/runtime primitive, and
public/browser bridge absence. The later D4C2 slice separately proves
no-contact operation and cleanup without running real apply.

P3E-9D4C2 now proves that no-contact boundary operationally. Exact core,
discovery-valid adapter repair `44ed7b3`, and Store Lite sources are staged in
one guarded temporary project. A runtime probe verifies URL streams and common
cURL/socket functions are unavailable; secret-value and proxy inputs are
removed. Only dry run and incomplete/changed apply refusals execute, leaving
the D4 ledger at authorization/claim only with zero start/result. The fully
confirmed apply set is never invoked. All temporary database, grant, files,
environment inputs, and evidence are removed; source repositories and the
configured disposable primary remain unchanged. D4D remains a separate
explicit authorization gate and was owner-deferred on 2026-08-22. Deferral
does not authorize key reuse, provider contact, a Checkout Session, payment,
webhook, browser flow, Store Lite mutation, hosted-demo change, or deployment.

Colombia C0 selects only a future separately distributed
`redcms.store-lite-wompi` candidate with customer-visible Nequi, `COP`, and
one-time Store Lite guest orders. The candidate must preserve a client-local
non-secret Wompi public-key setting plus distinct opaque secret references for
the private key, integrity key, and event secret. No private, integrity, or
event-secret value may enter the database, clean starter, package defaults,
fixtures, commands, logs, audits, evidence, or browser output. The event secret
is not interchangeable with the private or integrity key. Sandbox and
production hosts, keys, event URLs, provider identities, and data must remain
disjoint.

The existing guest-order email and phone may be read transiently only from the
locked client-local order snapshot for one exact request. The adapter must not
duplicate either value into payment-attempt history, normalized events,
provider references, idempotency material, diagnostics, or audits. Provider
payloads remain in memory only until signature/checksum, reference, amount,
currency, environment, method, and closed status validation finish.

Wompi transaction creation, `PENDING`, browser return, dashboard display,
email receipt, or an unverified lookup/event can never mark an order paid.
Only a verified final `APPROVED` fact may propose the provider-neutral `paid`
event, and Store Lite must still recheck the immutable order reference, exact
integer COP amount, currency, replay identity, current attempt, and order
state. `DECLINED`, `ERROR`, mismatch, malformed content, ambiguous transport,
unknown event properties, invalid checksum, or unavailable reconciliation
fails closed.

Direct Wompi/Nequi initiation returns no hosted checkout URL. Colombia C1 now
adds only a closed provider-neutral `out_of_band_confirmation` result alongside
a canonical `hosted_redirect` value. Existing Stripe-specific results and
helpers are untouched. Out-of-band success requires an opaque
reference, no URL, pending state, and the generic action
`approve_in_provider_app`; mixed, unknown, provider-named, URL-bearing, or
paid-on-initiation results fail closed. The browser must remain on a RED-CMS-
owned pending surface and cannot contact Wompi or receive the provider
reference.

The 55-assertion C1 fixture keeps transient request personal/acceptance data
behind hashes, resolves a bounded provider-supplied property list in declared
order, verifies its checksum within a retry-compatible window, binds that
boundary to the later parsed event, requires event/lookup agreement, and
normalizes only bounded proposed outcomes with order mutation false. Existing
Stripe hosted and generic adapter regressions pass. The helper has no runtime
caller and C1 adds no adapter package, manifest, migration, database row,
credential, route, network request, Wompi transaction, Nequi notification,
payment, order mutation, hosted-demo change, or client deployment.

C2 now adds only an external version `0.1.0` package at commit `e17a371`. Its
request planner accepts hashes/availability only; its PENDING gate emits no
URL; its sealed double is one-use and plan-bound; its event verifier resolves
bounded dynamic properties, uses a 25-hour retry-compatible window, verifies
the checksum, refuses replay, and requires lookup agreement. The typed package
handler supports only a fixed false-effect `contract.probe`; provider
operations and the declared event route refuse. Migrations inventory only
hashed/opaque evidence and exclude personal, credential, body/header, and URL
columns. No migration ran.

Generic discovery/registration accepts the package. C3A now recognizes its
exact id/adapter/dependency/settings/migrations/route/Sandbox-host surface only
as `store_lite_wompi_adapter_v1`. Profile evidence includes ordered, unique,
disjoint setting-key lists, and validation rechecks the exact keys rather than
trusting counts. Every effect and the existing four downstream blockers remain
false/present. All existing Stripe profile, registrar, ingress, synthetic, and
typed regressions pass unchanged.

C3B now proves guarded installation and registration only in a fresh disposable
database. Exact Wompi 0.1.0 remains `installed_disabled`; its two InnoDB tables
are empty, setting rows remain absent, registered handlers are never invoked or
published, and cleanup proves the database and grant absent with the configured
primary unchanged. C3C1 now adds exact Wompi body-signed capture plus atomic
enablement while keeping raw bytes opaque, secret values unresolved, handlers
uninvoked, and runtime routes unpublished. Injected failure rolls lifecycle
state and audit back together. At C3C1 close, C3C2 retained only two-client
enable/disable and isolation proof. C3C2 now proves separate database,
configuration, registration, ingress, and plan hashes; per-database locks;
rollback; and one-client disablement without cross-client change. It invokes no
handler and resolves no secret. Colombia C3 is complete.

C4A now records current official Wompi environments/value families, owner-only
account and terms acts, two acceptance tokens and contract links, explicit
two-contract consent, private-key Bearer transaction creation, COP/Nequi
fields, asynchronous lookup/event finality, dynamic event signatures, and
retry timing. It confirms that raw personal data, acceptance tokens, and secret
values had no wire builder or transport path at C4A close. C4B remained
credential-free/no-contact and had to close those engineering surfaces with strict Sandbox
host/prefix refusal, transient values, contained responses, one-attempt state,
redacted evidence, and transport doubles. C4C through C4E require separate
approval for account access and each provider effect. C5 demo deployment also
requires separate explicit approval. See
[`PAYMENT-ADAPTER-COLOMBIA-C0-DECISION.md`](PAYMENT-ADAPTER-COLOMBIA-C0-DECISION.md)
and
[`PAYMENT-ADAPTER-COLOMBIA-C1-INITIATION-CONTRACT.md`](PAYMENT-ADAPTER-COLOMBIA-C1-INITIATION-CONTRACT.md)
and
[`PAYMENT-ADAPTER-COLOMBIA-C2-PACKAGE.md`](PAYMENT-ADAPTER-COLOMBIA-C2-PACKAGE.md)
and
[`PAYMENT-ADAPTER-COLOMBIA-C3A-CORE-PROFILE.md`](PAYMENT-ADAPTER-COLOMBIA-C3A-CORE-PROFILE.md)
and
[`PAYMENT-ADAPTER-COLOMBIA-C3B-DISPOSABLE-LIFECYCLE.md`](PAYMENT-ADAPTER-COLOMBIA-C3B-DISPOSABLE-LIFECYCLE.md)
and
[`PAYMENT-ADAPTER-COLOMBIA-C3C1-ATOMIC-ENABLEMENT.md`](PAYMENT-ADAPTER-COLOMBIA-C3C1-ATOMIC-ENABLEMENT.md)
and
[`PAYMENT-ADAPTER-COLOMBIA-C3C2-TWO-CLIENT-ISOLATION.md`](PAYMENT-ADAPTER-COLOMBIA-C3C2-TWO-CLIENT-ISOLATION.md)
and
[`PAYMENT-ADAPTER-COLOMBIA-C4A-OFFICIAL-READINESS.md`](PAYMENT-ADAPTER-COLOMBIA-C4A-OFFICIAL-READINESS.md)
and
[`PAYMENT-ADAPTER-COLOMBIA-C4B1-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B1-CORE-ADOPTION.md).

C4B1 adds no core runtime path. External package `0.1.1` receives only a
public-key hash/availability fact for merchant-contract planning and returns
only two Wompi-controlled HTTPS contract links plus token/contract/response/
projection hashes from a pre-contained synthetic response. Raw tokens are not
returned; final path construction, presentation, consent, persistence,
transport, credentials, provider contact, payment, order mutation, and retry
remain false. Core adoption changes exact test/rehearsal pins only and passes
single/two-client disposable proofs with exact cleanup. At C4B1 close, C4B2
could add explicit
contract presentation/consent evidence and a transient server-side integrity/
wire builder, but still no transport or provider contact. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B1-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B1-CORE-ADOPTION.md).

C4B2 adds no core runtime path. External package `0.1.2` fixes exactly two
Wompi-controlled contract links and required controls, binds explicit consent
to presentation/order/subject/contract/token/nonce/time evidence, and
constructs/discards the exact Sandbox authorization/signature/body/request
inside a pure call. Actual signature, personal data and individual hashes,
tokens, credentials, headers, body, and request are not returned/persisted.
Domain-separated and second-order hashes avoid exposing the signature itself.
Core adoption changes only exact package pins/tests and passes disposable
single/two-client cleanup. C4B3 remains no-contact response containment. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B2-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B2-CORE-ADOPTION.md).

C4B3 adds no core runtime path. External package `0.1.3` strictly contains
already-captured synthetic create/lookup response arrays bound to C4B2 wire
and create evidence. It validates and discards documented personal/provider
detail, returns only bounded transaction facts, hashes, and discarded field
names, and treats even APPROVED as a proposed outcome. Payment verification,
event agreement, payment/order/provider mutation, and retry remain false. Core
adoption changes only exact package pins/tests and passes disposable single/
two-client cleanup. C4B4 remains no-contact one-attempt authority/state. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B3-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B3-CORE-ADOPTION.md).

C4B4A adds no core runtime path. External package `0.1.4` binds hash-only
client/database/actor/time authority to exact C2/C4B2 evidence, prepares only
the first claim, and projects C4B3 observations. Its valid result must keep
`claimPersisted`, `replayProtectionActive`, and `executionAuthorized` false;
provider/payment/order/retry effects also remain false. Core adoption changes
only exact package pins/tests and passes disposable single/two-client cleanup.
C4B4B remains no-contact durable claim/replay protection. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B4A-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4A-CORE-ADOPTION.md).

C4B4B uses the existing immutable administrator-action ledger, not a new
migration. Core validates exact C4B4A self-fingerprinted authorization/claim
evidence and revalidates the current client database, Owner, `addons.enable`,
exact `store.orders.manage` grant/declaration, enabled Wompi/Store Lite
installations, and four setting-reference availability facts. Under lifecycle,
package, actor, capability, installation, setting, and action-row locks, one
transaction records authorization plus claim and two value-free audits. Any
replay or partial failure refuses or rolls back. Successful durability activates
replay protection but keeps execution, secret resolution, network/provider,
payment/order, and retry effects false. C4B4C remains the no-contact sealed-
double runner. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B4B-DURABLE-CLAIM.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4B-DURABLE-CLAIM.md).

C4B4C requires exact durable authorization/claim rows, current authority and
package/setting state, and a hash-only sealed-double request. Under locks, core
commits a nonce-derived start row and audit before invoking only the final core-
owned double. A second transaction records a bounded result and audit. Start-
audit failure rolls back before invocation; any post-start failure permanently
spends the attempt. Fault or malformed output becomes a no-network/no-provider
indeterminate result. Replay refuses before a second call. The helper contains
no package invocation, secret resolver, network primitive, Wompi host, payment/
order mutation, or retry. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B4C-TRANSPORT-DOUBLE.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4C-TRANSPORT-DOUBLE.md).

C4B4D adds one CLI-only command that defaults to a zero-write dry run. Apply
requires exact bounded database/package/state and client/database/actor/order/
plan/wire/authorization/claim/request/start identities, a nonzero backup hash,
one attempt, no retry, network disabled, and explicit provider/transaction/
payment/order denials. It can construct only the final core-owned sealed
double. Its disposable runtime disables URL streams plus common cURL/socket
functions and clears proxy and secret-value environments. Dry run, incomplete
confirmation, one apply, replay refusal, four rows/four audits, empty Wompi
attempt/event tables, exact cleanup, and unchanged source/primary database are
proved. The command contains no credential input, secret resolver, registrar/
handler, network primitive, Wompi host, browser bridge, or arbitrary callable.
C4C remained the first separately owner-authorized provider-contact ladder. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B4D-OPERATOR-REHEARSAL.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4D-OPERATOR-REHEARSAL.md).

C4C1 implements but does not invoke the first read-only Wompi transport.
External package `0.1.5` accepts only a hash-matching `pub_test_` value and one
fixed Sandbox merchant-contract GET. TLS peer/host verification, GET, no
redirect/proxy/auth header, connection/total-time/header/body ceilings, and no
retry are fixed. Raw public key, response body/headers, and acceptance tokens
are cleared before the typed result. Production prefixes/host, private/
integrity/event secret use, provider mutation, transaction, payment, event,
order mutation, and retry are absent. Core adoption invokes only the sealed
no-network double in a fresh database and cleans all state. C4C2 must add the
Owner/database/evidence CLI and no-contact rehearsal before C4C3 may perform
one confirmed real GET. See
[`PAYMENT-ADAPTER-COLOMBIA-C4C1-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4C1-CORE-ADOPTION.md).

Display-only add-on administrator tools require an optional closed manifest
contract that maps one provided tool to one already-requested permission and
the fixed `read-only` mode. Core resolves the enabled request-local owner and
performs a fresh exact case-sensitive grant lookup in the current client
database. Owner, legacy administrator types, lifecycle capabilities, legacy
tool ids, and unrelated grants provide no access. The POST-only endpoint
requires a validated administrator session and CSRF token.

The trusted handler receives only the tool id and numeric actor record id and
returns only bounded plain text. Core escapes the final view and never accepts
package HTML, links, forms, buttons, scripts, styles, actions, writes, sessions,
request globals, database connections, redirects, or arbitrary headers.
Output, exceptions, malformed/oversized results, and buffer or HTTP-state
changes fail closed. Permission revocation applies on the next catalog or
dispatch lookup. Current enablement gates still reject every tool-bearing
package.

An optional form-target loader is a separate reviewed read-only callback, not
an expansion of that display handler. Core binds it to one declared
schema-bearing form, repeats the exact permission, resolves only that form's
closed runtime settings, and caps each page at 25 records. Its typed result may
contain only unique positive numeric targets, bounded text facts, and one safe
cursor. Core creates the Edit controls and protected POST; package markup,
links, request globals, writes, secrets, transaction control, and caller-chosen
package/form identities remain unavailable. Output, exceptions, response-state
changes, invalid targets, runtime-setting drift, and permission revocation fail
closed.

The Store Lite browser rehearsal may record an enabled installation only in a
uniquely named disposable schema after staging the separately distributed
package outside the clean starter. That fixture is acceptance-only: it does not
call or weaken the Owner enablement runner, does not authorize a retained
client installation, and must remove the exact server, schema, scoped grant,
and staged package while proving the configured primary fingerprint is
unchanged.

Operational administrator forms have a separate declaration and non-executing
planning boundary. A closed `adminToolFormContracts` entry binds one provided
tool to a unique form id, declared package permission, `POST`, required CSRF
policy, fixed `application/json`, and a body limit no larger than 256 KiB.
Executable fields, identity collisions, undeclared tools, ungranted
permissions, alternate methods/encodings, weakened CSRF, and invalid body
bounds fail manifest validation without loading package PHP.

An optional closed form `create` declaration contains only a bounded label and
description and requires a non-empty closed `fields` schema. It reuses the
exact form permission and remains data-only discovery evidence: the declaration
itself creates no callback, record allocation, or write authority. Executable
or unknown metadata fails before package PHP is loaded.

An enabled package that declares form creation must register one exact initial
value loader and one exact creator; the creator is constrained to one through
eight declared package tables. Missing, duplicate, undeclared, empty-table, or
reserved-table registration fails bootstrap. Registration exposes no execution
path by itself: no provider is invoked, no transaction begins, and no request or
browser surface is added.

Draft initial values use a separate typed result and validation mode. The full
closed field graph, scalar types, options, collection limits, node ceiling, and
body-size limit still apply; only required scalar emptiness is relaxed before
the author has entered data. Normal edit/save validation stays strict. Draft
state is bound to package, tool, form, contract, runtime settings, and values
without accepting or fabricating a record id. This type definition invokes no
registered loader and performs no authorization or database access.

The dedicated initial-value runner performs the same exact enabled owner,
fresh binary form-permission, manifest-contract, and runtime-setting checks as
current-value loading before invoking the initial loader. Output, exceptions,
buffer drift, HTTP-state drift, malformed results, and configuration changes
are contained or refused. It loads no current target and exposes no actor to
package code. It does not resolve or invoke the creator, open a transaction,
consume CSRF, read request globals, or create an endpoint.

Create preparation accepts a separate canonical target-free JSON body. Edit
state and numeric target ids are rejected rather than translated. Core checks
the body limit before loading the current initial draft, compares exact draft
state, and then applies strict completed-value validation. The resulting opaque
plan is actor, permission, contract, configuration, initial-state, and values
bound. No package or table selector enters the body, and the validation helper
contains no creator lookup, record allocation, transaction, audit, or endpoint.

Atomic creation requires the exact opaque preflight plan and serializes with
the add-on lifecycle, package, and enabled installation. Only declared
package-owned InnoDB tables enter the transaction. The creator returns one
typed positive record id; core never accepts a caller-selected id. Core reloads
that id through the exact current-value owner and compares the full normalized
graph before a value-free `addon.form.created` audit fact may commit. Provider
output, exception, buffer/HTTP drift, malformed results, partial or wrong
writes, drift, audit failure, and commit failure roll back both package and
audit state. The runner reads no request global.

The operational browser bridge keeps draft opening and creation separate from
edit/save. Core renders the Add control only when the exact enabled package owns
the target, current-value, initial-value, and creator registrations. The draft
endpoint accepts only target-free tool/form identity after administrator
authentication and header CSRF. The JSON Create endpoint authenticates and
consumes header CSRF before body I/O, accepts only the canonical target-free
submission, and delegates to the atomic runner. A positive target id is returned
only after package postconditions and both package/core activity facts commit;
responses contain no submitted values, plan hashes, or draft-state evidence.

The read-only form preflight requires the exact enabled request-local tool
owner and a fresh case-sensitive grant, then returns only bounded metadata and
deterministic contract/plan hashes. Owner and lifecycle access do not imply the
form permission. It reads no body or request/session globals, consumes no CSRF,
invokes no package callback, renders no HTML, opens no transaction, writes no
state, and creates no endpoint. The declaration is policy evidence, not a token
check or activation path.

Optional administrator-form field metadata is also closed and non-executable.
Core accepts only the existing scalar field vocabulary plus collections capped
at 128 rows, two collection levels, 32 fields per row, and 200 total declared
fields. This admits bounded product options and variants without admitting
package HTML, templates, conditions, JavaScript, callbacks, or an arbitrary
schema language. A pure core renderer escapes labels/help/options and emits
only disabled controls and collection templates. A separate current-value
loader may now provide one complete typed value graph for a positive numeric
target after the same exact enabled ownership and fresh case-sensitive
permission checks. The registrar binds one loader to each schema-bearing form
id; core passes only the database connection plus a final request containing
tool, form, and target—no actor or session data. Core contains output and
HTTP-state changes, rejects missing, extra, malformed, oversized, or
schema-drifted values, and binds the normalized graph to
package/tool/form/target/contract SHA-256 evidence before the renderer may
display it. The renderer still adds no names, editable or submit controls,
request body, CSRF operation, endpoint, or write path.

Form runtime settings are core-derived from the exact enabled tool/form owner
and the form's closed `runtimeSettings` declaration. Only configured,
non-secret, type-valid values with no manifest value default enter one final
immutable request object for that form's loader and writer. Missing or drifted
configuration refuses before provider invocation. An opaque configuration hash
is included in form-state and writer-plan evidence so changing configuration
invalidates stale edits. Package selection, arbitrary setting lookup, raw JSON,
secret references, endpoints, and setting writes are not exposed by this path.

The separate protected validation adapter is core-owned and remains unlinked
from that renderer. Its endpoint requires POST and calls the authenticated
administrator plus header-CSRF guard before opening the body stream. Only exact
`application/json`, a canonical decimal content length, the 256 KiB global
ceiling, and a canonical closed JSON root are admitted. After decoding, core
repeats the fresh form grant, applies the manifest body limit before provider
invocation, reloads current values through the exact registrar owner, refuses a
stale state SHA-256, and validates every submitted nested value. The resulting
opaque plan hash binds actor, package, tool, form, target, permission, contract,
current state, and submitted-values evidence. Public output contains only a
generic validated result or bounded refusal; it exposes no values or hashes.
That endpoint has no writer invocation, transaction, package mutation, Save
control, Store Lite provider, or Store Lite data.

Administrator-form persistence is a separate internal boundary. A package may
optionally bind one `registerAdminToolFormWriter()` callback only to its own
schema-bearing form and must declare one to eight package-owned InnoDB tables.
Core rejects undeclared or duplicate form writers, reserved/core tables,
noncanonical table names, missing exact loader/writer ownership, non-InnoDB
storage, and caller-owned transactions before mutation. Its write preflight
contains no values and binds the validation plan, package version, sorted table
set, actor, target, permission, contract, current state, and submitted-values
hash into a deterministic plan.

The internal runner acquires the database-scoped lifecycle and package locks,
locks the enabled installation row, repeats the full validation/current-value
preparation, and requires the exact write plan before invoking one immutable
typed writer request. Package output, exceptions, buffer drift, HTTP-state
changes, false returns, permission revocation, stale/replayed state, version or
contract drift, incomplete/wrong postconditions, transaction loss, and audit
failure fail closed. Core reloads the current values and requires them to equal
the complete normalized submission before the package mutation and one
value-free `addon.form.saved` audit fact commit together. Unchanged values roll
back without writer or audit work. The reviewed first-party writer must not
manage transactions or write outside its declared tables; it is not a database
sandbox. The validation-only endpoint remains disconnected from this runner.

The operational browser bridge is a separate core-owned surface. Its edit
endpoint is POST-only and performs database-backed administrator-session plus
header-CSRF checks before accepting exactly `tool`, `form`, and one canonical
positive `targetRecordId`. It derives package, permission, manifest, contract,
writer, declared tables, and enabled version from the current request runtime;
none is trusted from browser input. Only a complete freshly loaded value graph
may populate escaped core controls. Package HTML, JavaScript, CSS, field names,
actions, and response markup are never accepted.

The Save endpoint repeats method, administrator, and header-CSRF checks before
opening the bounded exact `application/json` body. The controller preserves
integer, boolean, nullable scalar, object, and ordered collection types and
applies the manifest's collection bounds, but server validation and locked
revalidation remain authoritative. Public results contain only `saved`,
`unchanged`, or a bounded refusal—never values, package identity, state or plan
hashes, table names, or audit evidence. A successful Save reloads the editor
instead of trusting client-derived state. The bridge does not select targets,
enable a richer package, or add Store Lite behavior. Target selection, when a
package separately registers it, remains a bounded read-only core-rendered
predecessor to this independently authorized edit bridge.

The loader is reviewed first-party PHP, not a database sandbox. It is required
to be read-only and may query only its package-owned current-client data; it
must not mutate state, manage transactions, expose secrets, or derive access
from the omitted actor. Permission revocation applies before the next loader
invocation. Store Lite remains blocked from activation and no Store Lite
package or table is included in core.

Administrator write actions have a split metadata and internal execution
foundation. An optional closed `adminToolActionContracts` entry maps one
provided tool to one unique action id, explicit package permission, bounded
text metadata, `POST`, `csrf: required`, and fixed
`idempotency: once-per-target`; weaker method, CSRF, or idempotency declarations,
undeclared tools, duplicate actions, ungranted permissions, and executable
metadata fail validation before package PHP is loaded. The enabled registrar
must bind that id exactly once as both an action handler and a read-only target
state loader, and the action declares one to eight package-owned InnoDB tables.

The original metadata preflight remains non-executing: it requires matching
request-local tool/action ownership, a fresh case-sensitive action permission
in the current client database, and one positive integer target record id. It
returns only deterministic contract and metadata-plan hashes; it reads no
package record, starts no transaction, writes no state, invokes no callback,
renders no control, and exposes no endpoint.

The separate internal runner starts its state-aware preflight in a transaction
that always rolls back. It accepts no request/session value and returns no
target state values—only opaque hashes. On a matching plan it serializes with
the lifecycle and package locks, locks the enabled installation, rechecks the
current grant, runtime binding, contract, target state, and no-prior-execution
ledger key, then reserves that key before the action callback runs. It reloads
the target state after callback containment and commits only an exact changed
postcondition together with the immutable per-client ledger row and a
value-free add-on audit fact. Output, exception, output-buffer tampering,
malformed state/result, stale plan, replay, revocation, lifecycle drift,
transaction loss, postcondition mismatch, or audit failure refuses or rolls
back the package mutation, reservation, and audit.

The runner is not a PHP sandbox and is not itself an endpoint. Reviewed
first-party loaders must remain read-only and package callbacks must not
control transactions. The separate core-owned
`admin/bin/run_addon_tool_action.php` endpoint is POST-only and validates the
database-backed administrator session plus CSRF token before it parses an exact
tool/action/positive-target request. It derives and revalidates the plan only
on the server, invokes no package handler directly, and returns only bounded
outcomes with no package, actor, target, plan, or state values. The manifest
CSRF policy is still not a token check. The endpoint is deliberately unlinked:
it adds no administrator form/control or public route. Current enablement gates
continue to reject tool-bearing packages.

## Public Mutation Declaration Boundary

[PUBLIC-MUTATION-BOUNDARY.md](PUBLIC-MUTATION-BOUNDARY.md) records the
security contract for a later optional-package public write path. The optional
closed `publicMutationContracts` manifest field and its value-free
non-executing preflight are implemented; the existing add-on public router
still refuses unsafe methods before package execution.

Each declaration binds one exact static public POST/CSRF route to one unique
mutation id and fixed form encoding, scalar bounds, anonymous-subject,
idempotency, privacy, rate-limit, postcondition, table, audit-category, and
outcome metadata. The validator rejects unknown/executable fields, route
drift, weak policies, duplicate identities, reserved core request names, and
core add-on tables. The preflight returns only deterministic hashes and counts;
it does not load package PHP, read a request/cookie/session/database, issue
tokens, check live table state, start a transaction, or invoke a handler.

An entry may also declare a closed list of one to sixteen `runtimeSettings`
keys. Each must be a package-declared non-secret scalar setting with no
non-null manifest default. The declaration and its preflight remain value-free;
they do not make a setting available to a route, browser, package, or another
client installation.

A separate read-only live-data preflight now uses that trusted declaration only
after the package is current and `installed_disabled`. It reads the one
client's existing migration ledger, declared package-table engines, typed
setting state, opaque secret-reference availability, and exact core
anonymous-subject/CSRF/rate-limit/idempotency/execution storage shape; its plan returns
only counts and SHA-256 evidence, never table names, setting values, references,
or secret material. It remains non-activating and non-executing: it does not
itself issue values, dispatch a request, resolve a secret, load package PHP,
mutate package data, or relax any enablement profile.

The separate internal subject/CSRF foundation is core-owned and client-scoped.
Its two empty generic tables retain only SHA-256 digests of random 256-bit
values, expiration facts, a scope hash, and an opaque subject relation. The
companion empty rate-limit table retains only the opaque subject relation, a
declaration/database SHA-256 scope, fixed window/expiry facts, and bounded
count. The empty idempotency table retains only that subject relation, a
declaration/database SHA-256 scope, a SHA-256 key digest, and creation/expiry
facts; package manifest table declarations cannot claim any of these core
tables. The subject helper returns a
future endpoint's host-only `Secure`, `HttpOnly`, `SameSite=Strict` cookie
descriptor with a 30-minute lifetime, while CSRF values expire after 10 minutes
and are bound to the current client database plus one validated declaration.
It reads no `$_COOKIE`, starts no session, emits no header, logs no raw value,
and gives no raw value to package code. Expired records are removed in bounded
core cleanup. The separate rate helper permits at most 12 requests per 60
seconds per client, declared route, and opaque subject; its standalone claim
owns a short InnoDB transaction, while the transaction runner invokes the same
claim and bounded expired-rate cleanup inside its own transaction. Both reject
unsafe transaction state and fail closed on storage loss. The separate
idempotency helper issues/resolves a 10-minute
256-bit opaque key only for the active subject and exact declaration, stores
only its SHA-256 digest, and refuses issuance inside a caller-owned transaction.
It does not consume a key or record a replay result itself. The separate empty
execution table retains only the idempotency-key relation, keyed HMAC command
and state evidence, a bounded outcome, and completion time; it contains no raw
request, token, package, cart, order, secret, or business value.

The internal core-only transaction runner is not a public endpoint. It accepts
only a typed command and opaque evidence from a later core dispatcher, plus a
current trusted first-party runtime binding. Under lifecycle and package locks
it validates the subject, CSRF, idempotency key, rate budget, and up to sixteen
declared package-owned InnoDB tables, optional declared runtime settings,
server-derived state, replay ledger, and a value-free anonymous audit fact in
one transaction. Replays return a bounded stored outcome; changed
commands are refused. Output, exceptions, malformed results, state drift,
transaction loss, ledger failure, and audit failure roll back or refuse.
The callback receives a core-supplied transaction connection, so this is a
reviewed first-party-PHP boundary rather than a database sandbox; package code
must not commit, roll back, alter buffers, read request globals, or write
outside its declared tables.

For a declared runtime-settings list, core derives exact package/route/mutation
identity from the current enabled binding, locks only that package's current
setting state inside the runner transaction, and passes one immutable typed
object to the matching state loader and handler. It exposes no secret or
secret-reference value, browser input cannot select a setting, and a
coexisting secret setting never enters values or configuration evidence. A
missing, malformed, unconfigured, defaulted, or secret selected value fails
before rate use, replay reservation, or package callback invocation. The opaque
configuration hash participates in declared command evidence, so a
configuration change cannot replay an old idempotency key. No declaration
receives an empty object and requires no configured setting row. Packages must
not query `RED_Addon_Settings` directly. See
[`PUBLIC-MUTATION-RUNTIME-SETTINGS-CONTRACT.md`](PUBLIC-MUTATION-RUNTIME-SETTINGS-CONTRACT.md).

The separate pure core response model maps only the runner's two bounded
outcomes and a fixed future-dispatch refusal vocabulary to exact JSON envelopes.
It supplies no-store, nosniff, content-type, length, and only a fixed
`Allow: POST` method-refusal header; it exposes neither a replay signal nor any
package, route, subject, token, key, state, cart, order, plan, secret, or
internal failure detail. It does not read request/cookie/session state, access
the database, load package code, emit HTTP state, or change lifecycle. The
core-owned dispatcher selects and returns that envelope only after
same-origin, CSRF, scalar-input, server-state, rate/idempotency, and transaction
checks are complete; its only front-controller caller is the separately gated
operational bridge.

The separate pure declared-form decoder accepts only one validated in-memory
manifest declaration and raw canonical URL-encoded package-field bytes. It
returns only sorted typed scalar values or no values, refusing duplicate,
nested, unknown, malformed, noncanonical, out-of-bounds, and oversized input.
It cannot inspect method, path, origin, content metadata, cookies, sessions, or
server globals; access a database, runtime, or package code; issue/verify
identity, CSRF, or idempotency material; claim a route; or emit a response.
Those facts remain exclusive to a later core-owned HTTP dispatcher.

The separate pure public-mutation form UI helper is also not an endpoint. It
accepts only that same closed declaration, a bounded package presentation
model, one core form-instance id, and exact same-subject issued CSRF and
idempotency result shapes. Core derives the static action and permits only
declared hidden identifiers, bounded positive-integer controls, identifier
selects capped at 128 choices, and declared formatted-string text, email,
telephone, or textarea controls; labels and options are escaped into semantic
form markup. Optional select-backed required/visibility conditions are
validated as data, rendered as core-owned attributes, and enforced in the
fixed controller for browser usability. Package validation remains the final
authority for domain-specific conditional semantics.
The internal execution command preserves only that flat validated field map,
including kebab-case keys and explicit optional empty strings; it does not
admit the broader nested service-payload grammar.
The two opaque values appear only in dedicated fetch-controller data
attributes and never as submitted package fields, visible copy, logs, or
package input. A later integration must additionally own `Cache-Control:
no-store`, subject-cookie response composition, expiry/rotation, and script
delivery before rendering this form on a public page. This helper reads no
request/session/cookie/database/package state, emits no output or header, and
is not included by `index.php`.

The core-owned form evidence bootstrap now joins the existing lifecycle,
CSRF, idempotency, and pure form helpers without creating an endpoint. It
validates the complete declaration, instance, label, and package field model
before changing storage; then it ensures or resolves one opaque subject,
issues one declaration/database-scoped CSRF token and idempotency key for that
same subject, and composes the existing form model. An issuance failure
returns no form or cookie descriptor and transactionally removes only the
subject or exact token/key records created by that failed attempt. Cleanup
revalidates the issued cookie-to-subject relation and refuses forged lifecycle
descriptors. The bootstrap accepts an explicit cookie value but reads no PHP
request, cookie, session, or server global; it loads no package code, renders
no HTML, emits no header, and remains absent from `index.php`.

The public component boundary separately accepts one optional data-only
mutation presentation beside its existing title, summary, and facts. The shape
is exact and closed: one valid route id, mutation id, bounded submit label, and
one to sixteen unique presentation fields. Fields are limited to identifiers,
positive integers, selects with at most 128 unique identifier choices, and
bounded text, email, telephone, or textarea descriptions with closed formats.
Core rejects reserved commerce/authority names, extra fields, control
characters, duplicate keys/options, and selections outside the supplied
choices. This is only compatibility evidence for later integration: the
default public renderer ignores the retained presentation and emits no form,
token, script, header, cookie, endpoint, or package-controlled markup.

A bounded collection row may retain one or two distinct presentations of that
same closed shape. Core refuses empty or associative lists, more than two
forms, repeated route/mutation pairs, malformed fields, raw authority, HTML,
or unknown row keys. The default renderer emits the row title and facts in all
contexts. It may additionally compose row controls only when the supported
public-mutation page gate is active and the caller supplied the explicit
database connection. Core revalidates the complete view, derives stable
placement/row/form instance IDs, verifies that the component, route, mutation
handler, and state loader still resolve to the same enabled package, and gives
each accepted form separate CSRF/idempotency evidence under the page's one
request-local subject. Only validated escaped core form HTML is emitted.
Packages provide no HTML, evidence, script, cookie, response behavior, instance
ID, or callback during composition. A disabled gate, malformed complete view,
ownership drift, bootstrap failure, or global form-cap exhaustion fails the
affected control closed without granting package authority.

The core-owned public component form integration then consumes one already
returned and revalidated component model; it never invokes the component,
route, mutation, or state-loader callbacks. Before evidence issuance, current
runtime ownership of the component, route, mutation handler, and state loader
must resolve to the same valid package and manifest. Core derives the form
instance from the numeric placement record, delegates evidence issuance to the
existing compensating bootstrap, and returns only the validated form model,
escaped markup, and cookie lifecycle descriptor. A display-only component
returns no form and changes no evidence state. The helper receives an explicit
connection and optional cookie scalar, reads no request globals, emits no
output/header/cookie, and is not linked from `index.php`; response ownership
and browser dispatch remain mandatory later gates.

The public-mutation browser controller is a fixed static core asset. It accepts
only one same-origin, query-free, fragment-free path;
the exact POST/form encoding; the fixed CSRF and idempotency header names; and
lowercase 64-hex evidence. Initialization copies evidence into one closure and
removes it from DOM attributes. On first submit, it serializes only bounded
identifier/integer controls, captures the canonical form bytes, disables every
mutable control, and never recomputes that command for the same idempotency
key. Only exact-body retry is allowed after a network, `429`, or `503` result.
The controller accepts only the closed core JSON status/body pairs, places
mutation-neutral messages with `textContent`, and schedules only
`window.location.reload()` 750 milliseconds after an accepted or unchanged
response. It never assigns or derives a location, so package/request values
cannot choose a navigation target. It uses no cookie API, browser storage,
logging, dynamic code, HTML sink, redirect, or external URL. `index.php`
delivers it once only after the request-local coordinator has accepted at least
one core-rendered form while the supported endpoint gate is active.

The separate pure HTTP request-envelope normalizer accepts only explicit values
from that later dispatcher. It requires one server-configured canonical HTTPS
origin rather than `Host` input, an exact static POST path, an exact matching
`Origin`, canonical form content metadata, bounded raw body, one opaque subject
cookie, and the fixed `X-RED-CMS-CSRF` plus `Idempotency-Key` headers. Duplicate
critical headers, content/transfer encoding, malformed values, token drift, and
body overflow fail closed before any raw value is returned. It neither reads
PHP request/cookie/session globals nor accesses a database/runtime/package,
issues/resolves tokens, claims a route, emits a response, or changes client
state. The final browser bridge must still prove that it passes the complete
received header list and uses no user-controlled origin configuration.

The separate private static mutation-route selector accepts only a bounded raw
request target plus one already initialized core runtime context. It selects an
exact un-decoded static path only when its public route, mutation handler, and
state-loader ownership all agree. A duplicate candidate or missing binding is
claimed and refused without revealing an owner. It does not read PHP request,
cookie, or session globals; bootstrap a runtime; read package files; invoke a
callback; open a database; issue browser evidence; emit a response; or change
  lifecycle, enablement, Store Lite, or client state. The operational bridge
  may now compose this selector only after all supported-server gates pass.

The separate non-routable server request-facts adapter resolves the future
canonical HTTPS origin only through the stricter server-local configuration
path. It reads only the current `REQUEST_METHOD` and raw `REQUEST_URI`; a
later supported web-server integration must provide raw body bytes plus a
complete ordered capture of fixed security-header families. It refuses associative header maps,
including the generic PHP header API and `HTTP_*` server projections, because
they cannot prove that duplicate critical wire headers were preserved. It has
no body-stream reader, route selection/claim, runtime/bootstrap, database,
cookie/session issuance, package invocation, response emission, front
controller, enablement, Store Lite, or client-state path.

The optional Caddy/FrankenPHP ingress-attestation source is a separate,
operator-built middleware module, not a default server configuration. It
removes `X-RED-Public-Mutation-Capture` and
`X-RED-Public-Mutation-Signature` from every incoming request before PHP can
observe them. Only a bounded `/addons/` `POST` with a known short unencoded
body and non-duplicate fixed security headers can receive newly HMAC-signed
replacement values. The signed payload contains only method, raw target, body
length/hash, and a fixed security-header subset; it never exposes arbitrary
request headers to PHP or package code. The paired PHP helper verifies the
HMAC before reading `php://input`, rechecks current method/target/body facts,
and is consumed by `index.php` only for a reserved candidate after the explicit
endpoint gate passes.

The handler source, Caddyfile placement example, and test command live under
`server-integrations/frankenphp-public-mutation-attestation/`. A stock
FrankenPHP binary cannot load the module dynamically: the operator must build
and deploy a matching custom binary and keep it, its Caddyfile, the per-client
environment key, certificates, and proxy configuration outside the clean
starter. The separate Docker proofs build a temporary matching binary and prove
module registration, Caddyfile adaptation, signed body preservation, spoof
stripping, and duplicate/encoded withholding through the unlinked PHP verifier.
The supported-server rehearsal additionally carries one secret-guarded fixture
request through the verifier, dispatcher, atomic runner, and fixed emitter
against a fresh MySQL database, then removes its temporary database, image,
network, package marker, and context. Neither proof deploys a binary or
configuration for a client. Until the validated profile is followed by actual
per-client deployment and browser-deployment review, invalid
or missing attestation creates
no public route, response, cookie, runtime/package invocation, lifecycle
change, Store Lite behavior, or client state.

The explicit `direct_php` ingress is the shared-host compatibility profile.
It is never inferred from the server brand or selected as a fallback. It
requires the same server-local enable flag and canonical HTTPS origin as the
attested endpoint plus a direct server-owned `HTTPS=on` or `HTTPS=1` fact. It
ignores `Host` and every forwarding value. Before reading `php://input`, it
accepts only one exact POST candidate, the canonical matching `Origin`, fixed
form content type, a decimal body length no greater than 8,192 bytes, one
    valid core subject cookie, and opaque CSRF/idempotency values. Transfer or
  content encoding that remains visible in the PHP projection and alternate
  content metadata projections fail closed. The
actual body length must equal the declared length before the existing request
adapter releases facts to the unchanged subject, CSRF, rate-limit,
idempotency, transaction, and response-owner pipeline.

PHP cannot prove the original order or count of raw duplicate wire headers on
every shared server. This profile therefore accepts only closed projected
values for which common comma/semicolon combination is invalid and relies on
  the front web server to reject HTTP request smuggling or normalize chunk
  framing into one measured PHP body. It is authorized only
for a direct HTTPS web-server-to-PHP path; a TLS-terminating proxy requires a
  separately reviewed adapter and must not spoof `HTTPS`. The disposable real
  Apache 2.4/PHP FastCGI proof confirms the direct profile's canonical request,
  refusal, normalization, and browser projection without authorizing a hosted
  installation.

The separate core-only response emitter accepts only an exact closed response
envelope from the existing fixed core response contract. It rechecks the six
allowed statuses and exact no-store/nosniff JSON headers, refuses once output
has begun, clears prior response headers, and emits only the fixed matching
body bytes. It never accepts a request, reads request/cookie/session globals,
accesses a database or runtime, invokes package code, issues a cookie, selects
or claims a route, or changes lifecycle, enablement, Store Lite, or client
state. It remains an emission primitive; the supported endpoint bridge is its
only front-controller caller.

The core-owned response-owner composer now binds that emitter contract to a
validated non-executing deployment profile and optional lifecycle result. It
can return no cookie line, one fixed issuance/clearance line, or clear-then-set
rotation lines, but it cannot accept arbitrary headers, package/theme response
ownership, a linked-dispatcher profile, cookie-attribute drift, or a response
body containing an opaque cookie token. It calls no output API and reads no
request/global/session, secret, database, filesystem, package, or client state.
The separate emitter remains the only HTTP response-emission primitive;
per-client deployment and browser evidence are still required before enabling
the operational bridge for a client.

The separate pure subject-cookie serializer accepts only the exact
issuer-descriptor shape and derives one fixed future host-only `Set-Cookie`
value. It has a 30-minute `Max-Age`, `Path=/`, `Secure`, `HttpOnly`, and
`SameSite=Strict`, with no `Domain` or `Expires` attribute. It cannot acquire
an unreviewed cross-subdomain scope or expiry behavior through this helper. It
does not itself issue a subject, call `header()` or `setcookie()`, read
request/cookie/session globals, access a database/runtime, invoke package
code, select a route, or change lifecycle, enablement, Store Lite, or client
state. The serializer remains pure; only the separate core-owned cookie emitter
may turn one validated lifecycle into fixed `Set-Cookie` header lines.

The core-owned lifecycle bridge now uses that serializer for transactional
`ensure`, `clear`, and `rotate` operations. It locks and resolves only the
current installation's hash-only subject, refuses malformed rotation sources
and active caller transactions, returns fixed set/clear descriptors without
emitting them, and expires the old subject before committing a distinct
replacement. Expiring the subject also invalidates its CSRF, rate, idempotency,
and execution relations under the existing foreign-key/cleanup rules. The
disposable 18-assertion fixture and supported-server rehearsal prove that a
valid cookie is not reissued, rotation returns one deletion plus one
replacement, the old token and CSRF fail closed, malformed input is safe, and
cleanup leaves no temporary subject state. The request-local page coordinator
may now call `ensure` while rendering accepted core-owned forms, but only after
the endpoint gate is active for the current installation.

The operational endpoint gate is deliberately conjunctive. The server-local
`PUBLIC_MUTATION_ENDPOINT_ENABLED` value must be exactly true or `1`, the
canonical trusted origin must resolve through the existing server-local path,
and one exact ingress profile must be selected. `frankenphp_attested` remains
the default and additionally requires a valid process-environment
`RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY`; `direct_php` requires direct HTTPS and
the fixed projection above. Missing or malformed configuration keeps page forms inactive and makes
a reserved mutation candidate fail closed. The front controller checks a
bounded unencoded `/addons/` candidate before theme or administrator-session
rendering, captures `POST` through only the selected ingress adapter, composes
the static selector and dispatcher, and delegates only a closed response
envelope to the core emitter. An unknown non-`POST` path remains unclaimed; a
selected mutation with the wrong method receives the fixed method refusal.

For normal `GET` rendering, core parses the raw `Cookie` header rather than
PHP's lossy cookie map. Duplicate, malformed, oversized, or control-bearing
subject cookies disable all mutation forms on that page. The coordinator
revalidates its exact request-local state, permits at most 128 forms, ensures or
resolves one subject for the first accepted form, requires every later form to
resolve the same database record, appends only core-rendered markup, delivers
the fixed controller once, and emits only the first validated lifecycle after
document assembly. Package and theme code supply no header name, cookie
attribute, script URL, response status, or response body. The clean starter
keeps the endpoint flag false, and this bridge does not install or enable a
package or deploy server integration for any client.

The non-executing per-client deployment profile is the current core review
boundary. It accepts only a closed operator packet with a separate client
database name and canonical HTTPS origin plus either pinned FrankenPHP/Caddy
attestation facts or pinned Apache/PHP direct-projection facts. Each shape has
an exact server-local trust source and route order; only the attested profile
contains the fixed ingress-key name and operator-owned key rotation. It
requires core—not a package or theme—to own response headers and
browser-cookie descriptors, preserves the fixed host-only cookie policy, and
requires configuration, binary, secret, media, and database isolation outside
the clean starter. Any secret value, request-derived origin/key, version or
route-order drift, cross-subdomain cookie, isolation gap, or activation flag
fails closed. The validator returns only a deterministic non-secret hash; it
does not load the profile, resolve a secret, access a database, or change
deployment, lifecycle, enablement, response, or client state.

The non-executing deployment-review validator binds a second operator packet
to that profile hash. The attested form accepts pinned Caddy/FrankenPHP/TLS/
proxy facts, artifact hashes, process-environment trusted-origin/HMAC sources,
and rotation evidence. The direct form accepts pinned Apache/PHP/SAPI/TLS
facts, hashes of the Apache configuration, runtime, certificate chain, and
request-projection report, plus explicit direct-HTTPS and ignored Host/
forwarding evidence. Both require artifacts outside the starter and bounded
desktop/mobile browser results. It
never accepts a secret value, reads a deployment file, opens a browser session,
or changes a response, cookie, database, package, lifecycle, or client state.
The packet must keep the dispatcher unlinked and must prove zero client-state
change. The local Apache proof can build and validate this direct packet, but
actual hosted per-client deployment and browser capture remain separately
required.

The installation-shaped HTTPS rehearsal is deliberately separate from that
pure validator. It creates a short-lived localhost certificate and two
short-lived process-environment HMAC values only in an external temporary
directory/container. It mounts the certificate read-only, restarts the same
fixture with the replacement key, verifies the previous key is absent from the
new process environment, and never records either secret or the private key in
the review packet. Browser evidence is limited to fixed HTTPS 200 checks with
zero console/network errors, no cookie, no opaque token in the body, and no
client-state change. Cleanup removes the private key, container, image, and
build context; retained evidence stays outside the starter and contains only
non-secret hashes and booleans.

The operational bridge and any later extension must use one static trusted declaration, a
client-scoped opaque anonymous subject, core-owned same-origin CSRF, exact
scalar input validation, server-derived state, privacy-preserving rate and
idempotency enforcement, declared-package-table transaction support, exact
postcondition reload, and only bounded no-store/nosniff responses. It may not
leak cookies, tokens, request bodies, package/actor/cart/order state, secrets,
or payment data. Package eligibility remains separately governed by the
runtime/enablement contracts; the bridge itself does not admit a package,
deploy a supported server, or create Store Lite behavior or client data. A live
dispatcher must additionally prove that its supported
web-server boundary preserves or rejects duplicate critical headers before PHP
and cannot turn any request value into trusted-origin configuration.

## Read-Only Public Utility And Site Search

The `read_only_public_utility` lifecycle profile is a closed exception for
operator-reviewed cross-cutting packages. It admits only exact public `GET`
routes, typed services, package-owned migrations, and immutable public assets.
Components, administrator surfaces, settings/secrets, public mutations, jobs,
adapters, outbound hosts, and admin assets fail the profile before package
execution. Atomic enablement repeats current trust, migration, dependency,
namespace, route, and exact registrar evidence under the existing lifecycle
locks.

The pre-theme front controller classifies only a current registrar-owned route
whose declaration is public, public-authenticated, CSRF not-applicable, and
includes `GET`. This occurs before the public-mutation dispatcher so a read
route cannot be reinterpreted as a mutation. Other package paths continue to
the mutation boundary. Core supplies no session, administrator identity,
request globals, database handle, HTML, redirect, arbitrary header, or upload
surface to the handler.

Site Search `0.1.3` opens a separate short-lived client-local connection and a
read-only transaction, selects only its package-owned derived index, rolls the
transaction back, and closes the connection. Query input is valid UTF-8,
control-free, two to eighty characters, limited to six full-text prefix tokens,
and returns at most eight text-only results. The core route boundary retains
its 32 KiB response ceiling and fixed `no-store`/`nosniff` JSON headers. The
browser uses an explicit mount marker, same-origin credentials, request
cancellation, and DOM `textContent`; package result HTML is never accepted.

The rebuild command is CLI-only, Owner/`addons.enable` gated, dry-run-first,
and exact-plan confirmed. It reads only active, started, unexpired Article
pages beneath an existing active Section/Category/Subcategory hierarchy,
writes only `RED_Addon_SiteSearch_Documents`, and atomically replaces only the
derived `core-article` source. Version 0.1.3 additionally implements
the closed `content.index-sync` service for canonical Article create, update,
delete, restore, and move events. Core invokes it only after commit and passes
only one closed event name plus 1-64 record ids; it supplies no body, actor,
session, request globals, database handle, or client identifier. The package
reloads eligibility and public hierarchy from the client-local database and
atomically replaces or removes only those derived rows. Failure is contained,
logged generically without ids, and cannot roll back the completed CMS write.
Scheduled full rebuild retains current Owner authority and exact
database/package/enabled-state confirmations, refuses manual apply/plan
arguments, and holds one non-blocking client-database advisory lock. It adds no
core scheduler, manifest job, route, setting, secret, or cross-client state.
This repairs missed events, hierarchy-wide changes, start/expiry transitions,
and drift.

Optional Store Lite 0.1.36 owns the only product-table knowledge and returns at
most eight public placement documents per typed-service call. Site Search
validates and deduplicates at most 1,000 placements and atomically replaces the
derived `store-lite-product` rows with core Article rows. It refuses price,
currency, stock, availability value, SKU, variant commercial facts, cart,
order, payment, customer, administrator, setting, secret, and database
identity fields. A missing or disabled provider becomes an empty source and
removes stale Store Lite rows; a malformed or failing enabled provider rolls
the rebuild back. There is still no private/member source, network request, or
deployment.

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

- Only failed administrator login attempts are stored here. Successful login
  history is intentionally not stored by the activity-audit policy; its
  allowlist covers successful Administrator Users mutations, Owner bootstrap,
  and the core-owned public-placement completion fact only.
- Stored fields are a lowercase/trimmed username SHA-256 digest, the packed client network address supplied by `REMOTE_ADDR`, and the failure timestamp. Passwords, password hashes, submitted usernames, session IDs, CSRF tokens, and content are not stored.
- The application deliberately ignores client-supplied `X-Forwarded-For`. A reverse proxy must normalize the trusted client address at the web-server boundary before PHP if per-client throttling is required behind that proxy.
- Within a rolling 15-minute window, a new login is temporarily blocked after five failures for the same username/client pair, 15 failures for the same username across clients, or 30 failures from one client across usernames.
- Blocked and failed requests preserve the existing generic HTTP 200 `no` response so account existence and lock state are not exposed through a new response contract.
- A successful login clears failures for that normalized username. It does not erase failures for other usernames from the same client and does not invalidate already-authenticated sessions.
- Failed attempts older than 24 hours are removed in indexed batches of up to 500 during login traffic. The table is InnoDB so rollback behavior remains available.

## Administrator Activity Audit

`database/migrations/2026-07-12-administrator-activity-audit.sql` adds
`RED_Admin_Activity_Log`, and `includes/admin_audit_helpers.php` writes only
explicitly allowlisted administrator event/target pairs.

- Events are successful `administrator.created`, `administrator.updated`, and
  `administrator.deleted` operations plus the server-local
  `administrator.owner_bootstrapped` event and the core-owned
  `component.public_placed` operation.
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
