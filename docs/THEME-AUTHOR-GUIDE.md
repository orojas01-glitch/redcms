# RED-CMS Theme Author Guide

Status: Milestone 4 complete, including production activation, rollback, active-theme CSS editing, and theme-defined layout extensibility. The public entry point reads migration-backed active/previous theme state, resolves only a validated production-supported package, and hard-recovers through `legacy-bootstrap` if state, validation, adapter initialization, or rendering fails. `legacy-bootstrap` retains its exact compatibility views. `starter-reference` 1.2.0 declares separate preview and production templates/assets and runs through the guarded `RedStandardThemeAdapter`. A standard theme may declare one layout or many layouts with meaningful ids; its validated manifest is the authority for public rendering and administrator layout/position choices. Every public layout/component still receives strictly validated core-prepared data; Contact/Login submission logic remains in the CMS-owned endpoints. The Webmaster Themes boundary provides fixed previews plus compatibility-gated **Activate** and **Roll Back** controls, while **Advanced → Website CSS** resolves the editable file from the effective active package. **Advanced → Website Logo** is an optional shared PNG/JPG override; when empty, each template keeps its package-owned logo.

## Goal

A theme author should be able to convert a documented HTML template into a RED-CMS theme without editing `index.php`, public component classes, public URLs, or existing database table names.

RED-CMS themes are file-based. The database now holds only the active and immediately previous theme ids alongside the existing selected page-layout ids and content; typed appearance-setting persistence remains later work. Theme PHP, HTML, CSS, JavaScript, and manifest definitions stay on disk so they can be validated, versioned, deployed, and rolled back safely.

## Theme Types

`standard`

- A portable theme whose templates and local assets live inside its own directory.
- Declares `template` paths for regions, layouts, and components.
- Declares local asset `path` values relative to the theme directory.

`legacy-adapter`

- A transitional compatibility package for the existing RED-CMS renderer.
- Declares an `adapter` PHP file and its safe `adapterClass` name inside the theme directory.
- May declare `legacySource` paths relative to the RED-CMS project root.
- Is never considered a portable theme package.
- May mark a region as `renderedBy` another region when the old placement must remain bundled. In the current adapter, `themes/legacy-bootstrap/partials/header.php` includes the manifest-declared navigation and hero partials in their original positions.
- May split a document region into adapter-controlled start/end phases when existing content dispatch must remain between them. The current adapter loads that view only from its validated `regions.document.legacySource` declaration.

`legacy-bootstrap` 2.5.0 resolves its live document, header, navigation, hero, footer, four public layout views, and four public component views only from the already validated manifest. The adapter supplies each layout view with one breadcrumb callback and a position-validated slot callback; database access, layout selection, query preparation, and component dispatch stay outside those views. Core prepares the current public component name, expiration state, and only the values already consumed by that component: Article receives `recordId`, `layout`, `article`, and `position`; Form receives `recordId`; Gallery receives `position`, `recordId`, `layout`, and `smallPicture`; Other receives `recordId`, `layout`, `article`, and `position`. The fixed registry routes each exact input set through a required adapter method and preserves the historical Form/Gallery spacers. The adapter then prepares and strictly validates each component's exact current records, layout dimensions, selected/link/field/media state, and ordered render inputs before loading the manifest-declared view. The prepared contexts and manifest contain no class, method, renderer, callback, table, or other executable mapping. The four legacy public class implementations remain rollback-compatible copies, and every `cp_*` component method remains the live control-panel renderer. The former header/footer includes plus the public `main_menu::menu()` and `feature_slider::slider()` renderers also remain rollback-compatible. The administrator `cp_menu()`, `cp_slider()`, and all four component editor methods remain compatible; standard-theme layout wrappers and positions are now generated from the active validated manifest, while `content::cp_articles()` retains its fixed classes, methods, markup, order, permissions, and endpoints.

## Required Structure

A portable theme should follow this structure. The installed [`starter-reference`](../themes/starter-reference/README.md) package is the concrete v1 example:

```text
themes/example-theme/
├── theme.json
├── preview.svg
├── templates/
│   └── page.php
├── partials/
│   ├── header.php
│   ├── navigation.php
│   ├── hero.php
│   └── footer.php
├── layouts/
│   ├── full-width.php
│   └── sidebar-right.php
├── components/
│   ├── article.php
│   ├── form.php
│   ├── gallery.php
│   └── other.php
├── fixtures/
│   └── preview.json
└── assets/
    ├── css/
    ├── js/
    ├── fonts/
    └── images/
```

The public legacy layout/component boundaries and the core-owned administrator wrapper boundary remain isolated. A production-capable standard package must keep preview templates separate from its manifest-declared production document, region, layout, component, and asset paths. `starter-reference` is the concrete example: its original isolated preview providers remain read-only, while its production paths consume only the guarded adapter's core-prepared live contexts. Active-theme persistence, Webmaster selection, and one-step rollback are complete; typed setting editors and package install/upload remain separate later batches.

## Manifest Contract

Every theme directory must contain `theme.json`. The authoritative machine-readable schema is [`theme-manifest.schema.json`](theme-manifest.schema.json).

Required manifest areas:

- Identity: schema version, id, name, semantic version, type, description, and preview.
- Compatibility: supported RED-CMS and PHP versions.
- Assets: ordered styles and scripts, including their load location.
- Regions: document, header, navigation, hero, and footer.
- Layouts: stable layout ids, human labels, and numbered content positions.
- Components: Article, Form, Gallery, and Other render views.
- Settings: typed appearance controls declared by the theme.

Theme ids use lowercase letters, numbers, and hyphens and must exactly match their directory name. A canonical layout id must begin with a lowercase letter and may then contain only lowercase letters, numbers, and hyphens, up to 64 characters total. It should describe intent—for example `landing-hero-grid`, `article-sidebar-right`, or `checkout-minimal`—rather than depend on a fixed `index-1` sequence. Compatibility alias keys may preserve bounded historical casing or underscores because they name existing stored assignments, not new canonical layouts.

A standard manifest must declare at least one layout, but RED-CMS imposes no four-layout or ten-layout ceiling. Each layout owns its human label, template, exact position list, and required core-owned `hiddenPosition: 0`. Public position ids are unique integers from `1` through `99`; they do not need to be consecutive, so a theme may deliberately expose positions `1`, `5`, and `12`. The active validated manifest drives all layout selectors, position selectors, public slots, and control-panel wrappers. The legacy `RED_Layouts` table remains a compatibility catalog, not the source of standard-theme layout choices. The current runtime component registry is deliberately exact: every package declares Article, Form, Gallery, and Other, with no extra executable component ids.

