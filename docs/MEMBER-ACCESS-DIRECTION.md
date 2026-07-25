# Member And Paid Section Access Direction

Status: architecture direction only. No public route is protected by this document or by the current legacy `AccessLevel` value.

## Current Boundary

- `RED_Sections.AccessLevel` is stored metadata, but the public router and content queries do not enforce it. A Section marked `Private` must currently be treated as public.
- `QueryLimit` is a functional rendering cap, not an access-control setting. New Sections keep a core-owned default of `100`, while the field is removed from the normal administrator interface.
- `RED_Admin` is the CMS administrator identity store. Public members and customers must not authenticate against it.
- The retired Audio Store login/register flow is not a safe foundation for a new membership product.

## Recommended Product Model

Use one member system with entitlements, then let a Section require an entitlement:

1. A visitor registers or signs in through a core-owned **Member access** Form purpose.
2. RED-CMS verifies the member session before resolving a protected route or querying protected content.
3. A free grant, manual grant, purchase, or subscription creates a time-bounded Section entitlement.
4. Every protected request checks that the member and entitlement are active and unexpired.
5. Payment callbacks update entitlements server-side and idempotently. Browser redirects never grant access.

Suggested dedicated records:

- `RED_Members`: normalized email, password hash, verification/status timestamps, and no payment credentials.
- `RED_Member_Identities`: optional external provider plus immutable provider subject, linked to one member without treating an email address alone as proof of identity.
- `RED_Member_Entitlements`: member, Section, source, state, start, and expiration.
- `RED_Payment_Transactions`: provider, unique external event/order/subscription identifiers, amount, currency, state, and audit timestamps.
- Revocable member sessions with rotation after login and privilege changes.

The Form Builder may present the experience, but Member registration, Member login, password reset, and email verification should be locked core purposes rather than arbitrary expert HTML or a user-selected database table.

An optional **Continue with Google** path can use Google Identity Services to sign up or sign in. RED-CMS must validate the returned ID token on the server, bind the immutable Google subject to `RED_Member_Identities`, and then create its own revocable member session. Google authentication must not be confused with permission to access a Section; the same entitlement check still applies.

## Add-On And Content-Package Boundary

Member Access / Protected Content is an optional cross-cutting package under
the Version 5.1 add-on contract. It is not a public listing directory.

- A public listing directory would present searchable people, businesses,
  locations, or similar records through a Listing component and search
  service.
- Member Access owns public member identities, sessions, entitlements, route
  checks, locked access forms, protected-content gates, and secure-download
  authorization.
- Themes may present the sign-in, teaser, and denial states, but they never
  decide whether protected content can be queried.
- Store Lite, Appointments, or another package may optionally request an
  entitlement after a verified transaction, but payment providers never own
  authorization.

See `docs/ADD-ON-CONTRACT.md` for package discovery, lifecycle, dependency,
permission, migration, client-isolation, and acceptance requirements.

## Route And Search Requirements

- Enforce access before layout rendering and before protected Article/Form/Gallery queries.
- Fail closed when member or payment state cannot be verified.
- Never include protected body content in public HTML, caches, feeds, search indexes, sitemaps, previews, or structured data.
- Keep an optional public landing/teaser page indexable. The protected URL should return an appropriate sign-in or purchase experience and use search directives deliberately.
- Apply CSRF protection, login throttling, secure password hashing, session rotation, email verification, password reset, and generic authentication errors.

## Payment Adapters

Payment providers should plug into the entitlement model rather than own authorization:

- **PayPal**: use Orders for one-time access or Subscriptions for recurring access. Grant only after a verified server webhook reports completed payment; handle cancellation, suspension, expiration, refunds, and duplicate delivery.
- **Nequi**: its current merchant APIs support one-time Push/QR payments and a subscription API. Production use requires merchant onboarding, credentials, certification/testing, and server-side webhook or status verification. A successful browser return alone is not proof of payment.

Official references:

- PayPal Checkout webhooks: <https://developer.paypal.com/payment-methods/webhooks/>
- PayPal Subscriptions webhooks: <https://developer.paypal.com/subscriptions/webhooks/>
- Google Identity Services: <https://developers.google.com/identity/gsi/web/guides/overview>
- Nequi APIs for businesses: <https://www.nequi.com.co/negocios/apis>
- Nequi subscription API: <https://www.nequi.com.co/negocios/pagos-recurrentes>
- Nequi developer documentation: <https://developer.nequi.com.co/>

## Delivery Sequence

1. Member identity, registration, verification, login/logout, reset, sessions, and route enforcement.
2. Free and administrator-granted Section entitlements in a disposable runtime.
3. PayPal sandbox one-time and recurring flows with replay-safe webhook tests.
4. Nequi sandbox integration after merchant onboarding, including status reconciliation.
5. Search/cache leakage tests, expiration/refund/cancellation cases, full desktop/mobile QA, then a separately approved production rollout.

Until stages 1 and 2 pass, the administrator must offer only **Public** and label **Members only** as planned.
