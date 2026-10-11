# Changelog

## 4.0.0

Release date: (in development)

### Breaking changes

* The email logs (Document Emails menu) and the External List part of the Document metabox need the new read_doc_ext_lists capability (previously edit_documents). On upgrade it is given to every role and user that has edit_doc_ext_lists; give it to anyone else who needs this access.
* Sending External List emails needs read_doc_ext_lists as well as the right to edit the Document.
* Users without edit_doc_ext_lists always send to every matched List.

* NEW: Capability read_doc_ext_lists controls who sees Document External Lists: the email logs (Document Emails menu) and the External List part of the Document metabox. Given on upgrade to every role and user with edit_doc_ext_lists.
* FIX: Security: Sending to External Lists needs read_doc_ext_lists; only users with edit_doc_ext_lists can choose which matched Lists to send to (the unticking was not enforced before).
* FIX: Security: The email logs need read_doc_ext_lists (previously any user with edit_documents could see every address).
* FIX: Security: Sending notifications now checks that the user can edit the Document being mailed.
* FIX: Security: Adding or deleting External List addresses now checks that the user can edit the List, instead of trusting a user ID sent by the browser. Email addresses are validated.
* FIX: Security: External List address table escapes names and email addresses.
* FIX: The plugin tables' indexes are named, so the table check on update no longer logs "Incorrect index name" database errors.
* DEV: PHPUnit test suite in tests/ (see tests/test_suite.md); runs locally with script/install-wp-tests and in CI.
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
