# Adriana Granobles 28-Route Content Migration

Date: 2026-07-19  
Updated: 2026-07-21 — complete 28-route pagewise site, signed-in shell, breadcrumb spacing, and Contact Form visual parity

This runbook covers the disposable content-migration phase for the Adriana Granobles RED-CMS theme. It does not authorize activation or content replacement in the primary `redcms_dev` database or in production.

## Safety boundary

The source site remains read-only:

`/Users/oscarrojas/Documents/adrianagranobles.com - version 4`

The durable, reviewable migration package is staged under:

`content-migrations/adriana-granobles-v4/`

The database activation and migrated public media are disposable:

- The database name must use the `redcms_adriana_28_` prefix and must not equal the primary database name.
- The disposable runtime uses a temporary web root under `/private/tmp/redcms-adriana-28-*`.
- The disposable server uses an alternate loopback port; it must never bind the primary port `8055`.
- The state file is `/private/tmp/redcms-adriana-28-current.state` and identifies the exact database, web root, port, and process eligible for `serve`, `status`, or `destroy`.
- The Adriana theme is activated only in the cloned database.
- The primary database, its active/previous theme state, and the primary PHP/MySQL services are not changed by disposable teardown.
- No production host, production database, remote deployment, or public DNS is part of this workflow.

The runtime utility must fail closed if its database prefix, primary-database inequality, state file, temporary-root marker, port isolation, or clone provenance cannot be proven. Do not work around one of those refusals manually.

## Staged source contract

The source inventory is exact:

| Item | Count |
| --- | ---: |
| Public root routes | 28 |
| Non-home `.html` routes | 27 |
| Imported content sections | 153 |
| Local media files | 42 |
| Content iframes converted to explicit external links | 4 |
| Static homepage forms replaced by a Contact CTA | 1 |
| Contact pages assigned a native RED-CMS Form anchor | 1 |

The layout distribution is:

| Layout | Routes |
| --- | ---: |
| `home-editorial` | 1 |
| `directory-hub` | 5 |
| `service-detail` | 15 |
| `campaign-story` | 6 |
| `contact-conversion` | 1 |

The authoritative route order, metadata, H1 values, section counts, sanitized body HTML, content hashes, footer, media hashes, and transformation decisions live in:

`content-migrations/adriana-granobles-v4/routes.json`

The home URL remains `/`. The approved canonical site now uses clean hierarchy-aware paths. The three parent Sections are `/clases-de-musica/`, `/estudio-de-grabacion/`, and `/voz-y-transformacion/`; their child Articles use nested paths such as `/clases-de-musica/canto`. CUDA, El Cantautor, Eventos, Sobre Adriana, and Contacto remain standalone routes. When `adriana-granobles` is active, the 27 former root `/*.html` URLs plus `/index.html` return HTTP 308 to their exact canonical targets. This redirect table is theme-scoped and leaves unrelated themes and unmatched dotted paths unchanged.

The original disposable prototype gives each flat source page one deterministic owner content record. It is now only a guarded bootstrap state used while constructing a fresh clone. Contact also receives one uniquely named Form child for the existing CMS-owned operational Form boundary.

The approved pagewise model now supersedes the prototype across all 28 routes. The 24 Article routes use one metadata-only `Article` owner at hidden page position `0`; Home and the three parent routes use their native RED-CMS area records. All 153 visible source sections are child `Other` records distributed through the declared layout positions. Their editable HTML lives only in `Other.ShortDesc`, the existing plain-HTML administrator field; every managed `LongDesc` is empty, so visible page HTML is never exposed through the Article WYSIWYG. Contact keeps one native Form at position `2` while its three source sections remain editable Others in positions `1` and `3`.

## Integration and executable-content exclusions

This migration intentionally does not import or execute:

- Google Tag Manager or Universal Analytics snippets;
- Jotform injection or the source placeholder form interceptor;
- source `script`, `style`, or `noscript` nodes;
- inline event handlers, inline style attributes, or `javascript:` URLs;
- embedded Wistia, Calendly, YouTube, or other content iframes;
- source form submission behavior;
- production analytics, consent, scheduling, CRM, email, or tracking configuration.

Content iframes become explicit external links. The homepage form becomes a link to `/contacto`. The Contact page receives the existing native RED-CMS Contact Form component; its endpoint, payload, validation, response, and anti-spam behavior remain CMS-owned. Browser QA inspects this Form without filling or submitting it and blocks any attempted `POST`, `PUT`, `PATCH`, or `DELETE` request.

