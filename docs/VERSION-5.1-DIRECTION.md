# RED-CMS 5.1 Direction

Status: implementation in progress. Per-page SEO compatibility, non-executing
add-on trust validation, persisted Owner authorization, per-client
registry/migration-ledger storage, read-only reconciliation, and guarded
server-local installation into a disabled state are implemented. Package
enablement/runtime, upgrades, disable/uninstall/purge, member access,
publishing, payment, and integration controls remain inactive.

## Product Goal

Version 5.1 should first close the per-page SEO metadata compatibility gap
blocking the isolated Adriana launch. Later work may extend the 5.0 authoring
foundation with member access, formal publishing operations, clearer
accountability, and optional integrations. RED-CMS should remain a reusable
core that can be adapted to different client types through separately
installed components, services, and provider adapters. New capabilities
should be modular, permission-aware, migration-backed, auditable, and
removable without breaking public content.

## 1. Per-Page SEO Metadata Compatibility

The first implementation milestone is a generic, compatibility-preserving SEO
model for public routes:

- Store an exact SEO title independently from the visible page title.
- Support canonical, robots, Open Graph, X/Twitter, and typed JSON-LD data.
- Generate safe fallback values without requiring duplicate editor entry.
- Keep new storage nullable so upgraded installations retain existing output
  until an override is populated.
- Report imported, derived, skipped, invalid, and non-representable metadata.
- Validate generic fixtures and all 28 Adriana routes in the separate client
  installation without copying client data into the starter.

The complete evidence, field model, fallback rules, migration requirements,
and acceptance criteria are in
`docs/SEO-METADATA-COMPATIBILITY-REPORT.md`.

## 2. Members, Paid Access, And Protected Content

Private Sections and protected downloads should use a dedicated Member Access / Protected Content package with member identity, sessions, entitlements, and route enforcement. They must never rely on the administrator account table, a client-side hidden folder, or the stored `AccessLevel` value alone. A visitor may register, sign in, receive a manual entitlement, or purchase access through a payment adapter.

- Enforce access before protected content is queried or rendered.
- Keep public administrators and public members in separate identity stores.
- Grant access only from verified server-side payment events.
- Support time-limited, revocable, and renewable entitlements.
- Keep payment credentials outside RED-CMS.
- Begin with PayPal sandbox support; add Nequi after merchant onboarding and certified server-side callback testing.

This use of protected content is different from a public listing directory. The
detailed security and delivery model is in `docs/MEMBER-ACCESS-DIRECTION.md`.

## 3. Roles And Permissions

Replace the current component-access model with composable roles and scoped permissions while preserving a protected owner account.

Suggested starting roles:

- Owner
- Administrator
- Publisher
- Editor
- Contributor
- Member-support or billing operator

Permissions should describe actions such as view, create, edit own, edit any, review, approve, publish, manage users, manage payments, manage themes, and install tools. Scopes may later restrict access by Section or site.

The first additive role slice is now implemented specifically for the future
add-on lifecycle. `RED_Admin_Roles` and `RED_Admin_Capabilities` remain empty
after migration, preserving every legacy account. An explicit server-local
bootstrap may assign one protected Owner and the six fixed add-on lifecycle
capabilities in one audited transaction. Login and protected requests refresh
those grants from the current client database. Broader Administrator,
Publisher, Editor, Contributor, and scoped-content permissions remain future
work.

## 4. Publishing Workflow

Introduce an explicit lifecycle:

`Draft → In review → Approved → Scheduled/Published`

Publishing state should be independent from version history. Every transition needs permission checks, an actor, timestamp, optional note, and a stable revision reference. Rejection should return content to Draft without destroying the review record. Existing installations should continue to behave as immediate-publish sites until workflow is deliberately enabled.

## 5. Notifications And Reminders

Create an internal notification center before adding external channels. Useful events include review requests, approvals, rejections, scheduled publication, approaching expiration, failed publication, payment exceptions, and assigned follow-up work.

- Store notifications in RED-CMS with read/unread and resolved states.
- Allow per-user preferences and quiet periods.
- Deliver email only through a configured queue or transport.
- Deduplicate retryable events and keep delivery failures visible.

## 6. Ownership And Change Accountability

Content should record a responsible owner separately from the administrator who performed the most recent change. Version history and the administrator activity log already provide useful foundations, but 5.1 should make ownership, assignment, and change attribution visible in the workspace.

