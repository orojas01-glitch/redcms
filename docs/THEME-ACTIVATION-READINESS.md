# RED-CMS Theme Activation Readiness

Status: **Milestone 4 implementation complete; production activation, rollback, active-theme CSS editing, and theme-defined layout extensibility available** as of 2026-07-18.

`starter-reference` 1.2.0 covers every supported layout and component id and declares a separate explicit production contract. Its fifth canonical layout, `feature-grid`, is a concrete package-only example with five named positions. Standard themes may declare any positive number of meaningful layout ids and exact position lists; the active validated manifest drives public rendering and administrator layout/position controls. Migration-backed global state records the active and immediately previous theme ids. The public entry point selects that state, permits guarded `standard` execution only through the audited production adapter, and falls back to `legacy-bootstrap` if state, package validation, adapter initialization, or rendering fails. The authenticated Webmaster Themes page exposes compatibility-gated **Activate** and **Roll Back** controls beside the existing fixed previews, and **Advanced → Website CSS** derives its editable file from the effective active package.

Both selected Form preview providers remain display-only: their read-only queries exclude operational Form columns and prepare no action, endpoint, payload, validation, response, request/session state, default value, or submit behavior. In production, the standard adapter bridges the already audited core-prepared live Form contexts, so Contact and Login keep their existing `/bin/contact.php` and `/bin/login.php` operation seams, payloads, honeypots, and results; the theme does not own submission logic. Activation changes only the two system theme-state rows. No content, layout assignment, typed setting, URL, table name, package-install path, or optional shared-logo override is changed.

## Current Live-State Note — 2026-07-18

The detailed route table below preserves the audited 2026-07-17 closeout baseline; it is not a current primary-data inventory. After the user's comparison and content tests, the verified current theme state is `activeThemeId=starter-reference`, `previousThemeId=legacy-bootstrap`. Section record `29` is restored to alias `test2`, layout `index`, with its Other content still attached. The former `/test/` alias now returns the semantic HTTP 404 inside the effective starter theme, while `/test2/` remains HTTP 200.

The live readiness command currently stops on a pre-existing active About navigation row whose link is empty. That data issue is separate from the rename/404 correction, so this note makes no fresh live `activationReady` claim. The pure readiness contract remains green at 50 assertions, both themes validate, migrations remain `27 applied, 0 pending, 0 drifted`, and full disposable acceptance run `redcms_acceptance_20260718_210039_57297` passes the clean-install, active-theme 404, authentication/authorization, Article/Form/Gallery CRUD and upload, forced-rollback, clean-log, and cleanup boundaries.

Earlier slider-closeout evidence remains historical: the byte-identical 57,752-byte readiness reports at SHA-256 `0dae27598006d25edd4c2d694135e4dc08df94908969d2ad12dfb2a83c1fa5f8` returned `activationReady=true` before the later navigation drift. The 10,672-byte region report at SHA-256 `eea27db315cf7fcfa9b772c9396e301c577733c4e0b3e44894ace4b55e3982d6` recorded the two ordered Home slider candidates and remains the slider proof snapshot, not a current readiness result.

## Run The Report

From the project root:

```sh
scripts/theme-readiness.sh starter-reference
scripts/theme-readiness.sh starter-reference --json
scripts/theme-readiness-self-test.sh
```

The live command requires the documented FrankenPHP CLI because it uses `mysqli`. Its only accepted theme id is the fixed `starter-reference` id. It accepts no route, record id, query, table, path, setting, template, or activation value.

Run the independent generic region-context provider and its safety suite with:

```sh
scripts/theme-region-context.sh
scripts/theme-region-context.sh --json
scripts/theme-region-context-self-test.sh
```

That provider accepts no caller input. It opens its own `START TRANSACTION READ ONLY` transaction and performs exactly four fixed reads: the four Spanish region settings, active Spanish navigation, active Spanish Section/Category/SubCategory hero areas, and bounded current hero Articles. Its output is data-only and remains unconnected to every theme and live runtime path.

## Fixed Read Boundary

The reporter opens one `START TRANSACTION READ ONLY` transaction and issues exactly eight allowlisted `SELECT` operations. Every query id, selected column set, table, row shape, relationship, and row limit is fixed in core.

