# RED-CMS Store Lite Direction

Status: the separately distributed Store Lite 0.1.31 package is deployed only
to the isolated `demo.red-sphere.com` RED-CMS 5.1.0 installation. The hosted
catalog, simple and bounded-variable products, Product and Cart placement,
Add-to-cart, quantity update, line removal, guest checkout, pickup and delivery,
pay on receipt, and Products/Orders administrator surfaces are verified. The
Store Lite v1 basic-demo target is achieved; hosted payment adapters remain a
separate later gate.

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
- simple products with one sellable item and variable products with bounded
  option groups such as size or color;
- one product parent per Product component record, with one resolved sellable
  variant selected when the parent is variable;
- product- and variant-level price, currency, availability, image, summary,
  and optional stock status;
- one cart per anonymous visitor session;
- guest checkout;
- orders and immutable order line snapshots;
- one currency per client installation;
- pay-on-receipt or hosted checkout through an optional adapter;
- server-verified payment events;
- a bounded administrator order workspace; and
- retained data when the package is disabled.

It deliberately excludes unbounded variant matrices, subscriptions,
marketplace sellers, weight-based pricing, automatic tax calculation, complex
shipping, restaurant modifiers, appointment scheduling, protected-content
entitlements, and stored card data.

## Product Types And Variant Boundary

Store Lite has two product paths that share one package and commerce service:

- A **simple product** has one price and availability state, with optional
  stock. A banana sold by unit or pack uses this path.
- A **variable product** is a product parent with a bounded set of explicit
  variants. Each variant may define its own option values, SKU, price,
  availability, image, and optional stock. A T-shirt can therefore expose
  Size and, when needed, Color without creating separate unrelated products.

The Product component references the parent product. A variable product must
resolve one valid variant before an add-to-cart action is issued. Cart lines
store the selected variant, and order lines snapshot the selected option
labels, SKU, price, currency, title, and quantity. Product and variant values
are always revalidated by the server; browser-submitted totals are never
authoritative.

The first package release will use bounded option groups and explicit variant
records. It will not support arbitrary modifiers, free-form personalization,
variant-specific shipping rules, or weight-based pricing. Gate 2A fixes the
package limits at three option groups, sixteen values per group, and 128
explicit variants per product parent, with 64-character identifiers and SKUs,
integer minor-unit prices, one uppercase three-letter installation currency,
and bounded UTF-8 title/summary text. The complete package-owned shape and
fail-closed normalization rules are in
[`STORE-LITE-PRODUCT-CONTRACT.md`](STORE-LITE-PRODUCT-CONTRACT.md).

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

The current Version 5.1 lifecycle can enable constrained registration-only
services, including the secret-capable service profile, and a default
component combined with registration-only services. It still cannot enable the
complete Store Lite manifest because that package needs administrator tools,
persistence, routes, settings editing, ordinary settings, commerce state, and
assets. Store Lite must remain blocked until
each richer generic surface below is implemented and accepted independently.

## Component Contract

The Product component owns placement and presentation, not commerce state.
Its persistent record should identify one package-owned product and expose only
the public fields required by the selected view.

The initial public view model should contain bounded, typed values such as:

- product identifier;
- product type (`simple` or `variable`);
- public title and summary;
- canonical product URL;
- image reference and alternative text;
- bounded variant choices and the selected variant identifier when applicable;
- resolved price amount in minor currency units;
- ISO 4217 currency code;
- availability state;
- call-to-action label; and
- an opaque add-to-cart action reference issued by core or the package
  service.

The component must not return raw HTML, SQL, executable templates, arbitrary
class names, absolute server paths, secrets, administrator controls, or
payment-provider credentials. Core or an explicitly approved package rendering
contract escapes output and supplies the accessible default view.

