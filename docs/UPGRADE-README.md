# THE QUIZ — React teams upgrade

The live quiz frontend now uses React + Vite. PHP and MySQL remain the backend.
Your existing question/category administration pages remain available in PHP.
There is no countdown, time limit, or time penalty.

## Start on Windows / XAMPP

1. Back up your current project folder and export your current database in phpMyAdmin.
2. Extract this project into `C:\xampp\htdocs\The_quiz2` (avoid a double nested folder).
3. Start Apache and MySQL in XAMPP.
4. In `conn.php`, set your database name, username, password and port. Your uploaded
   configuration uses database `the_quiz_v2`, root, a blank password and port **3307**.
   Use **3306** only if that is your actual MySQL port.
5. Select your EXISTING database in phpMyAdmin and import `database/upgrade.sql`.
   This creates only the `quiz_sessions` table. It does not replace your questions.
6. Open `http://localhost/The_quiz2/login.php` and log in with your existing admin account.
7. Click **Open React Live Quiz** on the dashboard, or open
   `http://localhost/The_quiz2/play/`.
8. Enter a session name and 2–12 unique team names, one per line. Start the session.
9. Open **Audience screen** in another browser window. Place that window on your
   projector with Windows **Extend** display mode. Keep the host screen private.

The `play/` directory contains the compiled frontend: Node.js is NOT required
just to run it with XAMPP. PHP 8.0+ with mysqli/mysqlnd and MySQL/MariaDB are required.
Do not open the HTML using a file:// URL; use Apache.

### Fresh database only

If you have no existing database, create `the_quiz_v2`, import `questions.sql`,
then import `database/upgrade.sql`. The supplied old export has only five sample
questions. It is not a backup of the full question bank corresponding to the many
uploaded images. Some sample images may be absent. Replace samples using the admin UI.

The old export lacks `categories.status`, which the existing PHP admin expects.
For a fresh import of that export, run:

```sql
ALTER TABLE categories ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active';
```

Skip that statement if the column already exists. Do not import the old
`questions.sql` over your existing database. It also contains the old default
admin account; change its password before exposing any fresh install publicly.
`reset_admin.php` is now command-line-only; it retains its legacy reset behavior.

## How to host

- Select the active team, then a category and question number.
- The team answers aloud. Mark **Correct** (+10) or **Wrong** (0).
- After a wrong answer, optionally offer **one steal** to a different team (+5 if correct).
- Reveal the answer, then click **Next question**. The turn rotates from the original team.
- **Undo judgment** clears the provisional result before the answer is revealed.
- After reveal, **Cancel these points** clears points without hiding the answer.
- **Undo last score** clears the most recently completed question's points. Its question
  stays used because everyone has already seen the answer.
- End the session to show the winner, or joint winners on a tie. There is no automatic tiebreaker.
- Sessions can be resumed from the Sessions page; refresh does not erase scores.
- The audience link is read-only and updates about every 2.5 seconds. Anyone holding
  that link can view that session. Hidden answers are omitted from audience responses.

## Session behavior

New sessions snapshot ALL questions, including legacy `inactive` questions because the
old app used that status to mean already played. Each new session starts unused.
If you used inactive as an editorial archive, remove those questions from your question
bank before starting a new session, or extend creation with a curated question filter.
Question numbers are stable within each session, assigned in ID order per category.
Editing questions later affects new sessions, not existing session snapshots.
Image references use existing files: deleting an image in admin can affect older sessions.

Scores are derived from recorded results, not arbitrary browser totals. API writes require
admin login, a CSRF token, ownership of the session, and a matching revision. Database row
locking plus revision checks prevents duplicate awards from double-clicks or stale tabs.
The host should keep one control tab open. Sessions are visible only to their creator.

## Develop the React frontend

Install a current Node.js LTS version. In `frontend/` run:

```bash
npm ci
npm run build
```

The build writes to `play/`. Keep `play/` beside `api/` and `uploads/` when deploying.
For development, run PHP from the project root:

```bash
php -S 127.0.0.1:8000
```

Then in `frontend/` run `npm run dev` and open its displayed URL. The Vite proxy
forwards API, images and login requests to PHP. For the full legacy admin pages use
`http://127.0.0.1:8000/`; their asset routes are not all proxied by Vite.

React/Vite setup reference: https://react.dev/learn/build-a-react-app-from-scratch

## Files added / modified

- `frontend/`: React source, styles, Vite configuration and locked dependencies.
- `play/`: compiled frontend, ready for Apache.
- `api/index.php`: authenticated JSON API, session snapshots and revision checks.
- `api/game.php`: scoring and round transition rules; public response filtering.
- `database/upgrade.sql`: additive session table.
- `tests/game_test.php`: scoring and state transition checks (`php tests/game_test.php`).
- `dashboard.php`: link to the React quiz.
- `login.php`: regenerates the PHP session ID at login.
- `includes/functions.php`: safe image filename extension chosen from validated MIME type.
- `reset_admin.php`: blocks web access to the old password-reset utility.

The old public quiz pages and PHP admin are retained for compatibility. They are not a
complete security modernization. Use `play/` for the new team game. This package has
not been deployed to your computer or connected to your live database.

## Validation performed

- Production React/Vite build passed.
- PHP syntax checks passed for all added and modified PHP files.
- PHP game-rule tests passed using PHP WebAssembly: correct answers, steals, wrong
  answers, duplicate judgment rejection, undo, turn rotation, used-question protection,
  hidden-answer filtering and ended-session protection.
- Chromium browser checks with a mocked API passed: create session, pick a question,
  judge, reveal, next question, refresh recovery, mobile horizontal overflow and
  read-only audience controls. No browser JavaScript errors were observed.
- Desktop and mobile layouts were visually inspected.
- Live PHP/MySQL integration, database migration execution and real admin login were
  NOT tested here. Verify these on a backed-up local database before running an event.

### Local verification checklist

1. Log in, create a session with two teams and award a correct answer. Refresh:
   the team must still have 10 points.
2. On another question, mark wrong, pass to the other team and mark correct:
   that team must receive exactly 5 points.
3. Open the same host session in a second tab. A stale action must return a
   refresh message rather than award points a second time.
4. In the audience window, confirm the answer is hidden before reveal and visible after.
5. Complete the question, start a new session, and confirm it is available again.
6. Cancel a score, confirm the leaderboard updates, then end the session.

## Layout refresh — September 30, 2026

The project now uses a consistent dark navy and mint design across the homepage,
login, admin dashboard, question bank, category pages, question editors, and live quiz.

- Desktop sidebar with current-page highlighting; collapsible mobile navigation.
- New `questions.php` question bank with search, category filtering and edit links.
- Dashboard category distribution based on actual database counts.
- Clear form labels, local icons, image previews and responsive layouts.
- Mobile question cards keep edit actions visible without scrolling a wide table.
- Live quiz includes Choose → Answer → Reveal progress and a desktop sticky leaderboard.
- System fonts and local shared assets avoid remote font/icon dependencies.
- Keyboard skip link, visible focus states, Escape-to-close navigation and reduced-motion support.

No additional database migration is needed for this layout refresh. If the earlier
React upgrade is already installed, keep your working `conn.php` and database,
then copy the updated project files including `assets/`, `includes/`, `frontend/`
and the rebuilt `play/` directory. Do not overwrite existing uploads with a stale
copy. No need to rerun `questions.sql`. Hard-refresh the browser after updating.

Layout validation: ten actual PHP page templates were rendered with fixture data
and checked in Chromium at desktop (1440px) and mobile (390px) widths. Navigation,
search, category filter, empty results and page overflow were checked. Screenshots
were visually inspected. These checks do not exercise a real MySQL connection or
submit admin forms. The preview image in `previews/` uses illustrative fixture data.