## Editable Website CSS

**Advanced → Website CSS** follows the effective active theme. The browser never chooses or submits a filesystem path; RED-CMS validates the active production package and resolves the editor target from its manifest.

For a portable `standard` theme:

1. The first existing local entry in top-level `assets.styles` is the primary author-editable stylesheet.
2. If top-level `assets.styles` contains no local CSS, RED-CMS may use the first existing local entry in `production.assets.styles`.
3. HTTPS/external styles are loadable assets but are never editable through the filesystem editor.
4. The resolved file must stay inside the theme directory, have a `.css` path and resolved extension, and pass the same safe-path confinement rules as other theme assets.

Asset order is therefore part of the authoring contract. Put the stylesheet you want a Webmaster to edit first in top-level `assets.styles`. In `starter-reference`, that file is `assets/css/theme.css`; `assets/css/production.css` remains the later compatibility layer. The guarded production adapter loads both groups in that order. The fixed `legacy-bootstrap` compatibility package continues to map its `theme-style` asset to project file `css/style.css`.

The editor form is bound to the active theme, relative path, and exact file bytes it displayed. If another editor changes the CSS or the active target changes before Save, the write is refused as stale and the Webmaster must reopen **Website CSS**. A package with no declared local CSS shows no editable target.

## Converting Static HTML

Start from a complete HTML package, not only a homepage screenshot. The source should include representative internal pages, responsive behavior, navigation states, hero behavior, cards/articles, galleries, video, forms, footer content, and all licensed assets.

Map static files as follows:

| Static HTML area | Theme destination |
| --- | --- |
| Document shell and `<head>` | `templates/page.php` |
| Header | `partials/header.php` |
| Static navigation list | `partials/navigation.php` using CMS menu data |
| Hero or carousel | `partials/hero.php` using CMS feature data |
| Page columns and rows | `layouts/*.php` using numbered slots |
| Article/card markup | `components/article.php` |
| Gallery/video markup | `components/gallery.php` |
| Form markup | `components/form.php` |
| Footer | `partials/footer.php` |

### Feature slider lifecycle

The slider has two separate administrator steps. Enabling **Slider** on a Section, Category, or SubCategory makes that area eligible to render a hero. Uploading an Article's **Features Picture** supplies the hero image, but it does not select that Article for the slider. Use the area's **Edit Slider** control to select the Articles and set their order. The control remains available when the eligible slider has no selected Articles, which is how the first slide is assigned. RED-CMS stores the selection and order in the matching scope columns (`HomeFeatures`/`HomeFeatures_Order`, `SectionFeatures`/`SectionFeatures_Order`, and so on).

Core passes each theme an `enabled` flag and the complete ordered, active, unexpired `slides` array. A theme must handle all three cardinalities deliberately:

- zero slides: render no hero wrapper;
- one slide: render the slide without redundant previous/next controls;
- two or more slides: render every slide with obvious keyboard-operable controls, a visible current state, and a usable no-JavaScript first slide.

Do not query `RED_Articles` or interpret feature columns inside a theme. Treat the prepared slide image, title, summary, URL, and target as the entire view contract. Declare any required controller in `production.assets.scripts`; `starter-reference` uses `assets/js/hero-slider.js`, while `legacy-bootstrap` uses its already loaded Bootstrap carousel controller. Both bundled implementations advance only on explicit user input rather than auto-rotating content.

The current compatibility renderer now calls `red_legacy_render_layout_slot()` after core validates the layout id, render mode, numbered position, query columns, limit, and control-panel table input. Future portable views should remain mostly HTML and receive an equivalent page-slot API instead of fetching content themselves:

```php
<main class="container">
    <div class="row">
        <section class="col-lg-8">
            <?= $page->slot(1) ?>
        </section>
        <aside class="col-lg-4">
            <?= $page->slot(2) ?>
        </aside>
    </div>
</main>
```

The four legacy public layout shells remain in `themes/legacy-bootstrap/layouts/index.php` through `index-3.php`. Each view may call only the adapter-supplied `$redThemeRenderBreadcrumb()` and `$redThemeRenderSlot($position)` callbacks. For a standard theme, `RedStandardThemeAdapter` registers every manifest-declared layout dynamically, resolves its exact declared positions, and builds the corresponding core-owned control-panel wrappers plus hidden position `0`; adding a fifth or twelfth layout requires no new switch in `class/class_page_layout.php`. The complete control-panel component switch remains in `content::cp_articles()`. Core prepares only the layout/position, visible-title or hidden-item wrapper state, fixed order endpoint/form/function/alert/message/CSRF values, authorization result, order values, and exact Article/Other/Form/Gallery editor inputs. The context contains no executable class, method, renderer, callback, or callable mapping; `content::cp_articles()` still owns the fixed `cp_Article`, `cp_other`, `forms`, and `gallery` calls and all HTML/JavaScript. Before public dispatch, `includes/legacy_component_helpers.php` separately prepares a non-executable context containing the supported component name, the existing expiration result, and that component's exact legacy inputs. Its fixed registry routes all four public components through validated adapter methods and manifest-declared component views. Those views do not open database connections, enforce permissions, choose component classes, or build public URLs; the Form compatibility view deliberately retains only the existing request/session-dependent validation behavior needed for exact output parity.

## Path And Execution Rules

- All standard-theme templates and local assets must remain inside that theme directory.
- Absolute paths, parent traversal (`..`), empty path segments, and symbolic-link escapes are rejected.
- External assets must use HTTPS. External scripts should include Subresource Integrity metadata.
- A manifest describes files; it does not make arbitrary paths executable.
- Theme installation is a trusted developer or Webmaster operation. A web ZIP uploader is outside the initial contract.
- Raw PHP and complete page templates must not be stored in the database.

The isolated fixture preview is intentionally narrower than the guarded production renderer. It executes only the audited `starter-reference` package from CLI, reconstructs exact document/region/layout/component arrays, and passes only scalar/array data or core-rendered HTML strings into fixed view variables. Template source inspection rejects request/session/global access, includes, classes, callbacks, variable calls, non-escaping function calls, and executable browser markup. Preview styles cannot load external resources, SVG fixtures cannot contain executable/resource-loading markup, and client-side scripts are refused. Local CSS and images are inlined so the rendered HTML performs no network request. Optional output is restricted to the operating-system temporary directory.

