# GitHub hardening CI — execution and reproducibility

The owner authorized pushing the candidate branch and running GitHub Actions on
2026-09-09. This does not authorize merging main, publishing a release, changing
provider credentials or deploying a client. Rollback baseline:
`checkpoint/before-github-ci-repairs` at `5148d108bb160be79c27026fa5048c0be85bf532`.
Revert individual repair commits to preserve later work; do not rewrite history.

## First execution

[Run 34408610546](https://github.com/orojas01-glitch/redcms/actions/runs/34408610546)
ran the published `5148d10` candidate on GitHub's Ubuntu runner. Its results were:

- Current tracked-source secret scan: passed.
- Bundled public dependency coordinates: passed.
- Locked browser-tooling dependency coordinates: passed.
- Historical scan: failed with one redacted finding, consistent with the tracked
  legacy PDT incident. No history suppression or credential action was performed.
- Core: failed because an offline Stripe request-boundary test required an
  undeclared sibling package checkout. Later core steps were skipped, not passed.

The initial outgoing-commit scan found no new secrets before the branch push.
The historical finding predates these outgoing commits. The protected workbook,
private workstation configuration and deployment artifacts were not published.

## Reproducible adapter fixture

Two offline subscription/webhook contract tests use classes from the separately
maintained Stripe adapter. These are test dependencies, not bundled core runtime
code. CI now checks out the public repository
`orojas01-glitch/redcms-store-lite-stripe-checkout` at immutable commit
`35a7b4bcb1dba1cf94e3d51ea50658b57ef09874`, the revision already recorded in the
client-kit manifest. Both tests accept `RED_STRIPE_ADAPTER_ROOT`; the older sibling
checkout default remains available locally. An invalid override fails explicitly.

The checkout lives in ignored `.ci-fixtures/stripe`, outside `addons`. It does not
install or enable an adapter, introduce credentials, or call a payment provider.
The source-scan job runs in a separate checkout without this fixture. Tests use
synthetic events and transport doubles. A local archive of exactly that pinned
package passed the core and full disposable-database suites.

Checkout actions are pinned to official v7.0.1 commit
`3d3c42e5aac5ba805825da76410c181273ba90b1`, using Node 24. This removes the deprecated
Node 20 action runtime observed in the first run. Read-only workflow permissions
and `persist-credentials: false` remain explicit. Unsafe PR checkout is not enabled.
See the [official pinned checkout documentation](https://github.com/actions/checkout/tree/3d3c42e5aac5ba805825da76410c181273ba90b1).

## ShellCheck review

All 72 tracked shell scripts pass shell syntax checks and ShellCheck at warning
severity after the following narrow corrections:

- 35 intentional empty `CDPATH` assignments now use explicit `CDPATH=''`, preserving
  the existing directory-resolution behavior.
- The authentication test uses `env` to pass the FrankenPHP launcher to its child
  rather than an assignment/expansion combination flagged by ShellCheck.
- Three variables consumed by scripts sourcing `db-common.sh` have line-scoped,
  explained SC2034 annotations. They were not exported merely to silence lint.
- An internal Store Lite wrapper called only through stdin no longer forwards
  unused positional arguments. Its existing call and database scope are unchanged.

No warnings/errors remain at this gate. There are still 76 informational and 21
style suggestions; this is not represented as every ShellCheck suggestion being
resolved. CI now enforces `shellcheck -S warning`, not merely error severity.

## Follow-up runner corrections

The second run passed the core/offline contracts and exposed an Apache fixture
startup failure on Ubuntu. The fixture had assumed every module was a shared
library, matching the local macOS layout, and discarded startup diagnostics.
It now detects compiled-in modules, requires remaining module files explicitly,
sets a private runtime directory, validates configuration before starting, and
reports bounded synthetic-server diagnostics. The local 14-path Apache gate passes.

The adapter-root change shifted a previously accepted synthetic fixture line
from 204 to 205. Its bytes were compared with the prior commit and were identical.
Only the current-source fingerprint's line number was updated; the original
commit-specific fixture exception and the unsuppressed PDT incident remain intact.

The next Linux run passed Apache and both HTTP policy fixtures, then reached the
full database suite. Its 405 response/body checks passed but the Allow-header
assertion failed. Literal `\r` in an extended grep pattern is not a portable
carriage-return expression. The Allow, logout and archive-count assertions now
use POSIX whitespace classes. A new gate extracts the nine actual shell patterns
and checks 36 LF/CRLF and wrong-value cases, preserving negative assertions.
The same correction applies to the separate subscription HTTPS proof script.

## Verification and remaining gate

Actionlint, local core contracts against the pinned fixture, and the full fresh
reference/disposable database suite passed. All 47 migrations, authentication,
content/theme/add-on checks, uploads and forced rollback completed; temporary
schema/grant cleanup passed. No retained primary or client database was migrated.

Inspect the [candidate's current workflow runs](https://github.com/orojas01-glitch/redcms/actions/workflows/core-hardening.yml?query=branch%3Acodex%2F5.1.2-security-hardening)
for the exact tested commit and final job statuses. A successful core job cannot
turn the unresolved historical security finding into a pass. Revocation or
confirmed invalidity needs owner evidence before any narrowly documented incident
closure is considered; deleting current code does not establish that evidence.
