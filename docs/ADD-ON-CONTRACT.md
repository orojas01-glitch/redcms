# RED-CMS Add-On Contract

Status: Version 5.1 trust validation and Owner authorization foundations are
implemented. RED-CMS does not install, enable, execute, upgrade, disable,
uninstall, or purge packages through this contract yet.

## Implemented Trust Boundary

The first foundation batch provides:

- a closed data-only `addon.json` schema;
- fixed `addons/vendor/package` discovery beneath a server-owned root;
- path, manifest, RED-CMS/PHP compatibility, route, CSRF, settings, dependency,
  and outbound-host validation;
- an exact SHA-256 inventory for every package file except the self-referential
  manifest;
- required declaration and verification of the fixed `addon.php` entry point
  without including or executing it;
- rejection of traversal, absolute paths, symbolic links, undeclared files,
  missing files, checksum mismatches, dependency cycles, and incompatible
  required dependencies;
- normalized per-client Owner role and exact lifecycle capability grants;
- database-backed login and protected-request authorization refresh;
- one explicit, audited, server-local first-Owner bootstrap with exact target
  confirmations;
- adversarial dependency-free tests and a read-only CLI report.

The clean starter contains no `addons/` package directory. A client or operator
may deploy package files separately later, but discovery alone never installs,
enables, loads, or migrates them. No Guest, Webmaster, or legacy Superadmin
receives package lifecycle authority automatically. An account receives it
only after a client-specific Owner row and exact grants are deliberately
bootstrapped.

## Product Objective

RED-CMS should remain a lightweight reusable core that can be adapted to
different kinds of clients through optional packages. A client installation
receives only the components, services, and integrations it needs.

Core owns only the extension framework: package discovery, validation,
lifecycle, permissions, routing boundaries, and migration controls. Store Lite
and every other business package are separately distributed optional add-ons.
They are not core components, bundled starter features, or automatically
installed or enabled.

The clean starter distribution must remain generic. Client add-on state,
business records, members, orders, appointments, donations, media, settings,
secrets, and database migrations belong only to that client's separate
installation and database.

The current optional package examples, in priority order if their individual
work is later approved, are:

1. Store Lite
2. Events Calendar
3. Appointments
4. Donations
5. Restaurant Ordering

Member Access / Protected Content is a separate cross-cutting package. It is
required before RED-CMS can safely activate private Sections or protected
downloads, but it is not a public listing directory and is not one of the five
business verticals above.

These packages are examples of the kinds of client adaptation the contract
should eventually support. They are not core features or committed Version 5.1
deliverables. Per-page SEO metadata compatibility and the isolated Adriana
launch gate take implementation priority over this add-on track.

## Terms

### Component

A component is an author-placeable content type that can occupy a RED-CMS page
layout position. It owns a bounded editor, validated data, a safe rendering
context, revision behavior, and a public presentation.

Examples include Product, Event, Appointment Service, Donation Campaign, and
Protected Content Gate.

### Service Add-On

A service add-on owns business behavior that cannot safely live in a component
template. It may own database records, administrator workspaces, public
endpoints, background jobs, notifications, and audit events.

Examples include carts and orders, calendar queries, appointment availability,
member entitlements, and donation transactions.

### Integration Adapter

An adapter connects one service capability to an external provider. Provider
credentials, callbacks, retries, and response identifiers belong to the
adapter rather than to a component.

Examples include PayPal checkout, Google Calendar synchronization, and an
email delivery transport.

### Content Package

A content package is a product-facing bundle of one or more components,
services, and optional adapters. It gives an installer one coherent capability
without merging unrelated client verticals.

For example, Store Lite combines a Product component and a commerce service,
then optionally enables a PayPal adapter.

### Public Listing Directory

A directory in the listing sense is a searchable collection of people,
locations, providers, or other public records. It would be a future Listing
component plus a search/filter service.

It is unrelated to private folders or member-only content.

### Member Access / Protected Content