The current compatibility header and footer views receive adapter-prepared arrays named `$redThemeHeaderContext` and `$redThemeFooterContext`. Header context includes `siteTitle`, `homeUrl`, trusted `customHtml`, and a nullable `logo` object. When present, `logo` contains only the confined `/images/...` URL, filename, PNG/JPEG MIME, intrinsic width/height, and `source=advanced.Website_Logo`; templates control its rendered dimensions and should retain their own bundled mark or text treatment when `logo` is `null`. An empty `Website_Logo` setting resolves to `null`; the first uploaded safe raster becomes the shared override. The navigation view receives `$redThemeNavigationContext`, whose ordered root, child, and grandchild items, active classes, links, targets, and legacy Home marker are prepared by core code. The hero view receives `$redThemeHeroContext`, whose enabled state and complete ordered, expiration-filtered slide image, title, description, link, and target values are prepared by core code; themes must not truncate that array to its first item. Public layout views receive callbacks rather than database state: breadcrumb output remains in its historical position, and each slot callback validates the declared layout/position before dispatching to the fixed component registry. The Article view receives `$redThemeArticleContext` with the current URL, selected alias, exact five-field layout dimensions, ordered 15-column Article rows, and derived selected/link/target/close-line state. The Form view receives `$redThemeFormContext` with the exact ordered Form records, parsed fields, raw/JavaScript aliases, action, endpoint, and payload mode. The Gallery view receives `$redThemeGalleryContext` with exact dimensions, ordered nine-column Gallery rows, stateful width/target, parsed photos/captions, Video provider/id, Banner image, and link inputs. The Other view receives `$redThemeOtherContext` with only the selected article alias, exact dimensions, and ordered Article rows. These component views open no database connections and choose no tables, feature columns, component implementations, or public URLs. This is a transitional legacy contract; portable themes will receive structured settings and prepared view data instead of querying RED-CMS tables.

## Layout Compatibility

Existing `Layout` values on Sections, Categories, SubCategories, and Articles remain unchanged during the compatibility migration. Before a new theme can be activated, every layout id currently in use must either:

1. exist in the new theme, or
2. have an explicit, reviewable fallback mapping.

Activation must stop with a readable report when a required layout id, numbered position, or component view is missing. It must never silently relocate content.

Fallback mappings are declared explicitly under `compatibility.layoutAliases`. Alias keys are existing stored layout ids; alias values must name canonical layouts declared by the same theme. Alias keys may preserve bounded historical casing such as `Full-Width`, but they cannot collide with a canonical id, exceed the 64-character stored-id contract, or point to an undeclared layout. Example:

```json
{
  "compatibility": {
    "cms": ">=4.0",
    "php": ">=8.2",
    "layoutAliases": {
      "Full-Width": "index-2"
    }
  }
}
```

This resolves old content at render and edit time without rewriting its database row. The alias remains visible as a compatibility value in administrator editors, while every new choice comes from the active theme's canonical layout declarations. Both activation and rollback run the same live compatibility preflight, so a candidate with an undeclared current id or a missing position used beneath that id is blocked before either state transition.

The `legacy-bootstrap` manifest declares its four historical canonical layouts—`index`, `index-1`, `index-2`, and `index-3`—and explicitly maps the live historical `Full-Width` id to `index-2`. That package's four-layout inventory is a property of the legacy template, not a CMS-wide limit. A different standard package may declare one, twelve, or more layouts independently.

`starter-reference` provides a concrete fifth-layout example named `feature-grid`. Its preview view is `layouts/feature-grid.php`, its guarded production view is `layouts/production-feature-grid.php`, and its five declared positions are Lead feature (`1`), Left card (`2`), Center card (`3`), Right card (`4`), and Closing row (`5`). The middle three positions form a responsive equal-card row; the opening and closing positions span the full content width. This example belongs only to the starter package and does not add a layout to `legacy-bootstrap`.

Two route states intentionally have no layout view, but they no longer share the same response. An active matched row whose exact layout result is the empty string uses `empty-layout-shell`: HTTP 200, the effective active theme shell, and zero layout/component bytes. A lookup with no active row uses `unmatched-theme-404`: HTTP 404 plus core-owned semantic not-found markup inside the effective active theme shell. Core applies this distinction before theme layout dispatch through [`PUBLIC-ROUTE-FALLBACK-CONTRACT.md`](PUBLIC-ROUTE-FALLBACK-CONTRACT.md). Theme authors may style the documented `.red-public-not-found*` hooks, but must not substitute a normal layout, query fallback content, move assignments, change the status, or redirect automatically.

## Read-only Compatibility Preflight

Run the current live inventory report for a portable theme:

```sh
scripts/theme-preflight.sh starter-reference
scripts/theme-preflight.sh starter-reference --json
```

The wrapper uses the documented FrankenPHP CLI because the standalone PHP build does not include `mysqli`. It issues only fixed `SELECT` statements against:

- `RED_Sections.Layout`
- `RED_Categories.Layout`
- `RED_SubCategories.Layout`
- `RED_Articles.Layout` and `RED_Articles.Component`
- the read-only `RED_Layouts` name/position catalog

Compatibility requires a valid manifest, `type: standard`, every layout id currently assigned to content either declared, explicitly aliased, or supplied by a published core custom layout, every numbered position currently rendered beneath those assignments, and every component id currently assigned to Articles. The report exposes provided canonical layouts/positions, accepted canonical-plus-alias ids, resolved requirements, source/count details, and the legacy plus published custom layout catalogs; unused layouts are not errors. The manifest validator requires exactly the four runtime-supported component views—Article, Form, Gallery, and Other—so an unassigned `Other` view remains part of a complete package and unsupported extra component ids are rejected. Custom layouts remain core-owned rather than package files: the active standard package supplies the document, regions, components, and design system, while RED-CMS supplies the validated 12-unit structure and generic grid renderer.

Preflight does not copy files, execute standard-theme templates, select a theme, persist a setting, create preview state, change a layout assignment, or write to any table. A readable failure report names missing assigned layout ids, exact positions, and component ids; the activation boundary consumes the result separately.

## Isolated Fixture Preview

Render the audited starter fixture in memory and print its report:

```sh
scripts/theme-preview.sh starter-reference --json
```

Write a browser-inspectable file only under a temporary directory:

```sh
scripts/theme-preview.sh starter-reference --output=/tmp/redcms-starter-preview.html --json
```

The fixture at `themes/starter-reference/fixtures/preview.json` supplies only the minimum portable data contract: document language/title/description; header, navigation, hero, and footer data; one declared layout and exact numbered slots; and component-specific Article, Form, Gallery, and Other values. Core validates and renders components first, then the selected layout, then the document shell. It never loads `includes/config.php`, opens a database connection, starts or reads a session, inspects the request, selects or activates a theme, persists settings, or changes the live runtime. Production may execute a validated active `standard` package through the guarded adapter, but fixture preview remains isolated from that state and hard recovery remains `legacy-bootstrap`.

This fixture remains a developer authoring aid and is not used by the administrator preview. Its deterministic JSON input and output stay fixed while separately validated real-content providers feed the same audited renderer.

### Offline Gallery Video Contract

The audited fixture renderer also accepts one exact, fixture-only Gallery Video shape for dependency testing:

```json
{
  "title": "Video Gallery contract",
  "video": {
    "provider": "youtube",
    "id": "pP8VJwjSnqA",
    "caption": "How to add content"
  }
}
```

The Gallery object may contain only `title` and `video`; the nested object may contain only `provider`, `id`, and `caption`. Provider must be exactly lowercase `youtube` or `vimeo`. A YouTube id is exactly 11 characters from `[A-Za-z0-9_-]`; a Vimeo id is 6 to 12 decimal digits and cannot begin with zero. URLs, embed HTML, mixed image/Video shapes, extra keys, noncanonical providers, malformed ids, scalar shortcuts, and executable mappings fail closed.

Core reconstructs only the allowlisted scalar values, maps the provider to a fixed display label, and passes them to the Gallery view in `fixture-preview` or the fixed `read-only-administration-preview` mode. The view renders an accessible offline placeholder with escaped text and inert provider/id data attributes. It emits no iframe, object, embed, script, link, autoplay behavior, cookie-bearing player, or HTTP(S) request. Contact and Home modes reject the Video shape. The Administration provider supplies only a recognized provider/id pair; it adds no chooser action, session mode, public runtime, or activation path.

The reviewed YouTube contract renders deterministically at 15,083 bytes and SHA-256 `d7b30add407ec65f2e7c68a5f83865d5e4b26c4e8b5b2a029e2dd583813e49b0`. The current image Gallery fixture remains exactly 19,507 bytes at SHA-256 `106c984a77643cb0a8b4f0154a59e0558b1d082ff90267d5cbd7e785bbd02a7d`. These values include the current Webmaster-edited Website CSS; an approved CSS save intentionally requires reviewing and refreshing every exact preview checkpoint.

## Read-only Contact Data Preview

Render the first real CMS data slice through the same audited starter outside the live request path:

```sh
scripts/theme-preview-contact.sh starter-reference --json
scripts/theme-preview-contact.sh starter-reference --output=/tmp/redcms-contact-preview.html --json
```

The wrapper uses FrankenPHP CLI because this mode needs `mysqli`. The provider in `includes/theme_preview_contact_helpers.php` opens a read-only transaction and executes exactly four fixed `SELECT` operations for active Spanish Contact Section `24`; its paired active Form Article/child; active Spanish Home/Contact root navigation; and only `Website_Title`/`Website_Footer`. It does not reuse live request/session state or accept a route, record id, query, table, template, setting key, or endpoint from CLI input.

Core requires exact selected columns and bounded row counts, the fixed `contacto:sp:index-1:Y` canary, the numeric Article/Form relationship, Form component in SectionPosition `1`, safe ordered navigation with `/contacto/` current, and the two-setting allowlist. The legacy Form definition must contain the expected six safe fields and one final button; unknown properties/types, defaults, endpoints, duplicate names, executable text, unsafe URLs, extra rows, and schema drift fail closed. Allowed legacy HTML is reduced to bounded plain text before the existing portable document/region/layout/Form contract is reconstructed.

The JSON report contains only provenance ids/counts/fallback labels plus explicit scope: four database reads, zero database writes, zero session reads/writes, and zero live-runtime changes. Output is still restricted to a temporary HTML path, has no form action or script, and cannot activate or select the starter. The same provider is now reused only after the authenticated boundary below closes its session; the provider and rendered theme still receive no session or request state.

## Read-only Home Data Preview

Render the second real CMS data slice through the same audited starter outside the live request path:

```sh
scripts/theme-preview-home.sh starter-reference --json
scripts/theme-preview-home.sh starter-reference --output=/tmp/redcms-home-preview.html --json
```

This wrapper also uses FrankenPHP CLI for `mysqli`. The provider in `includes/theme_preview_home_helpers.php` opens one read-only transaction and executes exactly five fixed `SELECT` operations for active Spanish Home Section `13`; active Spanish root navigation; bounded active/unexpired Home slider Articles; fixed Gallery Article `1154326271` and paired Gallery `2030445666`; and only `Website_Title`/`Website_Footer`. It accepts no route, record id, query, table, template, setting key, media root, or endpoint from CLI input.

Core requires exact selected columns and bounded row counts; canary `home:sp:index-1:Y`; query limit `100`; `slider` feature; Gallery component, relationship, Home position/order `1:1`, and Banner subtype; the original exact two-row root navigation; the two-setting allowlist; local URL; valid dates; and a confined fallback raster filename. The provider's hero query accepts zero or more qualifying slider rows within its fixed bound and its older single-hero fixture uses only the first candidate's title/summary; the production theme contract separately receives and renders the complete slide array. As of 2026-07-18, production Home has two ordered candidates—`demo article` and `test article`—but the live fixed preview intentionally fails closed before rendering because the user added a third About navigation row. Its 41-assertion pure contract remains valid; widening that historical preview inventory is a separate change. `images/gallery/layout-02.png` remains beneath the fixed Gallery media root and passes type, dimensions, size, and SHA-256 checks before core embeds it as a data URI. Absolute paths, raw filesystem access, and external media requests never enter the portable contract.

The JSON report exposes only bounded canary/component/gallery provenance, query ids/counts, fallback labels, media byte/hash facts, and explicit scope: five database reads, zero database writes, zero session reads/writes, and zero live-runtime changes. Unsafe media paths/URLs, schema or relationship drift, extra rows, altered query text, executable values, and unexpected Home data fail closed. The result cannot select or activate the starter and does not change the live Home route.

