# RED-CMS 5.1 Bug Report: Position 0 Content Overflows the Hidden Tray

- Status: Repaired and verified in the current RED-CMS 5.1 branch; pending merge
- Target: RED-CMS 5.1
- Priority: P1 — administrator usability before the Version 5.1 release
- Severity: Medium
- Data integrity risk: None observed

Area: Authenticated page-layout editor, Hidden content, position `0`, Article
component and the matching latent Other-component wrapper

## Summary

An Article assigned to position `0` does not stay contained by its structured
editor card or the Hidden content tray. Its title and **Edit Article** action
drop below the card, create excessive vertical spacing, and overlap the
administrator section that follows the tray.

The public theme is not the source of this defect. The reproduced DOM and
computed layout identify a compatibility regression between the core-owned
structured page-layout editor and a retained position-`0` float in the legacy
Article control-panel renderer.

## Reproduction

Confirmed on July 25, 2026 in Chrome at a `1222 × 755` viewport:

1. Sign in as an administrator with Article editing permission.
2. Open `/clases-de-musica/percusion`.
3. Open the page content editor.
4. Expand **Hidden content**, position `0`.
5. Inspect the hidden Article card labeled **Hidden · 1st**.

## Expected Behavior

- The Article title and **Edit Article** action remain inside the Article
  editor row.
- The Article editor row determines the height of its card, the Hidden content
  body, and the outer disclosure.
- Position `0` uses the same compact spacing and alignment as visible
  positions.
- The Hidden content tray does not overlap the next administrator section.

## Actual Behavior

- The Article form begins near the bottom of the Hidden content body and
  continues below it.
- The Article title and action appear visually detached from the card.
- The action overlaps the administrator content below the Hidden content tray.
- The extra height and spacing occur only after the position-`0` Article markup
  introduces its legacy wrapper.

## Measured Browser Evidence

| Element | Top | Bottom | Height |
| --- | ---: | ---: | ---: |
| Hidden content `<details>` | 371.28 px | 465.28 px | 94 px |
| Hidden body | 410.28 px | 464.28 px | 54 px |
| Hidden position controls | 411.28 px | 464.28 px | 53 px |
| Hidden Article form | 457.28 px | 505.28 px | 48 px |
| Hidden **Edit Article** action | 467.28 px | 495.28 px | 28 px |

The Article form extends approximately `41 px` below the Hidden content
controls and `40 px` below the outer disclosure.

For comparison, the visible position-`5` card contains its editor and form
within the same `353.28 px` bottom edge. Its action also remains within that
boundary.

## Confirmed Technical Cause

The structured editor path creates the modern card in
`class/class_content.php`:

```html
<article class="red-admin-layout-item">
  <div class="red-admin-layout-item__editor">
    <!-- component control-panel output -->
  </div>
</article>
```

The Article control-panel renderer in `class/class_article.php` still adds this
position-specific legacy wrapper when `$position === '0'`:

```html
<div style="float:left; padding-right:5px; margin-right:5px;">
```

The resulting structure places the Article form inside a floated `<div>`.
Because that float does not contribute normally to its parent's height, the
editor card, Hidden content body, and outer `<details>` finish before the form
and action finish.

The mismatch also bypasses two intended normalizations in
`admin/assets/css/cp.css`:

- the modern editor rule targets
  `.red-admin-layout-item__editor > form.form`, but the legacy `<div>` is now
  the direct child;
- the older Hidden content reset targets
  `.red-admin-position__controls--hidden > div`, but the structured
  `<article>` is now the direct child.

A later page-layout rule also forces all position controls to `display: block`,
overriding the earlier hidden-control grid declaration. That cascade is a
secondary compatibility defect; the escaped float is the direct cause of the
collapsed height.

The Other control-panel renderer contains the same position-`0` float wrapper.
It was not needed to reproduce the original Article failure, but it requires
the same structured-editor boundary to avoid leaving the equivalent latent
defect in place.

The same four involved files in the current project and the running local QA
installation have identical SHA-256 hashes, confirming that the reproduction
matches the current Version 5.1 source.

## Scope

Original reproduction:

