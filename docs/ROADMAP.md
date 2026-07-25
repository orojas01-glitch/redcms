# RED-CMS Roadmap

## Version 5.0 — Bonsai

Milestones 1 through 5 are complete on `main` through pull request #2.
Version 5.0 delivers the compatibility-first PHP/MySQL modernization,
administrator security and transaction boundaries, the polished authoring
workspace, reusable standard themes, visual layouts and Layout Builder,
drag-and-drop placement, content history, structured navigation, media tools,
and guarded acceptance testing.

The GitHub distribution is a clean starter installation. Client themes,
databases, and media are separate deliverables.

## Version 5.1 Direction

Planned work includes:

- Member Access / Protected Content for private Sections and account lifecycle
- Payment-assisted access, including regional provider integrations
- Expanded roles and permissions
- Draft, review, approval, and publish workflow
- Notifications and reminders
- Content ownership and change attribution
- Optional installable tools
- Social publishing APIs
- Optional first-login guided tour

These items are product direction, not active Version 5.0 features. Each
requires its own security, data-migration, privacy, accessibility, and rollback
design before implementation.

### Adaptable Add-On Platform

RED-CMS should support separately installed client capabilities rather than
bundle every business vertical into the core. The first planned content
packages, in order of importance, are:

1. Store Lite
2. Events Calendar
3. Appointments
4. Donations
5. Restaurant Ordering

Member Access / Protected Content is a cross-cutting security package required
before private Sections or protected downloads can become operational. It is
not a public listing-directory component. Public business or location
directories would be a separate future Listing component and search service.

See [`ADD-ON-CONTRACT.md`](ADD-ON-CONTRACT.md) for the package types, manifest,
runtime registration, lifecycle, permission, migration, theme, client
isolation, prioritized-package, and acceptance contracts. These packages are
direction only; none is active in RED-CMS 5.0.

### Version 5.1 Compatibility Work

- The authenticated page-layout ellipsis menu now resets inherited `details`
  and `summary` spacing, borders, backgrounds, and minimum height inside the
  core-owned editor workspace. Active themes can style public disclosure
  elements without changing the administrator card geometry.
- Per-page SEO metadata is a confirmed migration compatibility gap. The current
  renderer reconstructs document titles and cannot represent canonical URLs,
  complete Open Graph and X/Twitter metadata, or typed JSON-LD. See
  [`SEO-METADATA-COMPATIBILITY-REPORT.md`](SEO-METADATA-COMPATIBILITY-REPORT.md)
  for evidence, the proposed 5.1 model, migration requirements, and acceptance
  criteria.
