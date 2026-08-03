# RED-CMS Store Lite Direction

Status: implementation direction defined; package code has not started.

Store Lite is the first planned proof that RED-CMS can gain a client-specific
business capability through a separately distributed add-on. It is not a core
component, is not bundled with the clean starter, and is not installed or
enabled for every client.

The package combines one author-placeable Product component with a commerce
service. The component presents product content. The service owns catalog
records, carts, orders, totals, status transitions, and audit evidence. A
separate payment adapter may later connect hosted checkout to a provider.

## Product Goal

Store Lite serves small client sites that need a modest catalog and a safe way
to accept orders without turning RED-CMS into a general commerce platform.

The first release should support:

- a simple public product catalog;
- one sellable item per Product component record;
- price, currency, availability, image, summary, and optional stock status;
- one cart per anonymous visitor session;
- guest checkout;
- orders and immutable order line snapshots;
- one currency per client installation;
- pay-on-receipt or hosted checkout through an optional adapter;
- server-verified payment events;
- a bounded administrator order workspace; and
- retained data when the package is disabled.

It deliberately excludes variants, subscriptions, marketplace sellers,
automatic tax calculation, complex shipping, restaurant modifiers,
appointment scheduling, protected-content entitlements, and stored card data.

## Distribution And Isolation

Store Lite must be released as its own versioned package. The RED-CMS
repository may contain its generic contract and disposable acceptance fixtures,
but the clean starter must not contain:

- a deployed `addons/redcms/store-lite` directory;
- Store Lite database tables or migration-ledger rows;
- product, cart, order, payment, customer, or business data;
- Store Lite media, settings, secrets, or administrator grants; or
- a pre-enabled Store Lite registry record.

Each client deploys the package files separately and installs its migrations
only into that client's database. Disabling Store Lite retains its files,
migration evidence, settings, media, and business data. Uninstall and explicit
purge remain later lifecycle operations and must never be implied by disable.

## Package Shape

The first package identifier is `redcms.store-lite`, with type
`content-package`.

Its initial manifest contract should declare:

- component: `redcms.store-lite/product`;
- services: `commerce.catalog`, `commerce.cart`, and `commerce.orders`;
- administrator tool: `redcms.store-lite/orders`;
- permissions: `store.products.manage`, `store.orders.view`,
  `store.orders.manage`, and `store.settings.manage`;
- no required package dependency;
- optional payment-adapter compatibility through an explicit provider
  contract;
- package-owned immutable migrations;
- only Store Lite public and administrator assets;
- exact route, job, setting, outbound-host, and file-integrity declarations;
  and
- retained data as the default uninstall behavior.

The current Version 5.1 lifecycle can enable a constrained default component
combined with registration-only services. It still cannot enable the complete
Store Lite manifest because that package needs administrator tools,
persistence, routes, settings editing, actual secret lookup, and assets. Store
Lite must remain blocked until
each richer generic surface below is implemented and accepted independently.

## Component Contract

The Product component owns placement and presentation, not commerce state.
Its persistent record should identify one package-owned product and expose only
the public fields required by the selected view.

The initial public view model should contain bounded, typed values such as:

- product identifier;
- public title and summary;
- canonical product URL;
- image reference and alternative text;
- price amount in minor currency units;
- ISO 4217 currency code;
- availability state;
- call-to-action label; and
- an opaque add-to-cart action reference issued by core or the package
  service.

The component must not return raw HTML, SQL, executable templates, arbitrary
class names, absolute server paths, secrets, administrator controls, or
payment-provider credentials. Core or an explicitly approved package rendering
contract escapes output and supplies the accessible default view.

Themes may opt into a declared Product presentation contract later, but a
theme must not query Store Lite tables directly or become required for safe
rendering.

## Commerce Service Contract

The commerce service owns all mutable business behavior. Public templates and
component handlers must not change cart, order, inventory, or payment state
directly.

The initial service boundary should provide typed operations for:

- reading a public product;
- listing available products;
- creating or resolving an anonymous cart;
- adding, updating, and removing a cart line;
- recalculating authoritative totals;
- creating an order from the current cart;
- recording an administrator-approved status transition; and
- applying a verified payment event through an adapter.

Every write requires validated input, prepared database operations, a narrow
transaction boundary, an actor or anonymous-session identifier, and bounded
audit evidence. Public requests must use server-side product price and
currency values; client-submitted totals are never authoritative.

