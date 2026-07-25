# Operational Form Boundary

Status: Contact, Response, and Register resolve definition-driven operational forms through active Form articles; administrator Login retains its fixed compatibility contract. All live endpoints remain CMS-owned; themes render forms but do not own submission effects.

## Contact Resolution

`POST /bin/contact.php` accepts the rendered form's posted `RecordID`, then resolves it with one prepared, fixed-table query. A row is eligible only when all of the following are true:

- `RED_C_Form.FormType` is exactly `Contact`;
- `RED_C_Form.RefID` resolves to `RED_Articles.RecordID`;
- the paired article component is exactly `Form`;
- the paired article is active, has started, and is not expired; and
- the posted alias exactly matches the stored form alias.

This replaces the former fixed `93039112` Contact assumption. Different standard-theme Contact forms can therefore use the same endpoint without adding record-specific code. A missing, inactive, expired, wrong-type, orphaned, or mismatched form redirects through the existing home redirect behavior before the one-time Contact session is consumed.

## Definition And Submission Validation

The stored field definition is compiled on the server before message or mail work. Contact allows `textfield`, `textarea`, `checkbox`, `radio`, `select`, and fixed-value `hidden` fields. `button` and `paragraph` rows remain presentation-only. `password` and unknown field types fail closed.

The compiler and validator enforce:

- at most 50 input fields and 100 definition rows;
- unique, identifier-safe, non-reserved field names;
- bounded definition, payload, individual value, and choice-list sizes;
- explicit required settings;
- required values and valid email fields;
- exact radio, select, and checkbox membership in stored choices;
- arrays only for checkbox values;
- an unchanged stored value for hidden fields;
- no undefined submitted fields; and
- validated, header-safe stored sender and recipient mailboxes.

An enabled first option in a required select retains the legacy browser placeholder behavior. A disabled first option is excluded and does not make the first selectable option invalid.

The nonempty `MySpamTrap` path still returns the compatibility HTML response, consumes the one-time `contact` session, and suppresses both mail transports. It skips required/email checks because the submission is intentionally discarded, while identity, definition, shape, size, hidden-value, and choice-membership checks still apply.

## Mail And Response Behavior

The adapter builds an escaped HTML table only from validated definition fields and submitted values. It no longer performs IP geolocation or places request network metadata in the message.

The primary transport uses the bundled free PHPMailer class from the correctly cased `bin/phpmailer.php` path. Sender, To, CC, and BCC values come only from validated stored configuration. If PHPMailer returns failure, native `mail()` may target only the first validated stored destination; there is no hard-coded fallback recipient.

Compatibility response behavior remains unchanged: valid Contact, honeypot, PHPMailer success, and both mail-failure outcomes return HTTP 200 with the buffered HTML message. This preserves the current browser's AJAX-success flow. HTTP 200 confirms request handling, not email delivery.

Contact writes no database row. It reads the paired Form/Article, reads and consumes the existing `contact` session value, and may call mail transports only when the honeypot is empty. No external geolocation request is made.

## Response And Registration

`POST /bin/response.php` and `POST /bin/register.php` now use the same active, scheduled, paired-Article rules, exact stored-alias match, bounded payload preparation, definition compiler, field validation, password rejection, and stored-mailbox validation as Contact. A valid one-time `contact` session remains required, and a nonempty honeypot consumes that session without invoking mail or storage effects.

Response returns its existing administrator-authored trusted HTML and never creates or writes a database table. Its primary and fallback mail routes can target only validated stored recipients; the former hard-coded fallback address and geolocation lookup are removed.

Register accepts only the system-derived storage identifier `RED_Register_<ArticleRecordID>`. The administrator cannot choose or rename this identifier. Creation fails if that table already exists, creates new storage as InnoDB/utf8mb4, and removes only the newly created table if the paired Article/Form transaction fails. Required values, email formats, hidden values, radio/select/checkbox membership, field names, aliases, record relationships, and payload bounds are validated before a prepared insert. Existing saved Register field schemas remain read-only so a column contract cannot drift underneath stored submissions. Password fields are not permitted in public forms; only the separately protected administrator Login definition may contain one.

## Login Boundary

`POST /bin/login.php` remains separate and fixed to the existing Login contract. Its ordered `username`, `password`, `alias`, and `MySpamTrap` seam, throttle behavior, generic `yes`/`no` response vocabulary, password verification/upgrade, session regeneration, and administrator session keys are unchanged.

Neither public operation silently adds CSRF or prior-authentication requirements. Contact continues to require the one-time render session; Login continues to have no prior-session prerequisite.

## Verification

Run:

```sh
scripts/public-form-operation-self-test.sh
scripts/theme-readiness-self-test.sh
```

The operation suite is dependency-free: it opens no database, starts no session, sends no HTTP request, performs no network lookup, and sends no mail. It covers multiple Contact definitions and record IDs; the shared Response/Register configuration and active-pair source boundary; alias, record, definition, field, choice, array, hidden, email, and size failures; disabled select placeholders; honeypot suppression; primary/fallback ordering; validated fallback destinations; exact managed Register storage derivation; Login compatibility; report redaction; and live source anchors.

The protected-source section intentionally retains hashes for unrelated display/runtime files. During a parallel UI batch, the focused semantic checks can pass before the suite stops on a separately owned source-hash drift; update those unrelated hashes only in the batch that intentionally changed those files.

No verification submission should use an empty honeypot unless sending real email is explicitly intended. A safe local operational probe uses a valid rendered form, its one-time session, and a nonempty honeypot, then verifies HTTP/body/session behavior without invoking mail.
