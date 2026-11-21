<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\I18n\Locales;
use Modules\Core\Sections\Fields\Field;

/**
 * A type of section of a page built from sections (D-45-01; docs/sections.md): a hero banner,
 * quick links, cards… Modules declare their types in the manifest key `$sectionTypes`; the
 * `SectionRegistry` offers those of the enabled modules.
 *
 * A type gives:
 *
 * - `key()`: `<module>.<name>` (lower case; the module part is the declaring module's name);
 * - `label()`, `description()`, `icon()` and `thumbnail()` (an SVG schematic in the module's
 *   assets) for the editor's gallery;
 * - `schema()`: the fields of its form (`Fields\*`), validated on the server;
 * - `styles()`: the variants it offers (a select of fixed values; no CSS is ever typed);
 * - `render()`: its HTML through a theme view, `Views/sections/<name>.php` of the module by
 *   default, overridable in `themes/<slug>/Views/overrides/<Module>/sections/<name>.php`.
 *
 * The common options of every section (background, spacing, width, anchor) and the `<section>`
 * that wraps it belong to the Core (`SectionOptions`, `SectionRenderer`).
 */
abstract class SectionType
{
    /** "<module>.<name>": lower case letters and digits, `-` or `_` inside the name. */
    public const KEY_PATTERN = '/^[a-z][a-z0-9]{0,49}\.[a-z][a-z0-9_-]{0,49}$/';

    /** @var array<string, Field>|null */
    private ?array $fields = null;

    abstract public function key(): string;

    /** Translated name shown in the editor. */
    abstract public function label(): string;

    /**
     * The fields of the form, in order.
     *
     * @return list<Field>
     */
    abstract protected function schema(): array;

    /** One translated sentence for the gallery. */
    public function description(): string
    {
        return '';
    }

    /** A Bootstrap Icons class for the editor's list. */
    public function icon(): string
    {
        return 'bi bi-square';
    }

    /** The URL of a thumbnail (an SVG schematic), or null. */
    public function thumbnail(): ?string
    {
        return null;
    }

    /**
     * Variants offered by the type: key => label (language key). The first one is the default.
     *
     * @return array<string, string>
     */
    public function styles(): array
    {
        return [];
    }

    /**
     * Backgrounds the type offers (a subset of `SectionOptions::BACKGROUNDS`).
     *
     * @return list<string>
     */
    public function backgrounds(): array
    {
        return SectionOptions::BACKGROUNDS;
    }

    /**
     * Defaults of the common options for a new section of this type.
     *
     * @return array<string, string>
     */
    public function defaultOptions(): array
    {
        return [];
    }

    /**
     * The fields by name.
     *
     * @return array<string, Field>
     */
    final public function fields(): array
    {
        if ($this->fields === null) {
            $this->fields = [];

            foreach ($this->schema() as $field) {
                $this->fields[$field->name] = $field;
            }
        }

        return $this->fields;
    }

    /** The module part of the key (`pages`). */
    final public function module(): string
    {
        return (string) strstr($this->key(), '.', true);
    }

    /** The name part of the key (`hero`). */
    final public function name(): string
    {
        return substr((string) strstr($this->key(), '.'), 1);
    }

    /**
     * The theme view of the type: `<Module namespace>\Views\sections\<name>` (the class's own
     * module), overridable by themes.
     */
    public function view(): string
    {
        $class     = static::class;
        $namespace = str_contains($class, '\\Sections\\') ? substr($class, 0, (int) strpos($class, '\\Sections\\')) : substr($class, 0, (int) strrpos($class, '\\'));

        return $namespace . '\\Views\\sections\\' . $this->name();
    }

    /**
     * The values of a section in a language (the default language filling the gaps).
     *
     * @return array<string, mixed> field => resolved value
     */
    public function resolve(SectionData $section, string $locale, ?string $default = null): array
    {
        $default ??= Locales::default();
        $values = [];

        foreach ($this->fields() as $name => $field) {
            $texts = [];

            foreach ($section->texts as $code => $parts) {
                if (array_key_exists($name, $parts)) {
                    $texts[(string) $code] = $parts[$name];
                }
            }

            $values[$name] = $field->resolve($section->values[$name] ?? null, $texts, $locale, $default);
        }

        return $values;
    }

    /**
     * Rules of the whole section that its fields cannot check alone (e.g. an embed's host must be
     * allowed by a setting). Called by `SectionForm::validate()` after the fields.
     *
     * @throws SectionValidationException
     */
    public function check(SectionData $section, FieldContext $context): void
    {
    }

    /**
     * Problems of a saved section that the editor must see (in the section list, the form and the
     * preview), e.g. an embed whose host was later removed from the allowed list. The public page
     * shows none of them: a type renders nothing it cannot show.
     *
     * @return list<array{message: string, link?: array{url: string, label: string}}>
     */
    public function warnings(SectionData $section, ?User $user = null): array
    {
        return [];
    }

    /**
     * Sources a rendered section needs in the page's Content-Security-Policy (directive =>
     * origins), added for that response only (`SectionRenderer`; e.g. an embed's `frame-src`).
     *
     * @return array<string, list<string>>
     */
    public function cspSources(SectionData $section): array
    {
        return [];
    }

    /**
     * The HTML of a section (without the `<section>` that wraps it).
     */
    public function render(SectionData $section, RenderContext $context): string
    {
        $options = SectionOptions::normalise($section->options, $this);

        return theme_view($this->view(), [
            'section' => $section,
            'values'  => $this->resolve($section, $context->locale, $context->defaultLocale),
            'options' => $options,
            'style'   => $options['style'],
            'context' => $context,
            'type'    => $this,
        ]);
    }

    /**
     * A short plain-text summary for the editor's list: the first text found.
     */
    public function summary(SectionData $section, string $locale): string
    {
        $values = $this->resolve($section, $locale);

        foreach ($this->fields() as $name => $field) {
            $text = $field->summary($values[$name] ?? null);

            if ($text !== '') {
                return mb_strimwidth($text, 0, 90, '…');
            }
        }

        return '';
    }

    /**
     * Image keys a section uses (kept when it is saved again).
     *
     * @return list<string>
     */
    public function fileKeys(SectionData $section): array
    {
        $keys = [];

        foreach ($this->fields() as $name => $field) {
            array_push($keys, ...$field->fileKeys($section->values[$name] ?? null));
        }

        $background = $section->options['background_image'] ?? '';

        if (is_string($background) && $background !== '') {
            $keys[] = $background;
        }

        return array_values(array_unique($keys));
    }
}
