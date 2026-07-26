# RED-CMS 5.1 Bug Report: Per-Page SEO Metadata Is Not Preserved

- Status: Constrained JSON-LD core, fresh isolated Adriana verification, and hosted Schema.org validation complete; production deployment remains
- Target: RED-CMS 5.1
- Priority: P1 — first Version 5.1 implementation milestone
- Severity: Medium
- Launch dependency: Adriana Granobles 28-page migration

Area: Public rendering, content migration, page editing, SEO and social sharing

## Implementation Progress

The generic core implementation now provides:

- an empty, nullable `RED_Page_SEO` migration keyed to stable Article, Section,
  Category, or Subcategory owner records;
- shared validation, storage, deletion, public fallback, Open Graph,
  X/Twitter, canonical, and typed JSON-LD helpers;
- one shared administrator field contract in the Article, Form, Gallery,
  Video, Banner, and Other content workspaces;
- the same field contract in Section, Category, and Subcategory create/edit
  workspaces;
- transactional SEO persistence for every routed content component without
  changing its existing component table;
- route-aware, atomic area creation and update with rollback-safe SEO
  persistence;
- Article revision capture/restore for SEO values and atomic SEO cleanup when
  routed content is deleted;
- a fail-closed, client-neutral import manifest and CLI migration report with
  dry-run, exact-database confirmation, transactional apply, conflict refusal,
  and idempotency checks;
- a desktop/mobile browser gate for exact metadata, typed JSON-LD, redirects,
  crawl controls, images, overflow, console errors, and same-origin failures;
- dependency-free contract checks plus guarded disposable-database persistence,
  revision, validation, cleanup, and rollback tests integrated into the main
  acceptance runner.

The clean generic acceptance gate passes with 92 dependency-free SEO
assertions, 17 migration-contract assertions, 38 disposable-database SEO
assertions, 36 applied migrations, and the expected 26-table schema.

The separate Adriana 5.1 QA installation also passed its migration and public
browser gate: 28 owners resolved without conflicts, 28 SEO rows were applied,
the idempotent rerun was unchanged, and all 56 desktop/mobile route checks plus
all 28 legacy redirects passed. The clean starter and original Adriana 5.0
installation/database remained separate and unchanged. Production deployment
has not been performed.

The 87 unsupported source JSON-LD property occurrences are now classified.
The launch decision is to represent visible content with generated
relationships and constrained typed fields, normalize one redundant homepage
self-reference, deliberately exclude the visitor-invisible Course code and
rating, and provide no arbitrary custom JSON-LD input. The constrained generic
implementation, clean-starter acceptance, and fresh isolated client QA now
pass. All 28 public renders also pass the hosted Schema.org Markup Validator
with zero errors and zero warnings. Production deployment remains. See
[`SEO-JSONLD-LAUNCH-DECISION.md`](SEO-JSONLD-LAUNCH-DECISION.md).

## Summary

RED-CMS 5.0 cannot preserve a source page's complete search and social
metadata during migration. The administrator exposes page titles,
descriptions, tags, and content images, but it does not provide a separate
per-page SEO title, canonical override, complete Open Graph data, X/Twitter
Card data, or typed JSON-LD data.

The Version 5.0 public renderer also reconstructs the document title instead of
preserving an imported title. During the original 28-page Adriana Granobles
comparison, this changed 27 of the 28 source `<title>` values.

This is a migration compatibility problem even when the visible page content
renders correctly. Search result titles, canonical consolidation, social-card
previews, and structured-data meaning can all change or disappear.

This compatibility work is the first Version 5.1 delivery priority. Its
generic migration, editor, rendering, fallback, acceptance, and isolated
client-QA contracts have now passed before optional business-vertical add-ons
are scheduled.

## Version 5.0 Behavior And Evidence

### Document title

In Version 5.0, `class/class_pagetitle.php` builds the title from the shared
`Website_Title` and the visible area or Article title. It applies `ucwords()`
and, for Article routes, replaces hyphens before rendering.

Consequences:

- a source SEO title cannot be stored separately from the visible heading;
- exact capitalization and wording are not preserved;
- the CMS site-name pattern is imposed on every page;
- 27 of 28 titles changed in the reference migration.

### Metadata

In Version 5.0, `class/class_metatags.php` renders:

- `meta name="description"`;
- `meta name="keywords"`;
- `meta property="og:description"`.

The Version 5.0 `themes/legacy-bootstrap/document.php` contains static Open
Graph image type and dimension declarations, but it does not render a page-specific
`og:image` URL.

There is no complete Version 5.0 public-rendering path or per-page editor model
for:

- canonical URLs;
- `og:locale`, `og:type`, `og:title`, `og:url`, and page-specific `og:image`;
- X/Twitter card, title, description, and image;
- page-specific JSON-LD.

### Structured-data inventory that could not be represented

The reference 28-page migration contains:

- 24 `WebPage` objects;
- 2 `Course` objects;
- 1 `Service` object;
- 1 `WebSite` object.

