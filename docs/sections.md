# Section types

Pages can be built from **sections** instead of a single rich-text body (D-45-01; authors' side in
[`pages.md`](pages.md), "Pages built from sections"). The Core keeps a registry of **section
types**; modules add their own types in the manifest, and the page editor offers every type of the
enabled modules. There are no shortcodes: editors pick a section, fill in a form and move it.

## The pieces

| Piece | Where | What |
|---|---|---|
| `SectionType` | `modules/Core/Sections/SectionType.php` | a type: key, label, description, icon, thumbnail, field schema, styles, view |
| `SectionRegistry` | `modules/Core/Sections/SectionRegistry.php` | the types of enabled modules (manifest `$sectionTypes`, stored by `modules:sync`) |
| Field types | `modules/Core/Sections/Fields/` | text, textarea, rich text, image, link, icon, select, number, boolean, list |
| `SectionForm` | `modules/Core/Sections/SectionForm.php` | validates a posted section against its schema (forged posts refused) |
| `SectionFormView` | `modules/Core/Sections/SectionFormView.php` | renders the form from the schema (views `Modules\Core\Views\sections\…`) |
| `SectionOptions` | `modules/Core/Sections/SectionOptions.php` | the common options of every section |
| `SectionRenderer` | `modules/Core/Sections/SectionRenderer.php` | renders a list of sections, each in its `<section>` |
| `SectionData` | `modules/Core/Sections/SectionData.php` | one stored section: type, values, texts per language, options, hidden |
| `SectionLinks` | `modules/Core/Sections/SectionLinks.php` | link targets, checked like menu items |

The Pages module stores sections (`page_sections`, `page_section_translations`; the sections of
reusable blocks in `page_block_sections`, `page_block_section_translations`; service
`Modules\Pages\Services\PageSections`, `PageSections::forBlocks()`) and offers the editor.

## The section types

| Key | Module | What | Notes |
|---|---|---|---|
| `pages.hero` | Pages | hero banner: image under an overlay, title, text, two buttons, side image, wave edge | session 45 |
| `pages.links` | Pages | quick links: icons, tiles or a band | session 45 |
| `pages.cards` | Pages | cards with image, title, text, button | session 45 |
| `pages.media_text` | Pages | image and text side by side | session 45 |
| `pages.cta` | Pages | call to action band | session 45 |
| `pages.text` | Pages | rich text | session 45 |
| `pages.carousel` | Pages | slides like the hero; arrows, dots, keyboard; **no rotation by default** (D-46-02) | `pages/carousel` |
| `pages.testimonials` | Pages | quotes with photo, name and role (dark band by default) | |
| `pages.logos` | Pages | logos with required alt text and optional link; grayscale option | |
| `pages.embed` | Pages | an iframe of an **allowed host** (`Pages.embedHosts`), click to load (D-46-01) | `pages/embed`; [`security.md`](security.md), "Embedded content" |
| `news.list` | News | latest N news or the full list (pages, search), one or several categories | [`news.md`](news.md), "News in pages" |
| `events.calendar` | Events | the public calendar component, categories, first view, filter | [`events.md`](events.md), "Events in pages" |
| `events.list` | Events | the next N public events as a list or cards | |
| `files.gallery` | Files | the images of a shared folder, grid or masonry, lightbox | `files/gallery`; [`files.md`](files.md), "Galleries in pages" |
| `pages.block` | Pages | a **reusable block** chosen from a list (session 47, D-47-01); replaced by the block's own sections when the page is shown | not offered inside a block |
| `contact.form` | Contact | the contact form; may offer only some recipients (none = every active one) | session 48; posts to `contact` with every spam layer ([`contact.md`](contact.md)); the signed token carries the recipients offered and the page's path |

Types that show data of their module (news, events, files) override `render()` and show exactly
what the module's public pages show to the same visitor: public news and events only, a private
folder only to visitors with its `view` right.

## Adding a section type to a module

1. A class in `modules/<Name>/Sections/` extending `Modules\Core\Sections\SectionType`:

