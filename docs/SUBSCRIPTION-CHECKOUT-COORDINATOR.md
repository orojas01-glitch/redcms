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

## Unlinked public browser handoff

`includes/addon_subscription_checkout_public_response_helpers.php` now joins
only an exact completed or replayed Store Lite subscription-intent execution to
the matching coordinator result. It rechecks the subject, offer, Sandbox hosted
URL, no-store policy, and every external-effect denial before authorizing one
redacted AJAX result. The body contains only `ok`, the fixed
`subscription_checkout_ready` outcome, the transient Checkout URL, and
`location.assign`; subject ids, intent references, internal hashes, settings,
and credentials remain server-side.

The shared public-mutation browser controller accepts that result only from the
same-origin POST it already owns, independently validates
`https://checkout.stripe.com/c/pay/cs_test_...`, freezes the submitted command,
updates the live status region, and then requests `location.assign`. A
cancelable `redcms:subscription-checkout-ready` event permits the local browser
rehearsal to inspect the validated URL without navigating away or contacting
Stripe. Foreign origins, malformed responses, ordinary mutation responses, and
network failures retain their existing fail-closed behavior.

The response helper and emitter are deliberately not called by `index.php` or
the operational public-mutation endpoint yet. Linking them requires the later
provider operation to supply a real, validated Checkout result in the same
request after the subscription intent commits.

Run the focused and cross-repository offline checks with:

```text
php scripts/addon-subscription-checkout-coordinator-self-test.php
php scripts/addon-subscription-checkout-public-response-self-test.php
scripts/subscription-checkout-launch-rehearsal.sh
node scripts/subscription-checkout-browser-qa.cjs
```

The database rehearsal stages the clean core and both external packages into a temporary
project, applies all core and package migrations to a fresh database, enables
both packages with opaque secret references only, coordinates and replays one
synthetic subscription, builds the redacted public response, disables the
adapter to prove fail-closed ownership, and removes the database, grant, and
staged project. It verifies the configured primary database is unchanged. The
separate browser rehearsal covers desktop, mobile, keyboard submission,
responsive overflow, console/network errors, cancellation before external
navigation, and foreign-origin refusal.

## Remaining launch gates

This internal coordinator is not a public Checkout bridge. Launch still needs:

1. a fresh Stripe Sandbox provider-contact authorization and owner-entered
   secret availability;
2. one real Sandbox Checkout Session and connection of its validated result to
   the unlinked public response bridge;
3. one real browser navigation and return-path rehearsal;
4. externally reachable signed webhook ingestion and verified lifecycle events;
5. final supported-server recovery and client-isolation acceptance; and
6. separately authorized installation and deployment to `demo.red-sphere.com`.
