# RED-CMS 5.0

RED-CMS is a lightweight PHP and MySQL content management system for structured, template-driven websites. Version 5.0 modernizes the legacy application while preserving its public URLs, existing database table names, and compatibility-first deployment model.

The current release adds a consistent administrator workspace, standard theme packages, visual page structures, reusable layouts, content version history, safer database migrations, and repeatable acceptance testing.

## Release Status

RED-CMS 5.0 Bonsai and Milestone 5 are complete on `main`. The release
checkpoint was merged through [pull request #2](https://github.com/orojas01-glitch/redcms/pull/2)
on July 25, 2026.

Version 5.1 development includes per-page SEO compatibility with nullable
overrides, generated fallbacks, and constrained typed JSON-LD; non-executing
add-on trust validation; persisted Owner authorization; and per-client package
registry/migration-ledger storage with read-only drift reporting. A
server-local Owner-authorized installer can apply reviewed, checksum-verified
package migrations and always records the package as `installed_disabled`; it
never executes package PHP. A separate read-only Owner-authorized preflight can
inspect that disabled package's dependency, capability, and route readiness
without changing state or loading code. Front-controller page requests now bootstrap
only already-recorded `enabled` packages whose complete registry, dependency,
namespace, and integrity evidence remains current; the clean starter has none.
The existing `RED_Articles` placement parent now stores the full manifest
component id, reviewed package migrations may add only an exact foreign key to
its numeric `RecordID`, and public add-on dispatch verifies that persisted
parent against the enabled request-local owner. Package fields and business
records remain in separately installed package tables.
Manifest Version 1 can now validate optional data-only component editor
schemas with fixed field types, bounds, and declared permissions without
executing package code. Core can also normalize and validate submitted scalar
values against that schema, then render a display-only set of escaped,
accessible administrator controls from either an empty state or the validator's
exact result. The renderer opens no form, supplies no Save action, loads no
package or package data, and writes no database. Packages that declare an
editor now also have a fresh database-backed permission-decision prerequisite:
each fixed operation resolves to its exact manifest permission, and only that
per-client administrator grant passes. Owner and lifecycle grants do not imply
package access. The decision grants nothing and writes nothing. Packages remain
blocked from enablement. An enabled disposable fixture can now register one
exact package data loader per declared editor; core requires the view grant,
the persisted parent/runtime owner match, contained execution, and schema-valid
returned values before exposing a state hash. No operational form, component
create, history UI/restore execution, or delete runner exists. A separate activation-blocked
helper can now apply an existing package record update only after the exact
view and edit grants, current state hash, locked placement parent, enabled
runtime ownership, declared InnoDB tables, contained writer execution, and
reloaded postcondition all pass. It opens no endpoint or form and does not
create parent records, restore, delete, or activate a package. Successful
changes now atomically retain core-owned baseline and saved snapshots in the
current client database. A separate read-only helper can list a bounded,
integrity-validated timeline and produce a deterministic restore plan only
after current view/restore grants, ownership, state, and target evidence pass;
no history UI or restore runner is exposed.
Fresh isolated Adriana JSON-LD
verification and hosted Schema.org validation pass; production deployment
remains separate. Service, route, adapter, and administrator-tool dispatch,
upgrade, uninstall/purge, payment, member access, editorial workflow,
notifications, the broader role model, and social publishing integrations are
not active features.

## Highlights

- Polished, responsive administrator workspace
- Article, Banner, Form, FTP, Gallery, Other, and Video authoring
- Parent-backed Sections, Categories, and Subcategories
- Three-level top navigation
- Direct drag-and-drop component positioning
- Reusable Layout Builder with desktop and mobile maps
- Non-destructive content version history and restoration
- Local, provider-independent TinyMCE compatibility editor
- Standard theme contract with validation, preview, activation, and rollback
- Prepared database operations, CSRF enforcement, scoped permissions, and transactional writes
- Migration ledger, guarded backup/restore tools, and disposable acceptance testing
- Per-page canonical, Open Graph, X/Twitter, and constrained typed JSON-LD metadata
- Read-only add-on manifest, path, compatibility, dependency, and integrity validation
- Per-client Owner role and exact future add-on lifecycle capability grants
- Empty per-client add-on installation/migration registries with fail-closed reconciliation
- Owner-authorized server-local package installation that remains disabled and unloaded
- Deterministic read-only enablement preflight with dependency, namespace, and constrained activation-gate reporting
- Owner-authorized atomic enablement for constrained registration-only service,
  core-rendered default public component, and combined default-component plus
  registration-only-service profiles
- Owner-authorized atomic disablement with enabled-dependent refusal and no
  package execution or data deletion
- Fail-closed request bootstrap and lookup context for already-enabled first-party packages
- Generic package-owned component parent relationship with read-only,
  fail-closed persisted-placement resolution
- Non-executing bounded component editor-schema validation and normalized lookup
- Fail-closed component editor value normalization with no package execution or writes
- Core-owned display-only component editor controls with escaped fixed markup
- Fresh exact component-editor package-permission decisions with no implicit Owner access
- Bounded enabled-package component data loading with validated values and a core-owned state hash
- Transactional existing-record package updates with stale-state refusal and rollback proof
- Immutable per-client package-value revision snapshots committed with updates
- Read-only validated revision history and deterministic restore preflight

## Portable Starter Distribution

This repository is the clean, reusable RED-CMS distribution. It ships with the
`starter-reference` theme as the default public theme and keeps
`legacy-bootstrap` only as the hard recovery renderer.

Client themes, client media, and client databases are intentionally excluded.
Site-specific installations must be backed up and distributed separately so a
clean release can never overwrite retained production content.

## Local Development

The verified local environment uses:

- PHP 8.5.8 through FrankenPHP
- MySQL 8.4 LTS
- MySQL at `127.0.0.1:3307`
- Portable starter at `http://127.0.0.1:8055/`
- Starter administrator at `http://127.0.0.1:8055/admin/`

From the repository root:

```bash
scripts/dev-mysql-status.sh
scripts/dev-mysql-start.sh
scripts/dev-php-server.sh
```

Check service state first and start only services that are stopped. Local credentials belong in the ignored `includes/config.local.php`; never commit that file.

Detailed setup notes:

- [Clean installation](INSTALL.md)
- [Local PHP runtime](docs/LOCAL-DEV-PHP.md)
- [Local database](docs/LOCAL-DEV-DATABASE.md)
- [Database migrations](docs/DATABASE-MIGRATIONS.md)

## Verification

Verify that the tracked package contains only portable starter defaults:

```bash
php scripts/clean-starter-boundary-self-test.php
```

Run PHP syntax checks:

```bash
scripts/dev-php-lint.sh
```

Run the theme and administrator contract suite:

```bash
php scripts/theme-contract-self-test.php
```

Run the non-executing add-on trust gate and isolated runtime-contract check:

```bash
php scripts/addon-trust-self-test.php
php scripts/addon-component-editor-self-test.php
php scripts/addon-component-editor-renderer-self-test.php
php scripts/addon-runtime-self-test.php
php scripts/addon-validate.php --all
php scripts/admin-addon-owner.php --status
php scripts/addon-registry-status.php --all
php scripts/admin-addon-install.php --package=vendor.package --actor-admin=ID
php scripts/admin-addon-enable-preflight.php --package=vendor.package --actor-admin=ID
php scripts/admin-addon-enable.php --package=vendor.package --actor-admin=ID
php scripts/admin-addon-disable.php --package=vendor.package --actor-admin=ID
```

The install command is a dry run by default. Apply requires the exact database,
package, version, plan digest, SHA-256 from a separately verified backup, and
`installed_disabled` confirmations printed by the dry run. Package files are
deployed separately per client; the clean starter intentionally contains no
`addons/` directory. The enablement preflight is always read-only: it has no
apply mode, keeps `enableReady` false because it does not validate the
registrar, does not change the package's `installed_disabled` state, and
does not execute package PHP. It can identify a registration-only service,
a default public component, or a default public component with
registration-only services as declaratively eligible for later transition
validation. All three profiles exclude migrations, settings, routes, jobs,
public or administrator assets, administrator tools, adapters, and outbound
hosts. Core's escaped default component renderer is the complete
theme-compatibility contract for either component profile. Services are
registered into the request-local lookup context but are not automatically
invoked. The separate enable command is also dry-run first. It accepts only
those three constrained profiles and requires exact database, package,
version, plan, backup SHA-256, and installed-disabled confirmations before it
validates the fixed registrar and atomically records `enabled` plus its bounded
audit fact. Packages with any richer surface remain blocked behind their
explicit theme, settings, or live-data contract. The disable command is
likewise CLI-only and dry-run first. It requires the exact Owner
`addons.disable` capability, current
enabled package evidence, plan and nonzero backup checksums, and
`enabled`-state confirmation. Enable and disable transitions share one
database-wide lifecycle lock. Disablement refuses an enabled dependent, never
includes package PHP or runs migrations, and atomically returns the package to
`installed_disabled` with one bounded audit fact. Package code, migration
evidence, settings, and data remain in place, while later request bootstrap no
longer loads the disabled package. The
runtime-contract self-test executes only a temporary
first-party fixture outside the starter. It rechecks the fixed `addon.php`
checksum, requires exact manifest registration, orders required dependencies
first, and rejects output or registration ambiguity. The database-backed
acceptance suite additionally proves that request bootstrap ignores
uninstalled and disabled packages, loads only exact current enabled packages,
performs no registry write, and fails before execution on drift, missing code,
or disabled dependencies. The clean starter contains no package directory or
enabled package state.

Run the complete guarded acceptance lifecycle:

```bash
scripts/dev-acceptance.sh
```

The acceptance runner creates a uniquely named temporary database, refuses the configured primary database, exercises migrations and representative CMS operations, and removes only its exact temporary database, grant, server, media, and fixture artifacts.
It runs the clean-starter boundary check before creating any disposable
database.

## Documentation

- [Administrator Manual Introduction](docs/ADMIN-MANUAL-INTRODUCTION.md)
- [Roadmap](docs/ROADMAP.md)
- [Theme Author Guide](docs/THEME-AUTHOR-GUIDE.md)
- [Theme Activation Readiness](docs/THEME-ACTIVATION-READINESS.md)
- [Acceptance Suite](docs/ACCEPTANCE-SUITE.md)
- [Operational Form Boundary](docs/OPERATIONAL-FORM-BOUNDARY.md)
- [Member Access Direction](docs/MEMBER-ACCESS-DIRECTION.md)
- [Version 5.1 Add-On Contract](docs/ADD-ON-CONTRACT.md)
- [Store Lite Direction](docs/STORE-LITE-DIRECTION.md)
- [Version 5.1 Direction](docs/VERSION-5.1-DIRECTION.md)
- [Security Notes](docs/SECURITY.md)

## Database And Release Safety

- Back up a retained database before migrations or release work.
- Test migrations first against a disposable restored copy.
- Never edit an applied migration.
- Preserve public URL and table-name compatibility unless a separate migration explicitly approves a change.
- Keep every client database, media archive, and rollback point outside the clean starter release.
- Review and merge release branches through pull requests; do not publish directly from an unverified dirty worktree.

## License

RED-CMS source headers identify the project as MIT-licensed. Bundled third-party libraries retain their own license terms, including the local TinyMCE compatibility editor.
