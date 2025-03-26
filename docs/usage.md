# Email Notice WP Document Revisions - Usage Notes

## Configuration

Parameters for the plugin are contained under Settings.

General contains:

- Email From
- Email Address

Writing contains:

- Internal Notice mode - whether to automatically send the mails to internal users on Publish
- Notify internal users about - which type of published posts will be sent (Public, Password protected or Private)
- Notification email subject - template to create the email subject line
- Internal Notice email content - template to generate the email content for internal users  
- External Notice email content - template to generate the email content for external users
- Also whether to always attach the document to the mail.
- Notification email repeat - template to generate a message to be included if a mail has previously for the document.

Private sites, i.e. ones requiring logon wishing to send emails to External users should attach the Document to the email since otherwise they would not be able to access it.
 
## Managing the Matching Taxonomies

The taxonomies used to match the Lists to the Document are not defined separately as they are the taxonomies defined for Documents.

However some taxonomies are not useful for matching, so it is possible to filter the list using *wpdr_en_taxonomies*.

The delivered WordPress process for a custom post type will add menu items to manage the taxonomies - including adding and removing terms.
This is not appropriate here since it is Document usage that determines the taxonomy terms, not Document List usage.

So, by default, these taxonomy management options are removed from the Document List screens. The filter *wpdr_en_remove_taxonomy_menu_items* can be used to reinstate them.

## Testing the functionality

It can be useful when testing the functionality to see what messages would be sent without actually sending them.

This can be implemented by adding this code:
	`add_filter( 'wpdr_en_no_send_email', '__return_true' );`

A trace of what would be sent is written to the logs. (The translated text is not, however, produced.)

Once the setup has been validated, the code can be removed.

## Controlling which Internal users receive emails

Users that can receive emails are determined by their roles. By default, all roles will be eligible to receive emails.

A filter *wpdr_en_roles_email* can be used to create a subset of all roles which is used for selecting the users.

A user is eligible if they have any one of the chosen roles.

If the filter is set to return no roles, i.e. an empty array, then the functionality to support emails to internal users can be switched off.

The External user functionality process can, of course be used to send emails to Internal users by defining them in these lists.

Note that while a user may ask to receive emails, they will only be sent an email if they have permission to read the Document.

## Default content texts

While the content texts (templates) contain placeholder text, you are not able to directly use this placeholder text as a basis for your own.

As an aid, they are placed here:

- Subject

New document: %title%

- Internal

Dear %recipient_name%,&lt;br/&gt;&lt;br/&gt;
A new document is published. Check it out!&lt;br/&gt;&lt;br/&gt;&lt;strong&gt;%title_with_permalink%&lt;/strong&gt;&lt;br/&gt;%words_50%%extra%%repeat%&lt;br/&gt;
&lt;small&gt;In case you do not want to receive this kind of notification you can turn it off in your &lt;a href="%s"&gt;&lt;profile</a&gt;.
&lt;br/&gt;Also go there if you wish to change whether you will receive the document as an attachment.&lt;/small&gt;

*The profile destination would need to be entered for your own site.*

- External

Dear %recipient_name%,&lt;br/&gt;&lt;br/&gt;
A new document is published. Check it out!&lt;br/&gt;&lt;br/&gt;&lt;strong&gt;%title_with_permalink%&lt;/strong&gt;&lt;br/&gt;%words_50%%extra%%repeat%&lt;br/&gt;
&lt;small&gt;In case you do not want to receive this kind of notification you can reply with the message "Unsubscribe".&lt;/small&gt;

*No functionality is provided to unsubscribe a recipient. You will need to describe your own process.*

- Repeat text

&lt;p&gt;This document has previously been sent to you %num% time(s), with the latest sent on %last_date%.&lt;/p&gt;
