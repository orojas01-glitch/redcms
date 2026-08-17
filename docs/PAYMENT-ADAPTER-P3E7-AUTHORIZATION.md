# P3E-7 Provider-Contact Authorization

Status: core now revalidates and atomically records one Owner-authorized,
nonce-bound permission for a possible future read-only Stripe Sandbox contact.
It still performs no provider contact.

## Boundary

P3E-7 consumes only the closed, value-free P3E-6 `readiness` and `prepared`
objects. Core independently requires:

- exact package `redcms.store-lite-stripe-checkout` version `0.1.1`;
- current trusted package code matching its enabled client registry row;
- enabled `redcms.store-lite` in the same selected database;
- a database-backed Owner with the exact `addons.enable` grant;
- the core-derived client/actor Owner-subject SHA-256;
- unchanged canonical P3E-6 plan and authorization hashes;
- an active UTC window no longer than 15 minutes; and
- all retry, mutation, Checkout creation, payment, webhook, live-mode, and
  client-deployment permissions remaining false.

The first CLI mode prints only the client-bound Owner-subject hash needed to
prepare the external envelope. A separate dry run validates a JSON file with
exactly `readiness` and `prepared` objects and leaves the nonce unused.

## Atomic authorization

Apply acquires the shared lifecycle and adapter-package locks, starts an
InnoDB transaction, locks the Owner role/grant and Store Lite/adapter registry
rows, and recomputes the complete decision. The existing immutable
`RED_Addon_Admin_Action_Executions` ledger records:

- a nonce-derived one-time action id;
- plan and authorization SHA-256 values;
- the core-derived Owner-subject and resulting authorization-state hashes;
- the fixed package-authorization target plus numeric actor; and
- the completion timestamp.

The ledger primary key makes the same nonce unusable under either the original
or a changed envelope. One `addon.action.completed` /
`provider_contact_authorized` audit fact commits in the same transaction.
Audit failure rolls back the nonce reservation.

The successful result sets `contactAuthorized=true` only for one later runner
to claim. It keeps `executionPerformed=false`, and performs no secret
resolution, environment read, DNS, TLS, HTTP, Stripe call, Checkout creation,
payment, webhook, Store Lite mutation, browser route, or client deployment.

## Server-local workflow

```sh
php scripts/admin-provider-contact-authorize.php \
  --actor-admin=OWNER_ID \
  --show-owner-subject

php scripts/admin-provider-contact-authorize.php \
  --actor-admin=OWNER_ID \
  --evidence-file=/absolute/path/p3e6-evidence.json
```

The second command is always a dry run unless `--apply` and all exact
database, package, version, authorization-hash, nonzero-backup-hash, and
enabled-state confirmations are supplied. The evidence file contains no
credential value or value hash.

## Acceptance

The focused disposable-database fixture passes 33 assertions covering exact
P3E-6 shapes, package and same-database Store Lite state, current Owner
authority, client-bound subject matching, deterministic hashes, zero-write dry
run, atomic nonce/audit commit, original and changed-envelope replay refusal,
expiry, revocation, disabled dependency, audit rollback, package non-execution,
forbidden network/credential primitives, exact fixture cleanup, and final
`database:0 grant:0` cleanup.

The clean starter, 46 migrations, and 35-table schema are unchanged. P3E-7
reuses the existing core action ledger; it adds no package, seeded data,
credential, provider account, network client, endpoint, or client activation.

## Next stop

P3E-8 may define an atomic one-attempt claim and a separately approved
read-only Stripe Sandbox resource-miss probe. It must revalidate this exact
authorization, claim it before contact, resolve only the owning package's
restricted sandbox secret at the final boundary, and retain no key, response
body, or reusable authorization. No provider request is authorized by P3E-7.
