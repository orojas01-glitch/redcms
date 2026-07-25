# RED-CMS 5.1 Bug Report: Per-Page SEO Metadata Is Not Preserved

- Status: Confirmed compatibility gap
- Target: RED-CMS 5.1
- Priority: High
- Severity: Medium

Area: Public rendering, content migration, page editing, SEO and social sharing

## Summary

RED-CMS cannot currently preserve a source page's complete search and social
metadata during migration. The administrator exposes page titles,
descriptions, tags, and content images, but it does not provide a separate
per-page SEO title, canonical override, complete Open Graph data, X/Twitter
Card data, or typed JSON-LD data.

The public renderer also reconstructs the document title instead of preserving
an imported title. During the 28-page Adriana Granobles migration, this changed
27 of the 28 source `<title>` values.

This is a migration compatibility problem even when the visible page content
renders correctly. Search result titles, canonical consolidation, social-card
previews, and structured-data meaning can all change or disappear.

## Current Behavior And Evidence

### Document title

`class/class_pagetitle.php` builds the title from the shared
`Website_Title` and the visible area or Article title. It applies `ucwords()`
and, for Article routes, replaces hyphens before rendering.

Consequences:

- a source SEO title cannot be stored separately from the visible heading;
- exact capitalization and wording are not preserved;
- the CMS site-name pattern is imposed on every page;
- 27 of 28 titles changed in the reference migration.

### Metadata

`class/class_metatags.php` currently renders:

- `meta name="description"`;
- `meta name="keywords"`;
- `meta property="og:description"`.

`themes/legacy-bootstrap/document.php` contains static Open Graph image type
and dimension declarations, but it does not render a page-specific
`og:image` URL.

There is no complete public-rendering path or per-page editor model for:

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

These objects can be copied into a theme template as fixed markup, but they
cannot currently be represented as CMS-owned, per-page metadata. Fixed theme
markup would not be a valid general solution because the values would drift
when editors change page content or routes.

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

## Steps To Reproduce

1. Start with a static page that has an exact `<title>`, canonical URL,
   complete Open Graph metadata, X/Twitter Card metadata, and JSON-LD.
2. Migrate the page into RED-CMS using the available page title, description,
   tags, and image fields.
3. Render the resulting RED-CMS public route.
4. Inspect the `<head>` and compare it with the source page.

## Actual Result

- The `<title>` is reconstructed and may have different capitalization,
  wording, and site-name placement.
- The meta description and keywords can be represented.
- Only `og:description` is emitted dynamically.
- Canonical, core Open Graph, X/Twitter Card, and JSON-LD values are missing or
  require unsafe site-specific template markup.
- The migration has no structured way to report metadata that was not
  imported.

## Expected Result

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
