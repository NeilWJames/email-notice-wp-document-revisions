# Test suites

PHPUnit (PHP, below), Jest (JavaScript, see [JavaScript tests](#javascript-tests)) and Playwright (the screens in a browser, see [End-to-end tests](#end-to-end-tests-playwright)).

Integration tests run inside WordPress (the core test library) with WP Document Revisions loaded,
so they exercise the real hooks, capabilities and database tables.

**Status:** 178 tests in 21 classes (3 multisite-only); no known-bug tests left. 175 passed, 3 skipped (multisite) in the sandbox 2026-10-12; phpcs clean. Line coverage 84% (pcov), now including `uninstall.php`. The 147-test round is confirmed on 62wp and in CI.

## Running

One-off setup (on 62wp, from this plugin folder):

```bash
composer install
bash script/install-wp-tests wordpress_test <db-user> - localhost latest
```

`-` as the password makes the script ask for it. If the DB user can't create databases, create
`wordpress_test` and a user for it yourself (e.g. `wptest`, granted ALL on `wordpress_test.*`) and add
`true` as a 6th argument to skip that step.

`install-wp-tests` downloads WordPress to `/tmp/wordpress`, the test library to
`/tmp/wordpress-tests-lib`, and creates the `wordpress_test` database (the DB user needs
CREATE rights). **Use a throwaway database — the tests empty it.**

Then:

```bash
composer test                     # or bin/phpunit
bin/phpunit --filter Matching     # one class
bin/phpunit --group known-bug     # tests of bugs not yet fixed (none at present; excluded by default)
```

Environment variables:

| Variable | Default | Use |
|---|---|---|
| `WP_TESTS_DIR` | `/tmp/wordpress-tests-lib` | WordPress test library |
| `WPDR_PLUGIN_DIR` | `../wp-document-revisions` (sibling folder) | WP Document Revisions to load |

CI: the `phpunit` job in `.github/workflows/ci.yml` does the same with MySQL 8 and the latest WP Document
Revisions release from WordPress.org, on PHP 8.0, 8.3 and 8.5, on 8.4 as multisite (`WP_MULTISITE=1`, which
runs `class-test-wpdr-en-multisite.php`), and on 8.4 with coverage (pcov) sent to Codecov.

Multisite locally: `WP_MULTISITE=1 composer test`.

## How the bootstrap works (`tests/bootstrap.php`)

- On `muplugins_loaded` it sets an admin screen (the plugin only loads when `is_admin()`),
  reports WP Document Revisions as active (via the plugin's own `active_plugins` filter check),
  then loads WP Document Revisions and this plugin.
- After WordPress loads it does what activation and `admin_init` do on a real site:
  WPDR `add_caps()`, `db_version_check()` (tables + `read_doc_ext_lists` / `edit_doc_ext_lists` /
  `delete_doc_ext_lists` caps), then `wp_roles()->for_site()` — `WP_Roles::add_cap()` doesn't update
  the role objects already loaded, so without this the test users have none of the new caps —
  then `admin_init()` (settings, templates, AJAX hooks).

## Fixtures and helpers

`tests/trait-wpdr-en-fixtures.php` (trait `WPDR_EN_Fixtures`), used by both base classes:

| Helper | What it does |
|---|---|
| `create_users( $factory )` | One user per role in `self::$users[ role ]`; email `<role>@example.com`, display name `<role> user` |
| `subscribe( $user_id, $notify )` | Sets `wpdr_en_user_notification` (attachments off) |
| `create_document( $args )` | Published document by the editor, content "one two three four five"; `'terms' => [ taxonomy => ids ]` |
| `create_list( $args )` | Document External List with terms, addresses, match rule, pause, attach. Unhooks the plugin's form-only save hooks while inserting |
| `list_addresses( $list_id )` | Stored addresses of a list |
| `start_mail_capture()` / `$this->mails` | `pre_wp_mail` capture (nothing is sent) and no per-mail delay |
| `mailed_addresses()` | Sorted email addresses from captured mail |

Lists and documents use the built-in `category` (hierarchical) and `post_tag` (flat) taxonomies;
the plugin matches on term relationships in SQL, so any taxonomy behaves the same.

Base classes: `WPDR_EN_UnitTestCase` (WP_UnitTestCase) and `WPDR_EN_Ajax_UnitTestCase`
(WP_Ajax_UnitTestCase, with `ajax( $action, $user_id, $post )` returning the decoded JSON).
AJAX test files are named `class-test-wpdr-en-zz-*` so they run last, because
WP_Ajax_UnitTestCase defines `DOING_AJAX` for the rest of the run.

## Test classes

| File | Tests | Covers |
|---|---|---|
| `class-test-wpdr-en-address-table.php` | 3 | Security fix #3: Edit button values in escaped `data-*` attributes, Delete post ID forced to int, cells escaped |
| `class-test-wpdr-en-admin-pages.php` | 25 | Smoke tests (no PHP notices, output escaped): each settings field with a hostile stored value, settings/sections/fields registered; both log pages render (table, search, script); log column style only on log pages; Document metabox states (send, re-send, disabled for drafts, no extra-text box without `%extra%`) and its PublishPress Revisions exclusion; List metaboxes added and rendered (notes escaped, saved terms kept), refused without `edit_doc_ext_lists`; help tabs per screen and filter; List update messages; List error notice (draft warning, hidden when complete); log rows removed when a document or user is deleted |
| `class-test-wpdr-en-assets.php` | 6 | Stage 2: every `src/` file has a `build/` copy (JS, CSS); built JS keeps the global functions called from HTML; debug mode loads `src/` with file-time versions; document and List screens enqueue their script and style (nothing on other screens) |
| `class-test-wpdr-en-bug-fixes.php` | 18 | Bugs fixed in 4.0.0: Internal Notice mode saved (Auto/Manual) and shown, old 0 shown as Manual; "notify about" saved (known keys only), shown (all ticked until saved) and applied; Auto mode sends once on publish, notice for the publisher only; internal log status; internal extra text stored; internal and external %repeat%; `user_register` from code doesn't die; profile save (nonce, edit_user, roles without the choice), profile fields (Add New User shows them unticked), labels ("me" on one's own profile, "this user" otherwise), Add New User saves them, Users column |
| `class-test-wpdr-en-bulk-action.php` | 6 | Bulk subscribe/unsubscribe: menu actions, subscribe, unsubscribe (attachment off), other actions and empty selection ignored, roles without the choice refused and cleared |
| `class-test-wpdr-en-form-labels.php` | 7 | Markup fixed after the end-to-end tests: Notification mode radios, "notify about", profile and Quick Edit boxes inside their labels (Quick Edit without the `id` WordPress copies into every row), text settings labelled by their row heading (`label_for`), Users column boxes named (`aria-label`), Bulk Edit match rule text, one "Show more details" button per address row |
| `class-test-wpdr-en-capabilities.php` | 13 | 4.0.0 `read_doc_ext_lists`: default roles; install adds it when no role has it (whoever loads the page); tables checked only when the version changes; upgrade from 3.x gives it to roles and users with `edit_doc_ext_lists` (whoever loads the page), keeps an explicit denial, runs once; CPT read cap; log pages and menu items; metabox list section hidden / shown / fixed for read-only users |
| `class-test-wpdr-en-internal.php` | 5 | Internal recipients: opted-in only, private needs read access, drafts not sent, log row, HTML mail |
| `class-test-wpdr-en-log-export.php` | 6 | CSV export: link keeps search and sort, with nonce; internal rows (header, more than one page, search, extra text); external rows with the List; CSV text (BOM, quoting, formula cells made text); needs nonce and `read_doc_ext_lists`; both log pages link to it |
| `class-test-wpdr-en-log-purge.php` | 7 | Deleting old log entries: internal by the Writing setting (none, recent kept, old deleted); external by each List's days (90 deletes after 90 days, 0 or none keeps); unused extra text deleted; daily job scheduled on `admin_init`, run by the hook, cleared on deactivation; List days saved only with `delete_doc_ext_lists`, whole positive number; field read-only for others; setting sanitized |
| `class-test-wpdr-en-list-edit.php` | 8 | External List settings: list columns and cell values (attach falls back to the site setting), Quick/Bulk Edit fields; saving from the edit form (a ticked box is on), Quick Edit, Bulk Edit (translated button, No Change, bad nonce, only 0/1), from code (no change, no die), without edit rights |
| `class-test-wpdr-en-matching.php` | 9 | External Lists: any/all rule, parent term matches child (not the reverse), paused/draft lists and paused addresses skipped, selected lists only, public documents only, log row + sent flag |
| `class-test-wpdr-en-multisite.php` | 3 | Multisite only, skipped otherwise (run with `WP_MULTISITE=1`): security #6 — internal mail only to the site's members, only the site's own Lists used; a site is set up by its own (non-network) administrator |
| `class-test-wpdr-en-playground-demo.php` | 2 | `playground/demo-content.php` loads against the current code: users (password), Documents, Lists, settings, Departments; notifications sent give the log entries described in `docs/playground.md` (paused List and address skipped, parent term and all-terms matching, attachments, extra text, repeat text) |
| `class-test-wpdr-en-plugin.php` | 3 | `wpdr-email-notice.php`: WP Document Revisions active on the site or network; missing-plugin notice; translations folder |
| `class-test-wpdr-en-security.php` | 15 | Security #4 (term IDs cast, error message escaped, revert script safe and defined, code saves not redirected), #7 (Lists not public / not found by search), #8 (log search with quotes), #9 (recipient string, comma in name), #10 (attachment copy in private folder, removed), #11 (log tables, settings textarea, From address), #12 (bulk subscribe needs edit_user) |
| `class-test-wpdr-en-tags.php` | 9 | Default subject, trailing-number full stop and its filter, subject/content tags, `%words_n%`, `%extra%`, `%excerpt%` only for editors, external template |
| `class-test-wpdr-en-user-log.php` | 9 | Internal log table: rows newest first with titles and names, search by title or name, `%` searched as text, sorting by each column, unknown sort ignored, 20 per page, columns and empty message, links by capability, extra text from sending |
| `class-test-wpdr-en-zz-ajax-address.php` | 9 | Security fix #1: add/update/delete by an editor; contributor refused even when sending an admin `userid`; non-list refused; invalid email (F-17). Address search returns the filtered table; bad nonce (F-21), no post ID (F-22) |
| `class-test-wpdr-en-zz-ajax-send.php` | 14 | Security fix #2 (internal and external): editor can send; contributor and non-owning author get F-08; non-document F-07; bad nonce F-02. 4.0.0: author can send internal for own document; external needs `read_doc_ext_lists` (F-09); list choice honoured only for list editors |
| `class-test-wpdr-en-zzz-uninstall.php` | 1 | `uninstall.php` removes settings (including log days and the daily job), Lists, sent flags, user settings, role and user capabilities, and drops the tables (rolled back afterwards) |
Tests for bugs not yet fixed go in the relevant class with `@group known-bug` (excluded by default); remove the tag when fixed.

## JavaScript tests

**Status:** 29 Jest tests in 3 files (`tests/js`), all passing in the sandbox 2026-10-12; 100% of `src/js` lines and branches (V8 coverage).

Run from the plugin folder (Node 20+): one-off `npm install`, then `npm test` (or `npm run test:coverage`, report in `coverage/js`).
CI: the `js` job in `ci.yml` runs `npm run test:coverage` on Node 22 and uploads to Codecov with the flag `js` (PHP uploads use `php`).

How it works:

- `jest.config.cjs`: jsdom environment, tests in `tests/js/*.test.js`, coverage of `src/js` (the sources, not `build/`).
- `tests/js/global-script-transform.cjs`: the scripts are plain browser scripts whose top-level functions are globals
  (called from `onclick` attributes); the transform appends lines making them globals under Jest too.
- `tests/js/helpers.js`: `loadScript( name, html )` sets the page, fakes `wpdr_en_obj` (same keys as `load_js_methods()`),
  `jQuery.ajax` (a Jest mock: its calls hold the request, and the test calls `success` / `error` / `complete` itself) and `alert`,
  loads the script and fires `DOMContentLoaded`; `lastRequest( ajax )`; `asOnclick( fn, ...args )` calls a function with
  `window.event` set, as an inline handler does.

| File | Tests | Covers |
|---|---|---|
| `wpdr-email-notice.test.js` | 14 | Document metabox: internal and external requests (post ID, nonce, ticked lists, extra text only when ticked); button disabled and progress shown until the request completes, no second send; success, partial and failure messages (the "out of" text), alert with the error code, re-send label; response values cannot add markup; failed request; unticking all lists disables the button; extra-text boxes follow the text, the template and the buttons; one or no section |
| `wpdr-en-address.test.js` | 13 | List edit screen: Add (request, form submit stopped, no global variables), result table shown and entry reset, error shown as text, Delete, Edit/Clear, Add enabled only with name and email, no event; search keeps the edit action; "updated" message removed when the save was refused; unsaved terms restored; Quick Edit copies the row settings (not into Bulk Edit), ticking updates the value, other clicks ignored |
| `wpdr-en-log.test.js` | 2 | Extra Text shown and hidden; a row without extra text ignored |

## End-to-end tests (Playwright)

**Status:** 21 tests in 5 specs, all passing on the PC (Playground, 2.7 min) and in the sandbox 2026-10-12. CI (`e2e.yml`, wp-env, MySQL) not yet run. The "403 (Forbidden)" console line in a run is expected: the logs spec checks that an author is refused.

Browser tests of the admin screens, with `@playwright/test` and `@wordpress/e2e-test-utils-playwright`, set up as for Simple Taxonomy Refreshed:

| Where | WordPress runtime | Database |
|---|---|---|
| Locally (Windows, Node only) | **WordPress Playground** (`npm run env:start`, blueprint `tests/e2e/blueprint.json`): WebAssembly, no Docker, a new site at every start | SQLite |
| CI (`e2e.yml`) | wp-env **Docker** runtime (`.wp-env.json`) | MySQL |

Both install WP Document Revisions from wordpress.org (latest stable) and use this plugin folder. The site is `http://127.0.0.1:8888` locally (Playground listens on IPv4 only and uses that as its site URL) and `http://localhost:8888` in CI; log in as `admin` / `password`. Port 8888, so the demo playground (8889) can run at the same time. PHP 8.2, latest WordPress, `WP_DEBUG` on: a PHP error, warning, notice or deprecation on a visited admin page fails the test. Time limits are raised (test 5 min, action 30 s, navigation 60 s, `expect` 20 s) for the network share. One worker: the tests share one site.

Unlike STR, `@wordpress/scripts` is not used (this plugin does not otherwise need it): `playwright.config.js` copies its Playwright defaults and `tests/e2e/global-setup.js` does its login step.

The tests use `build/` as committed (what is released), not `src/`: run `npm run build` first after changing `src/`.

### Running

```bash
npm install                      # on 62wp (Linux), once and after package.json changes
npm run e2e:browser              # on the PC, once: downloads Chromium for Playwright (into the user profile)

npm run env:start                # start WordPress and leave it running (separate window; Ctrl+C to stop)
npm run test:e2e                 # runs all specs; starts WordPress for the run if it is not running
npm run test:e2e:ui              # Playwright's UI mode: pick tests, watch them, time-travel
npm run test:e2e -- logs         # one spec
```

Run these from PowerShell on the PC (as for `npm run playground`): Playground's server is only reachable on the machine running it. `node_modules` is shared through the network folder and installed on 62wp: Jest needs Linux-only native files there, so don't run `npm install` from Windows. Windows gets no `.cmd` launchers in `node_modules/.bin`, so `test:e2e` and `e2e:browser` call Playwright's JavaScript with `node`, which works on both systems (Playwright has no native parts). The Playground CLI does have a native part, so `env:start`, like `playground`, runs it with `npx @wp-playground/cli@latest` (downloaded into the npm cache on the PC). It must not be in `node_modules` at all, or npx tries the local copy and fails on Windows: so neither it nor `@wordpress/env` (which depends on it) is a dev dependency, and CI starts wp-env with `npx @wordpress/env start`; for other Playwright commands use `node node_modules/@playwright/test/cli.js …` instead of `npx playwright …`. For repeated runs keep `env:start` running in its own window; otherwise each `test:e2e` starts and stops WordPress. The tests reset the site themselves. Failures leave a trace, screenshot and video in `artifacts/test-results/`; open a trace with `node node_modules/@playwright/test/cli.js show-trace <file>`.

A full run takes about 3 minutes on the PC (the reset loads the demo content before most tests).

### Files

| File | Purpose |
|---|---|
| `tests/e2e/blueprint.json` | Local Playground site: installs and activates WP Document Revisions, activates this plugin (mounted by `env:start`), deletes WP Document Revisions' false capability warning (as the demo does), `WP_DEBUG`. No `login` step, and `env:start` passes `--no-login`: Playwright logs in itself |
| `.wp-env.json` | The same site for wp-env (CI): WP Document Revisions zip from wordpress.org, this plugin, the helper as a must-use plugin, PHP 8.2, `WP_DEBUG`, no separate tests site |
| `playwright.config.js` | Specs in `tests/e2e/specs`, output in `artifacts/`, site on port 8888; starts `npm run env:start` locally or `npx @wordpress/env start` when `CI` is set, and waits up to 15 minutes for the site to answer |
| `tests/e2e/global-setup.js` | Logs in as the administrator once and saves the login (`artifacts/storage-states/admin.json`) |
| `tests/e2e/mu-plugins/wpdr-en-e2e-helper.php` | Loads `playground/demo-mu-plugin.php` (Departments taxonomy, no mail server, no pause between emails) and records each email (`pre_wp_mail`, before the demo filter treats it as sent). Test-only REST routes (`wpdr-en-e2e/v1`, administrators only): `POST /reset` deletes Documents, Lists, log entries, demo users (and users whose login starts `e2e-`), Departments and the plugin settings, then with `{ "demo": true }` loads `playground/demo-content.php` and returns the post ID of each Document and List by title; `GET /mails` the emails recorded since; `POST /option` sets a `wpdr_en_set_*` setting. The plugin loads only in the admin, so requests for these routes define `WP_ADMIN` (as the playground blueprint's `runPHP` step does). Not shipped |
| `tests/e2e/helpers.js` | `resetSite()`, `getMails()` (adds `address`, the To address alone), `setOption()`, `editPost()`, `loginAs()` (another demo user in this test's browser context), `collectPageErrors()`, `expectNoPhpErrors()`, `notifyBox()` (Document metabox locators), `addressForm()` / `enterAddress()` (List address entry), admin URLs |
| `tests/e2e/specs/*.spec.js` | The tests |
| `artifacts/` | Output (login state, traces); gitignored |

Test data comes from the playground demo (see `docs/playground.md`) through the helper routes, not WP-CLI, because the Playground runtime has no `wp-env run`. So the demo, the PHPUnit demo test and these tests all use the same content.

Playground's SQLite is not MySQL: SQL details (sorting collation, for example) stay covered by PHPUnit; the tests avoid depending on them.

### Specs

| Spec | Tests |
|---|---|
| `document-notify.spec.js` | Document metabox, manual mode (4): sending to subscribed users (the four demo subscribers, the subscriber who asked for it gets the attachment; message with the count, button becomes Re-send, log link opens the internal log with the rows); extra text sent only when its box is ticked; Lists matched on Purchasing policy ticked, the paused List not offered, unticking all disables the button, only the ticked List mailed with its extra text, shown in the external log; a List that attaches sends the document (paused address skipped). No script errors |
| `list-edit.spec.js` | List edit screen (5): add (enabled only with name and email), edit (email address read-only, name and pause changed) and delete an address, all without reloading the page; an invalid address refused with a message, a valid one kept after a reload; match rule, attach, pause and log days saved and unticked again; an administrator can change the log days; an editor sees them read-only with the explanation, and saving the List keeps them |
| `lists-table.spec.js` | Lists table (3): settings columns; Quick Edit shows the row's settings and saves new ones (checked on the edit screen); Bulk Edit changes the chosen settings on the selected Lists only ("No Change" kept) |
| `logs.spec.js` | Email logs (4), demo loaded once: internal log rows, Export CSV (BOM, header, a row per email), search then export only the rows found; sorting by email both ways (first letters only: MySQL and SQLite order `subscriber2@` differently) kept in the export, extra text in the export; external log rows (paused address absent) and export with the Lists; an editor sees the logs, an author has no menu and gets 403 |
| `settings-users.spec.js` | Settings and users (5): Writing settings saved (mode, notify about, subject, log days); Auto mode: publishing a draft with Quick Edit sends, the count is shown once on the next page, a later save sends nothing; profile boxes ("me" on one's own, "this user" on another's) saved; Add New User subscribes the new user (Users column); Users bulk actions subscribe and unsubscribe |

Found by these tests and fixed (PHPUnit `class-test-wpdr-en-form-labels.php`): Quick Edit and settings checkboxes and radios had no usable label (the Quick Edit labels pointed at the hidden template row); Users column boxes had no name and a stray `</>`; Bulk Edit offered "All taxonomy elements-"; each address row had two "Show more details" buttons.

## Planned

- The loading code at the end of `wpdr-email-notice.php` runs in the bootstrap, before coverage starts, so it shows as not covered.
- Attachments (internal preference, list setting, size filter, temp file cleanup).
- End-to-end: first CI run (wp-env, MySQL); then perhaps the Document's own edit screen with a file upload (WP Document Revisions' uploader).

## Change log

- 2026-10-12: End-to-end tests: Playwright, 21 tests in 5 specs (`tests/e2e`), Playground locally and wp-env in CI (`e2e.yml`), as for STR. Markup they found fixed, with `class-test-wpdr-en-form-labels.php` (7). 178 tests.
- 2026-10-12: Playground demo test (`class-test-wpdr-en-playground-demo.php`, 2). The demo was also run in WordPress Playground (CLI, SQLite) in the sandbox: that found the List matching and log deletion SQL that SQLite treated differently; both now pass there. 171 tests.
- 2026-10-12: Log export: new `class-test-wpdr-en-log-export.php` (6). 169 tests.
- 2026-10-12: Log deletion: new `class-test-wpdr-en-log-purge.php` (7); settings field for the internal log days (admin pages, 1); uninstall checks the option and the daily job. 163 tests.
- 2026-10-12: Profile labels test (bug-fixes, 1). 155 tests.
- 2026-10-12: JavaScript tests: Jest + jsdom, 29 tests in `tests/js`, 100% of `src/js`; CI `js` job with Codecov flag `js`. Scripts fixed (see changelog 4.0.0).
- 2026-10-11: Bugs and tech debt: new `class-test-wpdr-en-plugin.php` (3) and `class-test-wpdr-en-zzz-uninstall.php` (1, runs last as `uninstall.php` can only be loaded once); per-version table check (capabilities), site set-up by its own administrator (multisite), Add New User (2). `uninstall.php` added to coverage. 154 tests.
- 2026-10-11: Smoke tests: new `class-test-wpdr-en-admin-pages.php` (24); List screen assets (1); address search AJAX (2). 147 tests; line coverage 69% → 83%.
- 2026-10-11: Coverage round: known bugs fixed and their tests moved to `class-test-wpdr-en-bug-fixes.php` (`class-test-wpdr-en-known-bugs.php` removed); new `class-test-wpdr-en-user-log.php` (9), `class-test-wpdr-en-bulk-action.php` (6), `class-test-wpdr-en-list-edit.php` (8). Bootstrap no longer unhooks `save_user_profile`. 120 tests; line coverage 55% → 69%.
- 2026-10-11: First CI multisite run: 3 test-setup fixes — the second site is created in `wpSetUpBeforeClass()` (tables made during a test are temporary and can't be used twice in one query); the install and bulk-subscribe tests use user 1, as on multisite only a network administrator can activate plugins or edit other users.
- 2026-10-11: Stage 1 + Stage 2 run on 62wp: 81 tests, 2 skipped (multisite), all others pass; phpcs clean.
- 2026-10-11: Built files keep the source names (`build/js/x.js`, no `.min`); asset and security tests updated.
- 2026-10-11: Stage 1 security tests: `class-test-wpdr-en-security.php` (15), `class-test-wpdr-en-multisite.php` (2, multisite only). Tags test: To is now `Name <email>` (with a space).
- 2026-10-11: Stage 2 asset tests added (`class-test-wpdr-en-assets.php`, 5).
- 2026-10-11: 59/59 on 62wp; phpcs clean; pushed to GitHub, CI green (phpcs + phpunit).
- 2026-10-11: Third run: 53/58 — the test database's roles already had edit/delete but not read, and the default install only ran when edit or delete were missing. Plugin now also installs the defaults when no role has read; test added (12 in the class).
- 2026-10-11: 4.0.0 capability tests: new `class-test-wpdr-en-capabilities.php` (11), 4 more in `zz-ajax-send`. Capability tests restore roles in `tear_down()` (rollback, cache flush, `wp_roles()->for_site()`).
- 2026-10-11: Second run on 62wp: 43/43 passed.
- 2026-10-11: First run on 62wp (WP 7.1, PHP 8.x, MariaDB): reload role objects after adding caps in the bootstrap; plugin fix for unnamed table indexes found by the run.
- 2026-10-11: Suite created: bootstrap, fixtures trait, base classes, 43 tests + 6 known-bug tests. AJAX handlers switched to `wp_send_json()` so they can be tested.
