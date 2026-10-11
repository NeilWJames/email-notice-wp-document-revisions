# PHPUnit test suite

Integration tests run inside WordPress (the core test library) with WP Document Revisions loaded,
so they exercise the real hooks, capabilities and database tables.

**Status:** 147 tests in 15 classes (2 multisite-only); no known-bug tests left. 145 passed, 2 skipped (multisite) in the sandbox 2026-10-11 (all 147 pass as multisite); phpcs clean. Line coverage 83% (pcov). Previous round (120 tests) confirmed on 62wp.

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
  WPDR `add_caps()`, `db_version_check()` as user 1 (tables + `edit_doc_ext_lists` /
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
| `class-test-wpdr-en-admin-pages.php` | 24 | Smoke tests (no PHP notices, output escaped): each settings field with a hostile stored value, settings/sections/fields registered; both log pages render (table, search, script); log column style only on log pages; Document metabox states (send, re-send, disabled for drafts, no extra-text box without `%extra%`) and its PublishPress Revisions exclusion; List metaboxes added and rendered (notes escaped, saved terms kept), refused without `edit_doc_ext_lists`; help tabs per screen and filter; List update messages; List error notice (draft warning, hidden when complete); log rows removed when a document or user is deleted |
| `class-test-wpdr-en-assets.php` | 6 | Stage 2: every `src/` file has a `build/` copy (JS, CSS); built JS keeps the global functions called from HTML; debug mode loads `src/` with file-time versions; document and List screens enqueue their script and style (nothing on other screens) |
| `class-test-wpdr-en-bug-fixes.php` | 16 | Bugs fixed in 4.0.0: Internal Notice mode saved (Auto/Manual) and shown, old 0 shown as Manual; "notify about" saved (known keys only), shown (all ticked until saved) and applied; Auto mode sends once on publish, notice for the publisher only; internal log status; internal extra text stored; internal and external %repeat%; `user_register` from code doesn't die; profile save (nonce, edit_user, roles without the choice), profile fields, Users column |
| `class-test-wpdr-en-bulk-action.php` | 6 | Bulk subscribe/unsubscribe: menu actions, subscribe, unsubscribe (attachment off), other actions and empty selection ignored, roles without the choice refused and cleared |
| `class-test-wpdr-en-capabilities.php` | 12 | 4.0.0 `read_doc_ext_lists`: default roles; install adds it when no role has it; upgrade from 3.x gives it to roles and users with `edit_doc_ext_lists` (whoever loads the page), keeps an explicit denial, runs once; CPT read cap; log pages and menu items; metabox list section hidden / shown / fixed for read-only users |
| `class-test-wpdr-en-internal.php` | 5 | Internal recipients: opted-in only, private needs read access, drafts not sent, log row, HTML mail |
| `class-test-wpdr-en-list-edit.php` | 8 | External List settings: list columns and cell values (attach falls back to the site setting), Quick/Bulk Edit fields; saving from the edit form (a ticked box is on), Quick Edit, Bulk Edit (translated button, No Change, bad nonce, only 0/1), from code (no change, no die), without edit rights |
| `class-test-wpdr-en-matching.php` | 9 | External Lists: any/all rule, parent term matches child (not the reverse), paused/draft lists and paused addresses skipped, selected lists only, public documents only, log row + sent flag |
| `class-test-wpdr-en-multisite.php` | 2 | Security #6 (multisite only, skipped otherwise; run with `WP_MULTISITE=1`): internal mail only to the site's members; only the site's own Lists used |
| `class-test-wpdr-en-security.php` | 15 | Security #4 (term IDs cast, error message escaped, revert script safe and defined, code saves not redirected), #7 (Lists not public / not found by search), #8 (log search with quotes), #9 (recipient string, comma in name), #10 (attachment copy in private folder, removed), #11 (log tables, settings textarea, From address), #12 (bulk subscribe needs edit_user) |
| `class-test-wpdr-en-tags.php` | 9 | Default subject, trailing-number full stop and its filter, subject/content tags, `%words_n%`, `%extra%`, `%excerpt%` only for editors, external template |
| `class-test-wpdr-en-user-log.php` | 9 | Internal log table: rows newest first with titles and names, search by title or name, `%` searched as text, sorting by each column, unknown sort ignored, 20 per page, columns and empty message, links by capability, extra text from sending |
| `class-test-wpdr-en-zz-ajax-address.php` | 9 | Security fix #1: add/update/delete by an editor; contributor refused even when sending an admin `userid`; non-list refused; invalid email (F-17). Address search returns the filtered table; bad nonce (F-21), no post ID (F-22) |
| `class-test-wpdr-en-zz-ajax-send.php` | 14 | Security fix #2 (internal and external): editor can send; contributor and non-owning author get F-08; non-document F-07; bad nonce F-02. 4.0.0: author can send internal for own document; external needs `read_doc_ext_lists` (F-09); list choice honoured only for list editors |
Tests for bugs not yet fixed go in the relevant class with `@group known-bug` (excluded by default); remove the tag when fixed.

## Planned

- Plugin loading paths in `wpdr-email-notice.php` (runs before coverage starts) and `init()`.
- Attachments (internal preference, list setting, size filter, temp file cleanup).
- Uninstall.

## Change log

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
