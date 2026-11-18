# Safe upgrade and setup

## 1. Back up before changing the school installation

1. Stop all question preparation and live competitions during the upgrade.
2. Copy the entire old project folder, including all of `uploads/`, somewhere outside the web root.
3. In phpMyAdmin, select your **actual existing database**, choose **Export → Custom**, select every table, and export SQL with both structure and data. Store it with the folder backup. Check that it contains your current questions and `quiz_sessions` records.
4. First rehearse the upgrade against a separate database restored from that backup. The supplied `database/questions.sql` contains only five old sample questions. **Never import it over your existing database.**

## 2. Place the updated code

Extract the supplied `The_Quiz/` folder into `C:\xampp\htdocs\The_Quiz` or your existing folder name. Avoid double nesting. Keep your old installation until verification finishes. Copy any newer local uploads into the updated `uploads/questions/` without replacing a different existing file. All images from the supplied archive are retained.

`conn.php` defaults to:

| Setting | Supplied value |
|---|---|
| Host | `127.0.0.1` |
| User | `root` |
| Password | empty |
| Database | `the_quiz_v2` |
| Port | `3307` |

Keep your actual database values. Use 3306 only if that is your MySQL port. Environment overrides are `QUIZ_DB_HOST`, `QUIZ_DB_USER`, `QUIZ_DB_PASSWORD`, `QUIZ_DB_NAME`, `QUIZ_DB_PORT`. Do not commit real hosted database passwords.

## 3. Run the repeatable migration

Open Command Prompt in the project root:

```bat
C:\xampp\php\php.exe database\migrate.php --dry-run
C:\xampp\php\php.exe database\migrate.php
```

The dry run prints planned changes and does not write data. The real migration checks the base tables, requires InnoDB, takes a migration lock, checks existing columns, and adds only missing fields/tables. It can be run again after interruption. MySQL DDL implicitly commits: the entire migration is not rolled back as one transaction. That is why a backup and a rehearsal are required. A second run skips added columns.

The PHP migration is authoritative. `database/upgrade.sql` creates supplementary tables but **does not add all required columns by itself**. Do not use only that file for this upgrade.

| Table | Additions |
|---|---|
| `admins` | `role` (default admin), `active`, `auth_version` |
| `categories` | `status` only if missing, `archived`, `version` |
| `questions` | `points` (default 10), `version`, `archived`, `created_by`, `updated_by` |
| `quiz_sessions` | Created if missing; existing schema/rows retained |
| `quiz_audit` | New action ledger with actor, timestamp and JSON details |
| `quiz_login_attempts` | New login-failure throttling table |

No old columns, legacy statuses, usernames, password hashes, questions, categories, sessions or uploads are removed or rewritten. Existing login cookies require a fresh sign-in. Existing v3 session JSON is upgraded lazily on an authorized action, with its original representation retained internally.

## 4. Run and configure

1. Start Apache and MySQL in XAMPP.
2. Open `http://localhost/The_Quiz/login.php`.
3. Sign in with your existing credentials. No new shared/default account is inserted by migration.
4. In **Users & roles**, create separate setter and marker accounts. Use at least 10 characters for new passwords. Give each person their account details privately.
5. Check the question bank for **Image needs attention** warnings. Restore missing originals or upload replacements. Two filenames referenced by the sample export are absent from the attachment.
6. In PHP configuration, set `upload_max_filesize=8M`, `post_max_size=10M`, `file_uploads=On`. Restart Apache after changing `php.ini`.
7. Ensure the Apache/PHP process can write to `uploads/questions/`. Do not use broad world-writable permissions on a hosted server. The app does not need to write to its PHP source folders.
8. Use the supplied compiled `play/`; Node is not required for normal use.

If an image is removed from a question or replaced, its previous file stays on disk for session snapshots. Do not manually delete old uploads unless you have checked every saved session reference.

## Fresh installation only

Create an empty `the_quiz_v2` database, import `database/questions.sql`, then run `database/migrate.php`. The supplied dump is unchanged historical input and contains an old account. Set a strong admin password immediately using the CLI-only recovery tool below. Remove or edit the five sample questions using the new archive/editor controls.

## Recover an admin password from the command line

Windows Command Prompt:

```bat
set QUIZ_RESET_PASSWORD=choose-a-new-long-password
C:\xampp\php\php.exe reset_admin.php your-admin-username
set QUIZ_RESET_PASSWORD=
```

Run the migration first. This creates or recovers that account as an active admin and invalidates its older logins. The tool is blocked over HTTP and has no hard-coded reset password. Do not use a password containing shell-special characters in this example without appropriate shell quoting; an existing admin can also reset another account in Users & roles.

## School network and projector

Use one central PHP/MySQL installation so multiple staff accounts share the same bank. Different PCs can connect to `http://HOST_LAN_IP/The_Quiz/` when Apache is permitted through the host firewall. If staff each use their own localhost installation, their databases will not synchronize.

On the marker's laptop press **Windows + P → Extend**. Open **Audience screen**, move that window to the projector, then click **Fullscreen** inside it. Keep the marker tab on the laptop. On a different audience device use the same generated URL with the host's reachable LAN address, not `localhost`. Keep the server running throughout the event.

## Development and tests

See `README.md` for build commands. Run PHP's built-in development server with `router.php`; it is not a production internet server. Apache must allow the provided `.htaccess` rules. Other web servers need equivalent source-folder deny rules and non-executable uploads.

Game rules:

```bash
php tests/game_test.php
```

For the HTTP/browser suite, create a disposable database whose name ends `_test`, import the old sample SQL into that empty database, run the migration, and run `php tests/seed.php` with `QUIZ_DB_NAME` set to that test database. Start the PHP server using the same environment. Install `tests/requirements.txt` with Python pip, then run `python tests/integration_test.py`. The integration test creates records and changes test accounts; reset the disposable database before another run. It never belongs on your school database.

For Chromium UI tests: `cd frontend && npm ci`, install Chromium via `npx playwright install chromium`, set `QUIZ_TEST_URL` to the test PHP URL, then run `node tests/browser_test.cjs` from the project root. Optionally set `QUIZ_CHROMIUM` to your browser executable and `QUIZ_TEST_PASSWORD` to the test seed password. The browser suite runs after the HTTP suite and uses records it creates. Screenshots are written under `tests/output` unless `QUIZ_TEST_OUTPUT` is set.

## Rollback

Stop writes and Apache, keep a separate copy of the failed-upgrade database and uploads for investigation, then restore the matching **pre-upgrade** database export and entire old project/uploads backup. Do not run old scoring code against sessions already written in the new schema. A rollback to the pre-upgrade backup loses changes entered after that backup; export them for manual reconciliation before restoring. Test the restored copy before reopening access.