These objects could be copied into a Version 5.0 theme template as fixed
markup, but they could not be represented as CMS-owned, per-page metadata.
Fixed theme markup would not be a valid general solution because the values
would drift when editors change page content or routes.

## Reference Migration Evidence Method

The aggregate result in this report comes from the approved 28-page static
source snapshot and a separate, client-isolated RED-CMS migration environment.
The comparison:

1. Enumerated only root-level public HTML files, excluding documentation,
   previews, generated QA artifacts, and other non-public HTML.
2. Extracted each source page's title, canonical URL, Open Graph fields,
   X/Twitter fields, and top-level JSON-LD type.
3. Matched each source page to its approved RED-CMS public route.
4. Rendered the migrated route and compared the decoded `<title>` text exactly,
   preserving capitalization, wording, punctuation, and site-name placement.
5. Classified metadata as imported, safely derived, skipped, invalid, or not
   representable by the current RED-CMS model.

| Evidence | Result |
| --- | ---: |
| Root-level public pages | 28 |
| Exact rendered-title matches | 1 |
| Changed rendered titles | 27 |
| Top-level `WebPage` objects | 24 |
| Top-level `Course` objects | 2 |
| Top-level `Service` objects | 1 |
| Top-level `WebSite` objects | 1 |

The page-by-page comparison manifest, client database, and client theme remain
part of the separate migration deliverable and must not be copied into the
clean starter repository. RED-CMS acceptance should reproduce the fallback and
override contracts with generic fixtures; each client migration should retain
its own complete import report and route-level evidence.

## Isolated Adriana 5.1 Verification

The completed client-only QA used:

- the approved 28-route source inventory;
- 24 Article owners and 4 Section owners in a separately cloned client
  database;
- a manifest SHA-256 of
  `9628c4d1b297aff9ab4a52474a6f5d5242bc85404d49f6e0f70a56630d46b7c9`;
- a dry run, guarded transactional apply, and idempotent post-apply dry run;
- Chrome at 1512 × 699 and 390 × 844 for every route.

| Verification | Result |
| --- | ---: |
| Owners ready / missing / conflicting | 28 / 0 / 0 |
| Imported fields | 335 |
| Safely derived values | 169 |
| Skipped values | 0 |
| Explicitly non-importable JSON-LD properties | 87 |
| Distinct SEO titles / canonical URLs | 28 / 28 |
| Desktop/mobile checks passed | 56 / 56 |
| Legacy redirects passed | 28 / 28 |
| Sitemap URLs | 28 exact; 0 missing; 0 unexpected |
| Hosted Schema.org validation | 28 / 28; 0 errors; 0 warnings |
| Console, page, or same-origin request failures | 0 |
| Full-page screenshots | 56 |

The 87 non-importable values are property occurrences, not silently dropped
pages. Most are `inLanguage`, `about`, and `isPartOf`; the report also retains
the smaller Course and Service property set.

The completed classification assigns 51 occurrences to generated output, 33
to constrained typed fields, one redundant homepage `WebSite` self-reference
to safe normalization, and two visitor-invisible claims to deliberate
exclusion. No occurrence requires arbitrary custom JSON-LD. The implemented
typed output covers `WebPage`, `Course`, `Service`, and the homepage `WebSite`
object, including the approved generated relationships and constrained
type-specific details.

The fresh 2026-07-26 verification rebuilt the client data on a clean current
36-migration schema without rewriting the preserved backup's historical
checksum ledger. The 28-route manifest applied as 28 additive updates and
reran as 28 unchanged rows, with zero missing owners or conflicts and exactly
the two deliberate exclusions. All 56 desktop/mobile route checks, all 28
legacy redirects, the exact sitemap and robots contracts, and local validation
against the official current Schema.org vocabulary passed. The local
vocabulary check also corrected `inLanguage` so it renders on the 27
CreativeWork-derived objects but not the single `Service`.

With explicit authorization, the 28 unauthenticated public route renders were
submitted individually to `https://validator.schema.org/validate`. Every
hosted request returned HTTP 200, and the aggregate result was 28 parsed
top-level objects with zero errors and zero warnings: 24 `WebPage`, two
`Course`, one `Service`, and one `WebSite`. No administrator HTML, cookies,
credentials, or private configuration were submitted. Production deployment
remains a separate operation.

The detailed manifest, import reports, crawl-control copies, machine-readable
browser report, and screenshots live with the isolated client QA installation,
not in the generic distribution.

## Steps To Reproduce

1. Start with a static page that has an exact `<title>`, canonical URL,
   complete Open Graph metadata, X/Twitter Card metadata, and JSON-LD.
2. Migrate the page into RED-CMS using the available page title, description,
   tags, and image fields.
3. Render the resulting RED-CMS public route.
4. Inspect the `<head>` and compare it with the source page.

## Version 5.0 Actual Result

- The `<title>` is reconstructed and may have different capitalization,
  wording, and site-name placement.
- The meta description and keywords can be represented.
- Only `og:description` is emitted dynamically.
- Canonical, core Open Graph, X/Twitter Card, and JSON-LD values are missing or
  require unsafe site-specific template markup.