| Query id | Fixed source | Current rows |
| --- | --- | ---: |
| `active-areas` | Active Spanish Sections, Categories, and SubCategories | 4 |
| `active-articles` | Active Spanish Articles plus current renderability | 7 |
| `active-navigation` | Active Spanish navigation | 3 |
| `form-components` | Active Form Articles and paired `RED_C_Form` children | 2 |
| `gallery-components` | Active Gallery Articles and paired `RED_C_Gallery` children | 1 |
| `layout-catalog` | Current `RED_Layouts` ids, positions, and dimensions | 4 |
| `custom-layout-catalog` | Published, non-archived core custom-layout definitions | 0 |
| `region-settings` | Four Spanish region settings plus the two global active/previous theme rows | 6 |

The live scope is explicit:

| Side effect | Count |
| --- | ---: |
| Database reads | 8 |
| Database writes | 0 |
| Filesystem reads | 42 |
| Filesystem writes | 0 |
| Session reads / writes | 0 / 0 |
| Theme-selection writes | 0 |
| Setting writes | 0 |
| Live-runtime changes | 0 |
| Standard-theme production executions | 0 |

## Audited Closeout Route Inventory

This preserved baseline inventory contains every active Spanish Section and Article alias from the 2026-07-17 closeout plus the established unmatched Vimeo fallback canary. The current 12-route totals and live state are recorded in the note above. “Discoverable” means the route has a menu, Banner, or Article-listing path; active unlinked aliases remain directly addressable.

| Public route | Source and current contract | Layout / ordered content | Exposure and fallback | Portable coverage |
| --- | --- | --- | --- | --- |
| `/` | Section `13`, `home`, Spanish, query limit `100`, feature `slider` | `index-1`; position `1`, order `1`: Gallery Article `1154326271`, Banner child `2030445666` | Menu-exposed and discoverable; no fallback | Exact fixed Home preview |
| `/contacto/` | Section `24`, `contacto`, Spanish | `index-1`; position `1`, order `1`: Form Article `459269660`, Contact child `93039112` | Menu-exposed and discoverable; no fallback | Exact fixed Contact preview |
| `/administracion/` | Section `25`, `administracion`, Spanish | `index-3`; position `1`: Login Form `966111194`; position `2`, orders `1/2`: Instructions Article `89196971`, Video Gallery `880701099` | Discoverable through the Home Banner; no fallback | Exact fixed Administration preview |
| `/administracion/instructions` | selected Article `89196971`, Spanish, trusted rich HTML and four confined clean-core screenshots | `index-2`; Page position `1` | Discoverable through the Article listing; no fallback | Exact fixed Instructions preview |
| `/administracion/login` | selected Login Form Article `966111194`, child `884542279`; exact empty required username/password plus inert button | `index-2`; Page position `1` | Addressable but unlinked; no fallback | Exact fixed display-only Login preview; live Login adapter remains outside the theme |
| `/contacto/contact` | selected Contact Form Article `459269660`, child `93039112`; exact six empty Contact fields plus inert button | `index-1`; Page position `1` | Addressable but unlinked; no fallback | Exact fixed display-only selected Contact preview; live Contact adapter remains outside the theme |
| `/administracion/admin-video` | active Video Gallery Article `880701099` | blank assigned layout | Addressable but unlinked; HTTP 200 empty-layout shell | Exact core-owned zero-byte shell fallback; no theme execution |
| `/banner-test` | active Banner Gallery Article `1154326271` | blank assigned layout | Addressable but unlinked; HTTP 200 empty-layout shell | Exact core-owned zero-byte shell fallback; no theme execution |
| `/administracion/test-vimeo` | no matching active content record; fixed regression canary | no assigned layout | HTTP 404 inside the effective active theme shell | Exact core-owned semantic `unmatched-theme-404`; no layout/component execution |

Coverage totals:

