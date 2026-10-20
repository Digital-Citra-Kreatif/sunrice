# AI tools for content

With the CMS, AI agents connected to the panel ([AI access](../admin/ai.md)) can write and manage content as well, as the token's user and with that user's permissions. Every change goes through the same actions as the screens: the same validation, drafts and revisions, and the activity log, marked **AI agent**.

| Tool | Permission | |
| --- | --- | --- |
| `cms_list_entries` | `cms.entries.view` | The collections; or a collection's entries (search, trash, pages) with their status in each language and which translations are outdated (`outdated_languages`). |
| `cms_get_entry` | `cms.entries.view` | An entry in a language: status (and `outdated` when the main language changed since it was translated), fields, and the draft's title, slug, data and SEO. With a collection instead of an id: a new entry's fields. |
| `cms_save_entry` | `cms.entries.create` / `.update` | Create an entry, or change a draft in a language; only what's sent changes. `"publish": true` publishes too (`cms.entries.publish`). |
| `cms_publish_entry` | `cms.entries.publish` | Publish a draft now, or schedule it. |
| `cms_unpublish_entry` | `cms.entries.publish` | Take a language off the site, keeping its draft. |
| `cms_translate_entry` | `cms.entries.create` | Add a language, copied from the main one. |
| `cms_trash_entry` | `cms.entries.delete` | Move an entry to the trash, or restore it. |
| `cms_list_terms` | `cms.terms.manage` or `cms.entries.view` | Taxonomies and their terms, for terms fields. |
| `cms_save_term` | `cms.terms.manage` | Add or rename a term, or give it another language. |
| `cms_get_globals`, `cms_save_globals` | `cms.globals.view`, `.update` | Global sets' fields and values, per language. |
| `cms_list_submissions` | `cms.submissions.view` | Forms and what visitors sent. |

Read-only tokens get only the reading tools (`cms_list_*`, `cms_get_*`). Agents are told to ask before publishing, unpublishing, trashing or changing globals, which are live as soon as they're saved.

Collections, taxonomies, global sets, menus, redirects and forms themselves are resources, reached with the panel's `list_records`, `get_record` and `save_record`. Media for media fields comes from `list_media`.
