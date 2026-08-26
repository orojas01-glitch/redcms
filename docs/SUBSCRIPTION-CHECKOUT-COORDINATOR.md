# Subscription Checkout Coordinator

RED-CMS 5.1 includes an internal, non-routable coordinator for the separately
distributed Store Lite and Stripe Checkout add-ons. The first closed binding is:

- RED-CMS `5.1.0`
- Store Lite `0.1.49`, service `commerce.subscriptions`
- Stripe Checkout adapter `0.1.9`, adapter
  `redcms.store-lite-stripe-checkout/checkout`

The coordinator performs four typed stages in order:

1. Load an authoritative provider-neutral subscription intent and offer from
   Store Lite.
2. Ask the Stripe adapter to prepare a credential-free Sandbox contract.
3. Ask the adapter to validate a supplied synthetic Stripe response.
4. Persist only the Checkout Session hash and bounded lifecycle evidence in
   Store Lite as `pending` and `inactive`.

Every package id, version, service, adapter, intent hash, offer hash, Checkout
origin, response hash, lifecycle state, and external-effect flag is rechecked.
Exact replay is allowed without adding a second lifecycle-history row. Package
drift, a disabled boundary, a foreign redirect origin, malformed results, or a
claimed network/provider/browser effect fails closed.

The accepted Checkout URL is transient return data. The coordinator does not
persist it, emit an HTTP response, call `location.assign`, resolve a secret,
contact Stripe, create a Checkout Session or subscription, process a webhook,
or grant entitlement. Those are later launch gates with separate authorization.

Run the focused and cross-repository offline checks with:

```text
php scripts/addon-subscription-checkout-coordinator-self-test.php
scripts/subscription-checkout-launch-rehearsal.sh
```

The rehearsal stages the clean core and both external packages into a temporary
project, applies all core and package migrations to a fresh database, enables
both packages with opaque secret references only, coordinates and replays one
synthetic subscription, disables the adapter to prove fail-closed ownership,
and removes the database, grant, and staged project. It verifies the configured
primary database is unchanged.

## Remaining launch gates

This internal coordinator is not a public Checkout bridge. Launch still needs:

1. a reviewed POST response bridge that converts a successful coordinator
   result into the no-store `303`/browser handoff;
2. a fresh Stripe Sandbox provider-contact authorization and owner-entered
   secret availability;
3. one real Sandbox Checkout Session and browser return-path rehearsal;
4. externally reachable signed webhook ingestion and verified lifecycle events;
5. disposable browser, recovery, and client-isolation acceptance; and
6. separately authorized installation and deployment to `demo.red-sphere.com`.
