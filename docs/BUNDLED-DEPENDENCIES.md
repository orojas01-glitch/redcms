# Bundled dependency review — 5.1.2 candidate

Inventory and advisory query date: 2026-09-09 UTC. No package manager lockfile
previously described the hand-bundled runtime. The CycloneDX inventory at
`release/bundled-dependencies.cdx.json` contains only public package coordinates;
OSV is queried with this file, never the repository or client configuration.

| Library | Verified bundled version / surface | Candidate result |
| --- | --- | --- |
| TinyMCE | 4.0.12, `admin/assets/js/tiny_mce/` | Release blocker: unsupported; 19 OSV advisory matches |
| jQuery | 3.7.1, `js/jquery-3.7.1.min.js` | No OSV matches returned in this query |
| Bootstrap | 5.3.3, `js/bootstrap*` and `css/bootstrap*` | No OSV matches returned in this query |
| PHPMailer | 6.9.3, `bin/phpmailer.php`, `bin/SMTP.php`, `bin/Exception.php` | No OSV matches returned in this query; SMTP delivery remains unverified |
| jQuery filedrop | 0.1.0 banner, three administrator copies | Legacy utility; no verified package coordinate or maintained upstream pin |
| jQuery easing | 1.3 banner | Legacy utility; separate source/compatibility review pending |
| mobilemenu, superfish, tm-scripts | Hand-bundled public utilities | Complete upstream version/provenance unresolved; excluded from OSV coverage claims |

No OSV match is not proof of support, exploit absence or a complete dependency
inventory. Independently distributed add-ons/adapters are not bundled core
libraries and must be checked in their own repositories.

## TinyMCE decision and compatibility boundary

Upstream [ended TinyMCE 4 support](https://www.tiny.cloud/blog/tinymce-4-support-window/).
The supported replacement requires an approved [GPL or commercial licensing
route](https://www.tiny.cloud/docs/tinymce/latest/license-key/). RED-CMS has no
root distribution license establishing GPL compatibility, and no commercial
license was supplied. Do not silently change RED-CMS's license or enable paid
cloud services. No dependency replacement is represented as completed here.

The [4-to-8 migration](https://www.tiny.cloud/docs/tinymce/latest/migration-from-4x-to-8x/)
changes plugin names and controls. Preserve the current editor layout and
capabilities; this is dependency maintenance, not the 5.2 redesign. The concrete
replacement review must cover:

- Article, Other, Video, Gallery create/edit and Advanced editor initialization;
- styleselect to styles mapping; paste/textcolor functionality after plugins move
  into core; code, table, lists, image, link, media, fullscreen and word count;
- current URL preservation, source mode, async editor teardown/reinitialization;
- Other's byte-exact canonical HTML and legacy mismatch reconciliation;
- Article's separate short/long fields, save/reload, revision restore and uploads;
- existing markup including embeds, desktop/mobile rendering and CSP behavior.

OSV advisory IDs for the installed TinyMCE are recorded by the scanner output;
CI intentionally fails its dependency gate until a reviewed replacement removes
the findings. Do not postpone this security dependency decision to a visual
redesign or call the current candidate clean/releasable.