Store Lite 0.1.10 implements the first pure presentation adapter. It repeats
the complete product normalization contract and returns only a published
product's title, summary, fixed price or price range, effective availability,
and bounded option-label facts. Core now accepts that optional fact list in its
escaped semantic default renderer. The presenter opens no database and does not
render media, expose variant controls, or create an add-to-cart action. Store
Lite 0.1.11 adds one restrictive package-owned placement relationship plus the
exact transactional editor callbacks and read-only runtime handler. The handler
reloads the bound product and delegates only to the pure presenter; no theme or
core query reads package business fields.

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
- listing valid variant choices and resolving a selected variant;
- creating or resolving an anonymous cart;
- adding, updating, and removing a cart line, including the selected variant;
- recalculating authoritative totals;
- creating an order from the current cart;
- recording an administrator-approved status transition; and
- applying a verified payment event through an adapter.

Every write requires validated input, prepared database operations, a narrow
transaction boundary, an actor or anonymous-session identifier, and bounded
audit evidence. Public requests must use server-side product price and
currency values; client-submitted totals are never authoritative.

Store Lite 0.1.12 implements the first non-writing commerce calculation. Its
pure resolver accepts only product, integer quantity 1–100, and an optional
variant declaration; the caller separately supplies the current server-loaded
product and installation currency. It re-normalizes that product, resolves one
sellable simple record or exact current variable variant, derives SKU, option
labels, integer unit price/total, currency, and stock evidence, and binds the
result to a product-state SHA-256. It is not registered as `commerce.cart` and
creates no database, route, cookie, response, inventory reservation, cart, or
order. See
[`STORE-LITE-CART-LINE-CONTRACT.md`](STORE-LITE-CART-LINE-CONTRACT.md).

Store Lite 0.1.13 implements the first internal cart persistence boundary.
One core-issued numeric anonymous-subject relation owns one package cart; the
package never reads or stores the raw token or cookie. Its caller-owned
transaction locks current cart, line, product, and selected-variant state,
requires a fresh cart-state SHA-256, reuses the server-authoritative resolver,
verifies the full postcondition, and writes one value-free activity fact.
Product/variant deletion is restrictive and a cart cascades only its own lines.
The class remains unregistered and non-routable. See
[`STORE-LITE-CART-PERSISTENCE-CONTRACT.md`](STORE-LITE-CART-PERSISTENCE-CONTRACT.md).

Store Lite 0.1.14 binds that persistence to the generic core atomic runner.
The package declares one Add-to-cart route/mutation with only product, integer
quantity, and optional variant fields, then registers a fail-closed route
callback, mutation handler, state loader, and exact eight package tables. Core
continues to own subject, CSRF, idempotency, rate limit, transaction, replay,
postcondition, audit, and response authority. The binding is proven only in a
disposable rehearsal; no production endpoint, browser control, or operational
`commerce.cart` service exists. See
[`STORE-LITE-CART-MUTATION-CONTRACT.md`](STORE-LITE-CART-MUTATION-CONTRACT.md).

## Data Ownership

All tables are package-owned and namespaced with `RED_Addon_StoreLite_`.
The first data model should separate:

- product parents and their publish/availability state;
- explicit product variants and bounded option values linked to one parent;
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
- Variable-product cart lines must contain one valid current variant; missing,
  stale, unavailable, or mismatched variant selections are refused.
- Quantity and price limits are enforced server-side.
- Order creation is idempotent and produces one immutable commercial snapshot,
  including the selected variant's option labels and SKU when applicable.
- An order cannot become paid because a browser returns from a provider.
- Status transitions follow a closed state machine and append audit history.
- Duplicate submissions and replayed payment events cannot create duplicate
  orders or duplicate paid transitions.

