# Email Notice WP Document Revisions

This plugin is an add-on to WP Document Revisions. This supports sending out email notifications about newly published documents to users who are interested to receive notifications either automatically (on publish) or manually.

It supports two different methods of holding the address lists:
1. Using the WordPress users of a site (Internal Users).
2. Making use of a custom post type called "Document External List" (External Users) .

The custom post type (with slug doc_ext_list) contains four sets of information:
1. A list of email names and addresses
2. Some taxonomy terms
3. A taxonomy match rule - either to match on all the terms or on any of them.
4. A pause attribute that, if set, excludes the list from being matched. 

When the user selects the Send External button on the Document Edit screen, then an email is sent to all users on published doc_ext_list records whose taxonomy terms match those on the Document.  

The Lists that mach the Document are displayed below that button and are marked as checked. If the user is able to edit the Lists, then they are able to deselect them before sending the notifications as they could change the List to no send them. 

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

* Users can opt-out if they don't want to receive emails (they can choose to get all/nothing)

* Bulk subscribe/unsubscribe users to/from email notifications (go to Users->All Users and see bulk actions)

The initial release sending emails to Internal users only was based on plugin "WP JV Custom Email Settings" by Janos Ver. 


