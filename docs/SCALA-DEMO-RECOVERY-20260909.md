# Scala demo recovery rehearsal

Scope: only `demo.red-sphere.com`. Performed on September 9, 2026 in
America/New_York (September 10 UTC). This is component recovery evidence, not a
client deployment or a complete disaster-recovery certification.

## Verified gap and correction

The earlier rollback database preceded final administrator setup. A fresh snapshot
was needed to demonstrate recovery of the current demo. The existing guarded
`scripts/db-backup.sh` was used from the private staged candidate with the live
configuration consumed server-side, without displaying credentials.

The scoped database contained 36 InnoDB tables. The transactional dump completed
and passed its completion-marker check. The current webroot, including media,
Apache rules, PHP settings and private configuration, was archived separately.
Both artifacts passed SHA-256 verification. The password workbook was excluded
explicitly and was not accessed.

The snapshot directory is private mode 0700, outside the webroot; artifacts were
created with umask 077. This is an access-controlled same-server recovery copy.
Encryption at rest and an independent off-server copy were not established.

## Actual restore checks

- Extracted the web archive into a new private directory outside the webroot.
  Recursive byte comparison against the current webroot passed. Restored private
  configuration retained mode 0600. Extraction and comparison started and finished
  within the same clock second, at 00:02:10 UTC; this is not a whole-site RTO.
- Created a separate empty database within the demo account, with no assigned
  database users. Imported the exact private dump using SPanel's existing account
  import facility. SPanel reported successful completion and 1.55 MB restored.
  No new database credentials or user grants were created.
- Independently inspected the restored structure: 36 tables. Aggregate SQL checks
  found 47 migration records, 2 administrator records, 4 articles, 5 content
  revisions and zero installed add-ons. A cross-database equality query confirmed
  both administrator password hashes matched the source; values were not displayed.
- The live document root, database configuration, account passwords and public
  routing were not replaced. At this initial stage the restored file tree was not
  served or bootstrapped. The subsequent isolated smoke test is recorded below.

## Isolated application smoke test

The owner explicitly approved temporary access for the existing demo database user
to the restored database, followed by removal. SPanel confirmed that exact grant.
Only the restored copy's private configuration was adjusted to select the restored
database; the original snapshot artifacts and live configuration were preserved.
The isolated connection was checked before starting the application.

PHP 8.3 served the restored directory on server loopback only, port 18099, using
dedicated private session storage. PHP mail, cURL execution and socket client
functions were disabled and URL fopen was off during the smoke test. No jobs,
public forms or provider integrations were invoked. This was an HTTP CLI-server
test, not an Apache/HTTPS/browser compatibility test.

- Canonical homepage `/` and `/administracion/` returned 200. The TinyMCE asset
  and restored uploaded PNG returned 200 with nonempty bodies.
- A nonexistent username with a synthetic invalid password returned the expected
  literal `no` response. The protected advanced-update handler returned 403
  without authentication. No real administrator password was retrieved or reset.
- The initial noncanonical `/index.php` request and nonexistent `/admin/index.php`
  request returned 404; these were not treated as canonical application failures.
  The follow-up used the canonical homepage and an actual protected handler.
- Both smoke-test logs had no PHP warnings, uncaught exceptions or fatal errors.
- MariaDB extended checksums matched between source and restored copies for all
  35 unchanged tables. `RED_Login_Attempts` was deliberately excluded because the
  invalid-login test changed it in the restored database. Checksum equality is
  supplementary integrity evidence, not a cryptographic proof of identical rows.
- An article-field update inside a transaction read back correctly, then rolled
  back to its original value. This verifies database write/readback and rollback,
  not authenticated editor save/reload or content-revision behavior.

After testing, the listener was stopped and SPanel showed no assigned users on
the restored database. An independent connection check confirmed restored access
was denied with error 1044 while the original database remained accessible.
The restored copy is retained privately with its isolated database name and no
active application connection. No test listener or temporary grant remains.

The provider also lists a daily cloud backup dated September 9. That listing alone
does not prove its contents, encryption, retention or recoverability; it was not
the artifact used for this rehearsal.

## Recovery runbook and remaining limits

For a repeat rehearsal, capture a fresh private snapshot, verify checksums, extract
outside the webroot and import only into a confirmed empty separate database.
Use the scoped SPanel import or the guarded restore script when a separately
authorized database account has access. Never enable primary/nonempty restore
overrides for a rehearsal. Keep credentials and completed private client records
out of the repository.

For any further recovered-application test, preserve the isolated database and
session configuration and prevent email, jobs and provider effects. Successful
owner-entered login and visual editor save/reload remain unverified. The loopback
smoke and database checks above do not replace those checks.
Whole-site recovery time has not been measured.

The rehearsal recovery point is the snapshot, with no recurring recovery point
or time guarantee established. The owner selected the NAS and entered an image
password privately; acceptable data loss, recovery time and retention remain
unset. Existing provider backup retention was not changed.

## Off-server copy preparation

The owner selected the REDNETWORK NAS, RED-SPHERE share. The mounted SMB share
was verified and a new `RED-CMS-Backups/demo.red-sphere.com/2026-09-09/` folder
was created there. This is a one-time copy; no recurring schedule or retention
policy was configured.

The first transfer attempt failed: SPanel File Manager's download endpoint returned
HTTP 400 for both the web archive and database dump. Neither expected filename
was present in the workstation Downloads directory. The browser tool blocked
access to Chrome's download-history page; no bypass was attempted. At that stage,
no encrypted backup had been created. The completed FileZilla transfer and NAS
verification below supersede that initial failure.

Follow-up: the owner connected FileZilla to the verified demo account. Its TLS
connection downloaded the database dump (122,969 bytes), web archive (8,204,300
bytes) and checksum manifest (160 bytes) into a private temporary workstation
directory. Both artifact SHA-256 checks passed. An accompanying restore README
was prepared. The first encryption attempt also failed: noninteractive image
creation failed before completion, and the Mac was locked when preparing the
owner password-entry UI. The 122,368-byte partial image was retained with an
`INCOMPLETE.dmg` suffix; it is not a usable backup. The downloaded plaintext
artifacts were held inside a mode-0700 temporary directory during encryption.

### Encrypted NAS copy completed

Disk Utility subsequently created `redcms-demo-recovery.dmg` in the selected NAS
folder with AES-256 selected. It reported success; independent image inspection
confirmed encryption. The image is 8,268,800 bytes, SHA-256
`197e5e7a6bff8808ad05711c5cbb53b2c123dae59d98f650312d613811a157a5`.
The owner entered the password privately and reopened the image. Both enclosed
backup artifacts passed their SHA-256 checks from the read-only mounted NAS image;
the restore README was present. This completes creation, private transfer,
encryption and reopening/integrity verification of the independent one-time copy.

The earlier component/application rehearsal used these same verified backup
artifacts. Successful owner login and visual editor save/reload remain separate
limits. Recurring scheduling, retention and whole-site recovery guarantees remain
unset. The failed image remains explicitly marked INCOMPLETE; temporary downloaded
source copies remain in the private workstation staging directory pending cleanup.

Retain the private snapshot and isolated restoration until cleanup is authorized.
No rollback of the live site is needed because the rehearsal did not replace it.
For an actual recovery, preserve newer writes first and obtain approval for the
exact cutover, database restoration and resulting data-loss boundary.

Local documentation baseline remains `b61b7ffc0c5267da218cb0a53218d8f5301929aa`;
the existing security-policy edits were preserved. Documentation whitespace checks
passed. No runtime code changed, so existing core acceptance was not repeated.
