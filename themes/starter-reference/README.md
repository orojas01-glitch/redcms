# RED Portable Starter Reference

This is a production-capable, file-backed `standard` theme package. Version 1.2.0 demonstrates the v1 manifest, safe local paths, theme-defined layout/component coverage, typed setting declarations, a bounded preview contract, explicit layout aliases, and a separate production document/region/layout/component/asset contract. Contact and Home previews can be launched through the authenticated Webmaster Themes page; the other fixed preview providers remain CLI-only. The same page permits a Webmaster to activate a compatible validated package and roll back to the immediately previous compatible package.

The default compatibility resolver still refuses standard execution; only the persisted public-theme caller explicitly enables the guarded production adapter. The package cannot select itself, authorize an administrator, open a database connection, write settings/content, or own Contact/Login side effects. Activation is core-owned and writes only the two fixed active/previous state rows after validation. Production views receive only audited core-prepared live contexts; operational endpoints, sessions, CSRF, mail, authentication, and database work remain in core. Missing/invalid state or any standard initialization/render failure hard-recovers through `legacy-bootstrap`.

## What this package covers

- All files referenced by `theme.json` live inside this directory.
- This package declares five canonical layouts—`index`, `index-1`, `index-2`, `index-3`, and `feature-grid`—because those are its own design variations, not because RED-CMS has a fixed layout limit.
- `feature-grid` is the explicit extensibility example: position `1` is a full-width lead feature, positions `2–4` form a responsive three-card row, position `5` is a full-width closing row, and position `0` remains hidden.
- Its explicit `Full-Width → index-2` compatibility alias preserves the live historical assignment without changing that Article row.
- Other standard packages may declare one, twelve, or more meaningful layout ids and exact positions in their manifests.
- Numbered public positions match the existing renderer; hidden position `0` remains declared for the core-owned control-panel boundary.
- Article, Form, Gallery, and Other component views receive component-specific scalar/array data.
- Article accepts its original escaped-summary shape plus one mode-confined core-sanitized `bodyHtml` shape for the fixed selected Instructions canary.
- Preview Form accepts only bounded display data. Production Form rendering receives the existing core-prepared legacy context so Contact/Login keep their established endpoints and payload behavior without moving operations into the theme.
- Gallery keeps its existing local-image list and adds one offline Video object containing only an allowlisted provider, provider-shaped id, and escaped caption; the shape is confined to the fixture and fixed Administration preview modes.
- Preview and production assets/templates are declared separately; all local package paths pass safe-path validation.
- The production hero renders the complete prepared slide array. It emits no empty wrapper, omits controls for one slide, and uses the local manual `assets/js/hero-slider.js` controller for two or more slides with buttons, indicators, status, and Left/Right Arrow support.
- Typed settings are declarations only; RED-CMS does not persist or edit their values yet.

## Isolated fixture preview

From the RED-CMS project root:

```sh
scripts/theme-preview.sh starter-reference --json
scripts/theme-preview.sh starter-reference --output=/tmp/redcms-starter-preview.html --json
```

The preview entrypoint is CLI-only and executes only the audited `starter-reference` package. Core reads `fixtures/preview.json`, rejects unexpected or executable mappings, validates every value, resolves only manifest-declared files inside the package, inlines local assets, and renders in this fixed order:

1. component data into component HTML;
2. component HTML into declared numbered layout slots;
3. region and layout HTML into the document shell.

Views receive only fixed arrays or core-rendered HTML strings. They contain no database connection, request/session access, callback, class dispatcher, include chain, endpoint mapping, filesystem write, or activation logic. PHP view calls are restricted to escaping, stylesheets cannot load external resources, SVG fixtures cannot contain executable/resource-loading markup, and client-side scripts are refused. Output files are permitted only under the operating-system temporary directory; rendering in memory is the default.

This remains a developer fixture and is not used by the authenticated administrator preview. It establishes a narrow render data contract independently of database-backed selection, setting persistence, or activation behavior.

## Offline Gallery Video contract

The fixture renderer accepts one second exact Gallery shape for dependency tests:

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