- 9 inventoried routes.
- 6 exact fixed preview data contracts: Home, Contact, Administration, Instructions, Login, and selected Contact.
- 0 normally renderable routes without a fixed provider.
- 2 matched shell-only routes and 1 unmatched active-theme 404 route in this preserved inventory.
- 2 menu-exposed routes and 4 discoverable routes.
- 5 fixed canaries currently valid: Home, Contact, Administration, Instructions, and unmatched Vimeo.

## Layout And Component Coverage

Manifest id coverage is complete:

- Currently assigned layouts: `Full-Width`, `index`, `index-1`, `index-2`, `index-3`.
- Starter canonical layouts: `index`, `index-1`, `index-2`, `index-3`, `feature-grid`.
- Starter accepted layouts: the five canonical ids plus explicit alias `Full-Width → index-2`.
- `feature-grid` positions: `1` Lead feature; `2` Left card; `3` Center card; `4` Right card; `5` Closing row; hidden position `0`.
- Assigned components: `Article`, `Form`, `Gallery`, `Other`.
- Starter components: `Article`, `Form`, `Gallery`, `Other`.
- Missing assigned layout/component ids: none.

The five starter layouts describe this package only; they are not a CMS limit. The manifest/runtime/admin contract has been dependency-tested with valid one-layout and twelve-layout packages, a 64-character layout id, meaningful ids, and non-consecutive position ids through `99`. The five persisted layout-id columns are `varchar(64) utf8mb4_unicode_ci`.

Production activation capabilities are available and independently gated:

| Surface | Current portable capability | Live dependency | Readiness |
| --- | --- | --- | --- |
| Document and regions | Fixed previews plus an input-free core provider for the exact four Spanish settings, navigation, hero inputs, and all nine route views | Production document/header/navigation/hero/footer templates declared separately from preview templates | Production contract valid |
| Article | Escaped listing content plus the audited trusted Instructions body and confined local media | Core-prepared live Article context bridged through the production adapter | Production route verified |
| Form | Display-only preview data plus dependency-tested CMS operation contracts and both live endpoint adapters | Core-prepared live Form context bridged without moving submission logic into the theme | Contact and Login operations preserved |
| Gallery image/video/banner | Confined local images and audited legacy provider/link behavior | Core-prepared live Gallery context bridged through the production adapter | Home and Administration verified |
| Route fallback | Core-owned `empty-layout-shell` and `unmatched-theme-404` contracts remain before layout/component dispatch | The selected theme provides the surrounding shell; core provides the semantic 404 body | Blank-layout 200 and unmatched 404 states independently verified |
| Runtime | Explicit production adapter with guarded standard execution and hard legacy recovery | Persisted active/previous state plus validated package | Ready |
| Layout administration | Active manifest supplies exact ids, labels, and positions; hidden position `0` remains core-owned | Existing aliased values remain editable without becoming new canonical choices | Ready |
| Custom layouts | Published `custom-*` definitions are strict 12-unit JSON and merge into the active standard catalog | Core grid rendering, conflict checks, version history, and legacy recovery remain independent from package PHP | Ready |

## Media And Operational Dependencies

| Dependency | Current evidence | Result |
| --- | --- | --- |
| Home Banner | `/images/gallery/layout-02.png`; client-local Gallery media | Confined path recorded; intentionally absent from the clean starter, so live preview fails closed until the installation supplies its own reviewed media |
| Administration Video | Fixed provider reconstructs only `youtube` plus id `pP8VJwjSnqA` into the inert offline contract | Preview representation and provider complete; playback intentionally absent |
| Instructions body | 7,506 source bytes; SHA-256 `ac05d87a2a7821e13083d66067b1af9e1f4ff131d3133658ba07b2416afd3c36`; sanitized to 497,598 bytes at SHA-256 `913fab88e6440a1c4e84fa612d8be80f52397f6f77de95c176c42a618f716455` | Exact fixed trusted-HTML canary passes |
| Instructions local media | 4 references; all 4 present; 367,642 bytes; manifest SHA-256 `330c2c4ca83b78522e9d3484430d85fc79e3c11363089fdd23d27efcf0157994`; embedded as bounded JPEG data | Complete fixed preview dependency set |
| Login template | 239 bytes; SHA-256 `2609b17e4e14419ac0c2117cfb699db242b193089e409d1aa0f6391da19049b5`; query excludes operational Form columns | Exact display-only selected Login canary passes |
| Contact template | 686 bytes; SHA-256 `5f84ca1244b3c9a66884783469ef6ee2bed4d469f2a75d73a337acc72c43d1a1`; query excludes operational Form columns | Exact display-only selected Contact canary passes |
| Form success icon | `/images/check.png`; 48x48; SHA-256 `3013696893c11da58f4041d2589a5b375a449be81beca3b71de7216f834f7099` | Present |
| Form error icon | `/images/icon-error.png`; 50x50; SHA-256 `b4a22c49685c5a5d1bb267f26b52cf941b2961ccabc7fed7ec1f73f0d3119383` | Present |
| Website logo override | `Website_Logo` is empty and no generic CMS placeholder is installed. The core resolver accepts only confined PNG/JPG files and exposes a bounded header context after a real replacement is uploaded. | Policy `core-managed-raster-override`: runtime connection true; public rendering false while empty; template fallback true until a safe raster is uploaded |