## Stage and verify the package

From the RED-CMS project root, regenerate the package from the read-only source:

```sh
/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php \
  scripts/adriana-content-stage.php \
  '/Users/oscarrojas/Documents/adrianagranobles.com - version 4'
```

Run the command twice when checking determinism. The manifest SHA-256 and all media hashes must remain identical. Then run the file-only gates:

```sh
scripts/public-source-html-route-self-test.sh
scripts/adriana-content-package-self-test.sh
scripts/adriana-source-render-self-test.sh
scripts/theme-validate.sh adriana-granobles --json
scripts/theme-validate.sh --all
scripts/dev-php-lint.sh
```

These checks do not activate a theme or write content to a database.

## Create the disposable runtime

Keep the local MySQL service and the primary PHP service running if they are already available. The disposable utility clones the current primary database and creates a separate temporary web root and alternate-port server:

```sh
scripts/adriana-disposable-runtime.sh create
```

Creation is expected to perform these bounded operations:

1. Verify the pinned package digest and run the read-only package self-test before creating clone resources.
2. Capture primary database and theme-state sentinels.
3. Create a uniquely named `redcms_adriana_28_*` database and restore a transactional primary backup into it.
4. Copy the project into a marked temporary web root and publish the 42 staged media files there.
5. Run Adriana theme preflight against the clone.
6. Activate `adriana-granobles` in the clone through the normal theme activation contract.
7. Import the 28 prototype owner pages plus the Contact Form child in a theme-locked transaction.
8. Re-run the prototype importer and require a verified `unchanged` result with identical package/database evidence.
9. Validate and apply the complete pagewise site package: 24 hidden metadata Articles, three canonical Section parents plus Home, 153 visible Other records, one native Contact Form, and the 28-row canonical navigation.
10. Re-run the complete pagewise importer and require an exact `unchanged` result.
11. Run a final live compatibility preflight against the imported clone.
12. Start the cloned runtime on an alternate loopback port, write the exact state file, and re-check primary isolation.

The migration import is destructive only inside the newly created disposable clone. It preserves administrative route records required by the cloned CMS. It must refuse the primary database even when invoked directly.

If the command session that ran `create` closes and its background PHP process exits while the validated state, clone, and web root remain, reattach that same runtime with the foreground server operation:

```sh
scripts/adriana-disposable-runtime.sh serve
```

`serve` refuses an active or mismatched recorded PID and an occupied port. It revalidates the disposable database, media, web root, and protected primary snapshot, atomically records its foreground PID without changing the state format, removes temporary database credential files, and then executes the cloned PHP launcher. Keep that command session open while inspecting the disposable site. Do not run `serve` when `status` already reports a healthy server.

## Inspect status

```sh
scripts/adriana-disposable-runtime.sh status
```

Use the URL, database name, run-root/web-root paths, and evidence paths printed by `status`. Those paths include the package self-test, initial preflight, activation, migration, no-op rerun, final preflight, media ledger, readiness response, PHP log, and state file. Confirm all of the following before browser QA:

- the disposable database exists and has the required prefix;
- the disposable server is healthy on a port other than `8055`;
- the temporary web root is the one recorded in state;
- `adriana-granobles` is active only in the clone;
- the primary active/previous theme state and database snapshot still match the pre-create sentinels;
- the guarded 28-route prototype, Contact Form child, menu hierarchy, footer, and 42 media hashes pass the base importer verification report before pagewise conversion;
- the final inventory is exactly 24 hidden metadata Articles, 153 visible Other records, one native Contact Form, Home plus three canonical Section parents, and 28 navigation rows;
- every visible Other stores one complete source section in `ShortDesc`, has an empty `LongDesc`, and exposes the plain HTML administrator editor;
- both immediate importer reruns report `unchanged`, and the final post-import preflight remains compatible.

## Complete pagewise site map

The durable site-wide mapping is:

`content-migrations/adriana-granobles-v4/pages/site.json`

It pins the source manifest SHA-256, all 28 canonical paths, route kinds, native parent Sections, Article aliases, layouts, every source-section position, and the complete navigation tree. The locked CUDA pilot map remains as a narrower canary, but the site map is authoritative for the complete conversion.

