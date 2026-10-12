# Changelog

## 4.0.0

Release date: (in development)

### Breaking changes

* The email logs (Document Emails menu) and the External List part of the Document metabox need the new read_doc_ext_lists capability (previously edit_documents). On upgrade it is given to every role and user that has edit_doc_ext_lists; give it to anyone else who needs this access.
* Sending External List emails needs read_doc_ext_lists as well as the right to edit the Document.
* Users without edit_doc_ext_lists always send to every matched List.
* If you chose Auto for "Internal Notice mode" (Settings > Writing), choose it again and save: since 3.2 it was stored as Manual.

* NEW: Export CSV on both email log pages: downloads every entry matching the page's search, in its order, for anyone who can see the logs. UTF-8 for Excel; cells that a spreadsheet would run as formulas are made text.
* NEW: Old log entries are deleted daily for Documents not modified for a set number of days: internal entries by a Settings > Writing value, external entries by a value on each Document External List (changed only by users who can delete Lists). Empty or 0 keeps them.
* NEW: Capability read_doc_ext_lists controls who sees Document External Lists: the email logs (Document Emails menu) and the External List part of the Document metabox. Given on upgrade to every role and user with edit_doc_ext_lists.
* FIX: Security: Sending to External Lists needs read_doc_ext_lists; only users with edit_doc_ext_lists can choose which matched Lists to send to (the unticking was not enforced before).
* FIX: Security: The email logs need read_doc_ext_lists (previously any user with edit_documents could see every address).
* FIX: Security: Sending notifications now checks that the user can edit the Document being mailed.
* FIX: Security: Adding or deleting External List addresses now checks that the user can edit the List, instead of trusting a user ID sent by the browser. Email addresses are validated.
* FIX: Security: External List address table escapes names and email addresses.
* FIX: Security: The parent/child term error on the External List screen reduces term values to IDs and escapes its messages; the missing revert_unsaved_hier() script function is added, so unsaved term changes are ticked again.
* FIX: The parent/child term check works (form term IDs were compared as text), and a List with only flat-taxonomy terms (e.g. tags) can be published.
* FIX: External Lists saved from code rather than the edit screen are no longer checked and redirected.
* FIX: Security: Multisite: each site uses only its own External Lists and mails only its own members.
* FIX: Security: Document External Lists are no longer public: not in site search, menus or the REST API.
* FIX: Security: The External log search uses a prepared query (and finds names with quotes).
* FIX: Security: Commas and angle brackets in a name can no longer add recipients.
* FIX: Security: The copy of a document made for attaching is put in a private, randomly named folder and always removed.
* FIX: Security: Log pages, settings fields and List notes escape their output.
* FIX: Security: Bulk subscribe/unsubscribe only changes users the current user may edit.
* FIX: The Email Address setting replaces WordPress's default From address (the site domain was worked out wrongly, so it never matched).
* FIX: The plugin tables' indexes are named, so the table check on update no longer logs "Incorrect index name" database errors.
* FIX: Auto mode works again: the Internal Notice mode was saved as 0, so automatic notices were never sent (since 3.2). A Document is mailed once, when first published, and the result is shown only to the user who published it, with a working link to the log.
* FIX: The "Notify internal users about" setting is saved and applied (it was saved empty and had no effect). Until the setting is saved again, all three kinds of Document are notified, as before.
* FIX: A user created other than on the Add New User screen (from code, an import or registration) no longer stops with "The link you followed has expired".
* FIX: The internal log records each email's status, and extra text sent to internal users is stored and shown in the log.
* FIX: Repeat emails to External List addresses include the %repeat% text.
* FIX: Ticking Document Attach or Pause on the External List edit screen switches it on (only switching off worked). Bulk Edit of Lists works on sites not in English. A List saved from code no longer stops with "The link you followed has expired".
* FIX: The internal log search treats % and _ as ordinary characters.
* FIX: The Add New User screen shows the Document Email settings; they are saved if the new user's role can receive notifications. When adding or editing another user they read "this user" rather than "me".
* FIX: Translations in the plugin's languages folder are loaded (it looked in includes/languages).
* FIX: The plugin works when WP Document Revisions is network-activated, and the notice that WP Document Revisions is missing is shown with the other admin notices (it was printed before the page).
* FIX: Multisite: each site's tables are checked once, not on every admin page, and a site gets its capabilities and tables when anyone first opens its admin (only a network administrator could install the capabilities).
* FIX: Uninstall removes the capabilities from every site's roles and from users given them directly, the External List sent flags on Documents, and the settings of every user on the network.
* FIX: Document metabox: the message when only some emails were sent no longer shows "undefined"; the Send button stays disabled and the progress icon shown until sending finishes (both were reset straight away); a failed request shows an error instead of "Sending..." for ever; the script error on every send (from `$`, undefined in WordPress) is gone.
* FIX: External List addresses: no script error after adding or deleting an address, no stray global variables, and the address error message is shown as text.
* DEV: Script and style sources moved to src/js and src/css; minified copies are in build/js and build/css, made with `npm run build` (terser, clean-css) or the Build assets workflow. Asset versions are the file times, so browsers pick up rebuilt files.
* DEV: PHPUnit test suite in tests/ (see tests/test_suite.md); runs locally with script/install-wp-tests and in CI.
* DEV: JavaScript tests with Jest and jsdom in tests/js (`npm test`), run in CI with coverage sent to Codecov (flag js; PHP has flag php).
* DEV: Tests added for the internal log, bulk subscribe/unsubscribe, External List settings, the admin screens, plugin loading, uninstall and the fixed bugs; uninstall.php is included in coverage. Class descriptions and @package tags made consistent. A duplicate loader of the bulk action class with a wrong path is removed.
* DEV: AJAX handlers reply with wp_send_json() instead of echo and die, so they can be tested.
* DEV: Plugin header version set to match the readme Stable tag.
* DEV: PHPCompatibility now checks against PHP 8.0+, the plugin's minimum.
* DEV: CI skips translation-only changes and runs with read-only permissions.
* DEV: Generate POT workflow restricted to main and given the permissions it needs to open its PR.

## 3.2.2

Release date: August 26, 2026

* NEW: Test up to WP 7.1 and PHP 8.3 with WP Coding Standards 3.4 and Plugin Check 2.1

## 3.2.1

Release date: January 19, 2026

 * NEW: Test up to WP 6.9

## 3.2

Release date: April 18, 2025

* NEW: GitHub documentation included in plugin and explicitly linked.
* NEW: Text domain changed as plugin_check plugin complains.
* FIX: Settings sanitized on update.
* FIX: Notification-specific text message metabox reviewed.
* FIX: Filter wpdr_en_ext_attach_notice withdrawn as effectively replaced by global setting.
* FIX: Consistent use of word email (rather than e-mail)
* FIX: Review uninstall process

## 3.1

Release date: December 24, 2024

* NEW: Can add a notification-specific text message in the emails being sent.

## 3.0

Release date: August 23, 2024

* NEW: Can pause sending to selected External User Lists or individual External Users.
* NEW: Capability delete_doc_ext_lists to delete External User Lists.
* FIX: Activation scripts not being executed.

## 2.0

Release date: September 14, 2023

* NEW: JS scripts will be called with Defer in WP 6.3 onwards.

## 1.0

Release date: June 6, 2023

* Initial release based on recast of Janos Ver's plugin "WP JV Custom Email Settings"