## Read-only Administration Data Preview

Render the fixed Administration landing composition through the same audited starter outside every live request and authenticated preview path:

```sh
scripts/theme-preview-administration.sh starter-reference --json
scripts/theme-preview-administration.sh starter-reference --output=/tmp/redcms-administration-preview.html --json
```

The FrankenPHP-backed provider in `includes/theme_preview_administration_helpers.php` opens one read-only transaction and executes exactly four fixed reads: active Spanish Administration Section `25`; the exact ordered Article/Form/Gallery composition with its paired Login/Video children; active Spanish root navigation; and only `Website_Title`/`Website_Footer`. It accepts no caller route, record id, query, table, Form template, media URL, provider/id, path, setting key, mode, or endpoint.

Core requires `administracion:sp:index-3:Y`, query limit `100`, no features, and the exact existing order: Login Form Article `966111194` with Form `884542279` at position/order `1/1`; Instructions Article `89196971` at `2/1`; and Video Gallery Article `880701099` with Gallery `1968830051` at `2/2`. Login must parse to exactly empty required username/password fields plus a final inert button and receives no action or defaults. Instructions rich markup is stripped to bounded plain listing text. The fixed YouTube URL is parsed in core, then only provider `youtube` and id `pP8VJwjSnqA` enter the non-executable Gallery Video object; the raw URL never crosses the contract.

The dependency-free Administration fixture-row checkpoint, including the current Website CSS and the clean-core Instructions summary, is deterministic at 13,685 bytes and SHA-256 `089e4aac3d7978dc732da6e369c74f2d536626aacc3653ceba37e2368dd0222f`. A real-data artifact additionally depends on the fixed database canary and should be reviewed by rerunning the CLI when those rows are present. The JSON report contains only fixed ids/counts, query ids, summary hash, provider/id, fallback labels, and explicit scope: four database reads, zero database writes, zero session reads/writes, and zero live-runtime changes. The command creates no chooser/session mode and cannot select or activate the starter.

## Read-only Selected Instructions Article Preview

Render the fixed selected Instructions Article through the audited starter outside every live request and authenticated preview path:

```sh
scripts/theme-preview-instructions.sh starter-reference --json
scripts/theme-preview-instructions.sh starter-reference --output=/tmp/redcms-instructions-preview.html --json
```

The FrankenPHP-backed provider in `includes/theme_preview_instructions_helpers.php` opens one read-only transaction and executes exactly three fixed reads: active Spanish Article `89196971` joined to parent Section `25`; active Spanish root navigation; and only `Website_Title`/`Website_Footer`. It accepts no caller theme, route, record, query, table, body HTML, media path, filename, setting key, mode, or endpoint.

Core requires the exact `/administracion/instructions`, `index-2`, Page position/order `1/0`, Article/Section ids, aliases, language, active state, dates, empty categories/link/target, Section layout/query limit, and source summary/body bytes plus SHA-256. The current 7,506-byte body is sanitized through a core-owned exact element/attribute policy. Headings are shifted beneath the preview H1/H2, links become local fragment references whose targets must exist, and executable elements/attributes, form controls, document boundaries, external URLs/resources, and caller styles fail closed.

The exact ordered four-screenshot inventory must resolve beneath `admin/images/red-cms-instructions-manual_files`, match its 367,642-byte manifest SHA-256 `330c2c4ca83b78522e9d3484430d85fc79e3c11363089fdd23d27efcf0157994`, and pass PNG/JPEG MIME, intrinsic-dimension, and one-megabyte-per-file bounds. Core applies the fixed responsive/lazy-decoding policy and embeds the reviewed bytes as data URIs. The package never receives a filesystem path or external resource URL.

Two live renders are byte-identical at 509,066 bytes and SHA-256 `d731c02698cc8197f36fb8d2f63586fda4fdb093c005905db82cb3e02ebcba3a`. The report exposes only fixed ids/counts, source and sanitized hashes, media manifest facts, normalization counts, fallbacks, and explicit scope: three database reads, zero database writes, zero session reads/writes, and zero live-runtime changes. This command creates no chooser/session mode and cannot select or activate the starter.

## Read-only Selected Login Form Preview

Render the fixed selected Login Form through the audited starter outside every live request and authenticated preview path:

```sh
scripts/theme-preview-login.sh starter-reference --json
scripts/theme-preview-login.sh starter-reference --output=/tmp/redcms-login-preview.html --json
```

The FrankenPHP-backed provider in `includes/theme_preview_login_helpers.php` opens one read-only transaction and executes exactly three fixed reads: active Spanish Login Article `966111194` joined to paired `RED_C_Form` child `884542279` and parent Section `25`; active Spanish root navigation; and only `Website_Title`/`Website_Footer`. It accepts no caller theme, route, record, query, table, Form template, mode, endpoint, payload, request, session, or response value.

Core requires the exact `/administracion/login`, `index-2`, Page position/order `1/0`, Article/Form/Section relationship, dates, empty categories/link/target, 10-byte summary hash, and 239-byte template SHA-256 `2609b17e4e14419ac0c2117cfb699db242b193089e409d1aa0f6391da19049b5`. The query excludes every operational Form column. The parser reconstructs only required empty `username` text and `password` fields plus the final inert `submit` button; the portable Form has no action, method, endpoint, default value, submit behavior, validation, payload, or response contract.

Two live renders are byte-identical at 12,408 bytes and SHA-256 `56601b59af8b7eff070beeac1c05a17f0fb60c7737f338b694fe3defc05758fb`. The report exposes only fixed ids/counts, field names/count, template byte/hash canary, fallbacks, and explicit scope: three database reads, zero database writes, zero session reads/writes, zero submissions, and zero live-runtime changes. This command creates no chooser/session mode and cannot select or activate the starter.

## Read-only Selected Contact Form Preview

Render the fixed selected Contact Form through the audited starter outside every live request and authenticated preview path:

```sh
scripts/theme-preview-selected-contact.sh starter-reference --json
scripts/theme-preview-selected-contact.sh starter-reference --output=/tmp/redcms-selected-contact-preview.html --json
```