## Data Ownership

All tables are package-owned and namespaced with `RED_Addon_StoreLite_`.
The first data model should separate:

- products and their publish/availability state;
- carts and cart lines;
- orders and immutable order-line snapshots;
- order status history;
- payment-event references and idempotency keys; and
- bounded Store Lite activity facts.

Order lines copy the purchased title, unit amount, currency, and quantity at
order creation. Later product edits must not rewrite historical orders.

Money is stored as integer minor units with one validated installation
currency. Floating-point prices are not permitted. Personally identifiable
checkout data is limited to fields needed to fulfill and support the order,
excluded from general RED-CMS logs, and governed by an explicit retention
policy.

Store Lite migrations must touch only package-owned tables. They cannot add
commerce columns to legacy Article, Form, Gallery, Other, administrator, or
core settings tables.

## Cart And Order Rules

- Anonymous cart identifiers are random, opaque, cookie-bound references.
- Cart ownership is checked before cart contents are read or mutated.
- Add-to-cart requests use POST, CSRF protection, and server-side product
  lookup.
- Quantity and price limits are enforced server-side.
- Order creation is idempotent and produces one immutable commercial snapshot.
- An order cannot become paid because a browser returns from a provider.
- Status transitions follow a closed state machine and append audit history.
- Duplicate submissions and replayed payment events cannot create duplicate
  orders or duplicate paid transitions.

The initial order states should remain small:

`pending`, `awaiting_payment`, `paid`, `processing`, `completed`, `cancelled`,
and `refunded`.

Any transition outside the approved state map fails closed.

## Payment Adapter Boundary

Store Lite can operate without an online-payment adapter. Pay-on-receipt is a
package setting, not a simulated payment event.

When hosted checkout is added:

- the provider adapter is a separate package and lifecycle unit;
- credentials remain outside public content and package manifests;
- Store Lite passes only a bounded checkout request to the adapter;
- the adapter returns an opaque provider checkout reference and URL;
- only a verified server-to-server event can mark an order paid;
- signature, amount, currency, order identity, duplicate, replay, refund, and
  reversal checks are mandatory; and
- browser redirects display status but do not authorize a transition.

Store Lite must not depend on PayPal-specific field names or callback behavior.
The first adapter may use PayPal, but the commerce contract remains
provider-neutral.

## Administrator Boundary

The first Store Lite administrator workspace should be limited to:

- product list and edit;
- availability and publish controls;
- order list, detail, and status history;
- permitted manual status transitions;
- installation currency and bounded operational settings; and
- package health and payment-adapter status.

Package permissions are separate from lifecycle permissions. An Owner may
install, enable, or disable the package without automatically receiving daily
order-management access. A non-Owner may receive Store Lite product or order
permissions without receiving `addons.install`, `addons.enable`, or
`addons.disable`.

Administrator routes, forms, assets, and CSRF actions must be declared by the
manifest and namespaced so active public themes cannot style or script the
workspace.

## Lifecycle Requirements

Installation, enablement, disablement, and later upgrade or purge operations
follow the generic add-on lifecycle:

1. Files are deployed by the server operator.
2. Discovery and trust validation execute no package code.
3. Owner-authorized installation applies reviewed package migrations and ends
   `installed_disabled`.
4. Enablement revalidates the exact package, dependencies, capabilities,
   routes, settings, live-data state, registrar, and theme fallback.
5. Enabled request bootstrap loads the fixed registrar.
6. Disablement refuses enabled dependents, records `installed_disabled`
   atomically, unloads the package on later requests, and retains data.

Store Lite still cannot use the current minimal enablement profile. Generic
combined component-plus-service registration is complete, but before Store
Lite's first release RED-CMS needs separate reviewed core batches for:

- an operational editor endpoint and form; read-only delete planning and the
  activation-blocked atomic delete runner now require exact permissions,
  inactive-shell and state/revision evidence, and a registrar-bound deleter.
  The runner retains both delete snapshots and rolls back partial deletion.
  The permission-enforced inactive
  parent-metadata writer, numeric placement-parent
  relationship, read-only public binding, non-executing declarative
  editor-schema, fail-closed submitted-value normalization, and core-owned
  display-only editor renderer foundations are complete; exact fresh
  package-permission decisions and bounded package data loading are also
  complete. The activation-blocked existing-record package update runner is
  also complete with stale-state, rollback, and immutable baseline/save
  revision snapshots. Validated history/preflight and atomic source-linked
  restore execution are also complete. Read-only inactive creation preflight
  and its atomic runner are complete with exact creator/loader postconditions
  and dual initial revisions. Read-only parent state and atomic
  title/layout/language updates are complete with exact grants, shell/package
  postconditions, and core revision rollback, but no grant-management
  workflow, restore UI action, delete endpoint/control, uninstall, or purge
  exists. The audited public-placement control is complete but does not by
  itself admit the Store Lite package;
