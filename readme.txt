=== Email Notice for WP Document Revisions ===
Contributors: nwjames, janosver
Tags: administration, email, e-mail, document, automatic
Requires at least: 5.9
Requires PHP: 8.0
Requires Plugins: wp-document-revisions
Tested up to: 7.1
Stable tag: 4.0.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Add-on to WP Document Revisions to notify your users about new documents published or create user email lists to send non-users notifications.

== Description ==

This plugin is an add-on to WP Document Revisions. This supports sending out email notifications about newly published documents to users who are interested to receive notifications either automatically (on publish) or manually.

By default when you send any notification from your blog (including many of the plugins as well) sender is "WordPress" and the sender email address is <wordpress@yourdomain.com>. 

This plugin will enable you to 

* Let your WordPress users (automatically/manually) receive email notifications of updates to your published documents

* Create lists of email users that can be sent email notifications of updates to your published documents

* Easily customize notification email subject and content templates

* Re-send email notifications manually as well

* You can add a notification-specific message in the email.

* When a notification is resent to a user, then you can include information that it is a resend and when previously sent

* Maintain email logs about sent email notifications.

* Replace default <wordpress@yourdomain.com> to any email address

* Replace default email from "WordPress" name to anything you want

* Administration forms contain significant pulldown Help information.

For your WordPress users:

* Send notifications about password protected posts as well to those that can read it (password will NOT be included in notification email)

* Users can opt-out if they don't want to receive emails (they can choose to get all/nothing)

* Bulk subscribe/unsubscribe users to/from email notifications (go to Users->All Users and see bulk actions)

For your External users:

* The lists will contain the terms which, when matched to the document, will then send the notification to all users in the list

* Matching rule can be set to either match one term or all terms on the list.

Initial release was based on plugin "WP JV Custom Email Settings" by Janos Ver. 


== Installation ==

1. Download email-notice-wp-document-revisions
2. Extract to `/wp-content/plugins/email-notice-wp-document-revisions` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

If the plugin WP Document Revisions is not activated, this plugin will output an error message and none of its functionality  will be available.

== Upgrade Notice ==

= 4.0.0 =
Breaking: Document External Lists, the email logs and the External List part of the Document screen now need the new read_doc_ext_lists capability. Roles and users with edit_doc_ext_lists get it on upgrade; give it to anyone else who needs it.

== Migration Notice ==

= Upgrading to 4.0.0 =

* The email logs (Document Emails menu) and the External List part of the Document metabox now need the new read_doc_ext_lists capability (previously edit_documents).
* On upgrade, read_doc_ext_lists is given to every role and user that has edit_doc_ext_lists. Give it to any other role or user that needs this access, e.g. Authors who send External List emails.
* Sending External List emails needs read_doc_ext_lists as well as the right to edit the Document. Only users with edit_doc_ext_lists can choose which matched Lists to send to.
* If you chose Auto for "Internal Notice mode" (Settings > Writing), choose it again and save: since 3.2 it was stored as Manual.

== Frequently Asked Questions ==

= How does the plugin work? =

When enabled a metabox will be added to the Document Admin screen. This can contain two buttons to send emails. One will be for Internal users (although you can filter this capability out) and the other for Document Lists. The Document List define a set of email names and addresses that will be sent the notifications; together with a list of Taxonomy matching rules.

Significant information is held in the Document and Document External List Help pulldowns found on the top right hand corner of their Admin screens.

Please make use of them to see how to use them and where you can see the data in context.

Also additional information is held in the [usage documentation](../docs/usage.md)

= Where can I define new "Email From" name and from "Email Address"? =

Go to Settings -> General -> "Document Email Settings" section.

= How does this work? What is the difference between Auto/Manual notification mode? =
In Settings -> Writing -> "Document Email Settings - Notifications" section you can select to send notifications automatically or manually. If it is set to Auto whenever you publish a document notification emails will be sent out automatically for users with access to the document and opted to receive such notifications. In case of Manual you will be provided a "Send/Re-send notification email(s)" button to notify your readers. 

= I'm not getting any notification emails, what is wrong? =
In order to receive notification emails users have to go to their profile and check “Notify me by email when a new post is published” checkbox in "Document Email Settings" section (or an admin has to do it for them).

It will check that the WordPress User can read the Document before sending a mail, it may be just that you cannot access the document.

= I would like the document attached to the email (WordPress user)
Even though the user should have access to the document via the front-end, as well, the user can choose to have the document attached to the email (subject to size limitations) by checking "Also send me the document as an attachment by email when a new document is published" checkbox in "Document Email Settings" section (or an admin has to do it for them).

= I have a site that requires a log-on but would like to send the External Users a copy of the document attached to the notification email.
This is a convenience when the document has public read access, but is necessary if the site requires users to log on to access a document.

In Settings -> Writing -> "Document Email Settings - Notifications" section, there is a checkbox to attach the Document to the email.

Individual lists can be defined to attach the document or not.

= What kind of tags I can use and for what? =
You can customize notification email template (both subject and content) In the content you can use any standard html tags as well on top of the following ones:

Details are given in the [tags documentation](../docs/tags.md).

= Where are the logs? =
Users with the read_doc_ext_lists capability can see the logs of notifications at Document Emails -> Internal User Email Log (for WordPress users) or Document Emails -> External User Email Log, where you can view which users were notified about which post and if email sending was successful or not (please note that bounce messages are not processed). 

= I've multisite. Does this plugin work for me as well? =
Yes, it does, but in that case each site will have its own log in Document Emails.

= Are there filters to configure plugin operations =
Yes, there are a number of these.

These are listed and described at [filters.md](../docs/filters.md).

== Changelog ==

= 4.0.0 =
Release date: (in development)

Breaking changes: see Upgrading to 4.0.0 under Migration Notice.

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
* DEV: Script and style sources moved to src/js and src/css; minified copies are in build/js and build/css, made with `npm run build` (terser, clean-css) or the Build assets workflow. Asset versions are the file times, so browsers pick up rebuilt files.
* DEV: PHPUnit test suite in tests/ (see tests/test_suite.md); runs locally with script/install-wp-tests and in CI.
* DEV: Tests added for the internal log, bulk subscribe/unsubscribe, External List settings and the fixed bugs. A duplicate loader of the bulk action class with a wrong path is removed.
* DEV: AJAX handlers reply with wp_send_json() instead of echo and die, so they can be tested.
* DEV: Plugin header version set to match the readme Stable tag.
* DEV: PHPCompatibility now checks against PHP 8.0+, the plugin's minimum.
* DEV: CI skips translation-only changes and runs with read-only permissions.
* DEV: Generate POT workflow restricted to main and given the permissions it needs to open its PR.

= 3.2.2 =
Release date: August 26, 2026

 * NEW: Test up to WP 7.1 and PHP 8.3 with WP Coding Standards 3.4 and Plugin Check 2.1
 
= 3.2.1 =
Release date: January 19, 2026

 * NEW: Test up to WP 6.9 and PHP 8.2 with WP Coding Standards 3.3 and Plugin Check 1.8
 
= 3.2 =
Release date: April 18, 2025

* NEW: GitHub documentation included in plugin and explicitly linked.
* NEW: Text domain changed as plugin_check plugin complains.
* FIX: Settings sanitized on update.
* FIX: Notification-specific text message metabox reviewed.
* FIX: Filter wpdr_en_ext_attach_notice withdrawn as effectively replaced by global setting.
* FIX: Consistent use of word email (rather than e-mail)
* FIX: Review uninstall process

The full change log is in the [changelog](../docs/changelog.md).

