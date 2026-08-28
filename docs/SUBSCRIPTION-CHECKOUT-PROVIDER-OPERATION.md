# Subscription Checkout Provider Operation

The provider-operation contract binds RED-CMS 5.1, Store Lite `0.1.50`, and
Stripe adapter `0.1.16`.

`includes/addon_subscription_checkout_provider_operation_helpers.php` requires
one short-lived, one-attempt authority packet, reloads the exact client-local
intent and offer, derives a deterministic operation plan, and uses a supplied
journal in this order:

1. inspect for a previous attempt;
2. durably record `started` before adapter or secret access;
3. invoke only `subscription.checkout.create-sandbox-real-post`;
4. validate the bounded provider result and persist hashed pending/inactive
   Store Lite lifecycle evidence;
5. durably record `completed`; and
6. return the transient Checkout URL to the existing redacted browser handoff.

Any existing `started` or `completed` row permanently refuses another adapter
invocation and returns no URL. A malformed/throwing post-start result, Store
persistence failure, or journal-completion failure is indeterminate and also
non-retryable.

`includes/addon_subscription_checkout_provider_journal_helpers.php` implements
the journal in the adapter-owned
`RED_Addon_StoreLite_Stripe_Subscription_Checkout_Operations` table. It stores
only the intent reference, subject/offer identifiers, plan/claim/start/result
hashes, Checkout Session hash, status, and timestamps. It has no Checkout URL,
credential, provider body/header, customer data, or payment data.

The focused sealed tests and 14-assertion disposable cross-repository rehearsal
prove exact ordering, package-version refusal, one-attempt authority, durable
start/result state, replay refusal, transient redacted response compatibility,
pending/inactive Store state, and exact cleanup. The provider exchange is an
in-memory sealed double; no DNS, TLS, HTTP, Stripe, secret resolution, Checkout,
browser navigation, webhook, payment, client, demo, or deployment effect occurs.

## Operational gate

The public runtime remains disabled until server-local configuration supplies
an owner-confirmed authorization hash. Each accepted intent creates a fresh
short-lived authority packet tied to the current package, client database,
secret-availability state, plan, and expiry evidence. Only the restricted
Stripe Sandbox API-key scope enters the adapter request; the webhook secret is
withheld. Browser navigation and signed webhook lifecycle confirmation remain
separate evidence.
