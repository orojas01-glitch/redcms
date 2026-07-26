# RED-CMS 5.1 Adriana JSON-LD Launch Decision

- Status: Constrained core, isolated Adriana verification, and hosted Schema.org validation complete; production deployment remains
- Decision date: 2026-07-26
- Scope: 87 JSON-LD property occurrences reported by the isolated 28-route
  Adriana SEO migration
- Production deployment: Not authorized or performed

## Decision

Do not launch the Adriana migration with the 87 occurrences merely accepted as
lost, and do not add an arbitrary custom JSON-LD editor.

Before production deployment, extend the generic RED-CMS typed SEO contract
with the smallest constrained fields needed to reproduce visible Adriana
content. Generate route-, language-, and site-identity relationships when RED-CMS
already owns their source values. Deliberately omit claims that are not visible
on the corresponding page.

This resolves the inventory as follows:

| Resolution | Occurrences |
| --- | ---: |
| Generate from existing validated CMS values | 51 |
| Map to constrained typed fields | 33 |
| Normalize away one redundant homepage self-reference | 1 |
| Deliberately omit unsupported visitor-invisible claims | 2 |
| Require arbitrary custom JSON-LD | 0 |
| Total | 87 |

The resulting launch target represents 84 occurrences in emitted typed
JSON-LD, safely normalizes one redundant source relationship, and retains two
explicit exclusions in the client migration report.

## Evidence Boundary

The classification uses the preserved client-only import manifest identified
by SHA-256
`9628c4d1b297aff9ab4a52474a6f5d5242bc85404d49f6e0f70a56630d46b7c9`.
That manifest contains 28 routes, 335 imported fields, 169 previously derived
values, and the exact 87 non-importable decisions.

The source manifest, database, theme, screenshots, and route-level values stay
with the isolated Adriana QA installation. They must not be copied into the
clean RED-CMS starter. This document records only the generic product decision
and aggregate evidence.

## Property Classification

| Source property | Count | Classification | RED-CMS 5.1 handling |
| --- | ---: | --- | --- |
| `inLanguage` | 27 | Generated | Normalize the validated Open Graph locale or owner language to a schema language value. Do not add duplicate storage. |
| `isPartOf` | 25 | Generated / normalized | Keep the existing generated `WebSite` reference on the 24 `WebPage` objects. Do not reproduce the homepage `WebSite` object pointing to itself. |
| `about` | 25 | Typed | Add a constrained Person/Organization identity and render it as the subject of `WebPage` or `WebSite`. |
| `provider` | 3 | Typed | Reuse the constrained identity as the provider of `Course` or `Service`. |
| `mainEntity` | 1 | Typed | Allow a `WebPage` to name a constrained `Course` main entity and reuse the validated provider identity. |
| `educationalLevel` | 1 | Typed | Store and render a bounded text value supported by visible course content. |
| `hasCourseInstance` | 1 | Typed | Store constrained delivery mode, ISO 8601 workload, and instructor name fields. |
| `teaches` | 1 | Typed | Store bounded newline-separated topics and render a normalized JSON array. |
| `serviceType` | 1 | Typed | Store and render a bounded service-type value supported by visible service content. |
| `courseCode` | 1 | Omit | `CANTAUTOR-001` is not displayed to visitors on the corresponding page. Keep the exclusion in the migration report. |
| `aggregateRating` | 1 | Omit | Three testimonials are visible, but the claimed five-star rating is not. Keep the exclusion in the migration report. |

## Generic Core Boundary

The implementation adds only constrained, nullable fields:

- identity type: empty, `Person`, or `Organization`;
- identity name and optional absolute URL;
- named Course main entity;
- educational level;
- Course delivery mode: empty, `online`, `onsite`, or `blended`;
- ISO 8601 Course workload;
- instructor name;
- bounded Course topic list;
- Service type.

The public renderer:

- generate `inLanguage` without storing a second language value;
- derive the `WebSite` URL from the canonical origin;
- render `about` only for `WebPage` and `WebSite`;
- render `provider` only for `Course` and `Service`;
- render Course-only and Service-only fields only for their matching schema
  type;
- omit empty optional properties;
- continue escaping JSON safely.

The batch does not add:

- raw JSON or executable metadata input;
- a rating field;
- a Course code field solely for this migration;
- client identity defaults in the clean starter;
- client routes, content, media, or database rows.

## Generic Implementation Verification

The generic implementation now includes the nullable migration, shared
administrator controls, schema-type-aware validation and storage, public
rendering, Article revision labels, migration checks, and isolated browser
coverage. It deliberately excludes Course code, aggregate rating, and arbitrary
JSON-LD input.

