# Pages

Content pages of the public site (specification "Modules" › "Pages"; decisions D-20-01 …
D-20-07). Module: `modules/Pages` (see its `CLAUDE.md`).

## What an author does

`backend/pages` (menu **Pages**) lists the pages as a tree (children indented under their parent,
siblings by **Order**), with their address, status, visibility and dates; filters by status and
visibility, search by title and slug (a search or filter shows a flat list). The page chosen as
home page carries a **Home** badge.

The form has one tab per site language (the site's default language required, `docs/i18n.md`): **title**, **slug**, **summary**, **text**
(the rich-text editor), **meta title**, **meta description** and **canonical URL**; then the
**parent page**, **order**, **layout** (article or sections, see below), **template**, **visibility** and **groups**, **publication date**,
**hide from search engines** and the **featured image**.

| Operation | Permission | What it does |
|---|---|---|
| Publish / Unpublish (also bulk) | `pages.publish` | Draft → published, or scheduled when the publication date is in the future; back to draft |
| Preview (`GET backend/pages/preview/{id}`) | `pages.show` | The page with its template, whatever its status or visibility, `noindex` |
| Revisions (`GET backend/pages/revisions/{id}`) | `pages.show` | Who saved, when, and the changes of each revision (before / after) |
| Restore a revision (`POST backend/pages/restorerevision/{id}`) | `pages.edit` | Texts, groups, template, visibility, noindex and image go back to that revision |
| Duplicate (`POST backend/pages/duplicate/{id}`) | `pages.add` | A draft copy under the same parent ("(còpia)", slug `…-copia`), sections included |
| Sections (`GET backend/pages/{id}/sections`) | `pages.edit` | The section editor of the page (below) |

New pages are drafts by the user who creates them. Writes and POST operations are written to the
activity log by the Core (`AuditedCrud`; labels `Pages.activity.*`).

## Permissions (D-20-01)

One resource named `pages`: `pages.{list,show,add,edit,delete,export,trash,restore,purge}` and
`pages.publish`. Default grants: admin `pages.*`; editor `pages.{list,show,add,edit,publish}`.

## Slugs and addresses

- A page's address is its ancestors' slugs and its own: `/qui-som/equip`.
- Slugs are per language: lower-case letters, digits and hyphens, at most 180 characters. An
  empty slug is made from the title of that language. A slug is **unique among the pages with
  the same parent in the same language** (trashed pages count: they can be restored); a typed
  slug that clashes is refused, a generated one gets `-2`, `-3`…
- A root page cannot take the first segment of another route (`news`, `login`, `install`…) or
  `api`, `assets`, `backend`, `themes`, `uploads`, `vendor`, `writable`.
- Public URLs carry the language (D-37-02, `docs/i18n.md` "Language in the URL"): `/qui-som` in
  the default language, `/es/quienes-somos` in Spanish, each level with the slug of the URL's
  language. A path is resolved with the slugs of any language (the URL's first, then the default
  one); when another language's slug was used, the page redirects (301) to its path in the URL's
  language. The texts are in the URL's language with the fallback to the default language. The
  language switch and the `hreflang` links use each language's own slugs; the sitemap lists every
  translation.
- The public route is a catch-all with the lowest priority (`Config\Routing::$prioritize`):
  every other route wins, and `backend/…` / `api/…` are never pages (D-20-04).

## Statuses and visibility

| Status | Shown on the site |
|---|---|
| `draft` | never |
| `scheduled` | once its publication date has passed (no task needed) |
| `published` | yes |

| Visibility | Who reads it |
|---|---|
| `public` | everyone; listed in the sitemap unless "hide from search engines" |
| `private` | signed-in users |
| `groups` | members of the chosen groups (`page_groups`) |

Guests opening a private page are sent to the login and back; a signed-in user without access
gets the 404. Non-public pages carry `noindex` and `Cache-Control: private, no-store`. Trashed
pages and their children are a 404 (D-20-02). The rule lives in `Services/PageVisibility.php`
(the Core's `PublishWorkflow` and `Audience`).

## Rich text

The text is edited with the Core's rich-text editor (`core/forms/editor`, Quill 2; see
`docs/frontend.md`) and sanitised with HTMLPurifier (`Modules\Core\Security\HtmlSanitizer`)
before it is stored and again when it is shown: no scripts, handlers, styles or iframes;
alignment is kept as the classes `text-center`, `text-right`, `text-justify`.

## Templates

The public theme declares its page templates (`$pageTemplates`, `docs/themes.md`); the default
theme offers:

| Key | Layout |
|---|---|
| `default` | the article in a readable column |
| `wide` | the article over the whole content width |
| `sidebar` | the article and the readable subpages beside it |

A template is the view `Modules\Pages\Views\templates\<key>`; a theme overrides it (or adds a
new key) in `themes/<slug>/Views/overrides/Pages/templates/<key>.php`. An unknown template falls
back to `default`. All templates include `Modules\Pages\Views\partials\article`.

## Pages built from sections

A page's **layout** (D-45-01) is **Article** (the page shows its text, as above) or **Sections**:
an ordered list of sections edited with forms, shown over the whole width of the public layout.
Switching keeps the other content (the text stays while the page uses sections, and the other
way round).

**For authors.** Row operation **Sections** (or `backend/pages/<id>/sections`):

- the notice at the top says what the page shows and switches the layout ("Use sections" /
  "Show the text instead");
- **Add section** opens a gallery of the section types with a picture and a sentence each;
  choose one, fill in its form and save;
- every section of the list can be **edited**, **duplicated**, **hidden** (kept, not shown) or
  **deleted**; drag it by its handle, or focus the handle and use the up and down arrow keys, then
  **Save the order** (without JavaScript the up/down buttons move one place per click);
- the form of a section has the language buttons ("Language of the texts") to fill in the texts
  of each language (the default language required, the others fall back to it), the section's
  fields (texts, images with their alternative text or "decorative", links to a page or an
  address, icons chosen in a searchable grid, lists of items that can be added, removed and
  reordered) and its appearance (style, background from the theme's palette or an image, spacing,
  width) and an optional anchor;
- **Preview** shows the page as it is now, whatever its status; every change records a
  **revision** of the page (sections included), and restoring a revision brings its sections back.

The Pages module offers six types: **Hero banner** (background image with an overlay colour,
title, text, two buttons, side image, height, alignment, straight or wave bottom edge), **Quick
links** (icon or image + label + link; styles icons, tiles and band; 2–6 columns), **Cards**
(heading, introduction, cards with image, title, text and button; 2–4 columns), **Image and
text** (image left or right, title, rich text, button), **Call to action** (title, text, button)
and **Text** (rich text with an optional heading). Other modules add more
([`sections.md`](sections.md)).

**For developers.** Routes (`modules/Pages/Config/Routes.php`, before the CRUD's catch-all,
filter `can:pages.edit`; the controller `Controllers/Sections.php` also checks the CRUD's
resolver and record rules for the page, a trashed page or another page's section answers 404):

| Route | What |
|---|---|
| `GET backend/pages/<id>/sections` | the list |
| `GET …/sections/add` | the gallery |
| `GET …/sections/new/<type>`, `GET …/sections/<section>` | the form (new / edit) |
| `POST …/sections/save` | save (`id`, `type`, `f[...]`, `o[...]`, `hidden`); a validation error answers 422 with the form |
| `POST …/sections/order` | `order` (JSON list of every section id of the page) or `move` (`<id>:up|down`) |
| `POST …/sections/<section>/duplicate`, `…/hide` (`hidden` 1/0), `…/delete` | the row operations |
| `POST …/sections/layout` | `layout` = `article` \| `sections` |

Every change records a page revision and an activity entry (`section_added`, `section_edited`,
`sections_reordered`, `section_duplicated`, `section_hidden`, `section_shown`, `section_deleted`,
`layout_changed`; a refusal by the record rules is a security entry `sections_denied`). Storage:
`Services/PageSections.php`; rendering: `PageRenderer` uses the template `templates/sections`
(full width) with the Core's `SectionRenderer`; the type views are `Views/sections/<name>.php`.

## Home page

Settings › Pages › **Home page** (`Pages.homePage`): the page to show at `/`, chosen from a list
of the pages (the id is stored, so renaming a slug keeps it; D-20-01). "The theme's home", a
missing, trashed or unpublished page, or one the visitor may not read: the theme's home. The page's
own path redirects to `/`. Implemented through the Core's `homePage` event (D-20-04).

## Revisions

Every save (and a create, a restore, a duplicate, a change in the section editor) records a
revision in `page_revisions`: the user, the date, the changed fields and a JSON snapshot of the
page, its texts, its groups and its sections (with their texts in every language). A
save that changes nothing adds none. A restore keeps the parent, order, status and publication
date (D-20-06).

## SEO and the sitemap

- `<title>`: the meta title, else the title; description: the meta description, else the summary;
  canonical: the canonical URL field, else the page's address (`/` for the home page).
- `GET sitemap.xml` (rate limit `feeds`): `/` and every published, public page not hidden from
  search engines, once per distinct address; a language with its own canonical URL is left out.

## Menus

`Menu/PageLink.php` offers the link type "Page" (key `pages.page`, value = page id) to the Menus
module (session 21): the URL of a published page, `/` for the home page, null otherwise.

## Tables

`pages` (with `layout`, added by a later migration), `page_translations` (title, slug, summary,
body, meta title, meta description, canonical per language), `page_groups`, `page_revisions`,
`page_sections` (page, position, type, options and non-translated values as JSON, hidden) and
`page_section_translations` (section, locale, translated values as JSON) (one migration each).
Section images are files of the same folder. Featured images
are files of the Storage service (public disk, folder `modules/pages`, backed up through
`$backup`).

## Try it

```bash
php spark modules:demo Pages     # after Auth's demo data
php spark serve
```

`backend/pages` as `admin@example.com` or `editor@example.com`; then `/portada-seccions` (a page
built from sections; its editor: row operation **Sections**), `/qui-som`,
`/qui-som/equip`, `/intranet` (login), `/intranet/guia-estil` (group `editor`), `/sitemap.xml`;
choose "Inici" in Settings › Pages › Home page and open `/`.