The top-level object must contain only `title` and `video`; the Video object must contain only `provider`, `id`, and `caption`. Provider is exactly lowercase `youtube` or `vimeo`. YouTube ids are exactly 11 characters from the provider-safe id alphabet; Vimeo ids are 6 to 12 decimal digits and cannot begin with zero. URLs, embed HTML, mixed image/Video shapes, extra keys, noncanonical providers, malformed ids, and executable mappings fail closed.

Core maps the provider to its fixed display label and passes only the reconstructed scalar values to the Gallery view. The view renders an accessible offline placeholder with provider/id data attributes and escaped text. It emits no iframe, object, embed, script, link, autoplay behavior, cookie-bearing player, or HTTP(S) request. The contract is confined to `fixture-preview` and `read-only-administration-preview`; the fixed Contact/Home modes reject it, and neither authenticated preview action changed.

The reviewed YouTube fixture is deterministic at 15,083 bytes and SHA-256 `d7b30add407ec65f2e7c68a5f83865d5e4b26c4e8b5b2a029e2dd583813e49b0`. The current image Gallery fixture is exactly 19,507 bytes at SHA-256 `106c984a77643cb0a8b4f0154a59e0558b1d082ff90267d5cbd7e785bbd02a7d`. These checkpoints include the current Webmaster-edited Website CSS, so an approved CSS save intentionally requires their review and refresh.

## Read-only real Contact preview

Render the existing Contact canary in memory:

```sh
scripts/theme-preview-contact.sh starter-reference --json
```

Write a browser-inspectable file only under a temporary directory:

```sh
scripts/theme-preview-contact.sh starter-reference --output=/tmp/redcms-contact-preview.html --json
```

This command is developer-only and CLI-only. Its shared core provider is also called by the administrator boundary only after that endpoint validates and closes the owning session. The provider itself starts a read-only transaction and issues exactly four fixed `SELECT` operations:

1. active Spanish Section record `24` (`contacto`);
2. its single paired active Contact Form Article and `RED_C_Form` row;
3. the active Spanish Home/Contact root navigation rows;
4. only `Website_Title` and `Website_Footer`.

Every selected column, row count, canary id, relationship, layout/position, menu route/order, allowed setting, and legacy Form property is reconstructed through a strict allowlist before entering the portable contract. Legacy setting markup becomes bounded plain text; Form defaults, endpoints, executable values, unknown fields, duplicate rows, unsafe URLs, and schema drift fail closed. The report exposes only record ids, query ids, row counts, fallback labels, and a side-effect scope of four database reads with zero writes, session access, activation, or live-runtime changes.

This is rendered preview data, not theme selection. It does not remember a theme choice, change the active website, or enable a submit endpoint.

## Read-only real Home preview

Render the existing Home canary in memory:

```sh
scripts/theme-preview-home.sh starter-reference --json
```

Write a browser-inspectable file only under a temporary directory:

```sh
scripts/theme-preview-home.sh starter-reference --output=/tmp/redcms-home-preview.html --json
```

This developer command uses the same isolated portable renderer as the fixture and Contact modes. Its core provider opens one read-only transaction and performs exactly five fixed `SELECT` operations:

1. active Spanish Home Section `13` (`home`);
2. the active Spanish root navigation rows;
3. the bounded, active, unexpired Home slider rows;
4. fixed Gallery Article `1154326271` and its paired `RED_C_Gallery` row;
5. only `Website_Title` and `Website_Footer`.

Core requires the exact `home:sp:index-1:Y` section, query limit `100`, `slider` feature, Gallery Article/child relationship, Home position/order `1:1`, Banner subtype, the original two-row root navigation, allowed settings, local URL, dates, and a confined fallback raster filename. Its hero query accepts a bounded ordered slider set, but this older display-only preview maps only the first candidate's title/summary into a single-hero fixture. Production receives and renders the complete prepared slide array separately. As of 2026-07-18, production Home has `demo article` and `test article` at slider orders `1` and `2`; the live fixed preview intentionally fails closed before rendering because the current About navigation creates a third root row. The 43-assertion pure preview contract remains valid, and widening that exact historical inventory is separate work. The portable fixture uses the tracked `v51-workspace.jpg` only through an explicit repository-confined test media root. Live preview still resolves `images/gallery/layout-02.png` only from the client-local Gallery root and fails closed because client media is intentionally absent from the clean starter; no filesystem path reaches the report or theme view. Unknown columns, extra rows, relationship drift, unsafe media paths/URLs, executable values, or altered query inventory fail closed.

