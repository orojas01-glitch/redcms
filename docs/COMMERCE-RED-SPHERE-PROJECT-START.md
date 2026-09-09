# Start instructions for the commerce.red-sphere.com project

Use this as the first message in a new Codex project whose local directory is
dedicated to `commerce.red-sphere.com`:

> Build the Red Sphere AI Assistant commerce site as a new, isolated local
> RED-CMS installation. Do not redesign or replace the existing configurator,
> and do not touch `demo.red-sphere.com`. Start by reading
> `docs/COMMERCE-PLATFORM-RECONCILIATION-2026-09-02.md` from RED-CMS branch
> `codex/commerce-platform-reconciliation` (or use `git show` from the local
> RED-CMS repository) and the package documents it names.
>
> Use the reconciled sources only: RED-CMS core 5.1.1 from client kit 0.1.12,
> Store Lite 0.1.51 at commit
> `57a948929142efb417285d2dbd76b5b3478b7738`, and Stripe adapter 0.1.21 at
> commit `35a7b4bcb1dba1cf94e3d51ea50658b57ef09874`. Create a separate local Git
> repository, installation directory, database, database user, administrator,
> secrets file, media directory, backup, and rollback checkpoint. Do not copy
> demo business data or credentials.
>
> Work in Stripe test mode only against the dedicated Red Sphere Design Studio
> Sandbox. Never use or inspect the Akashic Dimensions account. Keep credentials
> owner-entered and server-side. Do not contact Stripe until the local install,
> migrations, canonical catalog mapping, and no-contact tests pass. Use the
> locked `stripe/stripe-php` 21.3.1 dependency and `StripeClient`; do not use
> deprecated global key configuration. Do not enable automatic tax or choose a
> tax classification.
>
> Implement in slices: (1) isolated RED-CMS/Store Lite foundation and Red
> Sphere visual system, (2) sales-assisted draft carts and secure expiring
> review links, (3) multi-line Stripe Sandbox Checkout and signed idempotent
> webhooks, then (4) the cross-domain configurator handoff. Preserve the current
> `/bin/ai-assistant-quote.php` request flow and keep “Request this
> configuration” available.
>
> Pay now is allowed only when every selected item is fixed-price, dependencies
> and quantities are valid, every line maps to active authoritative lookup keys,
> and the required messaging-number decision is resolved. The choices are: use
> a compatible existing number, add a dedicated messaging number, or help me
> choose. If the client has no compatible number, Dedicated Messaging Number is
> mandatory. “Help me choose,” starting-at, quote-only, custom-evaluation, or
> unresolved items block Pay now and must be explained; never silently omit an
> item.
>
> Use seven-day sales-assisted cart links, 24-hour configurator cart links, and
> 30-minute Stripe Sessions. URLs contain only opaque random tokens. Amount due
> today for subscription carts includes setup plus the first monthly payment.
> Payment begins onboarding; it does not provision an assistant automatically.
> The public legal seller name is Red Sphere Design Studio Inc. Terms and
> conditions will be supplied later, so keep final legal-copy and live-readiness
> gates open.
>
> The HostGator website entry for `commerce.red-sphere.com` already exists, but
> do not inspect, upload, deploy, create or change DNS/TLS, publish configurator
> copy, configure a production webhook, enable live Checkout, mutate the live
> Stripe catalog, or process a live payment without a new explicit approval.
> Lead with a local implementation plan and continue through verified local
> acceptance, keeping a decision/evidence ledger and exact rollback steps.
