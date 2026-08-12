# Public-Mutation Runtime Settings Contract

Status: implemented core prerequisite. This contract gives one exact
public-mutation state loader and handler access to a small, typed,
client-scoped configuration object. It does not create a public endpoint,
package, checkout form, order, payment provider, or client business record.

## Declaration

An optional `publicMutationContracts` entry may declare `runtimeSettings`:

```json
"runtimeSettings": ["vendor.package.currency"]
```

The closed list contains one to sixteen unique package setting keys. Each key
must be an existing manifest setting with a supported non-secret scalar type
and no non-null manifest default. Manifest validation rejects an unknown,
duplicate, secret, secret-reference, defaulted, or malformed key before a
package file is loaded.

The declaration is optional. A mutation without it remains valid and receives
an empty typed settings object; it has no setting-row requirement.

## Resolution boundary

Only core resolves values. It derives package, route, mutation, manifest, and
keys from the current enabled runtime binding after the public-mutation runner
has acquired lifecycle and exact-package locks and opened its transaction. A
package cannot select a client, package, table, or setting key and must not
query `RED_Addon_Settings` directly.

For a declared list, core locks the package's setting rows and accepts only
complete configured values whose stored type and canonical JSON match the
current manifest. It returns a final immutable
`RED_Addon_Public_Mutation_Runtime_Settings` object only to that binding's
state loader and handler. The object exposes the declared typed values,
`has()`, `value()`, `values()`, `declared()`, and an opaque state SHA-256.

Coexisting secret-reference settings are not normalized, exposed, or included
in the state hash. A missing, disabled, stale, malformed, defaulted,
secret-bearing, or unconfigured selected setting fails closed before rate use,
replay reservation, or package callback invocation. The external response
continues to use the existing generic temporary-unavailability vocabulary.

## Evidence and isolation

The state hash binds the exact package, route, mutation, normalized contract,
and declared values. For a declared list, core includes that opaque hash in the
command evidence used for idempotency. Changing a configured value therefore
cannot replay an earlier command under the same key; it is refused as an
idempotency conflict without executing package code.

All resolution uses the current installation's database connection and exact
enabled binding. Raw settings never enter a browser request or response,
cookie, session, audit row, replay ledger, log payload, package selector, or
other client database. Separate RED-CMS installations retain separate
settings, secrets, add-on state, media, and business data.

## Deliberate exclusions

- No settings editor, rendering, component presentation, conditional form UI,
  browser script, endpoint, route claim, or deployment change.
- No secret resolution or secret value access.
- No Store Lite package file, migration, registry row, cart, checkout, order,
  pay-on-receipt flow, hosted-payment adapter, or demo-client mutation.
- No authority for a package to bypass the declared table, transaction,
  lifecycle, CSRF, idempotency, rate-limit, or postcondition boundaries.

## Acceptance

The dependency-free manifest contract test proves declaration compatibility and
refuses unsafe keys. The disposable atomic-runner test proves typed non-secret
injection into both callbacks, withholding of a coexisting secret reference,
missing-value refusal before any package callback or evidence write,
configuration-bound idempotency conflict, compatibility with no declaration,
and exact fixture cleanup. The full acceptance suite runs all of those checks
against a uniquely named disposable database and removes its database and
grant afterward.