The report exposes only canary/component/gallery ids, query ids, row counts, fallback sources, media facts, and a side-effect scope of five database reads with zero writes, session access, activation, or live-runtime changes. The rendered document emits exactly `Read-only Home data preview — five fixed database reads; no session, activation, or live website change.` once and never falls through to fixture or Contact notice copy. This is rendered preview data only; it does not select a theme or change the live Home route.

## Read-only real Administration preview

Render the fixed Administration landing composition in memory:

```sh
scripts/theme-preview-administration.sh starter-reference --json
```

Write a browser-inspectable file only under a temporary directory:

```sh
scripts/theme-preview-administration.sh starter-reference --output=/tmp/redcms-administration-preview.html --json
```

This developer-only CLI provider opens one read-only transaction and performs exactly four fixed `SELECT` operations:

1. active Spanish Section `25` (`administracion`);
2. its exact ordered Article/Form/Gallery composition and paired Login/Video children;
3. the active Spanish Home/Contact root navigation rows;
4. only `Website_Title` and `Website_Footer`.

Core requires the exact `administracion:sp:index-3:Y` canary and reconstructs only position `1` Login Form `966111194`/`884542279`, position `2` Instructions Article `89196971`, and Video Gallery `880701099`/`1968830051` in orders `1/2`. Login is display-only with empty username/password fields and no action; Instructions is reduced to bounded plain listing text; and the recognized YouTube source becomes only provider `youtube` plus id `pP8VJwjSnqA` in the inert offline Gallery contract. Raw SQL, Form template, rich HTML, media URL, filesystem path, selected-route state, and executable markup never reach the report or theme views.

The dependency-free Administration fixture-row checkpoint, including the current Website CSS, is deterministic at 13,539 bytes and SHA-256 `538feed727c73e64ce91275bfba7f7ab34c7cd0e59c178debcc17f5711f30ed0`. A real-data artifact additionally depends on the fixed database canary and should be reviewed by rerunning the CLI when those rows are present. Its report exposes only fixed ids, query ids/counts, summary hash, provider/id, fallback labels, and explicit scope: four database reads with zero writes, session reads/writes, or live-runtime changes. It is not connected to the authenticated chooser and cannot select or activate a theme.

## Read-only selected Instructions preview

Render the fixed selected Instructions Article in memory:

```sh
scripts/theme-preview-instructions.sh starter-reference --json
```

Write a browser-inspectable file only under a temporary directory:

```sh
scripts/theme-preview-instructions.sh starter-reference --output=/tmp/redcms-instructions-preview.html --json
```

This developer-only CLI provider opens one read-only transaction and performs exactly three fixed `SELECT` operations:

1. selected active Spanish Instructions Article `89196971` joined to parent Section `25`;
2. the active Spanish Home/Contact root navigation rows;
3. only `Website_Title` and `Website_Footer`.

Core requires the exact `/administracion/instructions`, `index-2`, Page position/order `1/0`, Article/Section relationship, summary/body bytes and hashes, and current empty link/target/settings fallback facts. The raw 7,506-byte clean-core body must match SHA-256 `ac05d87a2a7821e13083d66067b1af9e1f4ff131d3133658ba07b2416afd3c36`. Its fixed HTML policy accepts only the reviewed heading, paragraph, list, link, emphasis, blockquote, line-break, rule, and image elements; shifts headings beneath the preview H1/H2; and permits only local fragment links.

All four ordered clean-core screenshots must resolve beneath the fixed manual directory and match the 367,642-byte manifest SHA-256 `330c2c4ca83b78522e9d3484430d85fc79e3c11363089fdd23d27efcf0157994`. Core validates MIME/dimensions, applies one exact responsive/lazy-decoding policy, and embeds JPEG bytes as data URIs. Executable elements/attributes, forms, external URLs/resources, caller HTML/path/record/query input, missing or altered media, unsafe targets, and schema/content drift fail closed.

The reviewed live render is deterministic at 509,066 bytes and SHA-256 `d731c02698cc8197f36fb8d2f63586fda4fdb093c005905db82cb3e02ebcba3a`. Its report exposes only fixed ids, query ids/counts, source/sanitized byte hashes, media counts/manifest, normalization counts, fallback labels, and explicit scope: three database reads with zero writes, session reads/writes, or live-runtime changes. It adds no chooser action and cannot select or activate the starter.