The generic boundary for a future add-to-cart request is documented in
[PUBLIC-MUTATION-BOUNDARY.md](PUBLIC-MUTATION-BOUNDARY.md). The generic core
can now validate optional closed declaration metadata and, separately, inspect
the current migration, table, setting, opaque-secret-availability, and
subject/CSRF/rate-limit/idempotency/execution storage evidence for a trusted
installed-disabled package without exposing values. It also has internal
hash-only anonymous-subject/CSRF, fixed-window rate-limit, opaque
idempotency-key, and atomic transaction-runner foundations for a later core
dispatcher. A pure core response contract can now construct fixed redacted
success/refusal envelopes, and a separate pure decoder can normalize only
declared canonical package fields. A further pure HTTP request-envelope
normalizer now validates only explicit trusted HTTPS origin, static POST,
canonical form metadata, subject cookie, CSRF, and idempotency evidence before
the decoder runs; none of these helpers owns a browser adapter, browser identity
issuance, route, or response emission. Store Lite now has one separately
distributed declared route and internal runtime binding, but still has no core
production endpoint, browser cart cookie/control, package files in the clean
starter, or general enablement profile.
The separate private selector can now bind a known static path to one current
registrar-owned route/mutation/state-loader identity without calling it. It
does not create a request adapter, route claim, endpoint, browser evidence,
response, Store Lite state, or enablement change.
The separate core-only server request-facts adapter now provides the next
non-routable seam: its canonical HTTPS origin is available only from
operating-system/local configuration, and a later server integration must pass
an explicit complete fixed security-header capture rather than an associative PHP
header map. It can read only the current method/raw target and retains no
browser or business state. It still does not claim a route, read a body stream,
issue a cart cookie, invoke a package, emit a response, or create Store Lite
state or enablement.
The optional Caddy/FrankenPHP ingress-attestation source now supplies a
separate deployment-owned preparation step: it removes spoofed internal
headers and conditionally HMAC-signs only bounded `/addons/` POST method/target,
body length/hash, and fixed security-header facts for an unlinked PHP verifier.
It is not a Store Lite package, custom binary, active Caddyfile, dispatcher,
cart route, browser cookie, product/cart/order table, enablement change, or
client artifact. A separate isolated proof now builds the matching temporary
binary and verifies the Caddy-to-PHP ingress contract without a client
deployment. A later generic dispatcher still requires a client-specific
deployment review.
The separate core-only response emitter may now emit only an already-valid
fixed core response envelope after a future dispatcher finishes. It refuses
once output has started, clears and sets only the fixed no-store/nosniff JSON
headers, and emits only the matching fixed bytes. It remains unlinked from the
front controller and reads no request/cookie/session state, database, runtime,
or package code, so it creates no public route, cart cookie, Store Lite state,
or enablement path.
The separate pure subject-cookie serializer can now construct only the exact
future host-only cookie value for an exact core-issued descriptor shape: a
30-minute `Max-Age`, `Path=/`, `Secure`, `HttpOnly`, and `SameSite=Strict`,
without `Domain` or `Expires`. It emits no cookie/header and remains unlinked
from the front controller, so it creates no browser cart identity, public
route, Store Lite state, or enablement path.

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

Store Lite still cannot use the current constrained enablement profiles.
Generic combined component-plus-service registration and the narrow
secret-capable service profile are complete, but before Store
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
  postconditions, and core revision rollback. A dry-run-first server-local
  Owner grant/revoke workflow now exists, but no web grant-management UI,
  restore UI action, delete endpoint/control, uninstall, or purge exists. The
  audited public-placement control is complete but does not by
  itself admit the Store Lite package;
- typed service invocation is complete as an internal core-to-package boundary;
- exact static public `GET` routes are complete as a core-owned JSON boundary,
  while unsafe/member/placeholder routes and route-bearing package enablement
  remain gated. The public-mutation declaration preflight plus separate
  read-only live-data evidence, internal hash-only subject/CSRF foundation,
  internal fixed-window rate-limit foundation, opaque idempotency-key
  foundation, and atomic transaction runner reserve a future core-owned static
  POST path and inspect only
  trusted installed-disabled client data readiness; they do not dispatch it,
  emit a browser cart cookie, resolve secrets, or relax any route/enablement
  gate;
