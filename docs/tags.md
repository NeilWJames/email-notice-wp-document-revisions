# Email Notice WP Document Revisions Text Tags

The subject and content text of the email may be customised with data from the Document by adding specific tag text to the template text.

You can customize notification email template (both subject and content) In the content you can use any standard html tags as well on top of the following ones:

## %title%

This will be replaced by the Document title.

## %permalink%

This will be replaced by the URL of the post.

## %title_with_permalink%

This will be replaced by the URL and title of the post.

## %author_name%

This will be replaced by the name of the post author.

Note that this is the person that loaded the Document into the WordPress site and not necessarily the Document author.

## %excerpt%

This will be replaced by the excerpt of the Document post.

This field is used to hold the Revision Log for Documents - and so may not be appropriate for wide distribution.

## %words_n%

This will be replaced by the first *n* (must be an integer number) word(s) extracted from the post Document Description.

## %recipient_name%

This will be replaced by the display name of the user who receives the email for internal users or the user name as entered in the Document List for external users.

## %extra%

This optional text can be entered to provide a mailing-specific message when sending out the mail.

This can contain html tags, but these need to be entered as part of the text. If used, it will be preceded by a <br /> to separate it.

## %repeat%

This text will be included only if one of these emails has previously been sent for the document to the recipient. 

If used, it will be preceded by a <br /> to separate it.

The **repeat** tag can itself can use additional tags :

### %num%

This will be the number of times the Document has been previously emailed to the recipient.

### %last_date%

This is the last date that the document was emailed.

### %last_time%

This is the last date (including time element) that the document was emailed.