Member Access is an optional identity, session, entitlement, and route-
enforcement package. It may provide locked registration, login, access-gate,
and secure-download components, but its essential responsibility is to deny
unauthorized access before protected content is queried or rendered.

## Package Boundary

The proposed first-party package structure is:

```text
addons/
  redcms/
    store-lite/
      addon.json
      addon.php
      src/
      admin/
      public/
      templates/
      assets/
      migrations/
      tests/
```

- `addon.json` is data only. It cannot contain PHP, SQL text, callbacks, class
  names selected from the database, or executable template fragments.
- `addon.php` is the one fixed entry point. Core may load it only after the
  package path, manifest, compatibility, integrity, and installation state pass
  validation.
- Package paths must resolve beneath the discovered package root. Absolute
  paths, traversal, symlink escape, and remote includes fail validation.
- The SHA-256 inventory detects undeclared or changed files after deployment,
  but a self-declared checksum does not authenticate a publisher. This first
  filesystem-deployed phase relies on operator-reviewed package provenance;
  signed distribution would be a separate future trust layer.
- Version 5.1 should begin with trusted, separately distributed,
  filesystem-deployed first-party packages. "First-party" describes project
  provenance and maintenance; it does not mean built into core or included as
  an active starter feature.
- Core must not provide arbitrary PHP upload or browser-based package
  extraction.
- PHP add-ons run in the RED-CMS process and are not a security sandbox.
  Manifest validation and signatures establish provenance and compatibility;
  they do not make untrusted PHP safe.

## Manifest Contract

Every package declares:

- Schema version
- Stable namespaced package identifier
- Display name, description, and semantic version
- RED-CMS and PHP compatibility ranges
- Package type and provided capabilities
- Required and optional package dependencies
- Requested administrator permissions
- Components, services, administrator tools, and adapters
- Settings schema, including which settings are secret references
- Ordered immutable migrations and their checksums
- Public and administrator route declarations
- Background jobs and retry policy
- Exact outbound network hosts
- Public/admin assets and their loading locations
- Uninstall and data-retention behavior

An illustrative manifest shape is:

```json
{
  "$schema": "https://red-sphere.com/schemas/addon-manifest-v1.json",
  "schemaVersion": 1,
  "id": "redcms.store-lite",
  "name": "RED-CMS Store Lite",
  "description": "Optional product catalog, cart, and order capability.",
  "version": "0.1.0",
  "type": "content-package",
  "compatibility": {
    "cms": ">=5.1 <6.0",
    "php": ">=8.2"
  },
  "provides": {
    "components": ["redcms.store-lite/product"],
    "services": ["commerce.catalog", "commerce.cart", "commerce.orders"],
    "adminTools": ["redcms.store-lite/orders"],
    "adapters": []
  },
  "dependencies": {
    "required": [],
    "optional": [
      {
        "id": "redcms.paypal",
        "version": ">=0.1 <1.0"
      }
    ]
  },
  "permissions": [
    "store.products.manage",
    "store.orders.view",
    "store.orders.manage",
    "store.settings.manage"
  ],
  "settings": [],
  "migrations": [],
  "routes": [],
  "jobs": [],
  "outboundHosts": [],
  "assets": {
    "public": [],
    "admin": []
  },
  "integrity": {
    "entrypoint": "addon.php",
    "files": [
      {
        "path": "addon.php",
        "sha256": "0000000000000000000000000000000000000000000000000000000000000000"
      }
    ]
  },
  "uninstall": {
    "defaultDataAction": "retain",
    "allowExplicitPurge": true
  }
}
```

The zero checksum above is illustrative and must be replaced by the exact
SHA-256 of the deployed `addon.php`.

The exact Version 1 schema is
`docs/addon-manifest.schema.json`; the read-only PHP validation contract is
`includes/addon_manifest_helpers.php`. This remains a trust-inspection contract,
not an active loader contract.

## Core Registry And Execution

Add-on discovery and add-on activation are separate.

1. Core discovers package directories from a fixed server-owned root.
2. Core validates the manifest, package path, compatibility, integrity, and
   dependency graph without executing the package.
