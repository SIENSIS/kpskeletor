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

The Pages module stores sections (`page_sections`, `page_section_translations`; service
`Modules\Pages\Services\PageSections`) and offers the editor and the first six types: `pages.hero`,
`pages.links`, `pages.cards`, `pages.media_text`, `pages.cta`, `pages.text`.

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