- structured page-layout editor;
- Adriana `service-detail` custom layout;
- Article component in position `0`;
- desktop Chrome at `1222 × 755`.

Post-repair regression verification:

- Article, Other, Form, Video, Gallery, and Banner presentations;
- Adriana `service-detail` custom layout and core `index-3` standard layout;
- desktop `1222 × 755` and mobile `390 × 844` viewports.

## Recommended Repair Boundary

Remove or neutralize the legacy position-`0` float only in the structured
page-layout editor path, while preserving the retained non-structured control
panel behavior until its compatibility requirements are tested.

The repair should:

- keep the structured Article form in normal layout flow;
- make the modern card selectors match the actual component markup;
- update stale Hidden content selectors to target the structured card DOM;
- reconcile the hidden grid rule with the later generic `display: block`
  override;
- remain scoped to the core-owned authenticated workspace so public theme CSS
  and public rendering do not change.

## Implemented Repair

The Version 5.1 repair keeps the retained float behavior available by default
and adds an explicit structured-editor argument only at the modern dispatcher:

- `cp_Article()` and `cp_other()` accept an optional structured-editor flag;
- the structured page-layout dispatcher passes that flag only for Article and
  Other cards;
- position `0` skips the legacy float wrapper only when that flag is active;
- `.red-admin-layout-item__editor` establishes a scoped `flow-root` as a
  defensive containment boundary;
- the later generic position-control rule excludes Hidden content, allowing
  the existing hidden grid and narrow-screen single-column rule to apply.

The legacy non-structured dispatcher and both original float-wrapper strings
remain present. Public component rendering and database storage do not change.

## Acceptance Criteria

1. An open Hidden content tray fully contains every position-`0` card and its
   editor actions.
2. The position-`0` Article title and **Edit Article** action remain inside the
   card at desktop and mobile widths.
3. No Article form or action extends below the Hidden content `<details>`
   boundary, excluding intentionally open popovers.
4. The tray does not overlap Inactive Articles or any following administrator
   panel.
5. Visible positions retain their current spacing, alignment, drag handles,
   menus, and editing actions.
6. Article, Form, Gallery, Video, Banner, and Other components are checked in
   position `0`.
7. Standard and custom layouts are checked at desktop and at the existing
   narrow-screen breakpoint.
8. Mouse, touch, and keyboard Arrange controls continue to work.
9. Active public-theme `details`, `summary`, float, form, and button styles
   cannot change the authenticated workspace geometry.
10. Existing theme-contract and layout-distribution acceptance suites remain
    green.

## Regression Evidence

- Browser geometry checks require every position-`0` editor form and action to
  end at or above the open Hidden content tray's bottom edge.
- Desktop and mobile visual inspection covers the supported hidden component
  presentations.
- The theme source contract prevents structured Article and Other renderers
  from retaining an uncontained position-`0` float while requiring the legacy
  wrapper to remain available outside the structured editor.

## Verification Result

Verified on July 26, 2026 against a temporary installation containing the
current branch, the Adriana Granobles theme, and a separate disposable clone of
the QA database:

- At `1222 × 755`, the custom `service-detail` Hidden content tray ended at
  `503.28 px`; every editor form ended at `493.28 px` and every action ended at
  `483.28 px`.
- The desktop ellipsis summary remained `32 × 30 px`; at `390 × 844`, it used
  the intended `44 × 44 px` touch target.
- Article, Other, Form, Video, Gallery, and Banner presentations all remained
  inside the open tray. Article and Other emitted no structured legacy float
  wrapper.
- Both the Adriana custom `service-detail` layout and the standard `index-3`
  layout passed at desktop and mobile widths.
- Hidden controls computed to `display: grid`, with one column at the existing
  mobile breakpoint.
- The final browser reload reported no console errors, failed requests, or
  HTTP-error resources.
- The 276-assertion theme contract, 22-assertion clean starter boundary, and
  full disposable acceptance suite passed. The full suite included the
  21-assertion layout-distribution and 36-assertion custom-layout lifecycles,
  preserved its read-only baseline snapshot, and removed its acceptance
  database and grant.

The configured schema-behind local starter database was not migrated or
modified during verification.
