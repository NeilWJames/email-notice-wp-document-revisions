# PHPUnit test suite

Integration tests run inside WordPress (the core test library) with WP Document Revisions loaded,
so they exercise the real hooks, capabilities and database tables.

**Status:** 59 tests in 7 classes, plus 6 known-bug tests. Last full run on 62wp 2026-10-11: 43/43 passed (before the 4.0.0 capability tests were added).

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
bin/phpunit --group known-bug     # the known-bug tests (expected to fail)
```

Environment variables:

| Variable | Default | Use |
|---|---|---|
| `WP_TESTS_DIR` | `/tmp/wordpress-tests-lib` | WordPress test library |
| `WPDR_PLUGIN_DIR` | `../wp-document-revisions` (sibling folder) | WP Document Revisions to load |

CI: the `phpunit` job in `.github/workflows/ci.yml` does the same on PHP 8.3 and 8.4 with
MySQL 8 and the latest WP Document Revisions release from WordPress.org.

## How the bootstrap works (`tests/bootstrap.php`)

- On `muplugins_loaded` it sets an admin screen (the plugin only loads when `is_admin()`),
  reports WP Document Revisions as active (via the plugin's own `active_plugins` filter check),
  then loads WP Document Revisions and this plugin.
- After WordPress loads it does what activation and `admin_init` do on a real site:
  WPDR `add_caps()`, `db_version_check()` as user 1 (tables + `edit_doc_ext_lists` /
  `delete_doc_ext_lists` caps), then `wp_roles()->for_site()` — `WP_Roles::add_cap()` doesn't update
  the role objects already loaded, so without this the test users have none of the new caps —
  then `admin_init()` (settings, templates, AJAX hooks).
- It unhooks `save_user_profile` from `user_register` (known bug — it dies outside the profile
  form, which would break every factory-created user).

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
| `class-test-wpdr-en-capabilities.php` | 12 | 4.0.0 `read_doc_ext_lists`: default roles; install adds it when no role has it; upgrade from 3.x gives it to roles and users with `edit_doc_ext_lists` (whoever loads the page), keeps an explicit denial, runs once; CPT read cap; log pages and menu items; metabox list section hidden / shown / fixed for read-only users |
| `class-test-wpdr-en-internal.php` | 5 | Internal recipients: opted-in only, private needs read access, drafts not sent, log row, HTML mail |
| `class-test-wpdr-en-matching.php` | 9 | External Lists: any/all rule, parent term matches child (not the reverse), paused/draft lists and paused addresses skipped, selected lists only, public documents only, log row + sent flag |
| `class-test-wpdr-en-tags.php` | 9 | Default subject, trailing-number full stop and its filter, subject/content tags, `%words_n%`, `%extra%`, `%excerpt%` only for editors, external template |
| `class-test-wpdr-en-zz-ajax-address.php` | 7 | Security fix #1: add/update/delete by an editor; contributor refused even when sending an admin `userid`; non-list refused; invalid email (F-17) |
| `class-test-wpdr-en-zz-ajax-send.php` | 14 | Security fix #2 (internal and external): editor can send; contributor and non-owning author get F-08; non-document F-07; bad nonce F-02. 4.0.0: author can send internal for own document; external needs `read_doc_ext_lists` (F-09); list choice honoured only for list editors |
| `class-test-wpdr-en-known-bugs.php` | 6 | `@group known-bug`, excluded by default: Auto mode saved as 0, "notify about" lost, internal log status empty, internal extra text not stored, external %repeat% never shown, `user_register` dies |

When a known bug is fixed, remove `@group known-bug` from that test (move it to the relevant class).

## Planned

- Settings sanitizing (once the known bugs are fixed).
- User profile save and bulk subscribe/unsubscribe (needs the `user_register` fix).
- Attachments (internal preference, list setting, size filter, temp file cleanup).
- Remaining security findings 4, 6–12 as they are fixed (term error script, multisite tables, CPT visibility, log search SQL).
- Uninstall.

## Change log

- 2026-10-11: Third run: 53/58 — the test database's roles already had edit/delete but not read, and the default install only ran when edit or delete were missing. Plugin now also installs the defaults when no role has read; test added (12 in the class).
- 2026-10-11: 4.0.0 capability tests: new `class-test-wpdr-en-capabilities.php` (11), 4 more in `zz-ajax-send`. Capability tests restore roles in `tear_down()` (rollback, cache flush, `wp_roles()->for_site()`).
- 2026-10-11: Second run on 62wp: 43/43 passed.
- 2026-10-11: First run on 62wp (WP 7.1, PHP 8.x, MariaDB): reload role objects after adding caps in the bootstrap; plugin fix for unnamed table indexes found by the run.
- 2026-10-11: Suite created: bootstrap, fixtures trait, base classes, 43 tests + 6 known-bug tests. AJAX handlers switched to `wp_send_json()` so they can be tested.
