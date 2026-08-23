# P3E-9D4C2 Real-POST No-Contact Rehearsal

Status: complete. D4D remains the separately authorized first real Stripe
Sandbox POST.

## Exact Sources

The opt-in rehearsal stages immutable archives from:

- merged core snapshot containing D4C1;
- separately distributed adapter `0.1.8` at repaired discovery-valid commit
  `44ed7b3bd8f84f3f24340a6afc39881e8dee8c5d`; and
- Store Lite `0.1.35` at
  `f7de77eb1694fb6003340632c5018024753fe1fa`.

The rehearsal initially caught one stale adapter manifest hash for
`StripeSandboxCheckoutRealPostExchange.php`. Adapter PR #29 corrected only
that hash; all 19 package integrity hashes and the complete adapter suite then
passed before the rehearsal resumed.

## No-Contact Boundary

The script creates one guarded temporary project and one uniquely named
current-schema database. A temporary PHP configuration disables URL streams,
cURL execution, sockets, and stream-socket clients; a runtime probe must prove
those functions unavailable before database setup. Secret-value and proxy
environment inputs are removed. Only the opaque `stripe.secret-key` reference
is declared; no secret value exists.

The fixture records exact current package/migration identities, one disposable
Owner, exact permissions, opaque settings, fresh D4 authorization, and fresh
claim. It writes one five-object non-secret evidence file and one confirmation
file. It never replaces package source, registers a package, resolves a secret,
invokes a handler, or records start/result.

The command runs only in these modes:

1. default dry run, which revalidates every identity and writes nothing;
2. incomplete `--apply`, refused before the D4B2 call; and
3. one changed request-hash confirmation, also refused before the call.

The fully confirmed apply argument set is constructed only for refusal testing
and is never invoked. Ledger evidence remains exactly two action rows and two
audits—authorization and claim—with zero start/result rows.

## Acceptance

The pure source contract passes 92 assertions. The opt-in operational run
passes with:

`dry-run:1 incomplete-apply-refused:1 changed-confirmation-refused:1
real-apply:0 start-result:0 provider-effects:0`

Cleanup passes:

`database:0 grant:0 staged-project:0 evidence:0 environment:clear
source-repositories:unchanged primary:unchanged`

No credential, DNS, TLS, HTTP, Stripe request, Checkout Session, payment,
webhook, browser navigation, Store Lite mutation, retry, live mode,
hosted/client action, migration of a retained database, or deployment effect
occurred.
