# P3E-8B3C3A Server-Local Operator Command

Status: core contains one explicit CLI-only command capable of consuming the
reviewed B3C2 runner. The command defaults to a value-free dry run. This gate
does not configure a credential, invoke the adapter, or contact Stripe.

## Purpose

B3C2 proved the `0.1.4/provider_read_only` core runner against an
integrity-checked in-memory handler, but exposed no caller. B3C3A adds the
operator boundary needed for a later single real restricted-key rehearsal
without adding a browser, public route, job, scheduler, webhook, or automatic
path.

The command is:

`scripts/admin-provider-contact-sandbox-execute.php`

It accepts only an absolute bounded JSON evidence file containing the exact
`readiness` and `prepared` objects already used by P3E-7 and P3E-8A.

## Dry-run default

Without `--apply`, the command:

- opens only the selected client database;
- discovers and integrity-checks the enabled adapter package;
- revalidates current Owner authority, `addons.enable`, enabled same-database
  Store Lite, the active P3E-7 authorization, and the exact P3E-8A claim;
- validates only adapter `0.1.4/provider_read_only`;
- reads only value-free availability evidence for `stripe.secret-key`;
- proves that no execution-start row exists; and
- prints the exact bounded hashes and operator confirmations.

Dry run resolves no secret value, executes no registrar or package handler,
opens no connection, writes no ledger or audit row, and leaves the attempt
unconsumed.

## Exact apply contract

Apply requires every printed confirmation, including:

- database, package, version, and enabled state;
- plan, authorization, claim, execution-start, and secret-availability
  SHA-256 values;
- a nonzero verified backup SHA-256;
- operation `provider-contact.read-only-probe-sandbox`;
- target `stripe-sandbox`;
- credential mode `restricted_test`;
- maximum attempts `1`;
- retry authorized `no`; and
- mutation authorized `no`.

The command accepts no key argument and contains no credential literal. Secret
material remains owned by the existing package-scoped runtime resolver.

After exact confirmation, the command has one call site to the reviewed B3C2
runner. The runner commits the immutable start before registrar, secret, or
handler access. A successful operator rehearsal is only the exact bounded 404
resource miss with no body, headers, or credential returned or persisted.

Any credential refusal, permission refusal, rate limit, provider failure,
unexpected status, malformed result, interruption, or persistence failure is
non-retryable after the committed start. The command reports only bounded
classification, status, byte count, and transport-evidence hash. It never
prints a provider body, header, message, exception, or credential.

## Non-contact acceptance

The 40-assertion pure command-contract test verifies:

- CLI-only loading of the reviewed B3C2 helper;
- every exact apply confirmation;
- dry-run-first wording and behavior contract;
- one plan call and one execution call site;
- exact version, operation, target, restricted-test mode, one-attempt,
  no-retry, and no-mutation literals;
- no accepted key argument, secret environment variable, or credential
  literal;
- no hostname, DNS, TLS, HTTP, cURL, socket, shell, sleep, request-global, or
  browser/public bridge primitive; and
- success limited to the bounded 404 resource miss.

The unchanged 37-assertion B3C2 lifecycle continues to prove the execution
boundary with an in-memory handler. Full disposable acceptance must still pass
with 46 migrations, 35 tables, primary isolation, and exact cleanup.

## B3C3B remains separate

The first real restricted-key sandbox GET is not part of B3C3A. B3C3B requires
all of the following external prerequisites at the same time:

- a fresh disposable current-schema client database;
- the exact enabled Store Lite and adapter `0.1.4` packages;
- a current database-backed Owner and `addons.enable` grant;
- new value-free readiness and prepared evidence inside its 15-minute window;
- committed P3E-7 authorization and P3E-8A claim;
- a verified backup checksum;
- a package-scoped `rk_test_` restricted key and declared reference; and
- explicit operator approval of the complete dry-run output.

Live keys, Checkout creation, payment capture, refunds, webhooks, Store Lite
mutation, public routes, browser checkout, automatic work, retries, client
activation, and deployment remain unauthorized.