| Canonical area | Child routes | Content model |
| --- | ---: | --- |
| `/` | — | native Home area + editable Others |
| `/clases-de-musica/` | 14 | native Section + nested metadata Articles + editable Others |
| `/estudio-de-grabacion/` | 2 | native Section + nested metadata Articles + editable Others |
| `/voz-y-transformacion/` | 3 | native Section + nested metadata Articles + editable Others |
| CUDA, El Cantautor, Eventos, Sobre Adriana, Contacto | — | standalone metadata Articles + editable Others; Contact also owns one native Form |

Validate the file-only contract with:

```sh
/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp \
  php-cli scripts/adriana-page-content-migrate.php --package-only
```

Applying the script requires the guarded disposable database environment used by the lifecycle. It refuses the primary database and refuses unknown, edited, partially related, or colliding managed records. The first successful run is one theme-locked transaction spanning the affected Advanced, Article, Form, Menu, and Section tables; an exact rerun returns `unchanged`. Do not run the original bulk importer after a pagewise conversion because the bulk importer intentionally restores the bootstrap corpus. Use the lifecycle to build a fresh clone instead.

## Audit all 28 routes in a browser

Use the bundled Codex Node runtime so no project dependency is installed. Replace the sample alternate-port URL with the URL returned by `status`:

```sh
/Users/oscarrojas/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  scripts/adriana-route-browser-qa.mjs \
  --base-url http://127.0.0.1:8060 \
  --output-dir docs/adriana-content-qa/20260720-pagewise-site
```

The utility reads `/private/tmp/redcms-adriana-28-current.state` by default; use `--state-file` only for a lifecycle-approved alternate state path. It requires the exact recorded `http://127.0.0.1:<port>/` origin, refuses the primary port, and uses both the manifest and pagewise map inside the recorded disposable web root unless exact overrides are supplied. The report records both validated package bindings.

The QA utility visits the exact 28 canonical routes sequentially at both `1440x1000` and `390x844`. It also requests every legacy source URL without following redirects. It requires:

- 56 completed route checks, HTTP 200, exact final URLs, and no redirects;
- 28 exact HTTP 308 legacy redirects to the mapped canonical paths;
- the Adriana theme body class and route-specific layout class;
- the staged title contract after the existing RED-CMS article-title hyphen/case normalization, exactly one matching H1, and the exact source-page marker;
- no rendered PHP errors, console errors, uncaught page errors, failed same-origin requests, broken images, or horizontal overflow;
- one native visible POST Form on `/contacto`, inspected without submission;
- no Form in the main content of the other 27 routes;
- no attempted mutating request;
- a working desktop dropdown and mobile menu-toggle interaction;
- one full-page screenshot per route and viewport.

The command exits nonzero when an acceptance check fails. Evidence is written to:

```text
<output-dir>/report.json
<output-dir>/external-failures.json
<output-dir>/screenshots/desktop/*.png
<output-dir>/screenshots/mobile/*.png
```

External request failures are recorded separately instead of being hidden. A failed external image can still fail the route as a broken image, and a related browser console error still fails the console gate. The utility never submits the Contact form and prevents any accidental mutating request before it reaches a server.

## Verified disposable run — 2026-07-19

The final local proof is running at `http://127.0.0.1:8060/` from database `redcms_adriana_28_20260719_231453_95315` and run root `/private/tmp/redcms-adriana-28-runtime.TsqeEC`. The pinned manifest is `018fb1a336a7635c85fe883ec94feaad5ff447153d819d4497bd30b9c498937c`.

- Package validation passes 8,377 assertions for 28 routes, 153 source sections, 42 media files, and four converted frames.
- The initial import reports `changed`; its immediate exact rerun reports `unchanged`; final live theme compatibility is true with no missing layouts, positions, or components.
- A disposable-only tamper proof changed one owner to `Active=N` and altered the cloned Contact form. The importer restored `Active=Y`, removed the injected form marker, retained Contact metadata SHA-256 `896debc9bd730d23f8d82423c21955878e59d13089f203cf06be94472ef50a37`, reported `changed`, and then returned `unchanged` on the next run.
- The state-bound browser report at `docs/adriana-content-qa/20260719-disposable-final-rerun/report.json` passes all 56 route/viewport checks with zero failures, zero external failures, zero mutating requests, 56 screenshots, both navigation interactions passing, and the native Contact Form passing without submission.
- Primary state remains `active=starter-reference`, `previous=legacy-bootstrap`; its database snapshot is unchanged, `/` remains HTTP 200, and `/contacto.html` remains HTTP 404. The two unrelated starter-reference CSS sentinels caused by the user's administrator edit were not reverted or refreshed.

## CUDA pagewise verification — 2026-07-20

