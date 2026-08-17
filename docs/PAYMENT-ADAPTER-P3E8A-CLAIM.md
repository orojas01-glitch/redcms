# P3E-8A Provider-Contact Attempt Claim

Status: core can now atomically claim one exact P3E-7 authorization for one
possible future provider-contact attempt. It still performs no provider
contact.

## Boundary

P3E-8A consumes the same exact value-free P3E-6 `readiness` and `prepared`
objects used by P3E-7. Core independently recomputes the P3E-7 decision and
requires:

- exact package `redcms.store-lite-stripe-checkout` version `0.1.1`;
- current trusted package code and enabled registry state;
- enabled `redcms.store-lite` in the same selected database;
- a current database-backed Owner with `addons.enable`;
- the core-derived database/actor Owner-subject SHA-256;
- the unchanged plan, envelope, nonce, and authorization-state hashes;
- the original authorization window still active and no longer than 15
  minutes; and
- the exact immutable P3E-7 authorization row already committed.

A missing, changed, expired, revoked, disabled, or already claimed decision
fails closed. The claim never extends the original expiry.

## Atomic claim

Dry run verifies that the P3E-7 row exists and no claim row exists. It writes
nothing. Apply requires exact database, package, version, authorization,
authorization-state, claim-state, nonzero-backup, and enabled-state
confirmations.

Apply holds the shared lifecycle and adapter-package locks, starts an InnoDB
transaction, locks current Owner authority plus Store Lite/adapter registry
rows, and locks the exact authorization/claim ledger identities. The existing
`RED_Addon_Admin_Action_Executions` table receives one distinct
`provider-contact-attempt-claim` action whose fields bind only:

- plan and authorization SHA-256 values;
- the previous authorization-state and resulting claim-state SHA-256 values;
- the nonce-derived action identity and fixed target; and
- the numeric Owner actor.

The table primary key makes the attempt claim one-time. A matching
`addon.action.completed` / `provider_contact_attempt_claimed` audit fact
commits in the same transaction. Audit failure rolls back the claim, leaving
the authorized attempt available. After a committed claim, process failure or
operator abandonment burns the attempt; P3E-8A authorizes no retry.

## Server-local workflow

```sh
php scripts/admin-provider-contact-claim.php \
  --actor-admin=OWNER_ID \
  --evidence-file=/absolute/path/p3e6-evidence.json
```

The command is always a dry run unless `--apply` and every printed exact
confirmation are supplied. The evidence file contains no credential value or
value hash.

## Acceptance

The disposable-database fixture passes 34 assertions after the unchanged
33-assertion P3E-7 regression. It covers exact persisted authorization,
deterministic claim state, zero-write dry run, atomic claim/audit commit,
replay, changed and missing authorization, expiry, authority revocation,
disabled dependency, tampered ledger evidence, audit rollback and safe
subsequent claim, package non-execution, forbidden credential/network
primitives, and exact cleanup.

The clean starter, 46 migrations, and 35-table schema remain unchanged.
P3E-8A reuses the existing immutable action ledger and adds no package, seed,
credential, provider account, network client, endpoint, or client activation.

## Next stop

P3E-8B may implement the separately approved one-shot read-only Stripe Sandbox
resource-miss probe. It must require this exact claim while the original window
is still active, resolve only the owning package's restricted test secret at
the final boundary, enforce the exact GET/TLS/timeout/body-discard contract,
and persist only a bounded outcome. It may not retry, create Checkout state,
process payment or webhook data, mutate Store Lite, enable live mode, or deploy
a client. No provider request is authorized by P3E-8A.
