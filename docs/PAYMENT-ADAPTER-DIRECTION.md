# Store Lite Payment Adapter Direction

Status: Gate P0 is complete as a provider-neutral contract. Gate P1 selects
Stripe Checkout's hosted full-page flow as the first USD hosted-card adapter
candidate, recorded in
[`PAYMENT-ADAPTER-P1-DECISION.md`](PAYMENT-ADAPTER-P1-DECISION.md). No payment
adapter, provider account, provider credential, webhook, outbound payment
request, customer charge, payment migration, or payment state change is
implemented by these documents.

## Purpose

Store Lite 0.1.31 is complete for catalog, cart, guest checkout, pickup,
delivery, pay on receipt, and bounded order administration in the isolated
`demo.red-sphere.com` installation. Hosted payment is a later optional
capability for a client that explicitly elects it.

This direction keeps payment behavior outside the reusable RED-CMS core and
outside the Store Lite base package. It defines the minimum contract that a
later provider-specific adapter must satisfy before it may create a hosted
checkout or accept a payment event.

## Product Boundary

The base Store Lite package remains usable with no online payment method.
Pay on receipt is an order option; it is not a payment event and must never
pretend that money was collected.

A hosted payment adapter is an optional, separately versioned package and
lifecycle unit for one provider. Installing or enabling Store Lite must not
install, configure, enable, or contact an adapter. Installing an adapter must
not mark an existing order paid or change a customer's checkout experience
until that client completes its own configuration and release gate.

The first provider is deliberately not selected in Gate P0. A later selection
must be based on the client's country, installation currency, business type,
payout requirements, refund support, hosted-checkout capability, webhook
verification, sandbox availability, current terms, and fees. A payment adapter
must not make Store Lite depend on PayPal, Stripe, Square, or any other
provider's field names, URLs, or callback conventions.

## Logical Model

Store Lite owns the commercial order and immutable order-line snapshot. A
provider adapter may create and verify payment-attempt facts for that order,
but it does not own products, prices, inventory, carts, fulfillment, or
administrator order permissions.

The later package-owned payment model must keep these concepts separate:

| Concept | Owner | Required boundary |
| --- | --- | --- |
| Order snapshot | Store Lite | Server-derived amount, ISO currency, lines, fulfillment choice, and customer data are immutable after creation. |
| Payment attempt | Selected adapter | Opaque provider checkout reference, selected provider identity, current attempt state, and internal idempotency relation. |
| Provider event | Selected adapter | Opaque provider event identity, verified event type/outcome, received time, signature result, and bounded evidence only. |
| Order status transition | Store Lite service | Applies only a normalized verified event through a closed state transition. |

No raw card number, security code, bank account, wallet credential, provider
access token, webhook signature, full provider payload, or customer payment
instrument may enter RED-CMS storage, logs, audit facts, browser output, or
the clean starter.

## Checkout Creation Contract

Only the Store Lite service may ask an enabled selected adapter to create a
checkout. It must first lock and reload the current order, then pass a closed
request containing only:

- the internal order identity;
- the immutable expected total in integer minor units;
- the validated installation currency;
- one opaque internal idempotency relation;
- one approved return-status URL; and
- bounded display facts already present in the order snapshot, if the selected
  provider requires them.

The adapter returns only an opaque provider checkout reference and a validated
absolute HTTPS hosted-checkout URL. The browser can navigate to that URL, but
a browser return, cancellation, timeout, query string, or client-side script
cannot create an order, mark one paid, or change fulfillment status.

The adapter may create at most one current checkout attempt for the same order
and internal idempotency relation. Retry behavior must either return the same
attempt or refuse deterministically; it must not create a second commercial
order or a second paid transition.

## Verified Event Contract

Only a later selected adapter's server-to-server event handling may propose a
payment transition. It must preserve the provider's raw event body only in
memory long enough to verify the signature before parsing or normalizing it.
It must then verify all applicable facts before Store Lite receives a normalized
event:

- provider signature and permitted timestamp/window;
- provider event identity and duplicate/replay relation;
- adapter/provider identity and current client installation;
- opaque checkout reference mapped to exactly one current order;
- captured or refunded amount in integer minor units;
- exact installation currency;
- expected payment outcome and provider-side status; and
- any required server-to-server provider lookup for the selected provider.

The normalized event contains no raw provider payload or credential. It may
propose only one of `paid`, `refund_confirmed`, `reversal_reported`,
`failed`, `cancelled`, or `expired` with bounded opaque evidence.

Store Lite may move an order from `awaiting_payment` to `paid` only after an
exact verified `paid` event matches its immutable amount, currency, and order
identity. A verified refund may transition an eligible order to `refunded`
through the existing closed order-state policy. A reversal or dispute must
append bounded risk evidence and block automatic fulfillment; its exact
operator resolution is a separate future state-machine gate. Failed,
cancelled, and expired attempts do not mutate an order into `paid`.

## Secrets, Network, And Webhook Safety