- Rollback checkpoint `/private/tmp/redcms-cuda-pagewise.AvQGCO` contains the pre-change project/runtime theme files and a complete 301 KB disposable database dump at SHA-256 `e81e426eb1985ce5b8d90bb1ba9aef0e99a770e83eb7038839c72f49dc575d4d`.
- The guarded transaction converted only CUDA owner `3400000022` to a metadata-only Article at position `0` and inserted Other records `3500002201–3500002211`. Its exact rerun returned `unchanged`; live theme compatibility is true.
- Authenticated Chrome inspection found 11 `Edit Other` controls distributed `2/2/2/3/2` across the five labeled positions and one `Edit Article` control in hidden position `0`. Opening one Other in each visible position showed only `textarea#ShortDesc`, one source-section marker, no WYSIWYG frame, and no `LongDesc` control. No form was submitted.
- Anonymous source order is exactly `1–11`; navigation text resolves white, no horizontal overflow is present, and browser diagnostics are empty.
- The state-bound full audit at `/private/tmp/redcms-cuda-pagewise.AvQGCO/browser-qa/report.json` passes all `56/56` desktop/mobile route checks with zero failures, zero external failures, zero mutating requests, and 56 screenshots. CUDA passes at both `1440x1000` and `390x844` with no PHP/console/image/overflow failure.
- The disposable runtime remains at `http://127.0.0.1:8060/`. Its content/media/process checks pass. The protected-primary snapshot sentinel remains red because the primary changed after this clone's creation; it was not cleared or rewritten. The two user-edited starter-reference CSS sentinels were not reverted or refreshed.

## Complete pagewise site verification — 2026-07-20

- The pre-change source checkpoint is `/private/tmp/redcms-adriana-pagewise-site.4xchdE/files-before.tar.gz`, SHA-256 `1897a233252c29b5bb45a136d7f811f44aa3d60e8936278ee8aa8245edd7c730`. The accepted pre-rebuild disposable database dump is `/private/tmp/redcms-adriana-pagewise-site.4xchdE/disposable-before.sql`, SHA-256 `bb9f91eb5bca774c2af0fdde3c811e10820cc18f7ee056a082e01f961e0d6958`. They are recovery evidence only; restoration requires a separately reviewed decision.
- The authoritative `pages/site.json` mapping SHA-256 is `44aaa3efdc487a3b43ac9927f8a642207f97babc8e772c97ab4cd3e8dc4c7754`. Package-only validation proves 28 routes, 24 Article routes, four native area routes, 153 source sections, 28 menu rows, and 28 legacy redirects.
- The current fresh guarded clone is `redcms_adriana_28_20260720_233928_51031` under `/private/tmp/redcms-adriana-28-runtime.C3P87j`, served by foreground PID `52708` on `http://127.0.0.1:8060/`. The complete pagewise import reports `applied`; its immediate exact rerun reports `unchanged`; final live theme compatibility is true.
- Final managed content is exactly 178 records: 153 editable Others, 24 hidden metadata Articles, and one native Contact Form. All 153 Others contain their visible HTML in `ShortDesc` and have empty `LongDesc`. Home uses positions `1,1,2,3,4,5`; CUDA uses `1,1,2,2,3,3,4,4,4,5,5`; El Cantautor uses `1,1,2,2,3,3,4,4,5`; Eventos uses `1,1,2,3,4,4,5`; Sobre Adriana uses `1,1,2,3,3,4,5`; Contact source sections use `1,1,3` with the native Form at position `2`.
- The canonical parent rows are `clases-de-musica`, `estudio-de-grabacion`, and `voz-y-transformacion`. Navigation contains nine top-level rows plus 19 children, contains no `.html` links, and correctly marks parent and child routes active. Footer links use the same canonical targets.
- The state-bound browser report at `docs/adriana-content-qa/20260720-pagewise-site/report.json` passes all `56/56` canonical route/viewport checks and all `28/28` legacy redirect checks. It records zero route failures, external failures, or mutating requests; all 56 screenshots succeeded; desktop and mobile navigation interactions pass; and Contact's native Form passes without submission. Visual inspection of desktop Home, desktop Clases de Música, and mobile Contact found no blocking layout issue.
- Runtime status passes the complete pagewise content model, exact clone grant, recorded port/process ownership, Home marker, all 42 media hashes, and protected-primary isolation. The primary database and active theme were not changed or promoted. The two unrelated starter-reference CSS sentinels remain untouched.

## Authenticated shell import polish — 2026-07-20