```php
namespace Modules\Events\Sections;

use Modules\Core\Sections\Fields\NumberField;
use Modules\Core\Sections\Fields\TextField;
use Modules\Core\Sections\SectionType;

final class UpcomingSection extends SectionType
{
    public function key(): string { return 'events.upcoming'; }          // <module>.<name>
    public function label(): string { return lang('Events.sections.upcoming'); }
    public function description(): string { return lang('Events.sections.upcomingHelp'); }
    public function icon(): string { return 'bi bi-calendar-event'; }
    public function thumbnail(): ?string { helper('asset'); return asset('events::img/sections/upcoming.svg'); }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Events.sections.heading')->translated()->maxLength(120),
            NumberField::make('limit')->label('Events.sections.limit')->between(1, 12)->default(4),
        ];
    }

    public function styles(): array
    {
        return ['list' => 'Events.sections.styleList', 'cards' => 'Events.sections.styleCards'];
    }
}
```

2. Its view `modules/<Name>/Views/sections/<name>.php` (the default of `view()`: the class's
   module namespace + `Views\sections\` + the name part of the key). It receives `$values` (every
   field resolved in the reader's language), `$options`, `$style`, `$context` (a `RenderContext`:
   language, reader, preview, position) and `$type`. Escape every text with `esc()`; rich text is
   already sanitised. Section titles are `<h2>` (the page title is the page's `<h1>`). Override
   `render()` only when the view needs data of its own (e.g. the latest events), and keep the
   reader's permissions in mind: a section is shown to whoever may read the page.
3. Declare it: `public array $sectionTypes = [Sections\UpcomingSection::class];` in the manifest,
   then `php spark modules:sync`. The key's module part must be the declaring module's name in
   lower case; a type with another module's key, a repeated key or a missing class is skipped and
   logged.
4. Texts in `ca`, `es` and `en`; a thumbnail (an SVG schematic, 16:9) in `Assets/img/sections/`;
   tests of its rules and of its markup.

A type of a disabled module disappears from the gallery and its sections are not shown (they are
kept and listed as "Not available" in the editor).

## The field schema

Every field has a `name`, `label()` and `help()` (language keys), `required()` and, for texts,
`translated()` (one value per language of the site; required means required in the default
language). The server validates every posted value; anything that does not match its shape is
refused with the field's message (`SectionValidationException`), and unknown fields are refused.

| Field | Options | Stored | View receives |
|---|---|---|---|
| `TextField` | `maxLength()` (190) | text (kept as typed, escaped on output) | string |
| `TextareaField` | `maxLength()` (2000) | text with line breaks | string (`kp-pre-line` keeps the breaks) |
| `RichTextField` | `maxLength()` | HTML sanitised by `HtmlSanitizer` (again when shown) | safe HTML |
| `ImageField` | — | file key + decorative; alt text per language | `['key','url','width','height','alt','decorative']` or null |
| `LinkField` | `withoutLabel()` | target type + value; label per language | `['href','label','external']` or null when the target is gone |
| `IconField` | — | `bi-<name>` of the vendored Bootstrap Icons | string |
| `SelectField` | `options()`, `default()` | one key of the list | string |
| `NumberField` | `between()`, `default()` | int | int or null |
| `BooleanField` | — | bool | bool |
| `ListField` | `fields()`, `items(min, max)` | items (non-translated parts) + items per language | list of items, each `[field => value]` |
| `ChoicesField` | `options()` or `optionsFrom()`, `max()` | list of option keys (in the options' order) | list of keys still offered |

- **Options read when needed**: `SelectField::optionsFrom(fn (): array => …)` (and `ChoicesField`)
  reads its options once, when they are first needed: e.g. a module's categories. Their labels
  are names, not language keys. A stored key that is no longer offered is left out when shown.
- **Booleans** are off by default; `BooleanField::default(true)` ticks a new section's box (and is
  the value of sections saved before the field existed).
- **Images** that always need their alternative text (a logo): `ImageField::withoutDecorative()`.

- **Images**: chosen with the Core's file picker (upload or choose, JPG/PNG/WebP/GIF, the public
  disk, folder `modules/<module>` and the public shared folders the editor may view). A key the
  editor may not use is refused; a key the section already had is kept. Private files are never
  shown. Alternative text is required in the default language unless the image is marked
  decorative.
- **Links** (`SectionLinks`, the rules of menu items): a module link type (`$menuLinks`: a page, a
  news category…), a GET route without parameters, or an `https://` address / an internal path
  starting with `/`. `http:`, `javascript:`, `data:`, `//host`, credentials, spaces and control
  characters are refused. The address is computed when the section is shown; a target that is gone
  hides the link.
- **Lists** keep the order of the form; their translated fields are aligned by position. Lists do
  not nest.

Posted values: `f[<field>]` (`f[title][ca]`, `f[items][3][label][es]`, `f[image][key]`,
`f[image][alt][ca]`, `f[link][type]`, `f[link][value][<type>]`, `f[link][label][ca]`); the common
options `o[...]`; `hidden`.

## Rules of a whole section, warnings and CSP

Three optional methods of `SectionType`:

- `check(SectionData $section, FieldContext $context): void` — rules a field cannot check alone,
  run by `SectionForm::validate()` after the fields; throw
  `SectionValidationException::at($path, $message, $link)` (`$link`: `['url' => …, 'label' => …]`,
  shown after the message, e.g. the setting an administrator must change).
- `warnings(SectionData $section, ?User $user): list<array{message, link?}>` — problems of a saved
  section only editors see: in the section list, its form and the preview. The public page never
  shows them; the type renders nothing it cannot show (a section that renders nothing is left out
  with its `<section>`).
- `cspSources(SectionData $section): array<directive, list<origin>>` — sources the rendered section
  needs in the Content-Security-Policy, added by `SectionRenderer` **to that response only**
  (through `ContentSecurity::apply()`), e.g. an embed's `frame-src`.

## Query parameters

A section that pages or searches names its parameters after its id with
`Modules\Core\Sections\SectionQuery` (`page-<id>`, `q-<id>`), so two lists on one page never clash:
`$query->page()`, `$query->get('q')`, `$query->url(['page' => 2])` (keeps the rest of the query
string and jumps back to `#kp-s<id>`, `$query->anchor()`), and the Core's pager view
`Modules\Core\Views\sections\pager`. Section views of any module can use the Core's heading
view `Modules\Core\Views\sections\heading` (heading `<h2>` and introduction).

## Common options

Every section has, from fixed lists (never CSS): `style` (the type's `styles()`), `background`
(`none`, `light`, `dark`, `primary`, `image` + `background_image`; a type can offer fewer with
`backgrounds()`), `spacing` (`s`, `m`, `l`), `width` (`contained`, `full`) and `anchor` (a slug,
the section's id); `hidden` is a column. `defaultOptions()` sets a type's defaults.

## Rendering

`SectionRenderer` leaves out hidden sections and types that are not available, and wraps each
section:

```html
<section class="kp-section kp-section-<name> kp-bg-<background> kp-space-<spacing> kp-width-<width> kp-style-<style>"
         id="<anchor>" data-bs-theme="dark" data-section-type="<key>">
  <img class="kp-section-bg" alt="" …><span class="kp-section-overlay"></span>   <!-- background image -->
  <div class="kp-section-inner container">…the type's view…</div>             <!-- container-fluid when full -->
</section>
```

`data-bs-theme="dark"` is added on dark, primary and image backgrounds so Bootstrap's text colours
turn light. No inline styles (strict CSP): a background image is a decorative `<img>`. The page
template `sections` renders the sections in the public layout's full-width mode (`fullWidth`: no
outer container), with the page title as a visually hidden `<h1>`.

## Theme overrides

A theme overrides a section's view in `themes/<slug>/Views/overrides/<Module>/sections/<name>.php`
(e.g. `overrides/Pages/sections/hero.php`) and the form controls in
`overrides/Core/sections/fields/<type>.php`. Colours come from the theme's CSS variables
([`themes.md`](themes.md), "Section views and palette").

## Reusable blocks (D-47-01, D-47-02)

A **reusable block** is a named list of sections edited once (`backend/pages/blocks`, resource
`pages-blocks`: `list`, `add`, `edit`, `delete`; the same grants as pages without `delete` for
editors) and shown in two ways:

- **inside a page**, with the section type `pages.block` (a select of the blocks): the page
  renderer replaces it with the block's visible sections (`BlockRenderer::expand()`), so each one
  keeps its own `<section>`, options and CSP sources; changing the block changes every page that
  shows it;
- **in a theme zone** (see [`themes.md`](themes.md), "Zones"): a placement is a zone, a
  visibility rule and an order (table `page_block_zones`): `all` (every public page, also those of
  other modules), `home` (the home page), `only` (the pages chosen) or `except` (every page but
  those). Screen `backend/pages/blocks/<id>/zones`.

Blocks use the same editor, section types (without `pages.block`: no nesting), translations and
revisions as pages: tables `page_blocks` (name, `published` | `draft`), `page_block_sections`,
`page_block_section_translations`, `page_block_revisions` (D-47-02: tables of their own with the
same columns, because making `page_sections.page_id` nullable would rebuild the table on SQLite
and lose its `ON DELETE` actions). A draft block is shown nowhere; blocks follow the visitor's
language with the sections' fallback to the site's default language and the sections' hidden
state. Every change records a block revision (restore from the block's "Revisions") and an
activity entry (`block_section_*`, `block_placed`, `block_unplaced`).

Deleting a block that is used somewhere is refused by the plain delete; **Delete with its uses**
(`delete`, a confirmation) removes its placements and the block sections of the pages that show
it (a page revision each) and then the block.

| Piece | Where |
|---|---|
| `PageBlocks` (repository, uses, delete with uses) | `modules/Pages/Services/PageBlocks.php` |
| `BlockZones` (placements and rules) | `modules/Pages/Services/BlockZones.php` |
| `BlockRenderer` (expand a page's block sections, fill a zone) | `modules/Pages/Services/BlockRenderer.php` |
| `CurrentPage` (the page a request shows, set by `PageRenderer`) | `modules/Pages/Services/CurrentPage.php` |
| `BlockRevisions` | `modules/Pages/Services/BlockRevisions.php` |
| Controllers | `Blocks` (kpcrudy), `BlockSections` (the shared `SectionEditor`), `BlockPlacements` |

## Page models (D-47-02)

"New page from a model" (`backend/pages/models`, `pages.add`; a button in the pages list) creates
a **draft** page in `sections` layout from a ready-made model, with neutral placeholder texts in
every language of the site and placeholder images drawn for the skeleton
(`Modules\Core\Sections\PlaceholderImage`, stored on the public disk, folder `modules/pages`).
Every section goes through its type's form (`SectionForm::validate()`), exactly as if an editor
had filled it in; sections of a type that is not offered (a disabled module) are left out.

The Pages module ships two **generic** models (D-47-01: never the texts, images or
institution-specific content of a real organisation, no funding notices; a test checks the shipped
files for a list of forbidden words):

| Key | Name | Sections |
|---|---|---|
| `pages.school` | "Centre educatiu" | carousel, a band of icon links, illustrated quick links, latest news (`news.list`), a place for an embedded tour, logos, image and text |
| `pages.company` | "Empresa" | hero with a wavy edge and a cut-out image, icon links, cards, testimonials (dark band), image and text, call to action |

A module adds models in the manifest key `$pageModels` and a theme in `Theme.php` (`$pageModels`):
classes extending `Modules\Core\Sections\PageModel` with a key `<module or theme slug>.<name>`,
`label()`, `description()`, `thumbnail()`, `icon()`, `title($locale)` and
`sections(PageModelContext $context)`, which returns `SectionData` with texts for every language in
`$context->locales` (`$context->texts(fn ($locale) => […])`) and images from
`$context->image($name, $width, $height, $from, $to, 'gradient'|'figure'|'logo')`.
`PageModelRegistry` offers the models of the enabled modules and of the public theme chain; a
class with an invalid, foreign or repeated key is skipped and logged.