3. Installation records the package inventory in the current client database
   and applies only that package's reviewed migrations.
4. The package remains disabled after installation.
5. An authorized owner enables the package only after a compatibility and
   dependency preflight passes.
6. On later requests, core loads only enabled packages whose code and recorded
   integrity still match.

Database rows must never choose an arbitrary file, class, method, template, or
callback. Core invokes fixed interfaces returned by the validated package
entry point.

The existing Article, Form, Gallery, and Other runtime remains intact. Add-on
registration must be additive and must not reinterpret legacy
`RED_Components` rows as executable packages.

## Component Contract

A placeable add-on component must provide:

- A stable namespaced component id
- Human label, description, and administrator icon
- Permission requirements for create, view, edit, delete, publish, and restore
- A validated create/edit field schema
- Server-side normalization and validation
- Transactional persistence
- A parent relationship to the RED-CMS page-placement record
- A bounded public data loader
- A non-executable rendering context
- An accessible default public view
- Content-revision snapshot, restore, export, and import behavior
- Delete/deactivate behavior and dependent-media rules
- Theme compatibility and CSS isolation metadata

The existing `RED_Articles` record may continue to own route, placement,
layout, visibility, and ordering fields. Specialized records should use a
package-owned table with an explicit numeric relationship to the parent
article. Parent and child writes must share one transaction.

Add-on components must not be implemented by adding another hard-coded switch
to `class_content.php`. The add-on registry is the only new dispatcher.

## Theme And CSS Boundary

- Business rules, permissions, database access, forms, endpoints, and secrets
  remain core/add-on responsibilities, never theme responsibilities.
- Every component supplies an accessible default view so a theme does not have
  to implement every possible add-on.
- A theme may style stable namespaced hooks.
- Optional theme overrides require an explicit declared component contract and
  compatibility preflight.
- Public styles must be scoped beneath a namespaced add-on root such as
  `.red-addon--store-lite`.
- Add-ons must not ship unscoped element selectors that can alter the
  authenticated administrator interface.
- Administrator controls remain inside core-owned isolated workspace styles.

## Service And Route Contract

A service add-on may register bounded routes through a core router. It must
declare:

- HTTP methods and authentication requirements
- CSRF policy
- Request and response limits
- Permission requirements
- Rate limits where relevant
- Transaction and idempotency behavior
- Cache and privacy behavior
- Audit events
- External host access

Packages must not create arbitrary root PHP endpoints or edit `index.php` or
`admin/mainnav.php`. Public URLs already owned by a client remain unchanged.
New package endpoints use a reserved core namespace unless a separately
reviewed compatibility route maps an existing URL.

## Data, Migration, And Client Isolation

- Every add-on installation and migration ledger is scoped to one client
  database.
- Add-on tables use stable `RED_Addon_*` names that remain within MySQL's
  identifier limit.
- Applied migration files are immutable and checksum-verified.
- Installation and upgrades run first against a disposable restored database.
- The configured primary database is refused by automated acceptance tooling.
- Add-on media uses a package-owned client media root, not a shared starter
  fixture directory.
- Non-secret settings may be stored per installation. Secrets remain in
  environment variables or server-local ignored configuration.
- Client exports never replace `db-structure.sql`.
- The starter may contain empty generic add-on registry tables after their
  migration is approved, but it contains no enabled client package, client
  setting, or business record.

## Dependencies

- Dependencies use exact package ids and compatible version ranges.
- Circular dependencies fail validation.
- Installing a package does not silently install or enable another package.
- Required dependencies must be installed, compatible, and enabled before the
  dependent package can be enabled.
- Optional adapters remain disabled until explicitly configured.
- A package cannot be disabled or removed while another enabled package
  requires it.

Shared capabilities should be extracted only after a second real package proves
the abstraction. Store Lite must not become a speculative universal business
engine, and later verticals must not duplicate security-critical payment or
identity behavior.

## Permissions And Accountability

Package installation, migration, enablement, disablement, and data purge are
owner-level actions. The Version 5.1 role model must provide composable package
permissions rather than extend the legacy comma-separated component list.

