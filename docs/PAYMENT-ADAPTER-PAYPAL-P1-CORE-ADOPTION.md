# PayPal P1 Core Profile and Registrar Adoption

Status date: 2026-09-01.

## Outcome

RED-CMS now recognizes only exact package id `redcms.store-lite-paypal` as
closed profile `store_lite_paypal_adapter_v1`. The extension is data-only and
non-activating. It reads no request, environment, database, credential, or
network state.

The registration-only boundary also recognizes this profile. It may include
the integrity-checked package entrypoint to observe the declared adapter and
provider-event route, but it does not invoke either handler, publish the
request-local registry, expose the route, resolve a secret, contact PayPal, or
change state.

## Exact profile

The profile requires:

- package `redcms.store-lite-paypal`;
- adapter `redcms.store-lite-paypal/checkout`;
- Store Lite dependency `>=0.1.50 <1.0`;
- ordinary unset settings `checkout.return-origin` and
  `paypal.webhook-id`;
- opaque secret references `paypal.client-id` and `paypal.client-secret`;
- migrations, in order, `2026-09-01-paypal-order-attempts` and
  `2026-09-01-paypal-event-receipts`, with their exact package paths;
- `server-signature` POST route
  `redcms.store-lite-paypal/provider-events` at
  `/addons/redcms/store-lite-paypal/provider-events`; and
- sole server outbound host `api-m.sandbox.paypal.com`.

Changed package identity, adapter, dependency range, setting key/shape,
migration identity/path/order, route identity/path/method, production PayPal
host, permission, or public-mutation surface fails closed.

## Test-first evidence

Before implementation, the exact PayPal manifest failed profile validation
because core classified it as Stripe-shaped. After the closed provider branch
was added, the profile test passed 39 assertions, including all ten external
package integrity files.

Before registrar adoption, the exact PayPal registration plan failed final
validation because the registrar expected a Stripe profile. After adding the
PayPal profile identity and exact adapter/route checks, the registrar test
passed 10 assertions.

Preserved regressions:

- Stripe profile: 26 assertions;
- Wompi profile: 30 assertions;
- existing Stripe/Wompi registrar: 18 assertions;
- server-event ingress: 31 assertions;
- typed adapter invocation: 19 assertions; and
- provider-neutral initiation: 59 assertions.

## Remaining blockers

Profile validation retains atomic enablement, database-bound preflight,
registrar validation, and server-event ingress blockers. The standalone
registrar proof clears only registrar validation and retains atomic enablement
plus server-event ingress. A disposable database rehearsal must prove package
installation, both migrations, InnoDB tables, dependency evidence, and exact
cleanup before either blocker can be reduced further.

No database, installation, migration, credential, OAuth token, PayPal order,
capture, payment, webhook, browser, Store Lite mutation, hosted-demo change, or
deployment occurs in P1.
