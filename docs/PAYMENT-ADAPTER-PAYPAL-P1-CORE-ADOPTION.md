# PayPal P1 Core Profile and Registrar Adoption

Status date: 2026-09-02.

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

## P2 and P3 closure

Two later disposable rehearsals now close the remaining offline one-client
gates. P2 passes 16 assertions for disabled installation, exact migrations,
two empty InnoDB tables, database evidence, registrar evidence, repeat-install
refusal, and audit facts. P3 passes 17 assertions for four synthetic settings,
two opaque secret declarations, missing-secret and stale-plan refusal, forced
transaction rollback, one successful atomic enablement, and repeat-enable
refusal.

The non-routable ingress contract passes 13 assertions and captures the exact
PayPal verification-header set plus raw body only as transient verification
material. It performs no signature verification, postback, JSON parsing,
handler invocation, response, route publication, or provider contact.

Both database runs applied all 47 core migrations in unique disposable
schemas. Cleanup proved `database:0 grant:0 staged-project:0
primary:unchanged`.

No credential value, OAuth token, PayPal order, capture, payment, webhook
verification/response, browser, Store Lite payment mutation, hosted-demo
change, client deployment, or live-mode action occurs in P1 through P3.
