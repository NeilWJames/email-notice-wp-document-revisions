# Email Notice WP Document Revisions - Capability and Security Model

Document External Lists contain lists of email addresses - which are, of course, personal data. As such they need to be treated carefully and have limited availability.

It is expected that only a few people will have the right to maintain or direcly access them for a site.

Furthermore only Published Document External Lists are used by Document Editors, so there is no specific use case to support Private or Others capabilities.

The *edit_documents* capability is required to read them since they are used only within the Document maintenance process.

Maintainers of Document External Lists are required to have the *edit_doc_ext_lists* capability and may maintain any Document External List.

The *delete_doc_ext_lists* is required to delete any of these lists.

## Default Permissions

The delivered software allocates very limited permissions:

1. *administrator* has *edit_doc_ext_lists* and *delete_doc_ext_lists*.

1. *editor* has *edit_doc_ext_lists*

These default roles and their capabilities are filtered by the filter *wpdr_en_doc_ext_list_roles*.

The process to set these is called on plugin activation.

It will only process the named roles and will look to see if the capability is already defined for the role before attempting to add it.

The capability will be added with *true* access, so if previously set to *false* then re-activating will not change its value.