Each important record should answer:

- Who owns this content?
- Who last changed it?
- What changed?
- Which revision was reviewed and published?
- Who approved it?
- When is follow-up due?

## 7. Controlled Add-Ons And Social Publishing APIs

Add a controlled extension catalog rather than arbitrary uploaded PHP. A
package may provide placeable components, business services, administrator
tools, or external-provider adapters. It should declare its identifier,
version, compatibility range, permissions, settings schema, migrations,
background jobs, outbound hosts, dependencies, and uninstall behavior.

Optional future package examples are Store Lite, Events Calendar,
Appointments, Donations, and Restaurant Ordering, in that priority order if
separately approved. They are not core features or required Version 5.1
deliverables. Member Access / Protected Content is a separate cross-cutting
package required before private content is enabled. The full boundary is
defined in `docs/ADD-ON-CONTRACT.md`.

Social publishing should be an optional adapter layer:

- Connect accounts through provider-supported OAuth.
- Store tokens encrypted and outside public content.
- Use provider APIs and queues rather than browser automation.
- Preview the exact outgoing text and media.
- Require an authorized confirmation before publishing.
- Record provider response ids, failures, retries, and the initiating user.

Initial research can cover major providers, but implementation should begin with one well-supported API and a reusable adapter contract.

The first implementation should use trusted filesystem-deployed first-party
packages. Package discovery must not execute code, and the administrator must
not upload arbitrary PHP. Installation and activation remain separate,
owner-authorized actions scoped to one client database.

The read-only trust foundation now validates a closed `addon.json`, fixed
vendor/package paths, compatibility ranges, dependencies, reserved routes,
settings, outbound hosts, and an exact SHA-256 package inventory. It rejects
unsafe or incomplete packages without executing `addon.php`. Empty generic
installation and immutable migration-ledger tables now exist per client
database, and read-only reconciliation reports package identity drift, pending
or changed migrations, missing code, and unavailable runtime state. Owner
authorization is persisted per client database, but no account is promoted
automatically.

The first lifecycle consumer is a server-local install command gated by the
exact persisted Owner `addons.install` capability. It dry-runs a deterministic
plan, requires exact target/plan/backup/disabled-state confirmations, rechecks
trust and required enabled dependencies under a database-scoped advisory lock,
applies checksum-verified `RED_Addon_*` migration SQL, records immutable
migration evidence and bounded audit events, and finishes
`installed_disabled`. It never includes `addon.php`. Because MySQL DDL can
commit implicitly, partial failures remain visible as `installation_failed`
with an explicit resumable ledger rather than a false rollback claim. No
lifecycle UI, enabled-state transition, runtime loader, upgrade, disable,
uninstall, or purge command exists.

The next lifecycle boundary is a separate server-local, read-only enablement
preflight gated by the exact persisted Owner `addons.enable` capability. It
accepts only an exact current `installed_disabled` package, reconciles the full
package registry, binds currently enabled dependency/package identities, and
reports provided-capability, route-id, and route-method ownership conflicts in
one deterministic plan. It has no apply mode, performs no database or audit
write, and never includes `addon.php`. The plan always reports activation,
state mutation, and runtime loading unavailable until the theme, settings,
live-data, runtime-registration, atomic transition, and rollback contracts are
implemented in later reviewed batches.

## Suggested Delivery Order

1. Implement the generic per-page SEO storage, editor, rendering, fallback,
   validation, and migration-reporting contracts.
2. Pass the SEO acceptance suite using generic clean-starter fixtures.
3. Import and verify the 28 Adriana routes in its separate installation and
   database.
4. Complete Adriana launch readiness without copying its theme, data, media,
   metadata, or settings into the starter.

Each phase requires its own migration, rollback path, relevant authorization
tests, disposable-database acceptance coverage, and desktop/mobile
administrator verification.

After the SEO launch gate, later tracks can be approved independently: roles
and publishing workflow, Member Access, notifications, social adapters, or the
add-on platform. The five example vertical packages remain optional. If that
track is approved, its order is Store Lite, Events Calendar, Appointments,
Donations, and Restaurant Ordering. If private folders are scheduled for
activation, Member Access must pass its route-enforcement and leakage gates
before the administrator exposes an operational private setting.
