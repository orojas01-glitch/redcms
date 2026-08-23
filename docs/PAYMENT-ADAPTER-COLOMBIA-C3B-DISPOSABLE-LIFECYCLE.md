# Colombia C3B Disposable Wompi Lifecycle and Registrar Proof

Status: complete on `main` through PR #163 at `ef40abf`. C3B proves exact Wompi installation as
`installed_disabled`, its two reviewed migrations, database evidence, and
registration-only package execution in one fresh disposable database. It does
not enable Wompi, publish ingress/runtime handlers, configure settings or
secrets, contact a provider, create a payment, or change the hosted demo.

## Exact inputs

The rehearsal refuses dirty or different package repositories and requires:

- RED-CMS core at the reviewed C3B branch based on C3A merge `f0ee7e0`;
- Store Lite `0.1.35` at
  `f7de77eb1694fb6003340632c5018024753fe1fa`; and
- Store Lite Wompi `0.1.0` at
  `e17a371d73f286f5586deae88ad2c73d2f233651`.

Both external packages remain outside the clean starter. The rehearsal copies
them only into a temporary staged project and never modifies either source
repository.

## Narrow core extension

`includes/addon_payment_adapter_registrar_helpers.php` now selects the same
closed profile family as the C3A manifest helper. Exact package
`redcms.store-lite-wompi` produces `store_lite_wompi_adapter_v1`; every
existing Stripe fixture remains `store_lite_stripe_checkout_adapter_v1`.

The registration fingerprint now signs the selected profile id instead of a
Stripe constant. Final validation recomputes the profile from package identity
and, for Wompi, requires the exact adapter and provider-event route. No handler
is invoked and the request-local registry is discarded without publication.

The existing database helper required no provider-specific behavior change.
It already derives migration and InnoDB table counts from the closed C3A
profile and exact package inventory.

## Disposable rehearsal

`scripts/wompi-payment-adapter-c3b-rehearsal.sh`:

1. verifies both external repositories, commits, versions, and clean state;
2. captures a SHA-256 snapshot of the configured primary database;
3. creates one uniquely named `redcms_wompi_c3b_*` database and scoped grant;
4. installs the current RED-CMS schema and all 46 core migrations;
5. records the exact Store Lite `0.1.35` identity and 11-migration ledger as
   the already-proven enabled dependency baseline, without replaying Store
   Lite installation or loading Store Lite PHP;
6. obtains and executes the guarded exact two-migration Wompi install plan;
7. proves Wompi `0.1.0` is `installed_disabled` with two empty InnoDB tables
   and no setting rows;
8. obtains exact database preflight evidence;
9. executes only the integrity-checked Wompi registrar and validates its
   adapter plus refusing provider-event route without invoking either;
10. proves registrar validation changed no lifecycle, migration, audit,
    setting, or payment fact; and
11. revokes the grant, drops the database, removes the staged project, and
    proves the primary database is unchanged.

The inner PHP rehearsal passes 16 assertions. Its final cleanup evidence is:

```text
database:0 grant:0 staged-project:0 primary:unchanged
```

Run it with local database configuration plus the two package repository paths
when the repositories are not siblings of the core checkout:

```sh
RED_LOCAL_CONFIG=/path/to/includes/config.local.php \
RED_STORE_LITE_REPOSITORY=/path/to/redcms-store-lite \
RED_WOMPI_REPOSITORY=/path/to/redcms-store-lite-wompi \
scripts/wompi-payment-adapter-c3b-rehearsal.sh
```

The script activates `caffeinate` for its own lifetime on macOS and always
attempts cleanup on success, error, interrupt, or termination.

## Fast acceptance and preserved behavior

The dependency-free registrar self-test now passes 18 assertions. Its five new
assertions prove the exact Wompi two-migration profile, adapter, route,
registration count, no handler invocation/publication/network/route exposure,
and refusal to relabel Wompi evidence as Stripe.

Preserved focused results include:

- Stripe payment-adapter profile: 26 assertions;
- Wompi C3A profile: 30 fixture and 41 published-package assertions;
- Stripe plus Wompi registrar: 18 assertions;
- Stripe server-event ingress: 26 assertions;
- Stripe synthetic checkout: 37 assertions;
- generic typed adapter invocation: 19 assertions; and
- clean starter boundary: 22 assertions.

## Explicit exclusions

C3B creates no provider account, credential value, Wompi request or
transaction, Nequi notification, payment, order transition, browser flow,
public route publication, runtime adapter availability, Store Lite data,
hosted-demo change, client data, or deployment. No external package is copied
into the repository or starter distribution.

## C3C closure in two batches

C3C1 completes exact body-signed ingress plus one atomic enablement in a fresh
disposable database. C3C2 still owns independent enable/disable, rollback, and
isolation across two disposable client databases. See
[`PAYMENT-ADAPTER-COLOMBIA-C3C1-ATOMIC-ENABLEMENT.md`](PAYMENT-ADAPTER-COLOMBIA-C3C1-ATOMIC-ENABLEMENT.md).
