# Email Notice WP Document Revisions

This plugin is an add-on to WP Document Revisions. This supports sending out email notifications about newly published documents to users who are interested to receive notifications either automatically (on publish) or manually.

It supports two different methods of holding the address lists:
1. Using the WordPress users of a site (Internal Users).
2. Making use of a custom post type called "Document External List" (External Users) .

The custom post type (with slug *doc_ext_list*) contains four sets of information:
1. A list of email names and addresses
2. Some taxonomy terms
3. A taxonomy match rule - either to match on all the terms or on any of them.
4. A pause attribute that, if set, excludes the list from being matched.

When the user selects the Send External button on the Document Edit screen, then an email is sent to all users on published *Document External List* records whose taxonomy terms match those on the Document.

The Lists that match the Document are displayed below that button and are marked as checked. If the user is able to edit the Lists, because they would be able to edit the list data (to make them no longer match, i.e. choose which lists to send) then they can selectively deselect items before sending the notifications. Other users may only view the lists matched.

It is possible to include a notification-specific text message into the generated email.

By default when you send any notification from your blog (including many of the plugins as well) sender is "WordPress" and the sender email address is <wordpress@yourdomain.com>.

This plugin will enable you to

* Replace default <wordpress@yourdomain.com> to any email address

* Replace default email from "WordPress" name to anything you want

* Send email notifications automatically/manually to all registered users about new public documents

* Easily customize notification email subject and content templates

* Optionally add a notification-specific text message which is available in the logs.

* Re-send email notifications manually as well

* Maintain email log about sent email notifications.

For Internal Users, it will also

* Send notifications about password protected posts as well to those that can read it (password will NOT be included in notification email)

* Users can opt-out if they don't want to receive these emails (they can choose to get all or none of them).

^ They can also choose to have the Document attached to the email.

* Bulk subscribe/unsubscribe users to/from email notifications (go to Users->All Users and see bulk actions)

Further information is held in the various files of this directory::

1. [Usage](./usage.md) - Describes some usage options.
1. [Tags](./tags.md) - Describes the tags supported within the templates.
1. [Actions](./actions.md) - Describes the Actions provided by the plugin - currently none.
1. [Filters](./filters.md) - Describes the Filters provided by the plugin.
1. [ChangeLog](./changelog.md) - Gives the ChangeLog of the plugin.
1. [Capabilities](./capabilities.md) - Describes the capabilities provided by the plugin and their use.

The initial release sending emails to Internal users only was based on plugin "WP JV Custom Email Settings" by Janos Ver.


