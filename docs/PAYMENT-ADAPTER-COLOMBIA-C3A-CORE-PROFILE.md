# Colombia C3A Closed Core Wompi Profile

Status: complete on `main` through PR #162 at `f0ee7e0`. C3A changes only
non-executing core manifest-profile
validation and its tests. No database, package installation, registrar
execution for Wompi, setting, secret, network, provider, or deployment effect
occurs.

## Narrow extension

`includes/addon_payment_adapter_preflight_helpers.php` continues to classify
every existing Stripe fixture as
`store_lite_stripe_checkout_adapter_v1`. Only exact package id
`redcms.store-lite-wompi` selects the new
`store_lite_wompi_adapter_v1` profile.

The Wompi profile requires exactly:

- package id `redcms.store-lite-wompi`;
- adapter `redcms.store-lite-wompi/checkout`;
- one exact Store Lite dependency `>=0.1.35 <1.0`;
- one ordinary unset setting `wompi.public-key`;
- secret-reference settings `wompi.private-key`, `wompi.integrity-key`, and
  `wompi.event-secret` with no defaults;
- migrations in exact id/path order:
  `2026-08-23-wompi-payment-attempts` then
  `2026-08-23-wompi-event-receipts`;
- server-signature POST route
  `redcms.store-lite-wompi/provider-events` at
  `/addons/redcms/store-lite-wompi/provider-events`; and
- the sole outbound host `sandbox.wompi.co`.

The profile result now signs normalized ordinary/secret setting-key lists in
addition to their counts. Profile validation independently requires both lists
to be ordered, unique, valid permission-style identifiers, disjoint, and
count-consistent. A forged profile cannot substitute another third secret and
recompute only the outer fingerprint.

## Preserved Stripe behavior

The Stripe profile id, two-secret rule, `api.stripe.com` host, route/dependency
surface, blockers, non-effect flags, and deterministic validation remain
unchanged. No downstream database, registrar, ingress, enablement,
authorization, claim, execution, command, or adapter helper is modified in
C3A.

Focused regressions pass:

- existing Stripe P3A-1 profile: 26 assertions;
- existing Stripe P3A-3 registrar: 13 assertions;
- existing Stripe P3A-4 server-event ingress: 26 assertions;
- existing Stripe P3E-9B2 synthetic checkout: 37 assertions; and
- generic typed adapter invocation: 19 assertions.

## Wompi acceptance

`scripts/addon-payment-adapter-wompi-profile-self-test.php` passes:

- 30 assertions with a dependency-free exact manifest fixture; and
- 41 assertions when pointed to the published Wompi `0.1.0` package, including
  all nine integrity files.

The test refuses changed package/adapter/dependency identities, dependency
range, public setting, missing/renamed/defaulted/extra secret, migration id/
path/order, route id/path/method, Stripe or production host, permissions, and
public-mutation surface. It also proves the helper still contains no request,
environment, database, secret-value, or network primitive.

The exact package rehearsal command is:

```sh
php scripts/addon-payment-adapter-wompi-profile-self-test.php \
  --package-root=/path/to/redcms-store-lite-wompi/package
```

## Remaining blockers

The profile remains `contractReady=true` but `activationSupported=false` with
the same four blockers:

- atomic payment-adapter enablement;
- database-bound adapter preflight;
- registrar validation; and
- server-event ingress.

At C3A close, the Wompi package was not yet installable or enable-ready because
downstream database/registrar/ingress/enablement proof remained separately
gated. C3B closes only the database and registrar portions below.

## C3B closure

C3B completed the downstream database and registrar proof in a separate batch.
It installs the exact published package into a fresh disposable database as
`installed_disabled`, applies its two reviewed migrations, validates its
contained adapter/refusing-route registrations, and cleans the database/grant/
stage with the configured primary unchanged. C3C still owns enable/disable and
two-client isolation. See
[`PAYMENT-ADAPTER-COLOMBIA-C3B-DISPOSABLE-LIFECYCLE.md`](PAYMENT-ADAPTER-COLOMBIA-C3B-DISPOSABLE-LIFECYCLE.md).