The FrankenPHP-backed provider in `includes/theme_preview_selected_contact_helpers.php` opens one read-only transaction and executes exactly three fixed reads: active Spanish Contact Article `459269660` joined to paired `RED_C_Form` child `93039112` and parent Section `24`; active Spanish root navigation; and only `Website_Title`/`Website_Footer`. It accepts no caller theme, route, record, query, table, Form template, mode, endpoint, payload, request, session, or response value.

Core requires the exact `/contacto/contact`, `index-1`, Page position/order `1/0`, Article/Form/Section relationship, dates, empty categories/link/target/summary, and 686-byte template SHA-256 `5f84ca1244b3c9a66884783469ef6ee2bed4d469f2a75d73a337acc72c43d1a1`. The query excludes every operational Form column. Core reuses the strict reviewed Contact parser and reconstructs only empty `name`, `title`, `email`, `telephone`, `fax`, and `message` fields plus the final inert `submit` button; the portable Form has no action, method, endpoint, default value, submit behavior, validation, payload, or response contract.

Two live renders are byte-identical at 14,153 bytes and SHA-256 `f022c36609523c4e59c4594634e13bb25b33f185f2bd031ddf71949d341cc0a0`. The report exposes only fixed ids/counts, field names/count, template byte/hash canary, fallbacks, and explicit scope: three database reads, zero database writes, zero session reads/writes, zero submissions, and zero live-runtime changes. This command creates no chooser/session mode and cannot select or activate the starter.

## Webmaster Themes Chooser, Preview, Activation, And Rollback

While signed in as a Webmaster, open **Advanced → Themes** in the existing control panel or visit the fixed boundary directly:

```text
/admin/bin/theme_preview.php
```

The chooser discovers local theme directories through the shared manifest validator, removes every invalid result, and renders bounded metadata plus the persisted active/previous state. Each package card shows its manifest-defined layout count and meaningful layout ids. It marks the active package, identifies the rollback target, and gives the validated `starter-reference` the fixed **Preview Contact** and **Preview Home** actions. A valid inactive production-supported package receives **Activate** only when its canonical layouts plus explicit aliases cover every live assignment and numbered position. **Roll Back** is held to the same compatibility gate. A blocked card names the exact missing layout, position, or component ids. There is no arbitrary selector, path, route, template, setting editor, or package install/upload action.

Every preview, activation, rollback, and exit mutation is POST-only and requires the current RED-CMS CSRF token plus Webmaster authorization. Preview start forms retain their fixed action-only inputs. Activate accepts only an exact validated theme id; rollback accepts no caller-selected target and always uses the locked previous-state row. Guest and unauthenticated sessions receive HTTP 403 `no`; missing/wrong CSRF receives HTTP 403 `csrf`; unexpected theme, path, route, mode, record, setting, template, or query inputs are rejected. If a package becomes invalid or unavailable, its preview/activation action disappears and any existing preview state fails closed.

The server stores one exact preview-state object for at most 15 minutes. It is bound to the current Webmaster `RecordID`, a SHA-256 binding of the current session id, a fresh random nonce, fixed `starter-reference`, one exact allowlisted `contact` or `home` mode, and the unchanged `legacy-bootstrap` rollback target. Missing, copied, reordered, expired, cross-session, cross-administrator, arbitrary-mode, or otherwise tampered state fails closed and is removed without touching other session values.

The child frame renders only after that state validates and its fixed `view=contact` or `view=home` query exactly matches the stored mode; cross-mode child requests return HTTP 403. The endpoint then closes the administrator session before opening only the matching four-read Contact or five-read Home transaction. The response is no-store, protected by a restrictive Content Security Policy, and displayed in a sandbox that permits same-origin rendering but no scripts, forms, popups, or top-level navigation. The explicit exit action removes only the preview key and confirms that no active theme, setting, layout, content, or live-runtime value changed; logout or administrator invalidation also makes the child immediately unavailable.

Preview state remains isolated from public selection: starting or exiting a preview never changes the active theme. Public selection reads only the two fixed global theme-state rows. Activation atomically writes `previous=current` and `active=validated target`; rollback atomically makes the locked previous package active. Both transitions hold one database-scoped theme-contract mutex from the final live preflight through commit and post-commit verification, while content/layout writers use the same mutex. The two global theme-state rows are reserved from generic Advanced and logo-upload writes. Typed settings and package installation remain separate work.

## Read-only Activation Readiness

Manifest preflight answers one narrow question: does a validated standard package declare every currently assigned layout and component id? Activation readiness asks whether the package and core contracts can reproduce the actual routes, component subtypes, operational behavior, media, trusted content, navigation exposure, and fallbacks behind those ids. Complete preflight coverage must never be treated as authorization to activate.

Run the fixed live inventory and its dependency-free safety suite:

```sh
scripts/theme-readiness.sh starter-reference
scripts/theme-readiness.sh starter-reference --json
scripts/theme-readiness-self-test.sh
```

The live reporter opens one read-only transaction and performs exactly eight fixed `SELECT` operations for active Spanish areas, Articles, navigation, paired Form children, paired Gallery children, the legacy layout catalog, the published custom-layout catalog, four allowlisted region settings, and the two fixed global theme-state rows. It validates exact shapes and relationships, route/media canaries, manifest and custom-layout id coverage, the explicit production contract, both persisted package references, guarded standard initialization, authenticated controls, public selection, and legacy recovery. It accepts no caller route, record, query, table, path, setting, template, selector, or activation input and reports zero writes, session access, selection changes, or public renders.

Run the independent generic document/navigation/hero/settings data boundary with:

```sh
scripts/theme-region-context.sh
scripts/theme-region-context.sh --json
scripts/theme-region-context-self-test.sh
```

This provider accepts no caller input and performs exactly four fixed reads inside `START TRANSACTION READ ONLY`: the four allowlisted Spanish settings, active Spanish navigation, active Spanish Section/Category/SubCategory hero areas, and bounded current hero Articles. It validates a fixed route-independent shape and derives all nine current route views without opening a theme, inspecting request/session state, resolving logo media, or connecting to the live runtime.

Milestone 4 production activation infrastructure is complete. The standard production adapter bridges audited core-prepared Article/Form/Gallery/Other live contexts without giving themes permission, CSRF, database, session, mail, authentication, or endpoint ownership. Layout extensibility and live-assignment compatibility pass, including the explicit `Full-Width → index-2` mapping. Home Section `13` is restored to its audited `index-1` canary, and both selected feature Articles cross the zero/one/many hero contract in order; the current primary report returns `activationReady=true` with no gaps. Readiness verifies the empty optional `Website_Logo` override with public rendering false and template fallback true. The complete current matrix and activation/rollback evidence are in [`THEME-ACTIVATION-READINESS.md`](THEME-ACTIVATION-READINESS.md).

