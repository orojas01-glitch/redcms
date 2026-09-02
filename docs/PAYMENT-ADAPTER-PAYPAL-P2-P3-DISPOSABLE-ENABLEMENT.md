# PayPal P2-P3 Disposable Installation and Atomic Enablement

Status date: 2026-09-02.

## Outcome

The exact external `redcms.store-lite-paypal` `0.1.0` package now completes a
fresh, isolated RED-CMS lifecycle through atomic enablement without contacting
PayPal. The tests use only synthetic ordinary setting values and opaque secret
reference identifiers; they never supply, resolve, print, or persist a PayPal
credential value.

## P2 disabled installation

The guarded rehearsal:

1. pins clean Store Lite `0.1.50` and PayPal `0.1.0` repository commits;
2. creates one uniquely named `redcms_paypal_p2_*` database and scoped grant;
3. installs the starter schema and all 47 current core migrations;
4. records Store Lite as the already-proven enabled dependency baseline
   without loading Store Lite package PHP;
5. installs PayPal as `installed_disabled` and applies exactly
   `2026-09-01-paypal-order-attempts` and
   `2026-09-01-paypal-event-receipts`;
6. proves both declared PayPal tables exist as empty InnoDB tables with no
   setting rows;
7. validates database and registration-only evidence without invoking or
   publishing a handler;
8. refuses repeat installation; and
9. removes the grant, database, and staged project while proving the configured
   starter database is unchanged.

The inner test passes 16 assertions.

## PayPal ingress capture

The closed non-routable ingress plan requires exact ordered headers:

- `Content-Type`;
- `Content-Length`;
- `PayPal-Auth-Algo`;
- `PayPal-Cert-Url`;
- `PayPal-Transmission-Id`;
- `PayPal-Transmission-Sig`; and
- `PayPal-Transmission-Time`.

The boundary captures those values and the exact raw body only as transient
verification material. Missing, extra, reordered, empty, or legacy-PDT-target
input fails closed. The plan does not verify the signature, fetch a
certificate, perform PayPal's verification postback, parse JSON, invoke a
handler, emit a response, expose the route, or access a database/network. Its
focused suite passes 13 assertions.

## P3 atomic enablement

A second fresh disposable run stores only:

- synthetic return origin `https://demo.red-sphere.com`;
- synthetic webhook ID `WH-TEST-PAYPAL-P3-0001`; and
- two opaque availability references for the client ID and client secret.

The value-free enable plan signs the complete database, registrar, ingress,
setting-state, and secret-availability evidence. It excludes the ordinary
values and opaque reference identifiers. The test proves:

- a missing secret declaration is refused without resolution;
- a stale plan cannot mutate lifecycle state;
- a forced failure after compare-and-swap rolls back state and audit together;
- the exact confirmed plan commits one `enabled` state and one bounded audit
  fact while both payment tables remain empty; and
- repeat enablement is refused.

The inner test passes 17 assertions.

Both runs finish with:

```text
database:0 grant:0 staged-project:0 primary:unchanged
```

## Commands

Disabled installation and registrar proof:

```sh
scripts/paypal-payment-adapter-p2-rehearsal.sh
```

Ingress plus atomic enablement proof:

```sh
scripts/paypal-payment-adapter-p2-rehearsal.sh --enable
```

## Remaining gate

One-client enablement does not prove client isolation. The next offline gate is
two independent disposable client databases with different ordinary values and
opaque references, independent enable/disable transitions, distinct
client-bound evidence hashes, immutable package hashes, forced rollback, and
exact cleanup. Provider transports and real Sandbox credentials remain later
approval-gated work.
