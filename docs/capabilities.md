# Email Notice WP Document Revisions - Capability and Security Model

Document External Lists hold email addresses, which are personal data, so access is deliberately limited.
Only published Lists are used for sending, so there are no separate Private or Others capabilities.

## Capabilities

| Capability | Allows |
|---|---|
| *read_doc_ext_lists* | See both email logs (**Document Emails** menu); see the External List part of the Document metabox and send External List emails for Documents the user can edit. |
| *edit_doc_ext_lists* | Create, edit and publish any External List, and its addresses; untick matched Lists before sending (other users always send to every matched List). |
| *delete_doc_ext_lists* | Delete any External List. |

*edit_doc_ext_lists* does not include *read_doc_ext_lists*: List maintainers need both (the defaults and the 4.0.0 upgrade give both).

Internal (WordPress user) notifications need no extra capability: anyone who can edit a Document can send them for it.

## Default permissions

1. *administrator*: *read_doc_ext_lists*, *edit_doc_ext_lists* and *delete_doc_ext_lists*.
1. *editor*: *read_doc_ext_lists* and *edit_doc_ext_lists*.

The defaults can be changed with the filter *wpdr_en_doc_ext_list_roles*. They are set on plugin activation, only for the named roles, and only where the role does not already have the capability, so a capability set to *false* stays *false*.

## Upgrading to 4.0.0

Before 4.0.0 anyone with *edit_documents* could see the Lists, the logs and the External List part of the metabox.

On upgrade, every role and every user that has *edit_doc_ext_lists* is given *read_doc_ext_lists*. Anyone else who needs this access (for example Authors who send External List emails) must be given *read_doc_ext_lists*, e.g. with a role editor plugin.