- Before editing, `/private/tmp/redcms-adriana-admin-polish.JOns2Z/files-before.tgz` captured every affected shared/template/test/document file at SHA-256 `54043e4ea5a1f7d2ff76b75b45b974e3e1f55fbb10bcadc4da4e59477219dfdd`. The exact pre-rebuild disposable database is backed up as `/private/tmp/redcms-adriana-admin-polish.JOns2Z/disposable-before.sql` at SHA-256 `7027520d56ea77066b9f9e63ad56814149ffefb49d5ea6a8cdd1dbc6e5ea1f60`.
- The signed-in loss of side gutters came from the administrator stylesheet's global `.wrapper { width:100% }` rule loading after the theme. It is now scoped to `#advanced .wrapper` and `.cp .wrapper`, preserving the administrator panel/edit-form rows without overriding Adriana's shared content wrapper.
- Standard production themes now output the captured `#advanced` overlay after `<body>` and before the theme shell, never inside `<head>`. They expose `red-standard-theme--with-admin` only for authenticated output. Adriana uses that state to position its header/navigation at the top of `.adriana-site`, which begins after the administrator panel; the anonymous fixed header is unchanged.
- No hero crop was guessed or changed. The Home route still points to `/images/articles/adriana-granobles-v4/adriana-portrait-1.jpg` with original `1920x1080` dimensions, and `.hero__media img` retains `object-position: 62% center`. The regression suite locks both facts.
- PHP lint passes for both standard document templates and the contract suite. Theme contracts pass `203` assertions; Adriana source rendering passes `37`; the package remains valid; `git diff --check` is clean. The disposable webroot has exact SHA-256 equality with the working source for the changed Adriana document template, control-panel CSS, and production CSS.
- Final runtime status passes all 28 routes, all 153 editable source sections, the exact clone grant, foreground process/port ownership, Home HTTP 200 source marker, all 42 media hashes, and protected-primary isolation. Primary PHP remains HTTP 200 on `8055`, and MySQL remains alive on `3307`.
- The remaining closeout gate is visual and read-only: in the user's existing signed-in browser session, hard-refresh `http://127.0.0.1:8060/` and confirm normal side gutters, the expected Home portrait/eyes framing, and the template header below `div#advanced`. No operational Form should be submitted. The earlier anonymous `56/56` canonical and `28/28` redirect report remains the public-output evidence because these selectors and body state are authenticated-only.

## Breadcrumb capability and imported hero spacing closeout — 2026-07-21

- RED-CMS still owns both automatic breadcrumb paths. Legacy themes continue to call `class_build_breadcrumb.php`; standard themes continue to derive Home, Section, Category, SubCategory, and Article items from the active URL inside `theme_standard_adapter.php`.
- Standard packages may now declare reserved setting `navigation.breadcrumbs` as a checkbox with a boolean default. Omission remains enabled for backwards compatibility. A false value suppresses the complete public and administrator layout breadcrumb context before breadcrumb database lookups; the legacy adapter is unchanged. This is a package-level template switch. Persisted administrator editing of typed settings remains a separate later boundary.
- `adriana-granobles` declares the switch false. Its production navigation then emits the original empty `<nav class="spacer-like-breadcrumb" aria-label="Miga de pan"></nav>` and existing source CSS gives it exactly `92px` height. Visible breadcrumb bars stay absent, while the fixed navigation no longer covers the first hero image or content.
- Live Chrome comparison at `1920x798` now matches the original Home exactly at hero top `92px`, ornament top `184px`, and portrait `object-position: 62% 50%`; Adriana's eyes are visible. Piano retains the same image crop and intentionally uses the 92px blank spacer rather than the original visible breadcrumb's additional 40.8px bar.
- A sequential live pass over all 28 canonical routes found exactly one 92px spacer and hero top `92px` on every route, zero visible `.breadcrumb` elements, all 28 hero images loaded, and zero horizontal-overflow routes. Home and Piano also pass at `785x760` with the 92px spacer and no visible breadcrumb.
- Focused proof passes PHP syntax checks, `209` theme-contract assertions, `50` Adriana source-render assertions, both theme validations, Home/Piano HTTP 200, and the lifecycle's complete 28-route/content/grant/process/media/primary-isolation status. Source and disposable-runtime hashes match for all changed runtime files.
- Rollback checkpoint `/private/tmp/redcms-adriana-breadcrumb.Mz8fnh` contains pre-change source/runtime archives and the exact disposable database dump. The database dump SHA-256 is `66811c49029f2cc04029cf46d289b188969b4b4e9f9767ae22cb019df645fbcd`; restoration requires a separately reviewed decision.
- The existing authenticated shell contract is unchanged: `#advanced` remains before `.adriana-site`, and authenticated header/navigation positioning remains scoped inside that site shell. No login or operational Form was submitted during this read-only visual closeout.