The first authorization slice stores one additive `owner` role in
`RED_Admin_Roles` and individual grants in `RED_Admin_Capabilities`. It does not
reinterpret `AdminType`. The fixed grant vocabulary is `addons.install`,
`addons.enable`, `addons.disable`, `addons.upgrade`, `addons.uninstall`, and
`addons.purge`. Both the Owner role and the exact grant are required. Unknown
database values are ignored, revoked grants disappear on the next protected
session refresh, and Administrator Users cannot demote or delete the protected
Owner.

Each package declares its permissions, but core owns authorization enforcement.
An add-on cannot grant permissions to itself.

Important lifecycle, settings, order, payment, appointment, entitlement, and
donation changes require an actor, timestamp, target identifier, result, and
bounded audit event. Logs must not store secrets, complete request bodies,
payment credentials, passwords, or protected content.

## Lifecycle

The lifecycle is:

`Discovered → Validated → Installed/Disabled → Enabled`

Upgrades, disablement, and uninstall are explicit transitions.

- **Validate:** read-only checks; no package execution or database writes.
- **Install:** apply reviewed migrations and record the installed version;
  remain disabled.
- **Enable:** run dependency, permission, theme, route, settings, and live-data
  preflight, then persist the enabled state atomically.
- **Disable:** stop new execution without deleting package data. Refuse when
  required by another enabled package or when active public assignments would
  become unsafe without an approved fallback.
- **Upgrade:** back up, validate compatibility, test migrations against a
  disposable copy, apply immutable migrations, and verify postconditions.
- **Uninstall:** disable first. Retain data by default.
- **Purge data:** a separate owner-confirmed destructive action with an exact
  table/media inventory and verified backup.

Removing files is an operator deployment action in the first implementation;
the administrator does not delete executable package code.

## Failure Behavior

- Invalid or incompatible packages remain unexecuted.
- Missing code for an enabled package fails closed and produces an
  owner-visible diagnostic.
- Payment, entitlement, appointment, and donation state never changes because
  a public template failed to render.
- Public fallbacks must not expose protected data, executable errors, absolute
  paths, SQL, secrets, or administrator controls.
- Disabling an add-on must not silently delete or rewrite its content.

## Optional Future Content Package Examples

### 1. Store Lite

Store Lite is the first complete proof of the extension contract.

Initial scope:

- Product component
- Simple product catalog
- One cart per visitor/session
- Orders and order line items
- One currency per installation
- Hosted checkout through a separately configured payment adapter
- Server-verified, replay-safe payment events
- Order status and bounded audit history
- Guest checkout, with optional Member Access integration later

Initial exclusions:

- Stored card data
- Marketplace/multi-vendor behavior
- Automatic tax calculation
- Complex shipping fulfillment
- Product variants and subscriptions
- Restaurant modifiers, pickup windows, and table service

### 2. Events Calendar

Initial scope:

- Event component
- Start/end date, time zone, location, description, and status
- Accessible list and calendar views
- Filtering by date and category
- Calendar-feed/export capability
- Optional external-calendar adapter later

Ticketing, appointments, and paid registration remain separate packages.

### 3. Appointments

Initial scope:

- Appointment Service component
- Staff/resource availability
- Time-zone-aware bookable slots
- Conflict-safe reservation transaction
- Confirmation, cancellation, and bounded reminder events
- Optional payment and calendar adapters

Appointments may reuse a proven date/time presentation capability from Events
Calendar, but it must not depend on event content semantics.

### 4. Donations

Initial scope:

- Donation Campaign component
- Fixed and custom contribution amounts
- Hosted payment through an adapter
- Server-verified donation transactions
- Donor confirmation and administrator reporting
- Refund/reversal state and audit history

Donations do not require a product catalog or cart and must not be represented
as Store Lite orders merely to reuse payment code.

### 5. Restaurant Ordering

Initial scope:

- Menu Item component
- Menus, categories, item availability, and modifier groups
- Restaurant service hours and order-acceptance windows
- Pickup ordering with requested fulfillment time
- Customer cart, order confirmation, and status
- Administrator order queue and bounded status history
- Pay-on-pickup support and an optional hosted-payment adapter

Restaurant Ordering may reuse proven catalog, cart, order, and payment
capabilities through explicit service contracts. It remains a separate package
because menu modifiers, preparation availability, fulfillment timing, and
restaurant operations do not belong in Store Lite.

Initial exclusions:

- Multi-restaurant marketplace behavior
- Third-party courier dispatch
- Point-of-sale or kitchen-display synchronization
- Table reservations and appointment scheduling
- Complex delivery-zone and driver management

## Member Access / Protected Content Package

This package is the required boundary for planned private folders and
member-only Sections.

It owns:

- Separate public member identities
- Registration, verification, login/logout, reset, and revocable sessions
- Section entitlements
- Access checks before route content queries and rendering
- Protected-content gate and optional teaser experience
- Secure download authorization
- Manual, free, purchased, renewable, and revocable grants
- Search, cache, sitemap, preview, and structured-data leakage prevention

`RED_Sections.AccessLevel` alone never activates privacy. The administrator must
not offer an operational private state until Member Access is installed,
enabled, configured, and proven by its security and leakage acceptance suite.

## Acceptance Gate

Every package must prove, in a disposable isolated installation:

- Manifest, path, compatibility, integrity, and dependency validation
- Zero execution during discovery and validation
- Installation leaves the package disabled
- Migration apply, checksum, upgrade, rollback/failure, and cleanup behavior
- Owner-only lifecycle actions and scoped package permissions
- CSRF, request validation, prepared operations, and transactional writes
- Exact parent/child component relationships
- Public rendering through the active theme and hard recovery path
- Desktop and mobile administrator behavior
- Accessible labels, keyboard behavior, errors, and public output
- Namespaced public/admin CSS with no cross-boundary leakage
- Disablement with retained data
- Uninstall and separately confirmed purge behavior
- Client database, media, configuration, and secret isolation
- No change to the configured primary database during automated acceptance
- No client data in the clean starter distribution

Payments additionally require signature verification, idempotency, amount and
currency verification, duplicate/replay tests, refund/reversal tests, and proof
that browser redirects cannot mark an order, entitlement, or donation paid.

Protected content additionally requires proof that unauthorized body content
never enters public HTML, caches, feeds, sitemaps, previews, logs, error
responses, or structured data.

## Initial Delivery Sequence

1. Complete the Version 5.1 per-page SEO metadata compatibility work and the
   isolated Adriana launch acceptance gate. The compatibility work is
   implemented; launch QA remains an isolated client decision.
2. Approve this architecture contract as a separate future implementation
   track. The contract is now documented.
3. Add the manifest schema, filesystem discovery, read-only validation, and
   dependency preflight. This non-executing trust foundation is implemented.
4. Implement the persisted Owner role and package-lifecycle permissions
   required by that approved track. This authorization foundation is
   implemented without adding a package lifecycle endpoint.
5. Add per-client installed/enabled state and immutable migration tracking.
6. Implement and distribute Store Lite separately as the first complete
   optional component plus service package.
7. If private folders are scheduled for activation, implement and pass Member
   Access before exposing an operational private setting.
8. Implement Events Calendar as the second independent proof that a new
   component no longer requires core dispatcher edits.
9. Implement Appointments.
10. Implement Donations.
11. Implement Restaurant Ordering.

Each implementation remains a separate reviewed batch with its own rollback
path, migration evidence, acceptance fixtures, and client-specific activation
decision.

## Non-Goals

The first add-on platform will not provide:

- Arbitrary uploaded PHP
- A global unbounded hook/event system
- Direct database-selected classes, files, methods, or templates
- Automatic enablement
- Business-vertical packages bundled into the core or activated by the starter
- Automatic changes to a retained primary database
- One monolithic schema for stores, events, appointments, donations, and
  restaurant ordering
- Theme-owned authorization, payments, identity, or persistence
- Client business data or enabled add-ons in the clean starter