- Credentials are server-local secret references for one client and one
  adapter. They are never package defaults, manifests, migrations, fixtures,
  repository files, browser responses, or administrator form values.
- Each selected adapter declares exact reviewed outbound hosts and webhook
  paths. Unknown hosts, redirects, provider identities, or callback paths fail
  closed.
- A webhook endpoint requires its own reviewed ingress, raw-body, method,
  content-type, size, rate-limit, idempotency, and response contract. It is
  not implied by Store Lite's existing customer checkout routes.
- Signature verification precedes provider-event parsing and order lookup.
  Network origin or IP address may be supplementary evidence but never replaces
  cryptographic verification.
- Event receipts, order transitions, refunds, reversals, secret rotation, and
  configuration changes require value-free bounded audit facts. Logs must not
  retain raw event bodies, checkout URLs, credentials, customer payment data,
  or unredacted provider errors.
- Hosted checkout keeps card and wallet collection on the provider's approved
  surface. RED-CMS must not host card-entry fields or claim a PCI scope it has
  not independently established.

## Client Isolation And Lifecycle

Each enabled adapter belongs to exactly one client installation, its own
database, its own Store Lite package state, its own server-local credentials,
and its own provider configuration. No provider event, checkout reference,
secret reference, order, or payment attempt may cross that boundary.

The adapter is disabled by default. Disablement stops new checkout creation and
event processing without deleting payment-attempt or order evidence. Uninstall
and purge remain separate explicitly confirmed operations. An adapter upgrade
must preserve immutable recorded provider identities and event replay evidence,
or fail closed and recover through a reviewed package migration path.

## Acceptance Ladder

1. **P0 — complete:** this provider-neutral contract, threat boundary, state
   rules, and clean-starter exclusion are reviewed.
2. **P1 — complete provider decision:** Stripe Checkout's hosted full-page
   flow is the first candidate for a provisional USD online-card pilot. The
   decision remains reversible and does not choose a provider for another
   client; see [`PAYMENT-ADAPTER-P1-DECISION.md`](PAYMENT-ADAPTER-P1-DECISION.md).
3. **P2 — complete non-network adapter fixture:** the CLI-only Stripe Checkout
   contract fixture proves the closed checkout request, opaque response,
   raw-body verification boundary, event normalization, replay refusal,
   amount/currency mismatch refusal, refund, reversal, and browser-return
   non-authority with no provider account, credential, SDK, request, payment,
   database, or package. See
   [`PAYMENT-ADAPTER-P2-FIXTURE.md`](PAYMENT-ADAPTER-P2-FIXTURE.md).
4. **P3 — sandbox integration:** configure one disposable client-only adapter
   environment; prove signature verification, server-side provider lookup when
   required, idempotency, secret isolation, disable/re-enable behavior,
   rollback, and exact cleanup with no live charge. The approved planning
   boundary and its separate implementation stops are recorded in
   [`PAYMENT-ADAPTER-P3-SANDBOX-PROPOSAL.md`](PAYMENT-ADAPTER-P3-SANDBOX-PROPOSAL.md);
   P3A through P3D and the first read-only P3E provider contact are complete.
   P3E-9A and P3E-9B synthetic-only package/core integration are complete.
   P3E-9C1 records mutation-specific authorization, and P3E-9C2 records its
   one-attempt claim. P3E-9C3A transport-double start/result is complete, and
   P3E-9C3B1 CLI command and P3E-9C3B2 disposable apply rehearsal are complete.
   P3E-9D0 pure real-POST preflight, external adapter P3E-9D1 `0.1.7`, and
   core P3E-9D2 response containment/identity are complete. P3E-9D3A adds the
   CLI-only dry-run-first command contract, and P3E-9D3B completes the
   disposable no-contact rehearsal with exact package sources, zero provider
   effects, no database, unchanged repositories, and exact cleanup. P3E-9D3
   is complete. D4A is also complete in separately distributed adapter `0.1.8`
   with no Stripe contact; repair commit `44ed7b3` restores exact package
   integrity. D4B fresh authority, durable start/result, and sealed in-memory
   execution are also complete. D4C CLI/no-contact rehearsal is complete with
   real apply held at zero, and D4D remains one separately authorized real POST.
   Payment, webhook, and deployment remain later gated.
5. **P4 — deployment review:** approve one client's ingress, secret rotation,
   outbound-host allowlist, operational order workflow, browser behavior,
   backups, retention, and rollback plan. A separate explicit approval is
   required before any production credential or real transaction.

Every implementation gate must run against disposable databases and isolated
fixtures, leave no payment package or business data in the clean starter, and
preserve every other client installation and database.

## Explicit Exclusions

Gate P0 does not select a provider, add a core payment abstraction, create
tables or migrations, add a checkout button, issue a session/cookie, expose a
webhook, submit a provider request, store credentials, make a charge, handle a
refund, change order data, or alter the hosted demo. It also does not extend
payment behavior to Events, Appointments, Donations, Restaurant Ordering, or
Member Access. Those verticals may reuse a proven contract later through their
own separately approved packages.
