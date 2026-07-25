# RED-CMS 5.1 Direction

Status: product direction only. These features are not part of RED-CMS 5.0 and must not be treated as active security, publishing, payment, or integration controls.

## Product Goal

Version 5.1 should extend the 5.0 authoring foundation for organizations that need member access, formal publishing operations, clearer accountability, and optional integrations. New capabilities should be modular, permission-aware, migration-backed, auditable, and removable without breaking public content.

## 1. Members, Paid Access, And Secure Directories

Protected content should use a dedicated member identity and entitlement system, never the administrator account table or a client-side hidden folder. A visitor may register, sign in, receive a manual entitlement, or purchase access through a payment adapter.

- Enforce access before protected content is queried or rendered.
- Keep public administrators and public members in separate identity stores.
- Grant access only from verified server-side payment events.
- Support time-limited, revocable, and renewable entitlements.
- Keep payment credentials outside RED-CMS.
- Begin with PayPal sandbox support; add Nequi after merchant onboarding and certified server-side callback testing.

The detailed security and delivery model is in `docs/MEMBER-ACCESS-DIRECTION.md`.

## 2. Roles And Permissions

Replace the current component-access model with composable roles and scoped permissions while preserving a protected owner account.

Suggested starting roles:

- Owner
- Administrator
- Publisher
- Editor
- Contributor
- Member-support or billing operator

Permissions should describe actions such as view, create, edit own, edit any, review, approve, publish, manage users, manage payments, manage themes, and install tools. Scopes may later restrict access by Section or site.

## 3. Publishing Workflow

Introduce an explicit lifecycle:

`Draft → In review → Approved → Scheduled/Published`

Publishing state should be independent from version history. Every transition needs permission checks, an actor, timestamp, optional note, and a stable revision reference. Rejection should return content to Draft without destroying the review record. Existing installations should continue to behave as immediate-publish sites until workflow is deliberately enabled.

## 4. Notifications And Reminders

Create an internal notification center before adding external channels. Useful events include review requests, approvals, rejections, scheduled publication, approaching expiration, failed publication, payment exceptions, and assigned follow-up work.

- Store notifications in RED-CMS with read/unread and resolved states.
- Allow per-user preferences and quiet periods.
- Deliver email only through a configured queue or transport.
- Deduplicate retryable events and keep delivery failures visible.

## 5. Ownership And Change Accountability

Content should record a responsible owner separately from the administrator who performed the most recent change. Version history and the administrator activity log already provide useful foundations, but 5.1 should make ownership, assignment, and change attribution visible in the workspace.

Each important record should answer:

- Who owns this content?
- Who last changed it?
- What changed?
- Which revision was reviewed and published?
- Who approved it?
- When is follow-up due?

## 6. Installable Tools And Social Publishing APIs

Add a controlled extension catalog rather than arbitrary uploaded PHP. A tool package should declare its identifier, version, compatibility range, permissions, settings schema, migrations, background jobs, outbound hosts, and uninstall behavior.

Social publishing should be an optional adapter layer:

- Connect accounts through provider-supported OAuth.
- Store tokens encrypted and outside public content.
- Use provider APIs and queues rather than browser automation.
- Preview the exact outgoing text and media.
- Require an authorized confirmation before publishing.
- Record provider response ids, failures, retries, and the initiating user.

Initial research can cover major providers, but implementation should begin with one well-supported API and a reusable adapter contract.

## Suggested Delivery Order

1. Role and permission model
2. Ownership and assignment
3. Draft/review/approval/publish workflow
4. Internal notifications and reminders
5. Member identity and free/manual entitlements
6. PayPal sandbox, then Nequi
7. Signed extension manifests and one first-party tool
8. One audited social publishing adapter

Each phase requires its own migration, rollback path, authorization tests, disposable-database acceptance coverage, and desktop/mobile administrator verification.
