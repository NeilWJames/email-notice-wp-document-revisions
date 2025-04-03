=== Email Notice for WP Document Revisions ===
Contributors: nwjames, janosver
Tags: administration, email, e-mail, document, automatic
Requires at least: 4.9
Requires PHP: 7.4
Requires Plugins: wp-document-revisions
Tested up to: 6.7.1
Stable tag: 3.2
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

No specific instructions.

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
Logs of notifications are available at Document Emails -> Internal User Email Log (for WordPress users) or Document Emails -> External User Email Log, where you can view which users were notified about which post and if email sending was successful or not (please note that bounce messages are not processed). 

= I've multisite. Does this plugin work for me as well? =
Yes, it does, but in that case each site will have its own log in Document Emails.

= Are there filters to configure plugin operations =
Yes, there are a number of these.

These are listed and described at [filters.md](../docs/filters.md).

== Changelog ==

= 3.2 =
Release date: April 18, 2025

* NEW: GitHub documentation included in plugin and explicitly linked.
* FIX: Notification-specific text message metabox reviewed.
* FIX: Filter wpdr_en_ext_attach_notice withdrawn as effectively replaced by global setting.
* FIX: Consistent use of word email (rather than e-mail)

= 3.1 =
Release date: December 24, 2024

* NEW: Can add a notification-specific text message in the email.

= 3.0 =
Release date: August 23, 2024

* NEW: Can pause sending to selected External User Lists or individual External Users.
* NEW: Capability delete_doc_ext_lists to delete External User Lists.
* FIX: Activation scripts not being executed.

= 2.0 =
Release date: September 14, 2023

* NEW: Support External User Lists (of emails)
* FIX: Ensure Uninstall works

= 1.0 =
Release date: June 6, 2023

* Initial release based on recast of Janos Ver's plugin "WP JV Custom Email Settings"