The exact operation boundary remains documented in [`OPERATIONAL-FORM-BOUNDARY.md`](OPERATIONAL-FORM-BOUNDARY.md), and shell behavior in [`PUBLIC-ROUTE-FALLBACK-CONTRACT.md`](PUBLIC-ROUTE-FALLBACK-CONTRACT.md). Themes render prepared content but remain operationally unprivileged: live Contact/Login endpoints and side effects stay in core. Extending typed setting persistence or adding package installation would require separate approval.

## Settings

Themes declare typed settings such as text, URL, image, color, number, select, checkbox, and menu controls. A later CMS-owned settings boundary—not the theme—will validate and persist their values. The current starter declarations are metadata only.

`navigation.breadcrumbs` is the one reserved setting that already affects standard-theme rendering. Declare it as a `checkbox` with a boolean `default`. A missing declaration defaults to `true` for backwards compatibility; `default: false` suppresses the complete URL-derived breadcrumb context for every public and administrator layout in that package before any breadcrumb database lookup. Layouts should render the supplied `breadcrumb` array only when it is nonempty. The legacy adapter remains unchanged and continues to use `class_build_breadcrumb.php`; future persisted theme settings can reuse the same reserved key without changing the template contract.

The legacy manifest maps its current Website Title, Logo, Header HTML, and Footer HTML concepts for discovery. New themes should prefer structured settings such as logo, header call-to-action, footer menu, contact details, and social links over large raw-HTML fields.

## Validation

Validate every installed theme:

```sh
scripts/theme-validate.sh --all
```

Validate one theme:

```sh
scripts/theme-validate.sh starter-reference
```

Run the read-only live compatibility report:

```sh
scripts/theme-preflight.sh starter-reference
```

Run the separate all-route activation-readiness report:

```sh
scripts/theme-readiness.sh starter-reference
scripts/theme-readiness.sh starter-reference --json
```

Run the dependency-free contract and safety-path self-test:

```sh
/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php scripts/theme-contract-self-test.php
scripts/theme-preflight-self-test.sh
scripts/theme-preview-self-test.sh
scripts/theme-preview-contact-self-test.sh
scripts/theme-preview-home-self-test.sh
scripts/theme-preview-admin-self-test.sh
scripts/theme-preview-administration-self-test.sh
scripts/theme-preview-instructions-self-test.sh
scripts/theme-preview-login-self-test.sh
scripts/theme-preview-selected-contact-self-test.sh
scripts/public-form-operation-self-test.sh
scripts/public-route-fallback-self-test.sh
scripts/theme-region-context-self-test.sh
scripts/theme-activation-self-test.sh
scripts/theme-contract-lock-self-test.sh
scripts/theme-layout-extensibility-self-test.sh
scripts/theme-readiness-self-test.sh
```

The validator checks JSON/schema shape, safe ids and paths, referenced files, unique declarations, required regions/components, external assets, exact layout positions, optional layout aliases, and the separate production mapping. The legacy compatibility path still refuses standard execution unless the public persisted-state caller explicitly enables it. Existing preview/provider/operation/fallback/region suites retain their strict data and zero-side-effect contracts. The 58-assertion administrator suite locks the filtered inventory, layout counts/ids, fixed previews, active/previous labels, compatibility-gated actions, Webmaster/CSRF gates, exact Activate/Roll Back request shapes, and absence of settings/install controls. The 28-assertion activation suite locks persisted-state validation, missing-state legacy recovery, production candidate validation, live layout/component compatibility, atomic transitions, rollback target ownership, transaction failure, and post-commit verification. The 58-assertion extensibility suite proves valid one-layout and twelve-layout packages, 64-character meaningful ids, non-consecutive positions through `99`, exact-case assignments, bounded aliases, dynamic public slots, and core-owned control-panel wrappers. The 14-assertion live serialization suite proves one database mutex, same-connection reentrancy, cross-connection exclusion, exception-safe release, public/admin effective-theme agreement, safe upload placeholders, and reserved theme-row rejection. The 50-assertion readiness suite locks all previous route/media/operation/logo facts plus alias-aware layout and position coverage, persisted state, valid production files, guarded standard initialization, authenticated controls, public selection, hard legacy recovery, and zero-write report scope.

Warnings do not fail validation. Missing files, unsafe paths, invalid schema fields, or incomplete component/layout contracts do fail validation.

## Milestone 4 Migration Order

