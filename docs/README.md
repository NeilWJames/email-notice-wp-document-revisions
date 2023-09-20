# Email Notice WP Document Revisions

This plugin is an add-on to WP Document Revisions. This supports sending out e-mail notifications about newly published documents to users who are interested to receive notifications either automatically (on publish) or manually.

It supports two different methods of holding the address lists:
1. Using the WordPress users of a site (Internal Users).
2. Making use of a custom post type called "Document External List" (External Users) .

The custom post type (doc_ext_list) contains three sets of information:
1. A list of e-mail names and addresses
2. Some taxonomy terms
3. A taxonomy match rule - either to match on all the terms or on any of them.

When the user selects the Send External button on the Document Edit screen, then an e-mail is sent to all users on published doc_ext_list records whose taxonomy terms match those on the Document.  

By default when you send any notification from your blog (including many of the plugins as well) sender is "WordPress" and the sender e-mail address is <wordpress@yourdomain.com>. 

This plugin will enable you to 

* Replace default <wordpress@yourdomain.com> to any e-mail address

* Replace default e-mail from "WordPress" name to anything you want

* Send e-mail notifications automatically/manually to all registered users about new public documents

* Easily customize notification e-mail subject and content templates

* Re-send e-mail notifications manually as well

* Maintain e-mail log about sent e-mail notifications.

For Internal Users, it will also 

* Send notifications about password protected posts as well to those that can read it (password will NOT be included in notification e-mail)

* Users can opt-out if they don't want to receive e-mails (they can choose to get all/nothing)

* Bulk subscribe/unsubscribe users to/from e-mail notifications (go to Users->All Users and see bulk actions)

Initial release sending e-mails to Internal users only based on plugin "WP JV Custom Email Settings" by Janos Ver. 