- declared administrator routes;
- non-executing operational administrator-form metadata and permission-scoped
  planning are complete. The closed scalar/two-level collection schema and
  core-owned preview now represent both simple and variable-product
  structures. A registrar-bound, permission-scoped read-only value provider
  can populate complete current values into escaped disabled controls. A
  separate unlinked validation-only core JSON endpoint now authenticates and
  verifies header CSRF before body I/O, reloads current state, refuses stale or
  invalid submissions, and derives opaque preparation evidence without
  invoking a writer. A separate internal core boundary now accepts one exact
  optional form writer with declared package-owned InnoDB tables and atomically
  revalidates, writes, reloads, and audits an exact changed postcondition. A
  core-owned operational editor and authenticated JSON Save endpoint now bridge
  one exact tool/form/positive-target identity to that runner with typed scalar
  and bounded nested-collection controls. No Store Lite provider/package,
  product target list, package navigation, migration, table, or linked Store
  control exists;
- display-only administrator-tool dispatch is complete with exact package
  grants and core rendering. A separate non-executing write-action preflight
  now binds a declared action's exact runtime owner, package permission,
  `POST`/CSRF policy, and numeric target into deterministic evidence without
  invoking a package callback or writing state; atomic order/product actions,
  their protected UI/endpoint, and tool-bearing package enablement remain
  gated;
- core-owned public/admin package-asset injection is complete: its planner
  revalidates current trusted manifest and enabled-registry evidence without
  invoking package PHP,
  public CSS/JavaScript is added only at the document `head`/`body-end`, and
  administrator counterparts are added only for the existing signed-in overlay;
  invalid, drifted, or ambiguous state emits no package markup;
- typed validation, storage, read-only preflight, internal atomic persistence,
  core-only per-setting authorized reads, non-executing secret-reference
  availability, the core-owned permissioned ordinary-settings editor/endpoint,
  and the core-owned server-local secret-reference resolution/replacement
  boundary are complete. The narrow secret-capable registration-only service
  profile is also complete: it resolves only package-owned server-local
  references through the typed request and rejects secret-bearing results;
  Store Lite's richer settings and commerce surfaces remain gated; and
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
- both simple-product and variable-product flows, including invalid or stale
  variant-selection refusal and immutable variant order snapshots;
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

1. Approved at Gate 0: Store Lite supports simple products and bounded
   variable products; complex variant matrices and modifiers remain out of
   scope.
2. Completed: implement the generic combined-package activation contract with
   disposable component-plus-service fixtures.
3. Completed: implement generic package component persistence and bounded
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
   complete; operational editing and audited public placement are complete.
   Restore UI actions remain later. The core default renderer now also accepts an
   optional bounded label/value fact list while preserving the original
   title/summary output and refusing package HTML. Store Lite 0.1.11 now
   supplies the package-owned placement relationship and runtime Product
   binding on top of the 0.1.10 presenter. Transactional package callbacks,
   the core-owned Add component workflow, explicit language-homepage
   placement, and disposable desktop/mobile public rendering pass. The
   creation preflight
   invokes no creator and the delete preflight invokes no deleter;
   only the exact activation-blocked runner may write the parent/package rows.
4. Completed Gate 2A: lock the package-owned Product record contract for
   simple products and explicit bounded variants. The dependency-free fixture
   proves banana-style simple products, Size/Color shirt variants, integer
   minor-unit money, one installation currency, unique option tuples, and the
   three-group/16-value/128-variant limits without creating a package, table,
   migration, route, or Store Lite state.
5. Completed the optional public-mutation declaration preflight without adding
   a dispatcher, emitted cookie/header or session access, ledger, package code,
   or Store Lite behavior.
   It remains a prerequisite for a later cart write path.
6. Completed the separate read-only public-mutation live-data preflight. It
   checks current trusted installed-disabled migration, InnoDB-table,
   typed-setting, and opaque-secret-availability evidence through hashes and
   counts only; it does not dispatch, enable, execute package code, resolve
   secrets, or write package state.
7. Completed the generic core-only anonymous-subject/CSRF foundation: two
   empty per-client tables store hashes only, with a future secure host-only
   cookie descriptor and declaration/database-scoped CSRF values. It adds no
   endpoint, emitted cookie/header, cart identity, package behavior, or
   enablement change.
8. Completed the generic core-only fixed-window rate-limit foundation: one
   empty per-client table records only an opaque subject relation, SHA-256
   declaration/database scope, window facts, and bounded count. It permits at
   most 12 requests per 60 seconds for one client, declared route, and subject;
   it adds no public route, cart state, package code, or enablement change.