## Blocking Gaps

None. Current primary content has Home Section `13` on `index-1`; all five fixed canaries are valid and `activationReady=true`.

## Post-Milestone Sequence

1. Let the user continue content and slider testing with both bundled packages; use **Edit Slider** to change membership or order independently of an Article's Features Picture.
2. Continue Milestone 5 Admin UX and Cosmetic Work without changing public URLs or the hard `legacy-bootstrap` recovery path. The connected logo contract remains opt-in by upload and template-fallback safe.

## Activation Gate Result

The completed activation gate proves all of the following together:

- every current route has an exact provider or an explicit compatible fallback;
- every component subtype and operational boundary is represented safely;
- trusted HTML and local/external media policies are explicit;
- the core-managed raster logo override accepts only safe PNG/JPG files and preserves each template fallback until an explicit upload;
- standard-theme production execution fails closed and preserves `legacy-bootstrap` recovery;
- arbitrary manifest layout counts, meaningful ids, labels, exact non-consecutive positions, and explicit aliases are validated and consumed dynamically;
- activation and rollback both refuse a candidate whose canonical layouts plus aliases do not cover current assignments and their used numbered positions;
- activation, rollback, and layout/content writers share a database-scoped mutex; transitions are row-locked, atomic, and post-commit verified;
- generic Advanced and logo-upload paths cannot mutate the two reserved global theme-state rows;
- the authenticated boundary is Webmaster-only, POST-only, CSRF-protected, and accepts no caller-selected rollback target;
- a real `legacy-bootstrap → starter-reference → legacy-bootstrap` cycle succeeds across all nine routes;
- after rollback, every legacy route is exact to the pre-edit checkpoint after normalizing only the numeric `?v=` token.

## Audited Baseline And Current Evidence