## Read-only selected Login preview

Render the fixed selected Login Form in memory:

```sh
scripts/theme-preview-login.sh starter-reference --json
```

Write a browser-inspectable file only under a temporary directory:

```sh
scripts/theme-preview-login.sh starter-reference --output=/tmp/redcms-login-preview.html --json
```

This developer-only CLI provider opens one read-only transaction and performs exactly three fixed `SELECT` operations:

1. selected active Spanish Login Article `966111194`, paired `RED_C_Form` child `884542279`, and parent Section `25`;
2. the active Spanish Home/Contact root navigation rows;
3. only `Website_Title` and `Website_Footer`.

Core requires the exact `/administracion/login`, `index-2`, Page position/order `1/0`, Article/Form/Section relationship, 10-byte summary hash, and 239-byte Form-template SHA-256 `2609b17e4e14419ac0c2117cfb699db242b193089e409d1aa0f6391da19049b5`. It reconstructs only required empty `username` and `password` fields plus the final inert `submit` button. The fixed query does not select the Form's subject, recipients, response, table, submitter, or other operational columns; the portable input contains no action, endpoint, payload, validation, response, request/session state, default value, or submit behavior.

The reviewed live render is deterministic at 12,408 bytes and SHA-256 `56601b59af8b7eff070beeac1c05a17f0fb60c7737f338b694fe3defc05758fb`. Its report exposes only fixed ids, query ids/counts, field names/count, template byte/hash canary, fallback labels, and explicit scope: three database reads with zero writes, session reads/writes, submissions, or live-runtime changes. It adds no chooser action and cannot select or activate the starter.

## Read-only selected Contact preview

Render the fixed selected Contact Form in memory:

```sh
scripts/theme-preview-selected-contact.sh starter-reference --json
```

Write a browser-inspectable file only under a temporary directory:

```sh
scripts/theme-preview-selected-contact.sh starter-reference --output=/tmp/redcms-selected-contact-preview.html --json
```

This developer-only CLI provider opens one read-only transaction and performs exactly three fixed `SELECT` operations:

1. selected active Spanish Contact Article `459269660`, paired `RED_C_Form` child `93039112`, and parent Section `24`;
2. the active Spanish Home/Contact root navigation rows;
3. only `Website_Title` and `Website_Footer`.

Core requires the exact `/contacto/contact`, `index-1`, Page position/order `1/0`, Article/Form/Section relationship, empty-summary hash, and 686-byte Form-template SHA-256 `5f84ca1244b3c9a66884783469ef6ee2bed4d469f2a75d73a337acc72c43d1a1`. It reuses the proven strict Contact parser and reconstructs only empty `name`, `title`, `email`, `telephone`, `fax`, and `message` fields plus the final inert `submit` button. The fixed query does not select the Form's subject, recipients, response, table, submitter, or other operational columns; the portable input contains no action, endpoint, payload, validation, response, request/session state, default value, or submit behavior.

The reviewed live render is deterministic at 14,153 bytes and SHA-256 `f022c36609523c4e59c4594634e13bb25b33f185f2bd031ddf71949d341cc0a0`. Its report exposes only fixed ids, query ids/counts, field names/count, template byte/hash canary, fallback labels, and explicit scope: three database reads with zero writes, session reads/writes, submissions, or live-runtime changes. It adds no chooser action and cannot select or activate the starter.

## Webmaster Themes chooser, preview, activation, and rollback

While signed in as a Webmaster, open **Advanced → Themes** in the existing control panel or visit the fixed boundary:

```text
/admin/bin/theme_preview.php
```

The page lists only locally installed packages whose manifests pass the shared validator. It marks the persisted active package and rollback target, retains **Preview Contact** and **Preview Home**, and shows **Activate** for this valid inactive production package. After activation, **Roll Back** targets the locked previous package; rollback never accepts a caller-selected id. Invalid packages are hidden. There is no arbitrary selector, path/route/template input, setting editor, or package install/upload control.