9. Completed the generic core-only opaque idempotency-key foundation: one
   empty per-client table records only an opaque subject relation, SHA-256
   declaration/database scope, SHA-256 key digest, and expiry facts. It can
   issue or resolve a 10-minute key; its issuer/resolver cannot consume it or
   record a replay result itself.
10. Completed the generic core-only atomic transaction runner: one fifth empty
   per-client ledger stores only an idempotency-key relation, keyed HMAC
   command/state evidence, a bounded outcome, and completion time. A trusted
   in-memory first-party binding can commit or roll back declared package state,
   replay evidence, and one value-free audit fact together. It does not add a
   dispatcher, browser cart cookie, enablement profile, or Store Lite data.
11. Completed the generic pure core response contract: it maps only the fixed
   `accepted` / `unchanged` outcomes and generic invalid-request,
   method-not-allowed, request-conflict, rate-limited, or temporary-unavailable
   refusals to exact no-store/nosniff JSON envelopes. It emits no response and
   has no request, cookie, session, package, enablement, or Store Lite path.
12. Completed the generic pure declared-form decoder: it accepts only one
    validated declaration plus canonical URL-encoded package fields and returns a
    sorted typed scalar map or no values. It has no HTTP metadata, cookie,
    session, database, package, enablement, or Store Lite path.
13. Completed the generic pure HTTP request-envelope normalizer: it accepts one
    trusted canonical HTTPS origin, exact static POST path, complete header list,
    and raw body, then releases only validated opaque subject/CSRF/idempotency
    evidence to the later core dispatcher. It has no PHP-global request adapter,
    endpoint, response, route, browser issuance, enablement, or Store Lite path.
14. Completed the private static mutation-route selector: it binds one exact
    un-decoded path only to a current registrar-owned public route, mutation
    handler, and state loader, with fail-closed ambiguity and missing-binding
    refusal. It has no request-global adapter, package invocation, endpoint,
    response, browser behavior, enablement, or Store Lite path.
15. Completed the non-routable core-only server request-facts adapter: only
   operating-system/local configuration can provide a canonical HTTPS origin;
   a later server integration must attest an explicit complete fixed security-header
   capture. It reads only the current method/raw target and creates no route,
   body-reader, browser, package, response, enablement, or Store Lite path.
16. Completed the core-only non-routable response emitter: it accepts only
   exact fixed core envelopes, refuses after output starts, clears and sets
   only fixed no-store/nosniff JSON headers, and emits only matching fixed
   bytes. It has no request, browser, package, front-controller, enablement,
   or Store Lite path.
17. Completed the pure non-emitting subject-cookie serializer: it accepts only
   the exact core-issued descriptor shape and produces one fixed future
   host-only cookie value with 30-minute `Max-Age`, `Path=/`, `Secure`,
   `HttpOnly`, and `SameSite=Strict`, without `Domain` or `Expires`. It has no
   request, browser, package, header-emission, front-controller, enablement,
   or Store Lite path.
18. Completed the core-owned browser subject-cookie lifecycle bridge. Its
   transactional `ensure`, `clear`, and `rotate` operations return only fixed
   host-only descriptors, refuse malformed input and active caller
   transactions, and invalidate the old subject and CSRF evidence on rotation.
   The 18-assertion disposable fixture and supported-server HTTP proof cover
   issuance, resolve-without-reissue, fixed clearance, replacement, and
   cleanup; response ownership and client deployment remain separate gates.
19. Completed the non-executing per-client deployment profile. It validates a
   separate client database and canonical HTTPS origin, pinned FrankenPHP/Caddy
   versions, fixed HMAC/trusted-origin sources, attestation-before-PHP route
   order, core response/cookie ownership, the fixed host-only cookie policy,
   clean-starter isolation, and disabled activation flags. It returns only a
   deterministic non-secret hash and does not load or apply a deployment.
