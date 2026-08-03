# RED-CMS Public Mutation Boundary

Status: planned generic contract. This document records the prerequisite for a
future public write dispatcher. It does **not** add a manifest field, route,
endpoint, cookie, session, handler, database table, migration, package,
permission, enablement profile, or Store Lite behavior.

## Purpose

RED-CMS already has a deliberately narrow add-on public route boundary: an
enabled package may serve an exact static public GET route as core-owned JSON.
That boundary refuses every unsafe method before a package handler can run.

Store Lite will eventually need a different capability: a guest can ask to add
one available product to the guest's own cart. That must not turn the general
public router into a package-controlled form or checkout system. This contract
defines the smallest future path for a public mutation while keeping Store Lite
an optional, separately distributed package.

It applies to a later first-party public mutation runner only. Existing
/bin/contact.php, /bin/response.php, /bin/register.php, /bin/login.php, and
their legacy form/session behavior remain outside this contract and are not
changed by it.

## Current State

| Capability | Current behavior | Change authorized by this document |
| --- | --- | --- |
| Add-on public routes | Exact static public GET JSON only | None |
| Unsafe add-on methods | Refused before package execution | None |
| Anonymous cart identity | Does not exist | None |
| Public CSRF issuance/validation | No add-on public-mutation path exists | None |
| Public mutation ledger/audit | Does not exist | None |
| Store Lite files, tables, or records | Absent from the clean starter | None |

The planned contract cannot make a route-bearing package eligible for the
current constrained enablement profiles. It must be implemented and accepted
before a richer enablement decision can consider it.

## Proposed Declarative Shape

A later non-executing manifest-validation batch may introduce a closed
publicMutationContracts declaration. It is intentionally separate from the
existing routes array and is not a JSON schema or runtime API yet. Each future
entry must bind exactly one already-declared route to one mutation identity and
must declare, at minimum:

- the route id and a unique mutation id;
- scope public, authentication public, method POST, and CSRF required;
- an exact static, unencoded path inside the package's reserved public
  namespace; path placeholders remain out of scope;
- a closed request-field contract with scalar types, canonical encodings,
  requiredness, bounds, and an overall body limit;
- the permitted anonymous-subject mode, idempotency policy, privacy/cache
  policy, and rate-limit policy;
- the package-owned mutable-table set, expected postcondition class, and
  value-free audit category; and
- a bounded public outcome vocabulary.

The manifest may not name PHP files, classes, arbitrary callbacks, SQL, HTML,
redirects, response headers, cookie names, sessions, payment-provider fields,
or client-selected table names. It cannot weaken core method, CSRF,
same-origin, privacy, transaction, or enablement rules. An unknown,
incomplete, duplicate, route-mismatched, executable, or overly broad entry
must fail discovery and preflight before addon.php is loaded.

The initial mutation form must remain application/x-www-form-urlencoded with
no files, nested values, JSON, query-controlled operation selection, or
cross-origin support. POST is the only planned method. PUT, PATCH, and DELETE
remain closed until separately reviewed.

## Future Core-Owned Request Path

When implementation is approved, core—not a theme, browser script, or package
route file—must own this sequence:

1. Recognize only a current trusted, enabled, static declaration in the
   reserved package namespace. It must reject every malformed, noncanonical,
   disabled, stale, untrusted, or undeclared request before package code runs.
2. Establish a core-owned, client-scoped anonymous subject. It must be an
   opaque, unguessable cookie-bound reference; it is neither an administrator
   session, a Member Access identity, a raw cookie value exposed to package
   code, nor a client database/global identity. Cookie attributes, expiry,
   rotation, and privacy retention must be specified and tested in the
   implementation batch.
3. Issue and verify a core-owned CSRF token bound to that anonymous subject
   before semantic request parsing or handler invocation. The token, subject,
   raw cookie, request body, and secrets must not enter general logs, package
   output, audit rows, or public errors. The initial boundary is same-origin
   only and emits no CORS policy that would widen access.
4. Parse only the declared scalar form fields. Core rejects unknown, repeated,
   nested, noncanonical, malformed, oversized, or out-of-range values. A
   browser never supplies a package id, route owner, price, currency, total,
   order state, cart owner, permission, plan, or database target.
5. Re-derive the exact manifest, registry, enabled runtime owner, anonymous
   subject, idempotency key, rate limit, and current package state on the
   server. A future request must be refused when rate-limit storage or required
   state evidence is unavailable; a client-provided retry flag cannot bypass a
   refusal.
6. Invoke only one registrar-bound mutation handler through a future
   core-owned transaction runner. The handler receives a final typed command
   and controlled transaction context, never PHP request globals, raw cookies,
   CSRF values, server headers, arbitrary SQL text, or a package-selected
   connection. The runner must constrain writes to the declared package-owned
   InnoDB tables.