Fixed Contact/Home preview start and exit retain their isolated CSRF-protected session contract. Activate and Roll Back are separate POST-only, CSRF-protected, Webmaster-only actions. Activation revalidates the production package, locks both theme-state rows, stores `previous=current`, and stores the validated target as active in one transaction. Rollback uses the locked previous row as its target and performs the same post-commit verification.

Preview state never selects a public theme. `index.php` reads only persisted active-theme state; preview start/exit and logout do not change it. Invalid state, invalid packages, or production adapter/render failure fall back to `legacy-bootstrap`. At the 2026-07-18 layout-extensibility closeout, the user's current local state is `active=starter-reference`, `previous=legacy-bootstrap`.

## Validate and preflight

```sh
scripts/theme-validate.sh starter-reference
scripts/theme-preflight.sh starter-reference
scripts/theme-preflight.sh starter-reference --json
scripts/theme-layout-extensibility-self-test.sh
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
```

Validation proves the package contract and safe local paths. Preflight reads the current Sections, Categories, SubCategories, Articles, and layout catalog with fixed `SELECT` queries, then reports whether every assigned layout id, every numbered position used under that assignment, and every supported component id is covered by a canonical declaration or explicit compatibility alias. It never installs or activates the package. The extensibility suite proves one-layout and twelve-layout manifests, long meaningful ids, exact-case stored requirements, non-consecutive positions, aliases, dynamic public slots, and dynamic control-panel wrappers. The preview self-test proves deterministic output, strict rejection, isolated template execution, runtime fallback, and cleanup behavior.

## Read-only activation readiness

After validation and preflight, run the current route/dependency inventory:

```sh
scripts/theme-readiness.sh starter-reference
scripts/theme-readiness.sh starter-reference --json
scripts/theme-readiness-self-test.sh
scripts/theme-region-context.sh
scripts/theme-region-context.sh --json
scripts/theme-region-context-self-test.sh
```

The readiness report is intentionally stricter than manifest preflight. It opens one read-only transaction, performs exactly seven fixed `SELECT` operations, validates current Section/Article/Form/Gallery relationships, inventories every current public route, media dependency, navigation path, component subtype, and fallback, and compares those requirements with the fixture plus fixed Contact/Home/Administration/Instructions/Login/selected-Contact providers. It accepts no caller route/query/table/record/path/setting/template value and reports zero database/filesystem/session/selection/setting/runtime writes.

The independent region-context command accepts no caller input. It opens a separate read-only transaction and performs exactly four fixed reads for the four allowlisted Spanish settings, current navigation, current hero areas, and bounded hero Articles. It reconstructs one route-independent data shape plus all nine current route views without opening this package, rendering a template, resolving the configured logo, or connecting to production runtime.

Milestone 4 is complete. Layout/component compatibility is complete, including the explicit live `Full-Width` alias and exact used-position coverage; shell-only/unmatched routes retain an explicit core contract, and the CMS-owned Contact/Login operation seams remain outside the theme. The production contract validates 16 files, including the local hero controller; persisted state, public selection, compatibility-gated authenticated controls, guarded standard initialization, shared activation/write serialization, reserved theme-state rows, and hard legacy recovery all pass. Home is restored to audited layout `index-1`, its two selected feature Articles form a working carousel, and the current read-only report returns `activationReady=true` with no gaps. When `Website_Logo` is empty, the starter template keeps its built-in mark; a safe uploaded PNG/JPG can replace it through the shared header context. See [`docs/THEME-ACTIVATION-READINESS.md`](../../docs/THEME-ACTIVATION-READINESS.md) for the full evidence.

## Start a real theme

1. Copy this directory to a new lowercase, hyphenated directory.
2. Change the manifest `id`, `name`, `version`, description, and preview.
3. Keep every declared template and local asset inside the copied theme directory.
4. Declare the exact layouts this theme needs—one or many—with stable meaningful ids, labels, templates, and position lists. Add an explicit `compatibility.layoutAliases` mapping for each live historical id the new package must preserve; never invent a default mapping silently.
5. Replace the markup and assets, then run validation and preflight again.

The fixture renderer intentionally refuses copied or renamed packages until their fixed preview fixtures/providers are audited. A new production package must also declare and validate every production document/region/layout/component/asset path before **Activate** can appear. Active-theme persistence and rollback are complete; typed-setting persistence and package installation/upload remain later work. The optional shared PNG/JPG logo override is already connected through the prepared header context.
