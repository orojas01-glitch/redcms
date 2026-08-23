# Colombia C3C1 Wompi Ingress and Atomic Enablement

Status: complete on `main` through PR #164 at `91b4a62`. C3C1 adds the exact Wompi body-signed ingress shape
and proves one atomic enablement in a fresh disposable database. It does not
publish a route at runtime, invoke a handler, resolve a secret, contact Wompi,
create a payment, or change the hosted demo.

## Why Wompi ingress differs from Stripe

The existing Stripe contract requires this canonical header capture:

```text
Content-Type
Content-Length
Stripe-Signature
```

Wompi event verification instead consumes the checksum and ordered signed
properties carried in the JSON event body. C3C1 therefore defines an exact
body-signed Wompi capture with only:

```text
Content-Type
Content-Length
```

Core still does not parse JSON or verify the checksum. It preserves the exact
body bytes for the later package verifier and records only their length and
SHA-256 in ordinary evidence. An extra `Stripe-Signature` header on the Wompi
profile fails closed instead of silently creating a mixed-provider contract.

## Narrow core changes

`includes/addon_payment_adapter_server_event_ingress_helpers.php` now:

- derives the profile and exact required headers from package identity;
- preserves the existing three-header Stripe shape unchanged;
- permits an empty header-verification value only for exact package
  `redcms.store-lite-wompi`;
- reports `signaturePresent=false` for Wompi verification material;
- keeps raw body bytes hidden from JSON, debug, object casts, cloning, and
  serialization; and
- remains non-routable, non-parsing, non-verifying, non-publishing, and
  non-networking.

`includes/addon_payment_adapter_enable_helpers.php` now:

- signs the exact selected Stripe or Wompi profile id;
- preserves Stripe's two-secret rule;
- requires exactly three available secret references for Wompi; and
- otherwise reuses the existing lifecycle/package locks, fresh-plan
  recomputation, compare-and-swap, audit, and rollback transaction.

## Fast acceptance

The dependency-free ingress test grows from 26 to 31 assertions. Its five new
Wompi assertions prove:

- exact `store_lite_wompi_adapter_v1` selection;
- exact two-header body-signed contract;
- value-free raw-body capture;
- no invented header secret;
- extra Stripe header refusal; and
- no adapter or route-handler invocation.

Existing focused Stripe results remain:

- payment-adapter profile: 26 assertions;
- Stripe plus Wompi registrar: 18 assertions;
- Stripe atomic enablement: 24 assertions;
- Stripe synthetic checkout: 37 assertions; and
- generic typed adapter invocation: 19 assertions.

## Exact disposable rehearsal

`scripts/wompi-payment-adapter-c3c1-rehearsal.sh` reuses the C3B staging and
cleanup harness with a shorter safe database name. It requires clean exact
Store Lite `0.1.35` at `f7de77e` and Wompi `0.1.0` at `e17a371`.

After all 46 core migrations, it first runs the existing 24-assertion Stripe
atomic-enable database test, which cleans its own fixture. The 17-assertion
Wompi test then proves:

1. exact package discovery and Store Lite dependency identity;
2. guarded two-migration Wompi installation as `installed_disabled`;
3. one synthetic public setting and three distinct opaque `config:` secret
   references stored only in the disposable client database;
4. three value-free availability declarations;
5. an exact four-setting/three-secret enablement plan containing none of the
   public value or reference strings;
6. no state change during planning;
7. missing-secret and stale-plan refusal;
8. rollback of both lifecycle state and audit after an injected failure;
9. one locked, freshly revalidated atomic transition to `enabled`;
10. one bounded enable audit, four unchanged setting rows, and two empty
    payment-evidence tables; and
11. repeat-enable refusal.

The final cleanup proof remains:

```text
database:0 grant:0 staged-project:0 primary:unchanged
```

The full acceptance suite's dependency-free phase also passed through the new
31-assertion ingress test. Its broad database phase was not used as C3C1
evidence because the retained configured starter lacks the current
`RED_Admin_Roles` baseline required by that suite's primary snapshot. C3C1
instead runs the exact affected Stripe and Wompi database tests inside its own
current-schema disposable database; the retained starter is not migrated.

## Explicit exclusions

C3C1 creates no Wompi account, real public key, secret value, provider request,
transaction, Nequi notification, payment, event processing, order mutation,
runtime route publication, browser flow, hosted-demo change, client data,
external package copy into core, or deployment.

## C3C2 closure

C3C2 completes exact package installation and enablement independently in two
fresh client databases with different public values and secret references. It
proves database-bound hashes, state, tables, lifecycle locks, declarative
runtime order, rollback, and disablement remain client-local, then cleans both
databases/grants with the configured primary unchanged. See
[`PAYMENT-ADAPTER-COLOMBIA-C3C2-TWO-CLIENT-ISOLATION.md`](PAYMENT-ADAPTER-COLOMBIA-C3C2-TWO-CLIENT-ISOLATION.md).
