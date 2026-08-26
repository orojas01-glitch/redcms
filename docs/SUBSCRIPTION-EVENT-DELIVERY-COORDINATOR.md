# Subscription Event Delivery Coordinator

Status: complete as an unlinked internal recovery boundary for Store Lite
`0.1.50` and Stripe adapter `0.1.14`.

The coordinator joins four previously separate stages in one operation:

1. verify the bounded Stripe Sandbox signature envelope;
2. inspect or atomically claim the hash-only event receipt;
3. project the matching raw Stripe event into the bounded verified-event
   contract; and
4. apply the provider-neutral lifecycle event through Store Lite, then complete
   the receipt.

The receipt is claimed before projection or lifecycle access. An already
completed receipt returns its stored evidence hashes and stops. If Store Lite
commits but receipt completion fails, the receipt remains `verified`. On the
next invocation, matching last-event evidence reconstructs the same canonical
lifecycle-result hash without a second Store Lite mutation or adapter
normalization, and the receipt completes. A lifecycle refusal with a valid
projected intent closes as `refused`; an event that cannot safely project an
intent remains visibly `verified` and fail-closed for later operational review.

The focused fixture uses the actual adapter signature verifier, bounded JSON
decoder, raw-event projector, and verified-event normalizer with synthetic
in-memory data. It covers first application, terminal replay, a forced
post-lifecycle receipt-completion interruption, exact recovery, invalid
signature refusal, malformed correlation metadata, and private-data exclusion.

The disposable database rehearsal installs and enables exact Store Lite
`0.1.50` and Stripe adapter `0.1.14` packages, executes the same interruption
and recovery against the real transactional receipt and Store Lite lifecycle
tables, proves only one additional lifecycle-history row, and removes the
database, grant, and staged project while the configured primary database hash
remains unchanged.

No public dispatcher or front-controller route invokes this helper. It resolves
no real secret, contacts no provider, activates no webhook endpoint, emits no
response, navigates no browser, processes no payment, and deploys nothing.
