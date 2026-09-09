# Hash-only administrator authentication

Date: 2026-09-09. Local 5.1.2 hardening candidate.
Baseline: `a3c5d2a`; rollback checkpoint: `checkpoint/before-hash-only-auth`.
No client deployment, real account password change, retained/client schema change
or GitHub push.

## Current behavior

Administrator login accepts only hashes recognized by PHP's password API and
verified with `password_verify()`. Raw plaintext equality is removed. The former
`RED_ADMIN_ALLOW_LEGACY_PASSWORDS` environment flag has no runtime effect and
cannot restore the fallback. Legacy, empty, malformed and unrecognized values
receive the existing generic failure response and failed-login throttling. Unknown
password formats also perform a dummy password verification to avoid a cheap
plaintext-specific path.

Protected administrator requests refuse sessions backed by a non-hash database
password, even if their stored fingerprint matches. Password-change and account
invalidation remain in place. Valid hashes can be rehashed according to the
existing PHP PASSWORD_DEFAULT policy. Rehash persistence uses an exact comparison
with the originally verified hash: an intervening password reset cannot be
silently overwritten, and a failed rehash does not create an authenticated session.
No new database fields or changes to existing migration files are required.

## Pre-deployment audit and conversion

Run with a PHP CLI that has mysqli enabled, using the intended server-local
configuration. No password, hash or username appears in output or command arguments.

```sh
php scripts/admin-password-migration.php
```

The default is read-only. It reports counts (`hashed`, `legacy`, `resetRequired`),
column readiness and overall password-format readiness. Exit 0 means ready, exit 2
means conversion/reset/schema preparation is needed, and exit 1 indicates refusal
or an unavailable prerequisite. It does not prove that a person knows a working
manager password; verify a recovery manager separately before deployment.

Before deploying hash-only login, keep a protected backup and review the counts.
Where the owner confirms remaining eligible values are plaintext, perform the
separate one-time server-local conversion:

```sh
php scripts/admin-password-migration.php --apply \
  --confirm-database=EXACT_DATABASE_NAME --confirm-legacy-plaintext
```

This is a migration command, never a login fallback. It requires both explicit
confirmations, an InnoDB table and the existing hash-capable password column.
Rows are locked in one transaction. Existing hashes are preserved, legacy values
are hashed without changing the password the person types, and replay is a no-op.
Empty values, dollar-prefixed/hexadecimal hash-like values, NUL bytes and values
longer than 72 bytes are refused for automatic conversion. The limit avoids
bcrypt truncation. Ambiguous rows refuse the entire conversion; use the existing
Administrator Users password-reset workflow or an authorized database-operator
recovery procedure first. No partial conversion is committed.

Use the already existing `2026-07-02-red-admin-password-hash.sql` migration where
needed. Never restore plaintext compatibility to resolve a deployment failure.
Client conversions/resets are separate per-client operations requiring their
normal authorization and update record. No client database was inspected here.

## Local readiness

The existing local development MySQL service was stopped; it was started on its
configured loopback port for verification. The read-only audit found **2 hashed,
0 legacy, 0 resetRequired**, with a suitable password column. No local account
conversion or password change was necessary or performed.

## Regression evidence

- `scripts/admin-password-self-test.php`: bcrypt, available Argon2id, wrong
  passwords, hash-as-password refusal, plaintext refusal under all tested old-flag
  values, malformed values and conservative migration classification. Included in
  the core and full acceptance gates.
- `scripts/admin-password-migration-self-test.php`: disposable rows only; counts
  and non-disclosure, missing/wrong confirmation refusal, all-or-nothing ambiguity
  refusal, explicit conversion, unchanged password semantics, no-op rerun, refusal
  of a plaintext-backed session and protection against an intervening reset.
- Full HTTP authentication acceptance keeps one plaintext row only as a negative
  fixture, runs the server with the obsolete compatibility flag set to 1, checks
  generic refusal/no admin access/no implicit database rewrite, then verifies valid
  weak-hash rehashing and stable subsequent login. Other normal fixtures now store
  hashes instead of relying on legacy acceptance.
- An unrelated fixed-window rate-limit assertion failed exactly at a minute
  boundary. Its single disposable test connection now fixes its database clock
  for that one assertion and restores it in `finally`. Production rate limiting
  was not changed.

## Rollback

Revert the reviewed hash-only change as a unit if a local regression requires it;
review against `checkpoint/before-hash-only-auth` and preserve subsequent accepted
work. Do not roll back database password hashes to plaintext. Existing supported
hashes remain compatible with the earlier login code. The migration utility adds
no reversible schema operation and writes no password backup; operator backups
must remain private. The password/key workbook was not opened or modified.

Remote CI execution, real client manager login and client-specific pre-deployment
migration remain unverified. Hash-only readiness does not close the remaining
historical PDT credential or server/SMTP/backup/monitoring hardening gates.


Final local verification: 19 pure password-contract assertions, 15 disposable
migration/session/reset-race assertions, the complete core/theme suite and the full
47-migration disposable acceptance pass. Cleanup of both temporary databases and
grants passes. Current local audit remains 2 hashed / 0 legacy / 0 resetRequired.
Current-source Gitleaks and the relevant syntax/whitespace checks also pass.