20. Completed the core-owned non-emitting response-owner composer. It binds a
   valid deployment profile to the fixed response envelope and exact lifecycle
   cookie descriptors, preserves clear-before-set rotation, rejects arbitrary
   headers and ownership/policy drift, and remains unlinked from the front
   controller.
21. Completed the non-executing per-client deployment-review packet. It binds
   the profile hash to non-secret server/artifact, process-environment
   trust/rotation, and fixed desktop/mobile browser evidence without loading
   deployment files, resolving secrets, or changing client state.
22. Added the installation-shaped HTTPS deployment rehearsal. It builds the
   reviewed integration in a temporary context, mounts an external generated
   certificate, proves process-environment key replacement across restart, and
   captures fixed browser evidence without a client database, dispatcher link,
   package, or Store Lite path. A successful Docker/browser run remains
   required before the next gate.
23. Completed the optional non-routable Caddy/FrankenPHP ingress-attestation
   source, unlinked PHP verifier, and isolated custom-binary proof. The proof
   uses a temporary image only; it does not create a client binary, active
   Caddyfile, dispatcher, route, browser/cart cookie, package state,
   enablement, or Store Lite path.
24. The bounded dispatcher now has supported-server disposable rehearsal
   evidence through the pinned FrankenPHP/Caddy binary, PHP verifier, atomic
   runner, and fixed emitter against fresh MySQL. Continue with per-client
   Caddyfile/TLS/proxy, trusted-origin/HMAC, and browser-deployment review
   before any front-controller link; richer enablement remains a separately
   reviewed batch. The core browser subject lifecycle is
   already proven independently.
25. Completed Gate 2D2D3B in core: the supported-server endpoint remains
   dormant until its explicit local flag, trusted HTTPS origin, and ingress
   HMAC key all pass. Core now owns reserved-namespace dispatch, closed response
   emission, raw-cookie validation, one request-local subject across accepted
   component forms, fixed controller delivery, and host-only cookie emission.
   This installs or enables no Store Lite package and changes no demo/client
   deployment; real Store Lite desktop/mobile mutation QA is Gate 2D2D3C.
26. Completed Gate 2D2D3C through a separate opt-in supported-server browser
   rehearsal. It stages clean core plus pinned Store Lite 0.1.16 only in a
   temporary Docker context, applies core migrations and package migrations in
   manifest order to fresh MySQL, enables the endpoint only through temporary
   process configuration, and drives the real Product form and Add-to-cart
   route over self-signed localhost HTTPS. Desktop simple-product and mobile
   exact Size/Color variant acceptance, retry, conflict, invalid quantity,
   accessible controls/status, host-only
   Secure/HttpOnly/Strict subject cookie, zero console/page/network errors,
   exact atomic cart/audit state, and complete Docker cleanup passed. No hosted
   demo or client installation was changed.
   Typed internal service
   invocation, exact static public `GET` routes, display-only administrator
   tools, typed setting validation, per-client storage, read-only preflight,
   internal atomic settings persistence, non-executing server-local secret
   availability evidence, read-only immutable asset-delivery preflight, static
   immutable endpoint, and core-owned public/admin document injection are
   complete. Secret-capable registration-only service consumption is accepted
   only through its value-free typed boundary. The administrator-form
   current-value loader is also complete as a generic read-only boundary with
   fresh exact grants, closed nested values, record-bound evidence, and a
   disabled core preview. The separate unlinked validation-only JSON adapter is
   complete, and the separate internal exact-writer/atomic-runner boundary now
   passes rollback acceptance. The generic operational editor and authenticated
   Save bridge also pass focused and full disposable/HTTP acceptance, plus
   Chrome desktop/mobile rendered inspection with zero overflow and no
   console, page, or failed-request errors. Store Lite remains outside the
   clean starter and blocked from normal richer-package enablement.
27. Completed: create Store Lite in its separate distribution using only the
   accepted contracts.
