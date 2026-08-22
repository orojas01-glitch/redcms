# P3E-9D3A Real-Operation Preflight Operator Command

Status: complete as a CLI-only, dry-run-first command contract. It wraps the
merged P3E-9D2 non-executing runner without adding credentials, persistence,
network access, or provider execution.

## Command

[`scripts/admin-sandbox-checkout-real-operation-preflight.php`](../scripts/admin-sandbox-checkout-real-operation-preflight.php)
accepts one absolute regular JSON evidence file no larger than 64 KiB. The file
must contain exactly these top-level objects:

- `input`: the closed P3E-9A mutation-aware Checkout input;
- `preflight`: the complete recomputed P3E-9D0 request preflight; and
- `syntheticPlan`: the exact P3E-9B source-plan evidence.

The command discovers and integrity-validates external adapter
`redcms.store-lite-stripe-checkout` `0.1.7`, then asks P3E-9D2 to recompute the
plan. Relative paths, symbolic-link file arguments, oversized or malformed
JSON, extra keys, invalid discovery, and changed evidence fail closed.

## Dry Run Is The Default

Without `--apply`, the command prints the exact package/source versions,
manifest and inventory hashes, plan/input/synthetic-plan/contract/request
hashes, deterministic start-identity hash, operation identities, one-attempt
limit, and every no-effect confirmation. It exits before the package registrar
or adapter handler runs.

Dry run provides no secret access and creates no authorization, claim, start,
result, audit, migration, or business row. It performs no network request and
does not contact Stripe.

## Contained Apply

`--apply` is accepted only when the operator repeats every printed identity
and explicitly confirms:

- adapter `0.1.7` and source plan `0.1.5`;
- operation `checkout.create-sandbox-real-post-preflight` and future provider
  operation `checkout.create-sandbox-real-post`;
- maximum attempts `1`; and
- credential access, execution readiness/start, result recording, network,
  provider contact/mutation, Checkout creation, and retry are all `no`.

One accepted apply invokes the P3E-9D2 runner exactly once. D2 registers the
validated adapter with null secret access and invokes only its non-executing
preflight operation. The command accepts only `request_contract_adopted` plus
the exact non-persistent result identity and false-effect outcome.

This `--apply` means “run the contained adapter preflight,” not “execute the
provider operation.” It cannot accept or resolve a key, construct an
Authorization header, issue HTTP, create a Checkout Session, take a payment,
record a result row, mutate Store Lite, retry, activate the demo, deploy a
client, or enable live mode.

## Acceptance And Next Gate

[`scripts/addon-sandbox-checkout-real-operation-command-self-test.php`](../scripts/addon-sandbox-checkout-real-operation-command-self-test.php)
is a dependency-free source contract that proves CLI-only loading, exact
arguments and pinned identities, dry-run ordering, one contained D2 call,
non-persistent outcome checks, forbidden credential/network/database/package
execution primitives, and absence of a browser/public bridge.

P3E-9D3B remains separate. It must exercise dry run, changed-confirmation
refusal, one exact contained apply, and cleanup against the exact merged
external adapter in a disposable cross-repository fixture. P3E-9D4 remains a
separately authorized one-attempt real Stripe Sandbox POST after D3B merges.