- typed service invocation is complete as an internal core-to-package boundary;
- exact static public `GET` routes are complete as a core-owned JSON boundary,
  while unsafe/member/placeholder routes and route-bearing package enablement
  remain gated;
- declared administrator routes;
- display-only administrator-tool dispatch is complete with exact package
  grants and core rendering; writable order/product actions and tool-bearing
  package enablement remain gated;
- immutable namespaced package-asset delivery and public/admin injection;
- permissioned settings UI/endpoints and actual secret lookup; typed
  validation, storage, read-only preflight, internal atomic persistence, and
  non-executing secret-reference availability are complete; and
- live-data disable/upgrade compatibility checks.

Each core batch must remain generic and must be proven with disposable fixtures
instead of bundling Store Lite.

## Acceptance Gate

Store Lite is releasable only after disposable isolated acceptance proves:

- exact manifest, path, compatibility, dependency, and integrity validation;
- zero package execution during discovery, validation, and preflight;
- Owner-only install, enable, disable, upgrade, uninstall, and purge actions;
- scoped product, order, and settings permissions;
- package-owned migrations and exact cleanup of disposable fixtures;
- safe Product placement, editing, revisions, and public rendering;
- accessible keyboard and screen-reader behavior at desktop and mobile widths;
- theme-independent fallback rendering and CSS/JavaScript isolation;
- server-authoritative price, currency, quantity, and total calculations;
- cart ownership, CSRF, validation, transaction, and concurrency behavior;
- idempotent order creation and closed status transitions;
- duplicate and replay protection;
- retained data after disable and successful later re-enable;
- no Store Lite execution after disable;
- payment-event signature, identity, amount, currency, refund, and reversal
  validation when an adapter is present;
- no sensitive checkout data in public output, logs, errors, feeds, sitemaps,
  previews, or structured data;
- no change to the configured primary database during automated acceptance;
  and
- no Store Lite files, rows, settings, media, or client data in the clean
  starter.

## Delivery Sequence

1. Approve this Store Lite product and security boundary.
2. Completed: implement the generic combined-package activation contract with
   disposable component-plus-service fixtures.
3. Started: implement generic package component persistence and bounded
   editor/public view contracts. Full component-id storage, the exact numeric
   package-table parent relationship, and read-only public binding resolution
   are complete. Bounded data-only editor-schema validation and normalized
   lookup plus submitted-value validation are also complete but
   activation-blocked. Display-only administrator rendering, exact permission
   decisions, bounded enabled-package data loading, and existing-record package
   updates, immutable revision snapshots, validated history/preflight, and
   atomic restore execution are complete. Read-only inactive creation
   preflight and its atomic runner plus permission-enforced inactive parent
   metadata writes and the display-only value-free revision timeline are also
   complete. Read-only delete planning and atomic inactive deletion are
   complete; restore UI actions, operational editing, and public
   placement/activation remain. The creation preflight
   invokes no creator and the delete preflight invokes no deleter;
   only the exact activation-blocked runner may write the parent/package rows.
4. Continue with generic settings UI/endpoints and asset contracts as separate
   reviewed batches. Typed internal service
   invocation, exact static public `GET` routes, display-only administrator
   tools, typed setting validation, per-client storage, read-only preflight,
   internal atomic settings persistence, and non-executing server-local secret
   availability evidence are complete. Actual secret lookup remains blocked.
5. Create Store Lite in its separate distribution using only those accepted
   contracts.
6. Add package-owned migrations, Product editing, catalog, cart, orders, and
   pay-on-receipt.
7. Validate disable/re-enable, failure recovery, migration, responsive
   administrator, public rendering, and client-isolation behavior.
8. Add a separately reviewed hosted-payment adapter only after the
   provider-neutral event contract passes.

Events Calendar remains the second independent vertical proof. Store Lite
implementation must not add calendar, appointment, donation, restaurant, or
Member Access behavior to the package.
