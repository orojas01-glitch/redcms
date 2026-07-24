# Adriana Granobles — Conservatorio Íntimo

Portable RED-CMS `standard` theme adapted from the current 28-page static site at:

`/Users/oscarrojas/Documents/adrianagranobles.com - version 4`

- Status: inactive on the primary database; activated and verified only in the guarded disposable migration runtime
- Theme id: `adriana-granobles`
- Version: `0.1.2`

## What this package does

- Ports the current plum, gold, ivory, and night visual system into a file-backed RED-CMS theme.
- Uses CMS-prepared document metadata, navigation, feature slides, breadcrumbs, numbered layout slots, and component HTML.
- Preserves the exact RED-CMS `Article`, `Form`, `Gallery`, and `Other` component boundary.
- Preserves CMS-owned Contact and Login submission behavior.
- Supports zero, one, or many feature slides with manual keyboard-operable controls and no auto-rotation.
- Adds five reusable Adriana page-pattern layouts while retaining every currently assigned RED-CMS layout id and position.
- Keeps the existing active theme, public URLs, table names, content assignments, and database state unchanged.

This package defines presentation and does not create routes by itself. The separate reviewed migration package at `content-migrations/adriana-granobles-v4/`, its guarded importer, and `docs/ADRIANA-CONTENT-MIGRATION.md` now provide the disposable 28-route content/media proof without changing the primary database.

## Source authority

The current 28 root HTML files and current sitemap are authoritative. Historical Phase 4 and Phase 7 documents describe an older 16-page state and should not control this conversion.

The imported visual foundation comes from:

- `assets/css/main.css`
- `assets/js/main.js`
- `assets/js/animations.js`
- `assets/js/accessibility.js`
- `assets/svg/favicon.svg`
- `assets/img/source/logo.png`

The original stylesheet is retained as `assets/css/theme.css`. General RED-CMS production compatibility rules are isolated in `assets/css/production.css`; native Form presentation is isolated separately in `assets/css/production-forms.css`.

## 28-page layout map

| RED-CMS layout | Current source pages | Pattern |
| --- | --- | --- |
| `home-editorial` | `index.html` | Hero, primary pathways, brand story, programs, proof, and closing CTA |
| `directory-hub` | `clases-de-musica.html`, `instrumentos.html`, `canto.html`, `estudio-de-grabacion.html`, `testimonios.html` | Introduction, route or service directory, method, FAQ/proof, CTA |
| `service-detail` | `escuela-canto.html`, `escuela-piano.html`, `escuela-guitarra.html`, `escuela-bateria.html`, `escuela-percusion.html`, `escuela-bajo.html`, `escuela-flauta.html`, `escuela-clarinete.html`, `escuela-teoria-musical.html`, `escuela-composicion-produccion.html`, `escuela-violin.html`, `coaching-ontologico.html`, `canto-terapeutico.html`, `composicion.html`, `produccion-musical.html` | Overview, benefits, method/audience, proof/media/FAQ, CTA |
| `campaign-story` | `clases-de-musica-online-para-ninos.html`, `programa-cuda.html`, `el-cantautor.html`, `bodas-y-eventos.html`, `la-voz-que-sana.html`, `sobre-adriana.html` | Flexible long-form story, benefits, media/proof, journey/resources, CTA |
| `contact-conversion` | `contacto.html` | Contact introduction, CMS Form, contact details and alternatives |

Special source-page hero features such as CUDA video, a second CTA, program facts, Calendly, Wistia, or other embeds belong in CMS content slots. The theme hero itself consumes only the complete CMS-prepared slide array.

## Existing RED-CMS compatibility

The package retains these canonical layouts and position inventories so current stored assignments require no rewrite:

| Layout | Positions |
| --- | --- |
| `index` | `1`, `2`, `3` |
| `index-1` | `1`, `2`, `3`, `4` |
| `index-2` | `1`, `2`, `3`, `4` |
| `index-3` | `1`, `2` |
| `feature-grid` | `1`, `2`, `3`, `4`, `5` |

The historical `Full-Width` value maps explicitly to `index-2`. Hidden position `0` remains core-owned in every layout.

## Theme-owned assets

Production loads:

1. `assets/css/theme.css` — first and therefore Webmaster-editable through **Advanced → Website CSS** when this package is active.
2. `assets/css/production.css` — RED-CMS production and legacy-output bridge.
3. `assets/css/production-forms.css` — Adriana-only native Form bridge, loaded after the general production bridge.
4. `assets/js/accessibility.js` — Escape-key mobile-menu support.
5. `assets/js/animations.js` — progressive reveal and decorative music overlay with reduced-motion handling.
6. `assets/js/site.js` — header, mobile navigation, dropdown, tab, and scroll-to-top behavior.
7. `assets/js/hero-slider.js` — manual CMS feature-slide controls.

The production header uses the theme-local logo and favicon. Representative local images are included only for deterministic design fixtures and documentation; production hero and content media come from CMS-prepared paths.

RED-CMS keeps its CMS-wide operational Form baseline in `/css/forms.css`. This theme does not replace that endpoint, validation, honeypot, or submission contract. It layers the imported Adriana card, labels, controls, submit button, and responsive sizing from `assets/css/production-forms.css`. **Advanced → Website CSS** continues to edit the package's primary `assets/css/theme.css`; changes to the dedicated Form bridge are intentionally file-backed theme work.

## Intentionally excluded

- Universal Analytics and Google Tag Manager snippets from the static pages.
- Site-wide Jotform injection from `integrations.js`.
- The static placeholder form submit interceptor.
- Google Translator remnants described by older documentation.
- Hard-coded `.html` navigation and footer links.
- Route-specific canonical, Open Graph, Twitter, JSON-LD, and preload tags.
- External Wistia, Calendly, YouTube-thumbnail, or other iframe requests in isolated fixtures.
- Unused source images, alternate logo experiments, `.DS_Store`, build tools, and source-repository QA screenshots.

Tracking, third-party integrations, consent, and privacy behavior require their own CMS/configuration boundary. Operational Form actions, payloads, validation, and responses remain in RED-CMS.

## Package structure

```text
themes/adriana-granobles/
├── theme.json
├── preview.svg
├── templates/
├── partials/
├── layouts/
├── components/
├── fixtures/
├── docs/qa/
└── assets/
    ├── css/
    ├── js/
    └── images/
```

Preview templates and production templates are separate. Production views receive only prepared RED-CMS contexts and do not query the database, inspect requests or sessions, choose routes, or dispatch components.

## Preview boundary

The authenticated **Themes** page exposes exactly one action for this package: **Preview Home**. It renders the audited `fixtures/preview.json` through the package's preview templates inside the current Webmaster session, with zero database reads, writes, activation changes, or external requests. The starter-reference Contact/Home and other fixed database-backed providers remain separate and unchanged.

The Adriana Home fixture proves the portable theme shell; it is not a substitute for migrated-route verification. Exact 28-route content and media QA must continue to use the disposable cloned database/server before any real activation decision. The legacy `theme-preview.sh` and `theme-readiness.sh` commands remain starter-reference-only.

## Validate

Run from the RED-CMS project root:

```sh
scripts/theme-validate.sh adriana-granobles --json
scripts/theme-preflight.sh adriana-granobles
scripts/theme-preflight.sh adriana-granobles --json
scripts/theme-validate.sh --all
scripts/dev-php-lint.sh
scripts/theme-contract-self-test.php
scripts/theme-layout-extensibility-self-test.sh
scripts/theme-preview-admin-self-test.sh
scripts/theme-activation-self-test.sh
scripts/active-theme-css-self-test.sh
scripts/public-form-operation-self-test.sh
scripts/public-route-fallback-self-test.sh
scripts/db-migrate.sh --status
```

The dated results and the reviewed `starter-reference` CSS/manifest sentinel
refresh are documented in `docs/qa/README.md`.

The primary database must remain inactive during package development. Activation is a separate explicit Webmaster decision after disposable-runtime visual and operational QA.

## Disposable content migration

The reviewed `adriana-granobles-v4` package now stages all 28 exact root routes, maps them to the five semantic layouts above, publishes 42 local media files, converts four external frames to explicit links, and connects `/contacto.html` to the existing native RED-CMS Contact Form. The guarded lifecycle clones the current primary database into a uniquely named disposable database, activates this package only there, imports transactionally, verifies a no-op rerun, and binds browser QA to the recorded state/manifest/port.

This proof does not approve production activation or content replacement. Analytics, consent, Jotform, third-party embeds, deployment, and a production rollback decision remain separate reviewed work.

## Rollback boundary

The source/theme conversion is additive. Content replacement is allowed only inside a validated `redcms_adriana_28_*` clone recorded by the lifecycle state file; the primary database is denied by both activation and import guards. Existing primary URLs, tables, theme state, content, and the user's edited starter-reference CSS remain untouched.
