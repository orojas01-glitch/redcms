# P3E-8B3C3B Restricted-Key Sandbox Execution

Status: completed on 2026-08-18. One exact restricted-key read-only Stripe
Sandbox GET produced the expected bounded resource miss. No retry occurred.

## Approved boundary

The rehearsal used only:

- merged core `b0d07010cf25` with the B3C3A server-local command;
- Store Lite `0.1.35` from `f7de77eb1694`;
- adapter `0.1.4` from `2d2bf2f25e61`;
- the dedicated `RED-CMS Store Lite Development` Stripe sandbox;
- one restricted test key named `RED-CMS B3C3B Read-only Probe` with only
  Checkout Sessions Read permission; and
- a fresh disposable current-schema database with 46 migrations.

The hosted demo, every client installation/database, other Stripe projects,
and the clean starter remained outside the execution boundary.

## Result

After a value-free dry run and verified pre-contact backup, the command made
one request:

`GET /v1/checkout/sessions/cs_test_redcms_readiness_probe`

Core and the adapter recorded only:

- `outcome=resource_miss_observed`;
- `statusCode=404`;
- `responseBytes=358`;
- `transportEvidenceSha256=`
  `1ca717cbe624f69b672a22662e67e1857b23ffb733767962fc72c1db29f3b3ae`;
- network/provider contact true;
- response body, response headers, and credential included false;
- retry authorized false; and
- mutation authorized false.

Stripe Workbench independently showed one matching GET at 03:24:17 UTC with
`404 resource_missing`, authenticated by the named restricted key. No POST,
write, redirect, second request, Checkout creation, payment, refund, webhook,
Store Lite mutation, browser checkout, client activation, or deployment
occurred.

## Evidence and cleanup

The private checksummed archive `stripe-b3c3b-20260818T032154Z` contains the
value-free setup and dry-run reports, bounded execution report, pre-contact
database dump, and `SHA256SUMS`. All checksums passed; directory/file modes are
`0700`/`0600`; and credential-pattern scanning was clean.

The launcher cleared the clipboard and process secret, revoked the disposable
grant, dropped the disposable database, removed the staged project, and stopped
its sleep-prevention process. Independent verification returned exact
`database:0 grant:0`, clipboard bytes `0`, and the configured primary unchanged
at its prior 20-table state.

This gate adds no migration, table, package payload, public route, browser
control, client state, or deployment. After evidence review, the operator
explicitly expired the one-purpose restricted key. It no longer appears in the
active restricted-key list and cannot authorize another B3C3B request.