1. Complete — contract, validator, resolver, and legacy package without changing live rendering.
2. Complete — connect the resolver with a hard `legacy-bootstrap` fallback and prove unchanged public/admin output.
3. Complete — move the document shell and assets behind the adapter with exact output parity.
4. Complete — prepare fixed legacy header/footer data in core helpers and render their unchanged HTML from manifest-declared theme partials.
5. Complete — prepare main-menu data outside its markup and move unchanged navigation into its own manifest-declared theme partial while keeping hero behavior bundled.
6. Complete — prepare feature-slider data outside its markup and move unchanged hero output into its own manifest-declared theme partial.
7. Complete — inventory the four live public/control-panel slot sets, prepare their existing inputs in core, and route unchanged content dispatch through one verified boundary.
8. Complete — move the unchanged public layout HTML into four manifest-declared legacy theme views while retaining the control-panel wrappers and component switches.
9. Complete — prepare the current public Article/Form/Gallery/Other inputs in a pure core-owned contract without changing the public switch, component classes, or rendered output.
10. Complete — replace only the public component switch with a validated fixed core dispatcher/registry that consumes the prepared contract while retaining the same legacy component classes and markup.
11. Complete — prepare the exact public Other layout dimensions and ordered Article rows in core, then move only its unchanged public markup into a manifest-declared legacy component view while retaining every control-panel path.
12. Complete — prepare the exact public Article render data, link, target, and current URL context and move only its unchanged public markup into a manifest-declared legacy component view; retain Form/Gallery and every control-panel path unchanged.
13. Complete — prepare and strictly validate the exact public Form and Gallery records plus their parsed field/media state, then move only their unchanged public markup into manifest-declared legacy component views while retaining every control-panel path.
14. Complete — inventory and isolate the core-owned administrator layout/component editing wrappers so future portable views never own permissions, CSRF/endpoints, editing controls, ordering, or hidden-position behavior.
15. Complete — add the non-active `starter-reference` portable package and a read-only compatibility preflight covering current live layout/component assignments without changing the active runtime or database.
16. Complete — define and verify one isolated standard-theme fixture render/data contract with strict context reconstruction, audited template execution, local inlined assets, deterministic output, and no database/session/live-runtime access.
17. Complete — prepare the first read-only real CMS data slice for the Contact canary through four fixed `SELECT` operations and the existing isolated portable contract, with no database/session/live-runtime writes.
18. Complete — add one Webmaster-only, CSRF-protected, session-bound Contact preview with a 15-minute expiry, sandboxed read-only child render, explicit exit/rollback, cross-session denial, and no live-runtime or database writes.
19. Complete — add the smallest Webmaster Themes/preview chooser that lists only validated installed packages, identifies `legacy-bootstrap` as the live compatibility theme, and launches the proven fixed Contact preview without any selector, persistence, activation, or standard-theme production execution.
20. Complete — add one second fixed read-only real-content preview provider for the Home route and one explicit **Preview Home** action beside Contact, with exact five-read data/media validation, allowlisted session mode, cross-mode denial, and no activation or live-runtime change.
21. Complete — add one seven-read, zero-write activation-readiness inventory across every current route, layout, component subtype, feature, navigation path, media dependency, and fallback; prove all five fixed route canaries and the hard runtime fallback; and publish the explicit covered/gap matrix without adding a provider or active-theme state.
22. Complete — define and dependency-test one isolated portable Gallery Video subtype contract with exact YouTube/Vimeo provider-shaped ids, an accessible non-executable offline placeholder, strict schema/context rejection, deterministic output, zero database/session/runtime access, and unchanged image Gallery, Contact, Home, chooser, and live-runtime contracts.
23. Complete — raised `starter-reference` to `0.6.0` and added one fixed four-read Administration landing provider for Section `25` on `index-3`, mapping display-only Login, bounded Instructions listing text, and the recognized Gallery Video provider/id into the existing portable contract without a chooser action/session mode or live-runtime change.
24. Complete — raised `starter-reference` to `0.7.0` and added one fixed three-read selected Instructions Article provider for `89196971` on `/administracion/instructions`, with exact source/content/media canaries, a core-owned trusted-HTML policy, four confined embedded clean-core screenshots, deterministic offline rendering, and no chooser/session/live-runtime change.
25. Complete — raised `starter-reference` to `0.8.0` and added one fixed three-read display-only selected Login provider for Article `966111194` and Form `884542279` at `/administracion/login`, with exact relationship/template canaries, required empty username/password reconstruction, an inert final button, deterministic offline rendering, and no chooser/session/operational/live-runtime change.
26. Complete — raised `starter-reference` to `0.9.0` and added one fixed three-read display-only selected Contact provider for Article `459269660` and Form `93039112` at `/contacto/contact`, with exact relationship/template canaries, six empty Contact fields, an inert final button, deterministic offline rendering, and no chooser/session/operational/live-runtime change.
27. Complete — inventoried and dependency-tested the separate CMS-owned operational Contact/Login submission/result boundary while preserving every display-only theme input and live endpoint hash; no live or theme integration was added.
28. Complete — adapted only the existing Contact endpoint to the CMS-owned seam with real database/session/message/mail dependencies and exact legacy HTTP/browser parity, while preserving the display-only portable Form and every theme/runtime boundary.
29. Complete — adapted only the existing Login endpoint to the CMS-owned seam with exact legacy throttle/database/password/session and `yes`/`no` parity, including username/password-only callers and legacy-ignored alias/honeypot inputs, while preserving every theme/runtime boundary.
30. Historical closeout — originally defined `empty-layout-shell` and `unmatched-legacy-shell` as HTTP 200 compatibility states. The unmatched half was deliberately superseded by item 37 after administrator rename testing established the required 404 behavior.
31. Complete — added one input-free core-owned generic document/navigation/hero/settings provider for all nine current routes through exactly four fixed read-only queries, with strict data reconstruction, tamper rejection, zero side effects, and no theme or live-runtime connection.
32. Complete — raised `starter-reference` to `0.9.1` and added only the exact Home-mode five-read preview notice through the existing isolated document contract, with deterministic output, fixture/Contact notice exclusion, zero side effects, and no provider/runtime/chooser change.
33. Historical, superseded — the compatibility audit initially preserved `Website_Logo=logo.png` as a non-rendering placeholder; item 38 connected real raster overrides and item 39 removed that placeholder.
34. Complete — raised `starter-reference` to `1.0.0`, declared separate production document/region/layout/component/assets, added migration-backed active/previous state, guarded public standard execution, Webmaster-only CSRF-protected Activate/Roll Back controls, and hard legacy recovery. A real activate/rollback cycle passed all nine routes; final state is `active=legacy-bootstrap`, `previous=starter-reference`, and readiness returns `activationReady=true`.
35. Complete — connected **Advanced → Website CSS** to the effective active package through one server-derived, confined, stale-write-protected CSS target; starter edits `assets/css/theme.css`, legacy edits `css/style.css`.
36. Complete — removed the four-layout CMS ceiling. Standard themes now declare any positive number of meaningful layout ids and exact non-consecutive positions in `theme.json`; public rendering, administrator selectors/wrappers, preflight, activation, and rollback consume that contract dynamically. Five stored layout columns were widened to 64 characters, and explicit compatibility aliases preserve historical ids without rewriting content.
37. Complete — preserved matched blank layouts as `empty-layout-shell`, replaced only truly unmatched routes with `unmatched-theme-404`, standardized the semantic not-found hooks, and kept authenticated unmatched requests in the effective active theme by skipping their nonexistent control-panel layout grid.
38. Complete — connected `Website_Logo` as a core-managed PNG/JPG override for standard themes. Safe custom raster uploads resolve into the production header context, while an empty setting, absent/unsafe files, and templates that have not adopted the contract retain their package-owned fallback without a surprise public-brand change.
39. Complete — removed the generic tracked `images/logo.png` placeholder, cleared the live setting and clean-install seed, and replaced placeholder-specific UI/resolver logic with an explicit empty template-fallback state.
