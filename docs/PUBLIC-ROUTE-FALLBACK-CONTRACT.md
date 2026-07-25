# RED-CMS Public Route Fallback Contract

Status: amended on 2026-07-18 after administrator rename testing.

RED-CMS keeps two route states deliberately separate. A real active record may intentionally have no layout. A URL with no matching active record is instead a missing page and must return a real 404 inside the effective active theme.

## Fixed Contract

| Contract id | Route lookup state | HTTP | Effective theme shell | Layout/components | Body added by the boundary | Redirect |
| --- | --- | ---: | --- | --- | --- | --- |
| `empty-layout-shell` | Matching active row with `Layout === ''` | 200 | Preserved | Not rendered | 0 bytes | No |
| `unmatched-theme-404` | No matching active row; layout result is `null` | 404 | Preserved | Not rendered | Fixed 519-byte semantic 404 body | No |

The contract is based on the current database lookup result, not a hard-coded URL list. In the current primary data, `/admin/admin-video` is a matched blank-layout example and the old renamed `/test/` URL is an unmatched example. If content relationships change, those example URLs may change while the two state rules remain fixed.

## Rendering Ownership

[`includes/public_route_fallback_helpers.php`](../includes/public_route_fallback_helpers.php) owns strict `''` versus `null` classification, status selection, and the fixed not-found markup. It accepts no caller route, query, record id, table, theme id, session value, or caller-selected fallback mode, and it performs no database query, persistence, redirect, or theme selection.

[`class/class_page_layout.php`](../class/class_page_layout.php) applies the public contract after the normal layout lookup and before theme layout/component dispatch. The document start, header/navigation, footer, assets, and administrator overlay have already been selected by the effective active theme and continue around the fallback body.

The administrator control-panel pass uses the same strict classification only to skip a nonexistent editor grid. It does not emit status or body content. This prevents an authenticated unmatched request from throwing during control-panel layout dispatch and incorrectly activating the `legacy-bootstrap` recovery renderer.

[`class/class_pagetitle.php`](../class/class_pagetitle.php) emits `Page not found`, optionally prefixed by the configured website title, when the corresponding area or Article lookup has no row. Resolved routes retain their existing titles.

## Not-found Markup And Theme Styling

Core owns the stable, accessible body structure because every theme must be able to display a missing-page response. The hooks are:

- `.red-public-not-found`
- `.red-public-not-found__panel`
- `.red-public-not-found__code`
- `.red-public-not-found__message`
- `.red-public-not-found__action`

The body contains one `main#main-content`, one `h1` labelled **Page not found**, explanatory text, and a same-origin homepage link. Both bundled production themes style these hooks in their own CSS. A future theme may restyle them, but must not remove the HTTP 404 status, replace the response with a normal content layout, query content from the theme, or redirect automatically.

## Rename Relationship

Area renames are not handled by the 404 boundary. A successful Section, Category, or SubCategory rename returns the existing legacy response body plus a server-normalized `X-RED-Canonical-Alias` response header. The authenticated editor acknowledges its existing moved-content alert, then navigates to the new canonical hierarchy. The old URL correctly remains an `unmatched-theme-404` unless the administrator later creates content at that alias.

## Verification

Run the dependency-free contract test:

```sh
scripts/public-route-fallback-self-test.sh
```

Its 16 assertions lock the exact two shapes, unchanged blank-layout HTTP 200/zero-byte behavior, unmatched HTTP 404/fixed markup, strict classification and tamper rejection, resolved-layout pass-through, public call ordering, administrator-overlay skip ordering, not-found document titles, input/side-effect confinement, and source hashes.

Current source locks:

- fallback helper: `b1ec2cd86e8b36de0145daa9cd1c3b9922dbf429f48a081b567e32196f1be67b`
- connected page layout: `7d8caf9cfe37fae38978463a858ca4e75f9c046bea1e8082d2dfbcd40d1928b3`
- page title: `6562d955984ffc5c1d3a5100926dd686c6949257c48fc68a28ebc7b83df97a33`

Browser verification must cover both anonymous HTTP status and an authenticated tab. For the authenticated case, confirm the active theme marker/assets remain present, the administrator overlay remains usable, exactly one 404 body renders, the title is `Page not found`, no recovery-theme stylesheet appears, and desktop/mobile layouts have no horizontal overflow.