## Contact Form source parity closeout — 2026-07-21

- Contact now uses the editable RED-CMS Form definition for exactly four inputs: required `Motivo`, required `Nombre`, required email-typed `Email`, and optional `Mensaje`, followed by the native submit control and a display-only local-fallback note. `Motivo` starts with the required `Por favor seleccione` placeholder, then a disabled separator and the ten original service choices: Clases de música, Canto, Programa CUDA, El Cantautor, Canto terapéutico, Coaching ontológico, La voz que sana, Eventos, Composición, and Producción musical. The apparel values in the user's syntax sample were treated only as syntax examples and were not imported.
- The package definition SHA-256 is `27a89e4a87c88970ae185c97722bfbf275229ac0a4693bfb726146c3c362347c`; the complete pagewise mapping SHA-256 is `f063dc25780c17f6dc613f450fd4044e8135eda67cfd579ae9062da9e355c440`. The guarded importer may upgrade only the exact known predecessor definition, refuses an independently edited Contact definition, and returned `unchanged` on its immediate rerun.
- The active standard-theme compatibility Form view now supports allowlisted `inputtype`, `autocomplete`, `placeholder`, and `inputmode` attributes, renders display-only paragraph rows as `.form-note`, and excludes those note rows from legacy data-string construction. The protected `class/class_forms.php` and the CMS-owned Contact/Login operation endpoints retain their pre-change hashes and behavior.
- Form CSS remains deliberately separated. `/css/forms.css` is the CMS-wide operational baseline. `themes/adriana-granobles/assets/css/production-forms.css` owns only the Adriana production presentation and loads after `production.css`; the latter now owns no Form selectors. The theme's `assets/css/theme.css` remains the primary stylesheet editable through **Advanced → Website CSS**.
- Desktop comparison at `1280x720` measures the imported/original cards at `510.078px` wide and `683.172px`/`683.141px` high. At `390x844`, they measure `362px` wide and `643.172px`/`643.141px` high. Both use `312px` mobile controls, the imported page has no horizontal overflow, all four fields and placeholders render, and keyboard focus has the existing visible six-pixel focus halo.
- Browser verification made no Form submission. A fresh mobile load reports no console warning or error; all field inventory, options, input types, placeholders, note class, responsive stacking, and overflow checks pass. Full PHP lint, `215` theme-contract assertions, `55` protected operation-boundary assertions, `56` Adriana source-render assertions, `8,377` package assertions, theme validation, and guarded runtime status all pass.
- Rollback checkpoint `/private/tmp/redcms-adriana-contact-form.la5aBl` contains the pre-change source/runtime archives and disposable database dump. The database dump SHA-256 is `ff8e7c1ef2918b76be4c0dd402a6239f766d353ac2e5a409ea703a7a8d9fc64c`. The current guarded clone is `redcms_adriana_28_20260721_124433_59446` under `/private/tmp/redcms-adriana-28-runtime.Gmfm5v` on `http://127.0.0.1:8060/`; its exact Form operational-metadata fingerprint remains `1c79548df485fe6af2cbad2cff44b368f64df440b71d5244f95fc0c847861618`. Primary `redcms_dev` remains isolated and untouched.

## Teardown

Leave the disposable runtime available until visual and database evidence has been reviewed. When it is no longer needed, remove only the exact state-recorded clone:

```sh
scripts/adriana-disposable-runtime.sh destroy
```

`destroy` is expected to stop only the recorded disposable process, revoke/drop only the validated disposable database and grant, remove only the marked temporary web root, and remove its state file. It must not stop the primary MySQL service or the primary PHP service on `8055`.

Run `status` after teardown. A nonzero exit with `Disposable runtime state file is missing or unsafe` is the expected no-runtime result because teardown removes the state file; it must not report or affect the primary PHP/MySQL services.

## Production decision boundary

Passing this disposable migration proves route/content/theme compatibility only. It is not approval to activate Adriana or replace content in production or in `redcms_dev`. Production activation, analytics/consent work, external integrations, operational Form submissions, deployment, and rollback planning require separate explicit review and authorization.