The clean-starter gate passed with:

- 92 dependency-free SEO assertions;
- 17 SEO migration-contract assertions;
- 38 disposable-database SEO assertions;
- 36 applied migrations and zero checksum drift;
- the expected 26-table schema with normalized signature
  `f64ee78f8e6aaca54dd88f68e8e744716a7d41ae0542a010f1ab835df7cfe607`;
- desktop 1512 × 699 and mobile 390 × 844 administrator rendering with all ten
  constrained controls, no horizontal overflow, and no console, page, request,
  or same-origin HTTP errors.

The full acceptance run used a disposable current-baseline database because
the retained local starter database predates the latest add-on migrations. No
retained starter or client database was migrated, and the disposable database
and grant were removed after verification.

## Fresh Isolated Adriana Verification

The 2026-07-26 round-3 verification used a fresh current-schema database built
from the clean installer and all 36 current migrations, then imported the
client tables as data only from the preserved pre-round backup. This avoided
rewriting the backup's known historical migration-checksum mismatch and kept
the current migration ledger authoritative.

The narrowed 26-field manifest has SHA-256
`a482b4458901da070ff7f4cf8275537b0b663618a209abcd8c4633e803aef902`.
Its guarded lifecycle passed with 28 additive updates, no missing owners or
conflicts, transactional apply, and 28 unchanged rows on the post-apply dry
run. It reports 401 populated fields, 254 derived values, no skipped values,
and exactly the two approved exclusions. An adversarial dry run attempting one
non-empty SEO-title overwrite was blocked before apply.

All 56 desktop/mobile route checks and all 28 exact legacy redirects passed.
The sitemap contained exactly 28 canonical URLs; robots declared it without a
sitewide block; and there were no console, page, same-origin request,
broken-image, overflow, or external-network failures.

Local validation of all 28 unique rendered schemas against the official
current Schema.org vocabulary checked 84 typed objects and 282 property
occurrences with zero unknown types, unknown properties, domain mismatches, or
object-range mismatches. That check identified and corrected one generic
semantic issue: `inLanguage` belongs on the 27 CreativeWork-derived objects,
not the single `Service`.

With explicit authorization, only the 28 unauthenticated public route renders
were submitted individually to the hosted Schema.org Markup Validator. Every
request returned HTTP 200 with zero errors and zero warnings. The validator
reported 24 top-level `WebPage` objects, two `Course` objects, one `Service`
object, and one `WebSite` object. No administrator HTML, cookies, credentials,
or private configuration were submitted. Production remains a separate gate.

## Safety And Acceptance Gate

The generic implementation is acceptable only when:

1. A new nullable migration leaves existing installations unchanged.
2. Administrator input is constrained, length-bounded, and schema-type aware.
3. Invalid identity pairs, URLs, delivery modes, and duration values fail
   closed.
4. Public output contains only values supported by the selected schema type.
5. Dependency-free, migration-contract, and disposable-database tests pass.
6. The clean-starter acceptance suite passes without client data.
7. A fresh isolated Adriana dry run reports 28 ready routes, no missing owners
   or conflicts, and exactly the two deliberate exclusions.
8. Transactional apply and idempotent rerun pass in a disposable client clone.
9. All 28 routes pass desktop and mobile metadata, JSON-LD, redirect, sitemap,
   robots, console, request, image, and overflow checks.
10. Generic output passes the Schema Markup Validator; any separately approved
    Google rich-result target also passes its feature-specific test.
11. Production deployment remains a separately approved backup, migration,
    smoke-test, and rollback operation.

Google's structured-data guidance requires markup to describe visible page
content and favors fewer complete, accurate properties over additional
inaccurate claims. That rule is the reason the source Course code and rating
remain excluded.

This decision preserves valid, relevant schema semantics; it does not claim
eligibility for a Google Course list rich result. Google's current Course list
contract expects an Organization provider, at least three courses, and
`ItemList`/carousel markup. The Adriana source identifies a Person provider,
and RED-CMS must not rewrite that identity merely to pursue a search feature.
Any future Course list feature needs its own visible-content, provider,
inventory, validation, and browser/Search Console gate.

## Next Reviewed Batch

The hosted Schema.org validation gate is complete and its route-level evidence
is retained with the isolated client QA package. The next reviewed batch is
the separately approved production backup, migration, smoke-test, and rollback
operation. Do not modify the original Adriana 5.0 installation or database
before that operation is explicitly authorized.

## Standards References

- [Google structured-data introduction](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data)
- [Google general structured-data guidelines](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)
- [Google Course structured data](https://developers.google.com/search/docs/appearance/structured-data/course)
- [Schema.org](https://schema.org/)