- Historical primary report before custom-layout support: two byte-identical 57,752-byte schema-version-2 JSON reports at SHA-256 `0dae27598006d25edd4c2d694135e4dc08df94908969d2ad12dfb2a83c1fa5f8`; `starter-reference 1.2.0`; five canonical layouts and 16 production files; `activeThemeId=legacy-bootstrap`, `previousThemeId=starter-reference`; 12 routes; no gaps; `activationReady=true`; seven database reads, 42 filesystem reads, and zero writes.
- Audited closeout baseline report: two byte-identical 49,364-byte schema-version-2 JSON reports at SHA-256 `b2fef49e7c6ed0921bd165fcee5001af8396eb51d13a20d4638bd1109eb75352`; `starter-reference 1.0.0`, seven database reads, 43 filesystem reads, zero writes, an empty `gaps` list, and `activationReady=true`. Persisted final proof state was `activeThemeId=legacy-bootstrap`, `previousThemeId=starter-reference`; both package references and all 14 production files validated.
- Activation self-test: 28 assertions covering exact state shape, missing-state legacy fallback, candidate validation, request allowlists, compatibility-gated transition behavior, unchanged activation, rollback target ownership, transaction failure, and post-commit verification.
- Theme layout extensibility self-test: 58 assertions covering one and twelve canonical layouts, 64-character ids, non-consecutive positions through `99`, exact-case stored requirements, explicit aliases, fixed component registry, dynamic public slots, control-panel wrappers, and bounded route aliases.
- Theme contract lock self-test: 14 live, data-preserving assertions covering database-scoped naming, reentrancy, cross-connection exclusion, exception-safe release, effective-theme agreement, inactive hidden upload placeholders, and rejection of generic Advanced/logo writes against reserved theme-state rows.
- Generic region-context provider: the current 10,672-byte live report at SHA-256 `eea27db315cf7fcfa9b772c9396e301c577733c4e0b3e44894ace4b55e3982d6` uses the exact four-query transaction and returns four settings, three navigation rows, four hero areas, two ordered Home hero Article candidates, and nine fixed route views with zero writes/session/runtime/theme execution.
- Configured-logo policy: fixed setting record `3` is empty (`0` bytes; SHA-256 `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`). No generic `/images/logo.png` placeholder is installed. Readiness records exact policy `core-managed-raster-override`, `publicRendering=false`, `runtimeConnected=true`, and `templateFallback=true`; a newly uploaded safe PNG/JPG becomes the standard-theme header override.
- Fixture self-test: 73 assertions, including deterministic image/Video output, all five starter layouts, exact YouTube/Vimeo provider-shaped ids, URL/embed/mixed-shape rejection, fixed-mode confinement, zero database/session/runtime scope, no external execution, and unchanged image Gallery/Home/Contact contracts.
- Home self-test: 41 assertions, including the exact five-query inventory, fixed Section/Gallery/menu/hero/settings/media canaries, the single exact `Read-only Home data preview — five fixed database reads; no session, activation, or live website change.` notice, deterministic 557,807-byte output at SHA-256 `ab4fe2742660556d5878e2a638ddb6fdcb0b32a5e80cbb8b64cd0c16144151ec`, fixture/Contact notice exclusion, tamper rejection, zero-write/session/runtime scope, and hard runtime fallback. Desktop `1440x1000` and mobile `390x844` inspection confirms the same layout geometry, no overflow/forms/scripts/errors, and only the intended notice text change.
- Administration self-test: 92 assertions, including the four-query inventory, exact Section/composition/child/order/template/provider canaries, bounded plain-text reconstruction, deterministic output, query/schema/relationship/content tamper rejection, source redaction, zero-write/session/runtime scope, unchanged fixture output, and hard runtime fallback.
- Instructions self-test: 82 assertions, including the exact three-query transaction, clean-install/live body canary, 21-file manifest, deterministic render, schema/relationship/content/media tamper rejection, executable/external-resource denial, trusted-mode confinement, report redaction, zero-write/session/runtime scope, and hard runtime fallback.
- Login self-test: 60 assertions, including the exact three-query transaction, fixed Article/Form/Section relationship, 239-byte template canary, operational-column/input exclusion, required empty username/password reconstruction, inert button, deterministic render, schema/relationship/template tamper rejection, report redaction, zero-write/session/runtime scope, unchanged fixture output, and hard runtime fallback.
- Selected Contact self-test: 62 assertions, including the exact three-query transaction, fixed Article/Form/Section relationship, empty-summary and 686-byte template canaries, operational-column/input exclusion, six empty-field reconstruction, inert button, deterministic render, schema/relationship/template tamper rejection, report redaction, prior Contact/fixture parity, zero-write/session/runtime scope, and hard runtime fallback.
- Operational Form self-test: 55 assertions, including exact Contact/Login rendered and submitted payload inventories, client/server validation asymmetry, method/endpoint/record/alias/value tampering, isolated Contact guard/message/honeypot/mail-fallback ordering, generic Login outcome mapping, report redaction, both protected live adapter hashes/source anchors, legacy username/password-only compatibility, and absence of theme integration.
- Public route fallback self-test: 16 assertions, including exact contract shapes, strict empty-string/null classification, matched HTTP 200 with zero emitted bytes, unmatched HTTP 404 with fixed semantic markup, status/caller-route/non-scalar tamper rejection, resolved-layout pass-through, public and administrator call ordering, not-found titles, input confinement, and protected source hashes.
- Generic region-context self-test: 54 assertions, including exact query order/shape/limits, settings/navigation/hero/route reconstruction, unsafe or reordered data rejection, navigation cycles/orphans, hero relationship/media/expiry tampering, transaction rollback, report redaction, source confinement, and zero write/session/runtime/theme scope.
- Readiness self-test: 50 assertions, including the fixed seven-query inventory, write/query/shape drift rejection, exact `6/9` preview coverage, all prior operational/fallback/region/logo facts, persisted state, production contract validation, public selection, authenticated controls, hard legacy recovery, zero gaps, zero-write report scope, and deterministic output.
- Runtime capability probe: requested `starter-reference`; resolved `starter-reference`; fallback unused; standard production execution available; legacy recovery available. The persisted public site is intentionally left on `legacy-bootstrap` after the rollback proof.
- Administration output: two real renders are byte-identical at 13,584 bytes and SHA-256 `d01b4eb2df722d15576d99673ca7a7a6a8a8a9c527e72e9090ec0c413774eff2`; the scope is four database reads and zero writes/session/runtime changes.
- Instructions output: two real renders are byte-identical at 1,713,360 bytes and SHA-256 `470489fcdaa9c0ebc37c1c55a7f3e348131f5dec20cd61c4f7536ea52e5eb017`; the report records three database reads, 14 resolved local fragment links, 130 duplicate legacy targets removed, one intrinsic-dimension correction, and zero writes/session/runtime changes.
- Login output: two real renders are byte-identical at 11,974 bytes and SHA-256 `1c694678e4571847677324cca277d5627dd73dc8469149e0a6a564c16d8d30f7`; the report records three database reads, the exact two field names, no submission connection, and zero writes/session/runtime changes.
- Login browser inspection: desktop `1440x1000` and mobile `390x844` have exact empty required username/password fields, no value/action/method, keyboard order through the inert button, zero forced-click request or URL delta, no horizontal overflow or footer overlap, and no external resources, forbidden elements, console/page/request/HTTP errors. Full-page screenshots are `1440x1375` and `390x1249` at SHA-256 `f9d0c7bbe3e57b9ad349489e35e86f1c7548b11949bfda02e8fb6b59432a69f8` and `06cf3d21dfebac4cc33f34e19e26c23f958d2ae4cd5f800a7baf2362f1b80b4e`.
- Selected Contact output: two real renders are byte-identical at 13,719 bytes and SHA-256 `4521390746f2c9d377f9c8d2e83c84a9362ccaae43697f22bcccc366c530648b`; the report records three database reads, the exact six field names, no submission connection, and zero writes/session/runtime changes.
- Selected Contact browser inspection: desktop `1440x1000` and mobile `390x844` have the exact empty field types, required/autocomplete states, no values/action/method, keyboard order through the inert button, zero forced-click request or URL delta, one current Contact navigation item, no horizontal overflow or footer overlap, and no external resources, forbidden elements, console/page/request/HTTP errors. Full-page screenshots are `1440x1793` and `390x1699` at SHA-256 `e17e555f1a2673349cd27906ea2e0ae5812736ce50f5aaa10fd893161092d1a8` and `420c3a9832be3287e4c583e70e14832a74e41673840e78ae86bebddf9f7ec198`.
- Public Contact/Login page parity remains exact after normalizing only the established numeric `?v=` token. The direct Contact honeypot response is byte-identical before/after at 952 bytes and SHA-256 `8813cc7d86ca1b06f5e8af4bbbf0a35f259fe31fc24e900ca10deeed709eace0`; replay after one-time-session consumption remains an empty HTTP 302 to the current host.
- Live Contact browser interaction passes at `1440x1000` and `390x844` without sending mail. Empty validation focuses `name` and sends zero requests; both real safe honeypot submissions preserve the exact ordered payload, return the same 950-byte body at SHA-256 `e606d5d88ba60c866fb014cdc446d28a3ad4d13a2a372592355afe9ef3ce2857`, render `Enviado!`, retain the URL, and have zero console/page/request/HTTP errors or overflow. Reviewed success screenshots are SHA-256 `271fb73fef8a219ebe3bbccd0c851212315ce2bf58798b83dce4c3480f68ab62` and `f63d6126efda7bcf61e00c131ccadc30f63bc3fc71d721aecd3439e9ffb946b7`.
- Direct tamper probes preserve the fixed boundary: missing session, alternate Form record, alternate alias, and array field values return an empty redirect; a rejected payload does not consume the valid one-time guard, while the next valid honeypot request returns HTTP 200 and then consumes it. Dependency-isolated tests prove PHPMailer-success, PHPMailer-failure/native-fallback, and honeypot ordering with zero real mail sends.
- A fresh user-provided signed-in in-app-browser session completed exact pre-adapter/current parity without exposing or reading a credential, cookie, session store, or Form value. The four critical routes have exact before/current normalized HTTP 200 bodies at `61,725`, `62,012`, `65,833`, and `60,643` bytes; only localhost port and numeric `?v=` were normalized. Fresh Contact/Login screenshots are byte-identical at `1440x1000`, `390x844`, and the original `736x786` viewport. Password-manager autofill was excluded by temporarily holding the Login fields empty/read-only, with empty-state booleans verified after capture and final navigation restoring the untouched current page.
- Historical note: the preserved 2026-07-17 browser evidence below predates the deliberate `unmatched-theme-404` amendment. Its matched blank-layout parity remains relevant; its unmatched HTTP 200 expectation is superseded by the 2026-07-18 contract and browser proof.
- Primary database proof: pre/post transaction-consistent dumps are each 65,256 bytes and differ only in their generated completion line; replacing that complete line with the fixed `TIMESTAMP` marker leaves byte-identical 65,246-byte outputs at SHA-256 `62954211a116d3aaa6a07259951103e1ba8b1bc3afa967c75497714c30242c1c`. Contact/Login parent and child canaries remain `1:1:1:1`, and no disposable acceptance database remains.
- Full disposable acceptance run `redcms_acceptance_20260717_193353_27842` used isolated port `25842` and passed the clean installer, all 25 migrations plus no-op rerun, canonical state including the two system theme rows, generic-failure/throttle/plaintext-upgrade/hashed-relogin/session lifecycle, schema/relationship and primary-database isolation, canonical routes including the unmatched shell, adversarial Guest permissions, Article/Form/Gallery CRUD, Gallery upload/media cleanup, forced paired rollback with exact all-table checksums, clean logs, and automatic removal of its server/database/grant.
- Live route HTTP/normalized SHA-256 evidence:

| Route | HTTP / bytes | Normalized SHA-256 |
| --- | --- | --- |
| `/` | `200 / 6650` | `f018fbc922e28da44c36ad3a102df260f29e26d154fbf52bb52787803f4d76db` |
| `/contacto/` | `200 / 10132` | `8e9c62713780caec491c62ae40f6c1328dbbb5387d00c046854502d3b55875e7` |
| `/administracion/` | `200 / 10442` | `8e88677ffa9c38e80685b1b848d4d127dd2f70da4bc6242b0c5b38d642a9915e` |
| `/administracion/instructions` | `200 / 26973` | `29006816270aa7969673413bf2dd88dd0565ffc8efcf30dd219623df3bb9c974` |
| `/administracion/login` | `200 / 9064` | `57a9fd83ad264b472777f5bba6fd1331a2a0949d443c9b6f00725166d7784b6a` |
| `/contacto/contact` | `200 / 10239` | `d9f604977fa86ffdd8b8d4b2cf9574f7d5ad301838dc4fe07e76a25700b6ed34` |
| `/administracion/admin-video` | `200 / 6053` | `6322146070f286c6f443db7e2c00c744fdd73c53fa4f2ccf9e9396a8dec1b46c` |
| `/banner-test` | `200 / 6053` | `950d119957f23d79d73b73fd0120d08896eed86104d7a66a35039aaa39fd7ce1` |
| `/administracion/test-vimeo` | `200 / 5914` | `8cda2860ca48c36bb2f3c7d567503b4169d0a085ceb2a1b9ae43a5b51b49f8d6` |

Only the existing numeric `?v=` cache timestamp is normalized in route hashes.
