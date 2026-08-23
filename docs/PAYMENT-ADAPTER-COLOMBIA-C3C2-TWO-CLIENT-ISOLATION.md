# Colombia C3C2 Wompi Two-Client Isolation

Status: complete on `main` through PR #165 at `7f4e29d`. C3C2 closes the
offline/disposable Colombia C3 gate
without changing a core runtime helper. It proves exact Wompi installation,
configuration, atomic enablement, rollback, disablement, and declarative
runtime-order isolation across two fresh client databases.

No provider account, real credential value, Wompi request, transaction,
payment, browser flow, hosted-demo change, or deployment is involved.

## Exact inputs

The rehearsal requires clean reviewed repositories:

- RED-CMS core based on C3C1 merge `91b4a62`;
- Store Lite `0.1.35` at
  `f7de77eb1694fb6003340632c5018024753fe1fa`; and
- Store Lite Wompi `0.1.0` at
  `e17a371d73f286f5586deae88ad2c73d2f233651`.

External packages are copied only into one temporary staged project. They are
never added to the clean RED-CMS starter.

## Two-client rehearsal

`scripts/wompi-payment-adapter-c3c2-rehearsal.sh` creates two uniquely named
databases and two database-scoped grants. Each receives the clean installer
and all 46 core migrations. One FrankenPHP process then uses separate MySQL
connections to execute the 21-assertion C3C2 test.

For each client independently, the test:

1. records an Owner with only `addons.install`, `addons.enable`, and
   `addons.disable` lifecycle capabilities;
2. records exact Store Lite `0.1.35` as the already-proven enabled dependency
   baseline;
3. guarded-installs Wompi `0.1.0` and its two migrations as
   `installed_disabled`;
4. stores one synthetic public value plus three client-specific opaque
   `config:` secret references;
5. creates three value-free availability declarations;
6. builds one exact body-ingress/atomic-enable plan; and
7. atomically enables Wompi with one bounded audit record.

## Isolation evidence

The two clients intentionally share the immutable package contract, manifest,
inventory, and staged code. Everything bound to client state differs:

- database identity hash;
- setting-state hash;
- secret-availability hash;
- registration hash;
- ingress-contract hash; and
- final enablement-plan hash.

Neither database contains the other client's public marker or opaque secret
reference. Both start with exact `enabled:4 settings:2 migrations:2 tables`
state and zero payment-attempt/event rows.

Declarative runtime load order is initially identical:

```text
redcms.store-lite
redcms.store-lite-wompi
```

No package registrar or handler is invoked by the runtime-order comparison,
and no secret value is resolved.

## Lock, rollback, and disablement

The database-derived lifecycle lock can be held simultaneously in client A and
client B, while a second connection to client A is refused. This proves the
lock serializes lifecycle transitions inside one installation without coupling
separate clients.

Client A then receives one exact non-executing disable plan. An injected
failure after its state update rolls back lifecycle state and audit together;
fingerprints for both A and B remain unchanged.

The successful retry changes only client A to `installed_disabled` and records
one disable audit. Its four setting rows, two migration rows, two tables, and
empty payment evidence are retained. Client B remains enabled and byte-for-byte
unchanged. Later declarative load order excludes Wompi only from A. A repeat
disable refuses without affecting B.

Bounded returned evidence contains hashes only; it includes no public setting
value or opaque reference.

## Cleanup

The shell trap runs on success, failure, interrupt, or termination. Final
evidence is:

```text
databases:0 grants:0 staged-project:0 primary:unchanged
```

Mac sleep prevention is active only for the rehearsal process.

## C3 closure and C4 boundary

Colombia C3 is complete through C3A profile validation, C3B installation and
registrar proof, C3C1 body-signed ingress and atomic enablement, and C3C2
two-client enable/disable isolation.

C4 is separately owner-gated. Before any Wompi Sandbox request, the owner must
complete current merchant/account acceptance, review current official provider
terms and test-data rules, enter client-local Sandbox public/private/integrity/
event credentials outside Git, confirm redacted evidence and cleanup handling,
and explicitly authorize the bounded provider-contact operation. C3C2 does not
grant that authorization.

C4A has since completed the dated official provider-contract/readiness audit
with public documentation reads only and no account/provider API contact. It
records current requirements and the hard C4B engineering blockers in
[`PAYMENT-ADAPTER-COLOMBIA-C4A-OFFICIAL-READINESS.md`](PAYMENT-ADAPTER-COLOMBIA-C4A-OFFICIAL-READINESS.md).

C4B1 later publishes package `0.1.1` at `7e4f8cb` and reruns the exact
single/two-client lifecycle proofs without changing core runtime helpers. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B1-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B1-CORE-ADOPTION.md).

C4B2 later publishes package `0.1.2` at `fdbf881` and repeats those exact
proofs for presentation/consent/transient-wire contracts. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B2-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B2-CORE-ADOPTION.md).
