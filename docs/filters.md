# Email Notice WP Document Revisions Filter Hooks

## Filter: wpdr_en_doc_ext_list_roles

In /includes/class-wpdr-email-notice.php

Filter the default roles that will be allowed to manage the lists.

## Filter: wpdr_en_ext_force_attach

In /includes/class-wpdr-email-notice.php

Filter to force attach a document for external users.

Special case for normally private site but where certain non-users may be sent documents.

## Filter: wpdr_en_filesize

In /includes/class-wpdr-email-notice.php

Filters whether to attach the file depending on its size and type of user.

## Filter: wpdr_en_help_array

In /includes/class-wpdr-email-notice.php

Filters the default help text for current screen.

## Filter: wpdr_en_mail_delay

In /includes/class-wpdr-email-notice.php

Filters the delay time introduced to avoid flooding the mail system.

## Filter: wpdr_en_no_send_email

In /includes/class-wpdr-email-notice.php

Filters whether to actually send the email - useful for setup testing.

## Filter: wpdr_en_register_del

In /includes/class-wpdr-email-notice.php

Filters the delivered document external list type definition prior to registering it.

## Filter: wpdr_en_remove_taxonomy_menu_items

In /includes/class-wpdr-email-notice.php

Filters whether to remove the taxonomy menu items from the list menu.

## Filter: wpdr_en_roles_email

In /includes/class-wpdr-email-notice.php

Filter all roles to determine those who can choose to receive mails.

By default, all internal users will be able to sign up to receive documents. This is implemented via a list of roles. 

This filter allows all roles to be reduced to a subset of applicable roles. If an empty array is returned then the entire internal user mailing functionality is disabled.

## Filter: wpdr_en_subject_trailing_number

In /includes/class-wpdr-email-notice.php

Filter to ensure that the mail subject does not end in a number.

Some spam filters increase the spam value if the subject ends in a number. Adding a period to the end removes this effect.

## Filter: wpdr_en_taxonomies

In /includes/class-wpdr-email-notice.php

Filter to select subset of document taxonomies available for the lists.

By default, thr Document List post type is created with the same taxonomies as Documents. Some taxonomies, such as workflow_state may be irrelevant to be used. This filter allows it to be removed.