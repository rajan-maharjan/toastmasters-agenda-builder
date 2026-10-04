# Security review — 2026-09-26

## Current public access policy

Anyone may create, edit, delete, view and download agendas. No login or organizer setup is required. Edit/delete controls are visible publicly. Saves and deletes require POST and a valid session-bound CSRF token; deletion also has a browser confirmation. Public edits/deletions by a visitor with a valid token are intentionally allowed, not an authorization bypass. Backups are necessary if accidental or malicious public changes must be recoverable.

This review covers this application folder and its database connection. It is not a penetration-test certification or a review of the parent website's code/server configuration.

## Injection and parent-site checks

| Area | Result / protection |
| --- | --- |
| SQL injection | Submitted values use native PDO prepared statements. Dynamic table/column fragments in save.php come from fixed server-side lists. Malformed IDs, unexpected nested input, excessive lengths and invalid UTF-8 are rejected. |
| Stacked SQL / file imports | Multi-statement SQL execution and MySQL LOCAL INFILE are explicitly disabled on the connection. Tests confirm multi-statements fail and SQL-shaped strings remain literal bound values. |
| Database containment | The application uses its dedicated random-password account. Verified grants are limited to specific tables: agenda tables permit CRUD; required club/member catalogue and settings tables permit SELECT. No FILE, administrative, schema, global or grant-option privileges. MySQL system-table access is denied. The shared catalogue remains intentionally readable. |
| Stored/reflected XSS | HTML output is escaped; dynamic display values use textContent. A temporary agenda containing a script tag and SQL-shaped text was created and rendered safely, then updated and deleted. CSP restricts scripts to same-origin and blocks framing/objects. |
| PHP/command/file injection | No request-controlled include path, shell execution, deserialization or upload-storage path was found in the public application routes. Templates/autoload paths are fixed. Uploaded files are explicitly rejected on mutation endpoints. PDF content uses text cells and a fixed local logo path, not user-supplied HTML or remote-image URLs. |
| Internal files | Apache denies configuration, dependencies, schema, tests, tools, dotfiles and backups. Directory listing is disabled. Executable-script extensions are denied under assets. |
| Session scope | Session cookies are HttpOnly/SameSite, Secure on HTTPS, and scoped to the app URL directory instead of the parent site's root. Strict cookie-only session handling and CSRF validation remain active. Cookie paths are not a browser-origin security boundary. |
| Errors/logging | Visitors receive generic errors. Server logs record errors and security events without passwords, tokens or full form submissions in the security-event records. Input and collection limits restrict request processing. |

## Verification

- `php tests/security_http_test.php`: no sign-in; creates a uniquely marked temporary agenda, tests escaped script/SQL payloads, edits it, downloads its PDF and deletes it. Also tests CSRF, malformed IDs and traversal/SQL-shaped IDs. Only its own test record is modified; cleanup runs on failure.
- `php tests/database_security_test.php`: read-only binding, multi-statement rejection, grant whitelist and system-table access tests.
- `php tests/security_test.php`: CSRF, invalid input and escaping regression checks (also retains tests for dormant legacy authentication helpers).
- `php tests/agenda_test.php`: agenda/speaker timing regressions.
- PHP syntax checks, JavaScript syntax check and local Apache HTTP smoke checks.
- Prior Composer advisory check (2026-09-25): no reported advisories in the lock file, TCPDF 6.11.4; TLS verification remained enabled. This does not assess PHP/Apache/MySQL/OS vulnerabilities.

## Parent-site containment still requires server configuration

A URL subfolder shares the parent's browser origin. A future XSS vulnerability here could make same-origin requests to the parent website. A future PHP execution vulnerability could access anything allowed to the shared PHP service account. Neither .htaccess nor cookie Path guarantees containment.

For a strong boundary, deploy this application on a separate origin with host-only cookies and a separate PHP process/service identity or container. Give that identity no read/write access to parent-site code, credentials or session storage. Use a dedicated session/temp directory, read-only application code, and only the restricted database credentials. Configure HTTPS, request/PDF resource limits, patching, backups and log monitoring at the server. Cross-subdomain cookies must not be broadly shared with the parent.

These server/account changes are outside this writable application folder and have not been applied. Parent-site isolation is therefore **not certified**. The locally verified database privileges reduce SQL impact, but do not substitute for OS/process/browser-origin isolation. The shared Laragon PHP runtime and MySQL administrator account were not upgraded or rotated.

The `.htaccess` rules require Apache with mod_rewrite and appropriate AllowOverride settings. Other servers need equivalent rules; PHP's built-in development server does not apply them.

Keep `config/database.local.php` private; it is excluded from Git and denied over HTTP. Protect it with OS ACLs in deployment. `AGENDA_DB_*` environment variables override local configuration. Legacy organizer password tools are no longer needed for public use.

References: [OWASP SQL Injection Prevention](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html), [OWASP Database Security](https://cheatsheetseries.owasp.org/cheatsheets/Database_Security_Cheat_Sheet.html), [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).