- The migration has no structured way to report metadata that was not
  imported.

## Version 5.1 Expected Result

RED-CMS should preserve an explicit per-page SEO title exactly as entered and
should support all relevant metadata through a combination of nullable editor
overrides and safe automatic values.

The CMS should not require editors to enter the same value repeatedly:

- canonical URL and `og:url` should come from the resolved public route;
- `og:locale` should normally come from the page or site language;
- `og:type` should come from a constrained content/schema type;
- Open Graph title and description should fall back to SEO title and meta
  description;
- X/Twitter fields should fall back to Open Graph values;
- social images should fall back from page-specific media to a site default;
- JSON-LD should be generated from typed, visible CMS content.

## Proposed RED-CMS 5.1 Model

Add a dedicated nullable SEO record associated with the stable owner of a
public route. Do not overload the visible `Title`, legacy `Tags`, or `BigPict`
fields.

### Basic SEO

- SEO title override
- Meta description
- Canonical URL override under Advanced options
- Robots index/follow controls
- Search-result preview

### Social preview

- Social/Open Graph title override
- Social/Open Graph description override
- Social image asset reference and image alternative text
- Optional constrained Open Graph type
- Optional X/Twitter card type
- Optional X/Twitter title, description, and image overrides

### Structured data

- Schema type selected from supported types
- Typed fields for `WebPage`, `Course`, and `Service`
- Site-level `WebSite` identity generated on the homepage
- Generated JSON-LD preview
- Validation before publishing
- Permission-gated custom JSON-LD only if a typed model cannot represent a
  legitimate property

Prefer CMS media identifiers over raw image URLs. Automatically generated
values such as canonical URL, `og:url`, locale, and default schema should not
be stored redundantly unless an authorized override is needed.

## Required Fallback Behavior

```text
SEO title       -> visible page title using the current RED-CMS default
OG title        -> SEO title -> visible page title
X title         -> OG title -> SEO title -> visible page title

OG description  -> meta description
X description   -> OG description -> meta description

X image         -> OG image -> page image -> site default image
OG URL          -> resolved canonical URL
```

An explicit SEO title override must bypass `ucwords()` and other wording or
capitalization transformations.

## Migration Requirements

- Use a versioned database migration and keep all new fields nullable.
- An upgraded installation with no populated SEO overrides must retain its
  existing public behavior.
- Import existing source values into their corresponding override or typed
  fields without silently rewriting them.
- Resolve old `.html` canonical URLs against the approved RED-CMS route and
  redirect map instead of copying them blindly.
- Report every value that cannot be imported.
- Report missing or inaccessible social-image assets.
- Report unsupported custom JSON-LD properties instead of discarding them.
- Do not place the site-level `WebSite` object in every page record.
- Keep canonical URLs, redirects, and sitemap URLs aligned.

## Acceptance Criteria

- A page can store an SEO title independently from its visible page title.
- An explicit SEO title is rendered byte-for-byte after normal HTML escaping;
  it is not passed through `ucwords()` or slug formatting.
- Every indexable public route emits exactly one canonical tag.
- Every public route emits one internally consistent Open Graph set containing
  `og:locale`, `og:type`, `og:title`, `og:description`, `og:url`, and
  `og:image`.
- A rendered `og:image` also includes useful type, dimensions, and
  `og:image:alt` when the information is available.
- Every public route emits an internally consistent X/Twitter Card set,
  including an image through an explicit value or documented Open Graph
  fallback.
- Standard pages can generate valid `WebPage` JSON-LD.
- Course and Service pages can generate their corresponding typed JSON-LD from
  visible content.
- The homepage can generate the site-level `WebSite` object without duplicating
  it across every page.
- Metadata output is escaped safely and rejects invalid schemes or malformed
  URLs.
- Empty optional fields do not produce empty or duplicate tags.
- Existing RED-CMS installations remain functional after migration and retain
  the current fallback title behavior until an override is populated.
- A migration report identifies every imported, derived, skipped, and invalid
  metadata value.
- Automated tests cover area routes, Article routes, the homepage, missing
  overrides, complete overrides, malicious input, canonical redirects, and
  schema validation.
- Desktop and mobile browser QA verifies all 28 reference routes with no
  duplicate metadata, missing required fields, PHP errors, or console errors.

## Out Of Scope

- Guaranteeing a particular search ranking or search-result title;
- automatically publishing content to social networks;
- adding arbitrary executable code through metadata fields;
- changing existing public URLs without a separately approved redirect plan.

## Standards References

- [Google Search title guidance](https://developers.google.com/search/docs/appearance/title-link)
- [Google canonical URL guidance](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
- [Open Graph protocol](https://ogp.me/)
- [Google structured-data introduction](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data)
- [Schema.org WebPage](https://schema.org/WebPage)
- [Schema.org Course](https://schema.org/Course)
- [Schema.org Service](https://schema.org/Service)
- [Schema.org WebSite](https://schema.org/WebSite)
