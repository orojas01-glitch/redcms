# P3E-9D4B2 Durable Real-POST Execution

Status: complete in core; D4C operator command and no-contact rehearsal are
now also complete. No real provider request occurred.

## Durable Boundary

The runner consumes only the fresh D4B1 authorization and claim for adapter
`0.1.8`, Store Lite `0.1.35`, the exact D0/D2 identities, database, order
snapshot, Owner, permissions, value-free secret availability, and provider
operation `checkout.create-sandbox-real-post`.

Apply commits one immutable execution-start row and one value-free audit before
package registration, secret-value resolution, handler invocation, or any
possible provider effect. It then resolves only the owning package setting
`stripe.secret-key`, invokes exactly one integrity-checked typed adapter
operation, validates one closed bounded outcome, rechecks the complete start
row, and atomically records one immutable result plus one value-free audit.

Any post-start secret, registrar, handler, malformed-output, ambiguous-result,
or result-storage failure spends the attempt permanently. Replay is refused
before a second handler call. The bounded indeterminate result conservatively
marks provider effects possible only when the handler was invoked and always
keeps payment, webhook, browser, Store Lite mutation, retry, live mode, and
client deployment false.

## Acceptance

`scripts/addon-sandbox-checkout-real-mutation-execution-self-test.php` passes
29 assertions in the uniquely named disposable database. It proves:

- start commits before registrar, scoped secret, and one final in-memory
  handler invocation;
- created output retains only the Session reference, validated-URL fact,
  open/unpaid/non-live state, amount, currency, expiry, and hashes;
- exact four-row/four-audit authorization, claim, start, and result evidence;
- replay refusal, start-audit rollback and recovery, result-audit failure with
  permanent no-retry, throwing and malformed handler containment, and missing-
  secret indeterminate recording without handler invocation;
- scoped webhook-secret refusal and absence of credential bytes from results,
  source, audits, and evidence; and
- exact row, package, table, file, environment, database, and grant cleanup.

The complete development acceptance runner also passed all 46 migrations,
idempotent migration replay, schema/primary isolation, isolated PHP runtime,
public/admin/browser lifecycles, and forced rollback checks.

## Explicit Stop

The core helper has no DNS, socket, TLS, HTTP, cURL, Stripe SDK, request global,
route, public endpoint, browser bridge, CLI command, retry loop, or deployment
path. The acceptance handler is a final in-memory fixture and contains no
network primitive. The later D4C slices add the dry-run-first CLI contract and
network-disabled cross-repository rehearsal without real apply. D4D remains
the separately authorized first production-transport Sandbox POST.
