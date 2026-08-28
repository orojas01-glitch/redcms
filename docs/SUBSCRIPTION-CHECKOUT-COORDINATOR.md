# Subscription Checkout Coordinator

RED-CMS 5.1 includes an internal, non-routable coordinator for the separately
distributed Store Lite and Stripe Checkout add-ons. The first closed binding is:

- RED-CMS `5.1.0`
- Store Lite `0.1.50`, service `commerce.subscriptions`
- Stripe Checkout adapter `0.1.16`, adapter
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

## Gated public browser handoff

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

The operational public-mutation endpoint now links this handoff only for the
exact Store Lite subscription-intent route. It recomputes the subject and
offer from the already validated request, requires an explicit server-local
Sandbox enable flag and authorization hash, resolves only the Stripe API-key
scope, records the one-attempt provider journal before contact, persists the
pending/inactive lifecycle, and emits the Checkout URL only after every stage
completes. Ordinary public mutations retain their existing response path.

The front controller also owns fixed no-store return pages at
`/subscription/complete` and `/subscription/cancel`. The success copy does not
claim entitlement before a signed provider event activates it.

## Real provider-operation boundary

Stripe adapter `0.1.16` retains the exact real subscription POST operation, while
core adds an unlinked one-attempt coordinator and durable hash-only journal.
The journal commits `started` before adapter access, records only hashed terminal
evidence, and permanently refuses retry after either a started or completed
attempt. A successful sealed result must persist Store Lite pending/inactive
state and a completed journal row before it can enter the redacted browser
handoff. See `docs/SUBSCRIPTION-CHECKOUT-PROVIDER-OPERATION.md`.

## Verified subscription-event boundary

Store Lite `0.1.50` adds the read-only
`subscription.lifecycle.load` operation, and Stripe adapter `0.1.16` retains the
pure `subscription.event.normalize-sandbox-verified` operation. The unlinked
core event coordinator loads the current lifecycle from Store Lite, asks the
adapter to normalize one already-verified bounded event, and submits only the
resulting provider-neutral event to `subscription.event.apply`.

Activation, renewal, past-due revocation, cancellation, and uncompleted
Checkout expiry are closed transitions. Raw provider event, Checkout, and
Subscription references do not enter the core result. Repeated delivery after
a completed transition is refused without adding a second lifecycle-history
row.

This helper does not parse a request, verify a signature, resolve a webhook
secret, expose a route, contact Stripe, or emit a response. Those ingress
effects remain a separate gate.

Adapter `0.1.16` retains the fourth hash-only receipt migration. Core's unlinked
transactional journal inspects, claims, and completes those receipts under row
locks and exact enabled-package identity. The disposable rehearsal proves
`absent → verified → applied`, then returns the completed lifecycle-result hash
on replay without invoking Store Lite again. Drift, duplicate completion, and
caller-owned transactions fail closed.

The restartable delivery coordinator now joins signature verification,
transactional receipt claim, raw-event projection, and Store Lite lifecycle
application in one internal operation. Lifecycle-result evidence is canonical
across first application and recovery. If Store Lite commits but receipt
completion is interrupted, the next invocation recognizes the exact last-event
evidence, performs no second lifecycle mutation or adapter normalization, and
closes the original receipt with the same result hash. Completed receipts stop
before projection and lifecycle work.

Run the focused and cross-repository offline checks with:

```text
php scripts/addon-subscription-checkout-coordinator-self-test.php
php scripts/addon-subscription-checkout-public-response-self-test.php
php scripts/addon-subscription-checkout-public-runtime-self-test.php
php scripts/addon-subscription-checkout-provider-operation-self-test.php
php scripts/addon-subscription-event-coordinator-self-test.php
php scripts/addon-subscription-event-delivery-coordinator-self-test.php
scripts/subscription-checkout-launch-rehearsal.sh
node scripts/subscription-checkout-browser-qa.cjs
```

The database rehearsal stages the clean core and both external packages into a temporary
project, applies all core and package migrations to a fresh database, enables
both packages with opaque secret references only, coordinates and replays one
synthetic subscription, applies one signed synthetic activation event, injects
a receipt-completion interruption, recovers without another lifecycle row,
replays the completed receipt, builds the redacted public response, disables the
adapter to prove fail-closed ownership, and removes the database, grant, and
staged project. It verifies the configured primary database is unchanged. The
separate browser rehearsal covers desktop, mobile, keyboard submission,
responsive overflow, console/network errors, cancellation before external
navigation, and foreign-origin refusal.

## Remaining launch gates

The public bridge remains disabled by default. Launch still needs:

1. separately authorized server configuration/deployment;
2. one published Store Lite subscription offer and component placement;
3. one real Sandbox browser navigation, return, and provider-originated
   activation-event rehearsal; and
4. separately authorized live-mode configuration and smoke purchase.
