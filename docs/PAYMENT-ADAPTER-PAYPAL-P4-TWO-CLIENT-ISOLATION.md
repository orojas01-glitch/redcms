# PayPal P4 Two-Client Isolation

Status date: 2026-09-02.

## Outcome

The PayPal offline lifecycle is now verified across two independent disposable
RED-CMS client databases. Both use the exact same Store Lite `0.1.50` and
PayPal `0.2.0` package bytes, but different client-local ordinary values and
opaque secret references.

No PayPal account, credential value, OAuth token, provider request, order,
capture, payment, webhook verification/response, browser, hosted-demo change,
or deployment is involved.

## Rehearsal

`scripts/paypal-payment-adapter-p4-two-client-rehearsal.sh`:

1. pins clean Store Lite and PayPal package repository revisions;
2. creates two uniquely named databases and two scoped grants;
3. installs the starter schema and all 47 current core migrations in each;
4. records Store Lite as the enabled dependency baseline in each;
5. guarded-installs PayPal and its exact two migrations as disabled in each;
6. stores different synthetic return origins, webhook IDs, and opaque client
   ID/secret reference identifiers;
7. independently plans and atomically enables PayPal in each database;
8. compares immutable and client-bound evidence;
9. tests independent lifecycle locks;
10. forces a client A disable rollback while proving both client fingerprints
    remain unchanged;
11. disables client A successfully while client B remains byte-for-byte
    unchanged and enabled;
12. refuses repeat disablement; and
13. removes both grants, both databases, and the staged project while proving
    the configured starter database is unchanged.

## Isolation evidence

The following immutable hashes match across clients:

- adapter contract;
- package manifest; and
- integrity inventory.

The following database-bound hashes differ:

- database identity;
- setting state;
- secret availability;
- registration evidence;
- ingress contract; and
- enablement plan.

Neither database contains the other client's return-origin marker, webhook-ID
marker, or opaque secret-reference marker. Returned evidence contains hashes
only and excludes those values and reference identifiers.

Both clients initially have exact `enabled:4 settings:2 migrations:2 tables`
state with zero payment-attempt or event rows. Declarative runtime order is:

```text
redcms.store-lite
redcms.store-lite-paypal
```

After client A is disabled, only A excludes PayPal from later runtime order;
client B remains enabled with unchanged state.

## Lock and rollback evidence

Database-derived lifecycle locks may be held concurrently in A and B, while a
second connection to A is refused. The lock therefore serializes transitions
within one client without coupling distinct client databases.

The forced A disable failure rolls back both state and audit. The successful
retry changes only A to `installed_disabled`, retains its four setting rows,
two migration rows, two empty evidence tables, and records one disable audit.

## Acceptance and cleanup

The inner test passes 21 assertions. Final cleanup is:

```text
databases:0 grants:0 staged-project:0 primary:unchanged
```

The exact command is:

```sh
scripts/paypal-payment-adapter-p4-two-client-rehearsal.sh
```

## Next gate

P4 closes the offline package/core lifecycle and client-isolation boundary.
The next work is credential-free OAuth, create/capture, and webhook verification
transport with sealed doubles. Only after those pass should the owner enter
Sandbox client ID, client secret, webhook ID, and return origin for one bounded
PayPal Sandbox purchase.
