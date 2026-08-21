# P3E-9B2 Synthetic Checkout Core Runner

Status: complete locally as a dependency-free, non-persistent core runner.

## Purpose

Adapter P3E-9B1 version `0.1.5` made the P3E-9A contract available through one
synthetic-only typed operation. P3E-9B2 provides the matching core boundary
without adding the mutation-specific authorization and durable execution
ledger reserved for P3E-9C.

## Dry Plan

`red_addon_checkout_synthetic_plan()` accepts only:

- one discovery-valid, integrity-complete adapter package with exact id
  `redcms.store-lite-stripe-checkout`, version `0.1.5`, and declared adapter
  `redcms.store-lite-stripe-checkout/checkout`;
- `contactTarget=synthetic-checkout-package`;
- the closed synthetic USD checkout projection and arithmetic;
- the exact same-origin HTTPS return and 30-minute-through-24-hour expiry
  policy;
- the exact mutation-aware `p3e9a-v1` profile; and
- one contract SHA-256.

It returns only package manifest/inventory hashes, a canonical input hash, a
plan hash, exact operation `checkout.create-sandbox-synthetic`, and false
runtime-effect facts. It executes no registrar or handler.

Read-only credential mode, mutation-disabled or retry-enabled profiles,
customer fields, invalid arithmetic, expiry drift, wrong package version, and
input expansion fail closed.

## Synthetic Execution

`red_addon_checkout_synthetic_execute()` requires:

- the exact unchanged plan SHA-256;
- one `RED_Addon_Runtime_Secret_Access` object owned by the adapter package;
- exactly one scoped setting value; and
- the same discovery-valid package and input.

Core integrity-checks and runs the fixed package registrar, resolves the exact
adapter handler, and invokes only `checkout.create-sandbox-synthetic` through
the existing typed adapter containment boundary. Core does not resolve a
secret reference, read an environment variable, or open a database. The
access object is injected by the acceptance fixture for the in-memory proof.

The result gate accepts only the exact adapter `0.1.5` synthetic outcome:

- open/unpaid synthetic Session reference and exact expiry;
- contract, response-evidence, result, plan, and outcome hashes;
- no response body/header, Checkout URL, or credential;
- network, provider contact/mutation, Checkout creation, payment, webhook,
  browser, order mutation, retry, and client deployment false; and
- `executionPerformed=true` only for the contained in-memory handler call.

Malformed output, handler exceptions, emitted output, altered buffer behavior,
wrong plan, wider secret access, registrar failure, or outcome drift return no
partial bounded outcome.

## Explicit Exclusions

P3E-9B2 adds no:

- migration, table, database connection, authorization, claim, start, result,
  or audit row;
- credential resolver, environment read, provider host, HTTP client, Stripe
  request, or Checkout Session;
- real Checkout operation, retry, payment, webhook, public route, browser
  controller, Store Lite transition, demo/client state, or deployment.

The helper has no caller in request bootstrap, public dispatch, administrator
routes, CLI commands, jobs, or schedules.

## Acceptance

The focused dependency-free fixture passes 37 assertions. It creates one exact
temporary adapter plus Store Lite dependency, validates discovery without
execution, proves deterministic dry planning and cross-profile refusal,
invokes the synthetic handler with one in-memory scoped key, contains malformed
and failing handlers, scans the helper for forbidden primitives, and removes
the complete temporary project. No database is opened.

The complete disposable RED-CMS acceptance suite also passed against a fresh
46-migration database with normalized schema signature
`0e75f9590094e9875c8df2aa83a8fe5646f2aad6931ed168a7ee935984f9f313`.
Browser/runtime, authentication, permission, CRUD, rollback, and isolation
checks passed. Final cleanup independently returned temporary primary,
acceptance database, and temporary grant `0:0:0`; the retained
`redcms_v51_starter` database was not migrated or modified.

P3E-9B is closed by external adapter P3E-9B1 plus this core P3E-9B2 runner.
P3E-9C1 separately records new mutation-specific authorization rather than
widening completed P3E-8 read-only evidence, and P3E-9C2 records its one-attempt
claim without execution. P3E-9C3A later records transport-double start/result,
and P3E-9C3B1 adds its CLI command contract. P3E-9C3B2 later completes its
disposable apply rehearsal. P3E-9D0 later defines the pure POST contract; D1
is next while the real provider action stays separately gated. See
[`PAYMENT-ADAPTER-P3E9C1-MUTATION-AUTHORIZATION.md`](PAYMENT-ADAPTER-P3E9C1-MUTATION-AUTHORIZATION.md)
and
[`PAYMENT-ADAPTER-P3E9C2-MUTATION-CLAIM.md`](PAYMENT-ADAPTER-P3E9C2-MUTATION-CLAIM.md).