7. Reserve the declared idempotency fact before mutation, reload the exact
   server-derived postcondition afterward, and atomically commit the package
   change, replay evidence, and one value-free audit fact. Output, exceptions,
   buffer/header tampering, stale state, transaction loss, postcondition drift,
   or ledger/audit failure must roll back or refuse the complete request.
8. Construct the response in core. It may expose only a small documented
   success/refusal vocabulary with no-store and nosniff protections. It may
   not expose package/actor/cart/order/plan/state values, HTML, redirects,
   arbitrary headers, payment data, or a privileged action token.

The exact rate budget and short-lived rate-limit evidence/retention policy are
an implementation gate, not a browser choice. They must be bounded per client,
package, route, and anonymous subject, protect privacy, and fail closed when
the required enforcement state is unavailable.

## Store Lite Mapping

The first Store Lite use of this boundary is limited to cart intent. A future
add-to-cart command may carry only a public product reference, bounded
quantity, and core-issued idempotency/CSRF evidence. Core and the commerce
service must resolve current product availability, price, currency, cart
ownership, and authoritative total server-side.

Update-cart and remove-cart need the same opaque-cart ownership and
idempotency protections. Initial checkout, customer fulfillment fields,
payment handoff, refunds, webhooks, and paid transitions are separate later
contracts. A browser return from a payment provider can never authorize an
order or paid-state change.

This boundary does not make Store Lite core functionality. It does not create
addons/redcms/store-lite, RED_Addon_StoreLite_* tables, catalog records, carts,
orders, settings, media, cookies, or a registry row in the clean starter.
Every client that later adopts Store Lite receives an independently deployed
package and a separate database installation.

## Enablement And Live-Data Gates

Before a package declaring a public mutation can be enabled, a later richer
enablement review must prove all of the following without executing a live
mutation:

- exact trusted filesystem, manifest, migration, registry, and runtime-owner
  evidence for the one client installation;
- no route, asset, capability, setting, or namespace collision;
- declared package-owned InnoDB tables and every required migration present;
- exact anonymous-subject, CSRF, idempotency, rate-limit, privacy, retention,
  and audit readiness;
- package settings and any secret availability evidence required by that
  declared mutation, without returning secret material;
- route-specific public fallback, accessibility, theme/CSS isolation, and
  cache/privacy behavior; and
- disabled-state and later-disable proof: no route claim, runtime bootstrap,
  asset injection, or mutation after disable, with client data retained.

No current enablement profile satisfies these gates. The future validator must
report a bounded, value-free refusal rather than silently downgrade a public
write to a weaker route.

## Required Future Acceptance Evidence

An implementation is not ready because a manifest validates or a route returns
HTTP 200. A disposable, client-isolated acceptance suite must prove:

- malformed, duplicate, executable, unsafe, mismatched, and drifted
  declarations fail before package execution;
- current public GET routing still refuses unsafe methods until the new
  dispatcher is explicitly present;
- anonymous subjects and CSRF tokens are opaque, scoped to one client, and
  unavailable to another client, administrator session, member session, or
  package request;
- no-CSRF, stale-CSRF, cross-subject, wrong-route, malformed-field,
  cross-origin, rate-limited, disabled, revoked, replayed, and stale-state
  requests cause no package mutation;
- every successful mutation derives authoritative server state, performs only
  declared package-table writes, rechecks its postcondition, and emits no
  sensitive value in response, log, audit, feed, sitemap, preview, or
  structured data;
- duplicate requests, concurrent requests, callback output/exceptions,
  transaction loss, ledger/audit failure, and forced late failure preserve the
  exact atomic/replay outcome; and
- exact fixture, temporary package, temporary grant, temporary database, and
  temporary cookie cleanup completes while the configured primary database is
  unchanged.

Desktop and mobile verification must also prove keyboard/screen-reader
behavior, theme isolation, no horizontal overflow, and an accessible bounded
refusal path. No real payment provider, customer, or client installation is
used for that evidence.

## Explicit Non-Goals

This planning slice does not authorize:

- an endpoint, browser form, administrator control, package JavaScript, or
  Store Lite user interface;
- package activation, a relaxed enablement profile, a live package fixture, or
  a registry/migration change;
- Member Access, checkout, payment adapters, webhooks, external network calls,
  file upload, e-mail, or PII collection;
- a generic CMS hook system, arbitrary public PHP endpoint, raw package HTML,
  redirect, or headers; or
- changes to legacy public forms, client installations, client databases,
  client media, client settings, or production data.

## Delivery Order

1. This contract records the fixed safety boundary without code or state.
2. A later batch may validate the closed data-only declaration and produce
   non-executing readiness/preflight evidence; it must still refuse enablement
   and invoke no mutation handler.
3. A separate batch may add the core-owned anonymous-subject/CSRF/rate-limit
   foundation and transaction runner, with disposable fixtures only.
4. A separate richer enablement/live-data batch may admit only packages that
   satisfy every declared prerequisite.
5. Store Lite can then implement its separately distributed catalog and cart
   behavior against the accepted generic contract. Checkout and payments stay
   later, provider-neutral work.
