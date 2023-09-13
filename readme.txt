=== Email Notice for WP Document Revisions ===
Contributors: nwjames, janosver
Tags: administration, email, e-mail, document, automatic, user, multisite
Requires at least: 4.9
Requires PHP: 7.4
Tested up to: 6.3
Stable tag: 2.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Add-on to WP Document Revisions. Notify users about new documents published and customise your e-mail notification settings

== Description ==

This plugin is an add-on to WP Document Revisions. This supports sending out e-mail notifications about newly published documents to users who are interested to receive notifications either automatically (on publish) or manually.

By default when you send any notification from your blog (including many of the plugins as well) sender is "WordPress" and the sender e-mail address is <wordpress@yourdomain.com>. 

This plugin will enable you to 

* Replace default <wordpress@yourdomain.com> to any e-mail address

* Replace default e-mail from "WordPress" name to anything you want

* Send e-mail notifications automatically/manually to all registered users about new public documents

* Easily customize notification e-mail subject and content templates

* Re-send e-mail notifications manually as well

* Send notifications about password protected posts as well to those that can read it (password will NOT be included in notification e-mail)

* Users can opt-out if they don't want to receive e-mails (they can choose to get all/nothing)

* Bulk subscribe/unsubscribe users to/from e-mail notifications (go to Users->All Users and see bulk actions)

* Maintain e-mail log about sent e-mail notifications.

Initial release based on plugin "WP JV Custom Email Settings" by Janos Ver. 


== Installation ==

1. Download email-notice-wp-document-revisions
2. Extract to `/wp-content/plugins/email-notice-wp-document-revisions` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

== Upgrade Notice ==

No specific instructions.

== Email Notice WP Document Revisions Filters ==

These are listed and described in the [Plugin Repository - Filters](https://github.com/NeilWJames/email-notice-wp-document-revisions/blob/main/docs/filters.md).

== Frequently Asked Questions ==

= Where can I define new "Email From" name and from "Email Address"? =

Go to Settings -> General -> "Document Email Settings" section.

= How this works? What is the difference between Auto/Manual notification mode? =
In Settings -> Writing -> "Document Email Settings - Notifications" section you can select to send notifications automatically or manualy. If it is set to Auto whenever you publish a document notification e-mails will be sent out automatically for users with access to the document and opted to receive such notifications. In case of Manual you will be provided a "Send/Re-send notification email(s)" button to notify your readers. 

= I'm not getting any notification e-mails, what is wrong? =
In order to receive notification e-mails users have to go to their profile and check “Notify me by e-mail when a new post is published” checkbox in "Document Email Settings" section (or an admin has to do it for them).

= I would like the document attached to the e-mail
Even though the user has access to the document via the front-end, as well, the user can choose to have the document attached to the e-mail (subject to size limitations) by checking "Also send me the document as an attachment by e-mail when a new document is published" checkbox in "Document Email Settings" section (or an admin has to do it for them). 

= What kind of tags I can use and for what? =
You can customize notification e-mail template (both subject and content) In the content you can use any standard html tags as well on top of the following ones:

Hetails are given in [Plugin Repository - Tags](https://github.com/NeilWJames/email-notice-wp-document-revisions/blob/main/docs/tags.md)

= Where are the logs? =
Log of notifications is available at Documents -> Document Email Log, where you can view which users were notified about which post and if e-mail sending was successful or not (please note that bounce messages are not processed). 

= I've multisite. Does this plugin works for me as well? =
Yes, it does, but in that case each site will have its own log in Documents -> Document Email Log.

== Changelog ==

= 2.0 =
Release date: June 6, 2023

* NEW: Support External User Lists (of emails)
* FIX: Ensure Uninstall works

= 1.0 =
Release date: June 6, 2023

* Initial release based on recast of Janos Ver's plugin "WP JV Custom Email Settings"