28. Completed through Add-to-cart: package-owned Product migrations,
   simple/variable Product
   administration, Product component persistence, administrator creation,
   Homepage placement, public rendering, the server-authoritative cart-line
   resolver, and cart persistence are complete. Store Lite 0.1.14 now declares
   and registers the closed Add-to-cart route, mutation handler, state loader,
   and exact package tables; the real core atomic runner passes simple product,
   explicit variant, replay, conflict, rollback, postcondition, and audit proof.
   Gate 2D2 now also proves the connected core form, evidence, supported-server
   dispatch, fixed response/cookie, and Store Lite handler together through
   desktop/mobile Add-to-cart QA.
29. Completed the separately distributed Store Lite 0.1.19 placeable read-only
   Cart component. The supported-server rehearsal creates and places Product
   and Cart components through the real administrator flow, proves empty Cart
   rendering, then reloads the same subject-owned Cart after desktop simple and
   mobile Size/Color Add-to-cart requests.
30. Completed the Store Lite 0.1.24 editable Cart: package-owned quantity and
   removal presentations compose through core-owned row forms and pass real
   desktop/mobile supported-server mutation, recalculation, refresh, retry,
   conflict, and cleanup proof.
31. Completed the Store Lite 0.1.28 guest checkout: one Cart view combines its
   collection with a top-level closed twelve-field form; configured pickup and
   delivery plus pay on receipt persist immutable server-derived order and line
   snapshots. The isolated supported-server gate passed 100 administrator and
   268 public checks with exact database facts and complete cleanup.
32. Completed the generic read-only operational content-package preflight. It
   aggregates exact manifest coverage, recorded migrations, complete non-secret
   per-client settings, InnoDB tables, and every declared public mutation into
   one deterministic plan while keeping Store Lite installed-disabled and
   unexecuted. Store Lite 0.1.28 matches the pure profile, but this evidence
   cannot validate its registrar or enable it.
33. Completed Owner-authorized atomic operational enablement. Core revalidates
   the read-only plan under both locks, requires exact registrar coverage and
   live InnoDB transaction bindings, and atomically records enabled state plus
   audit. Generic and real Store Lite disposable rehearsals prove failed
   registration never changes lifecycle state, disable executes no package
   code, and re-enable reproduces the registrar evidence without deleting or
   changing package code, settings, migrations, components, products, carts,
   order tables, or seeded business data.
34. Completed. The generic core upgrade/recovery gate allows a disabled
   package to move only to a higher trusted same-type target with unchanged
   historical migration evidence and compatible stored settings. Forced
   partial migration and completion-audit failures remain non-loadable and
   resume only the exact remaining work. The real Store Lite 0.1.28 to 0.1.29
   proof adds two append-only order-list indexes, forces failure after the first,
   preserves one order and five settings with the old identity, and resumes only
   the second before finishing disabled.
35. Completed Release C3. The supported-server rehearsal reruns 100
   administrator and 268 public desktop/mobile checks against Store Lite
   0.1.29 with exact cart/order facts and complete Docker cleanup. A separate
   14-assertion rehearsal installs and enables 0.1.29 in two fresh client
   databases with distinct USD/pickup and COP/delivery settings and products,
   proves mutations and lifecycle changes cannot cross between them, preserves
   the configured primary by full database hash, and removes both databases,
   grants, and the staged project. This evidence did not deploy the hosted demo.
36. Completed the separately reviewed basic `demo.red-sphere.com` deployment
   and closeout. Store Lite 0.1.31 remains a client-local optional package;
   RED-CMS 5.1.0 exposes nine hosted products, bounded variant selection,
   Product and Cart placement, Products and Orders tools, an empty/current cart,
   pickup and delivery checkout, and pay on receipt. Desktop and 390-pixel
   inspection produced no browser warnings or errors. No new hosted order was
   submitted during closeout; real order creation remains covered by the
   isolated supported-server acceptance gate. See
   [`STORE-LITE-DEMO-CLOSEOUT-20260815.md`](STORE-LITE-DEMO-CLOSEOUT-20260815.md).
37. Add a separately reviewed hosted-payment adapter only after the
   provider-neutral event contract passes.

Events Calendar remains the second independent vertical proof. Store Lite
implementation must not add calendar, appointment, donation, restaurant, or
Member Access behavior to the package.
