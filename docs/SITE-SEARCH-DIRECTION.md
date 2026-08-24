# RED-CMS Site Search Direction

Date: 2026-08-23

## Product boundary

Site Search is an optional, separately distributed first-party package. The
clean RED-CMS 5.1 starter contains only the generic capability needed to admit
a bounded cross-cutting read-only utility. Search PHP, migrations, index rows,
CSS, JavaScript, and source integrations remain outside the core repository.

The package id is `redcms.site-search`. Its current reviewed version is `0.1.4`
at private-repository main commit `193c1a1` and tag `v0.1.4`.
It declares the typed `content.search` and `content.index-sync` services, one
exact static public `GET` route, one package-owned migration, and two immutable
public assets. It has no component, administrator tool, public mutation,
secret, job, adapter, administrator asset, outbound host, or client-shared
state.

## Repository ownership

- The `redcms` core repository owns the manifest/runtime/lifecycle contract,
  the generic `read_only_public_utility` profile, early exact-GET route
  classification, documentation, and disposable acceptance fixtures.
- Site Search belongs in its own private repository, recommended name
  `redcms-site-search`, with `package/`, `tests/`, `docs/`, release tags, and
  downloadable package archives.
- Store Lite remains in its existing separate repository
  `redcms-store-lite`. Search must not be copied into that repository or read
  Store Lite tables directly.
- Client projects install a reviewed, pinned package version beneath
  `addons/redcms/site-search/`. They do not use a shared working tree, shared
  database, floating branch, or Git submodule in the clean core.

This keeps the reusable core visible while allowing commercial add-ons to stay
private. Access for another project should be granted through the private
repository or a versioned release archive, not by copying an unversioned live
folder from another client.

## Runtime flow

1. A client places `<div data-redcms-site-search></div>` in its Website Header.
   An optional `data-redcms-site-search-language="sp"` value pins the current
   two-letter RED-CMS language.
2. Core injects the enabled package's checksum-versioned CSS and deferred
   JavaScript.
3. The script renders one accessible search form only inside that exact marker,
   waits 250 milliseconds after input, cancels stale requests, and calls
   `/addons/redcms/site-search/query`.
4. The pre-theme front controller classifies exact public `GET` package routes
   before public-mutation routes. Non-GET requests to the search path receive
   `405` with `Allow: GET`.
5. The package opens its own short-lived client-local MySQL connection, starts
   a read-only transaction, searches only its package-owned index, rolls back,
   and returns a bounded typed result. Core owns the JSON envelope and fixed
   `no-store`/`nosniff` headers.

## Index contract

`RED_Addon_SiteSearch_Documents` is derived, client-local InnoDB data with a
FULLTEXT key over title, summary, body text, and keywords. The unique source
identity is source type, source record id, and language. Version 0.1.4 indexes
only active, started, unexpired core `Article` pages with a nonempty public
alias whose stored Section/Category/SubCategory hierarchy exists and remains
active. HTML is converted to plain UTF-8 text before storage, and page URLs are
rebuilt from that hierarchy.

Version 0.1.4 also refreshes only the affected core Article rows after a
successful canonical create, update, delete, restore, or move transaction.
Core invokes the exact enabled service owner after commit with one closed event
name and 1-64 record ids. Notification is deliberately best effort: a package
failure is logged generically and cannot reverse the completed CMS change.

The rebuild command is CLI-only, Owner-gated through `addons.enable`, dry-runs
by default, reads one consistent core source snapshot, and requires exact
database, package, enabled-state, and plan-SHA confirmations before atomically
replacing the `core-article` and optional `store-lite-product` source rows. The
Store Lite source is obtained only through Store Lite 0.1.43's typed provider
service and contains no price, currency, stock, availability value, SKU, cart,
order, payment, customer, administrator, setting, secret, or database identity.
Scheduled mode retains the Owner and exact database/package/enabled-state
gates, refuses manual apply/plan arguments, and uses one non-blocking advisory
lock per client database. It adds no core scheduler, public route, manifest
job, setting, secret, or shared client state.

## Current proof and remaining gates

The package now has its own adjacent local Git repository at
`/Users/oscarrojas/Documents/redcms-site-search`. It passes pure normalization,
integrity, trust-gate, install, enable, registrar, index-rebuild, HTTP route,
incremental create/update/deactivate/restore/move/delete synchronization,
hierarchy deactivation/reactivation, future-start/expiry repair, concurrent
rebuild refusal, a 50,000-document atomic rebuild with 125.19 ms local query
p95, a 16-assertion Store Lite provider lifecycle, desktop, and mobile
interaction checks against fresh disposable databases.
Private release `v0.1.4` contains the reproducible reviewed archive. The first
client-local installation on `demo.red-sphere.com` is enabled with its own
database/index, Spanish header mount, exact immutable assets, ten-minute cPanel
scheduled repair command, and demo-specific log. Live HTTP and desktop/mobile
QA returned real Article and Store Lite results with zero browser/runtime
errors. This does not authorize another client installation.

Before each later client release, independently review the pinned archive,
database, Owner, theme mount, language, PHP command, schedule, index contents,
backup, rollback, and hosted browser/HTTP behavior.
