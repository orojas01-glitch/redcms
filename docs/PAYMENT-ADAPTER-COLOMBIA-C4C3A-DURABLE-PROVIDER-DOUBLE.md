# Colombia C4C3A Durable Provider-Double Gate

Status: complete in this change. C4C3A adds the durable execution shape needed
before any real read-only Wompi request, but invokes only a core-owned provider
double under a network-disabled disposable rehearsal.

## Durable boundary

The core accepts only fresh, exact, hash-only authorization evidence for the
current client database, Owner, Store Lite `0.1.35`, Wompi `0.1.5`, Sandbox
public-key state, three opaque references, and one authorization nonce. The
evidence expires after 15 minutes and fixes:

- one maximum attempt and no retry;
- provider-double-only execution;
- disabled network and real provider contact; and
- no provider mutation, transaction, payment, event registration, or order
  mutation.

Planning is zero-write. Apply acquires the lifecycle and Wompi package locks,
revalidates authority and current state, then commits one immutable start row
and audit before invoking the final typed provider double once. A second
transaction records one bounded completed or indeterminate result and audit.
The existing `RED_Addon_Admin_Action_Executions` ledger is reused; C4C3A adds
no migration.

Replay is refused before another invocation. A failed start audit rolls back
and permits one clean recovery because no attempt was consumed. Once the start
commits, any provider fault, malformed result, or outcome-storage failure is
permanently no-retry; the system never guesses whether another attempt is
safe.

## Operator command

`scripts/admin-wompi-merchant-read-provider-double-execute.php` is CLI-only
and dry-run-first. It requires an absolute authorization-evidence file and
prints only database/package identities plus bounded hashes and denials. Dry
run writes nothing and does not construct or invoke the provider double.

Apply requires every printed database, package, state, client, actor, setting,
reference, plan, preflight, authorization, request, and start hash; a nonzero
backup SHA-256; the exact provider-double operation/target; one attempt/no
retry; network-disabled; and every real provider/business authorization set to
`no`. Only then may it construct one final
`RED_Addon_Wompi_Merchant_Read_Provider_Double` and call the durable runner
once. There is no browser or public command bridge.

This command is deliberately not the C4C3B real-target command. It cannot load
the package cURL transport, resolve a secret, use a Wompi hostname, or perform
DNS, TLS, HTTP, account, dashboard, or provider work.

## Acceptance evidence

The disposable rehearsal stages exact Store Lite `0.1.35` and external Wompi
`0.1.5` at `cc2ddd03ab54f663a089f7d059d802180e555d15`, applies all 47 core
migrations, and runs with URL streams disabled, common cURL/socket functions
disabled, proxy variables removed, and secret-value variables removed.

It passes:

- 32 durable helper assertions;
- 78 pure operator-command source assertions;
- one zero-write dry run;
- one incomplete-confirmation refusal;
- one provider-double apply with two durable action rows and two audits;
- one replay refusal with no state change;
- zero Wompi payment-attempt and event-receipt rows; and
- cleanup `database:0 grant:0 staged-project:0 primary:unchanged`.

The external Wompi package suite remains 321 assertions. No account, enrollment,
tax selection, real key value, DNS, TLS, HTTP, Wompi request, provider mutation,
transaction, payment, event, order mutation, client data, demo, or deployment
effect is part of C4C3A.

## Next boundary

C4C3B is owner-deferred. Resuming it requires a new explicit authorization for
the actual account/Sandbox value and exactly one read-only merchant GET through
a separate real-target command. C4C3A evidence does not authorize that contact.
C4D remains a still-later and separately approved one-transaction gate.
