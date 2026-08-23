# Colombia C2 External Wompi Package

Status: complete in the separately distributed public repository at commit
`e17a371d73f286f5586deae88ad2c73d2f233651`.

Repository:
<https://github.com/orojas01-glitch/redcms-store-lite-wompi>

## Exact package

- Package id: `redcms.store-lite-wompi`
- Version: `0.1.0`
- Type: `adapter`
- Adapter: `redcms.store-lite-wompi/checkout`
- Required dependency: `redcms.store-lite >=0.1.35 <1.0`
- Initial method/currency: Nequi / `COP`
- Initiation mode: `out_of_band_confirmation`
- Declared future Sandbox host: `sandbox.wompi.co`
- Payload integrity inventory: 9 exact files
- Package migrations: 2 unexecuted InnoDB evidence migrations

The public-key setting is ordinary, client-local, and unset. Private key,
integrity key, and event secret are separate secret-reference settings with no
default or value. The package declares one future server-signature POST route,
but its registered handler always throws `c2_route_handler_not_operational`.

## Offline implementation

The external repository contains five byte-identical source/package contracts:

1. a hash-only request planner fixed to Wompi Sandbox, Nequi, COP, POST
   `/v1/transactions`, and a deterministic self-fingerprint;
2. a strict synthetic PENDING response gate that emits exactly the merged C1
   URL-free out-of-band value;
3. a one-use plan-bound sealed transport double with no network primitive;
4. a bounded dynamic-property event verifier with a 25-hour retry-compatible
   window, checksum verification, replay refusal, and exact lookup agreement;
   and
5. a registration-only typed adapter whose only successful operation is
   `contract.probe`; every provider operation returns
   `provider_transport_disabled`.

The two migrations retain only client/order hashes, opaque transaction
reference, integer amount, COP, NEQUI, closed status/outcome, request/
acceptance/event hashes, and bounded timestamps. They contain no customer
email/phone, token, key, secret, raw event, provider body/header, redirect URL,
or checkout URL.

## Acceptance evidence

- Offline provider contract: 34 assertions passed.
- Package discovery/integrity/registrar/migration/source/refusal/cleanup:
  60 assertions passed.
- Total focused assertions: 94.
- PHP lint passed for all five source files, all five package copies, package
  registrar, and both tests.
- JSON, shell, internal-link, credential-shape, forbidden-primitive, and
  project-boundary audits passed.
- Local and GitHub `main` both resolve to the exact commit above.

## Deliberate C3 blocker

Generic RED-CMS add-on discovery validates the package, its dependency,
settings, route, migrations, outbound host, and all nine integrity hashes. The
contained generic registrar sees only the declared adapter and refusing route.

The current core payment-adapter profile is still
`store_lite_stripe_checkout_adapter_v1`; it requires exactly two secret
settings and outbound host `api.stripe.com`. It therefore refuses the Wompi
manifest with exactly:

- `outbound_host_invalid`; and
- `setting_contract_invalid`.

This refusal is required C2 evidence. The package is not claimed to be
installable, enable-ready, or runtime-ready. C3 must generalize or add a
separately closed core profile for this exact package while preserving every
existing Stripe assertion and refusal.

## C3 boundary

C3 may change core only to recognize this exact provider-neutral/Wompi profile
and prove it in fresh disposable databases. It must preserve:

- clean-starter exclusion of the external package;
- exact per-client database and settings/secret isolation;
- install-to-`installed_disabled` behavior;
- non-executing discovery and migration planning;
- contained registrar validation;
- disabled-by-default and no-route-publication behavior;
- exact enable/disable and rollback boundaries;
- two-client isolation and exact cleanup; and
- every existing Stripe profile and regression.

C3 must not create a Wompi account, key value, provider request, transaction,
Nequi notification, payment, public webhook ingress, browser flow, Store Lite
order mutation, hosted-demo change, client deployment, or production host.
Wompi Sandbox contact remains C4 and separately approval-gated.
