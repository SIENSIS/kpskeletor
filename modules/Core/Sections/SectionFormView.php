<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Frontend\RichTextEditor;
use Modules\Core\I18n\Locales;
use Modules\Core\Sections\Fields\Field;
use Modules\Core\Sections\Fields\ListField;

/**
 * Renders a section's form from its type's schema (views `Modules\Core\Views\sections\…`,
 * overridable by themes): the fields (`f[...]`), one control per language for translated ones
 * (`data-locale`, switched by the language tabs of `core/sections/form`), and the common options
 * (`o[...]`). Without JavaScript every language is shown at once.
 */
final class SectionFormView
{
    /** @var array<string, array<int|string, string>>|null */
    private ?array $choices = null;

    private ?SectionLinks $links = null;

    /**
     * @param array<string, mixed>                  $values  field => posted shape
     * @param array<string, mixed>                  $options the common options
     * @param array{path: string, message: string}|null $error   the validation error to show
     */
    public function __construct(
        private readonly SectionType $type,
        private readonly FieldContext $context,
        private readonly array $values,
        private readonly array $options,
        private readonly bool $hidden = false,
        private readonly ?array $error = null,
    ) {
    }

    /**
     * The whole form's fields (content, appearance, advanced), without the `<form>` element.
     */
    public function render(): string
    {
        $fields = '';

        foreach ($this->type->fields() as $name => $field) {
            $fields .= $this->field($field, 'f[' . $name . ']', 'sf-' . $name, $name, $this->values[$name] ?? null);
        }

        return theme_view('Modules\Core\Views\sections\form', [
            'view'        => $this,
            'type'        => $this->type,
            'fields'      => $fields,
            'options'     => SectionOptions::normalise($this->options, $this->type),
            'hidden'      => $this->hidden,
            'error'       => $this->error,
            'icons'       => $this->usesIcons() ? implode(',', array_map(static fn (string $icon): string => substr($icon, 3), Icons::all())) : '',
        ]);
    }

    public function field(Field $field, string $name, string $id, string $path, mixed $value): string
    {
        return theme_view('Modules\Core\Views\sections\field', [
            'field' => $field,
            'name'  => $name,
            'id'    => $id,
            'path'  => $path,
            'value' => $value,
            'view'  => $this,
        ]);
    }

    public function control(Field $field, string $name, string $id, string $path, mixed $value): string
    {
        $views = ['text' => 'text', 'textarea' => 'text', 'select' => 'text'];

        return theme_view('Modules\Core\Views\sections\fields\\' . ($views[$field->type()] ?? $field->type()), [
            'field' => $field,
            'name'  => $name,
            'id'    => $id,
            'path'  => $path,
            'value' => $value,
            'view'  => $this,
        ]);
    }

    /**
     * One item of a list field (the template's index is `__i__`).
     *
     * @param array<string, mixed> $item
     */
    public function listItem(ListField $field, string $name, string $id, string $path, string $index, array $item, int $number): string
    {
        return theme_view('Modules\Core\Views\sections\fields\list_item', [
            'field'  => $field,
            'name'   => $name . '[' . $index . ']',
            'id'     => $id . '-' . $index,
            'path'   => $path . '.' . $index,
            'item'   => $item,
            'number' => $number,
            'view'   => $this,
        ]);
    }

    /** The error message of a path or of something inside it, or null. */
    public function errorFor(string $path): ?string
    {
        if ($this->error === null) {
            return null;
        }

        $at = $this->error['path'];

        return $at === $path || str_starts_with($at, $path . '.') ? $this->error['message'] : null;
    }

    /**
     * The site's languages: code => name, the default one first.
     *
     * @return array<string, string>
     */
    public function locales(): array
    {
        $names   = Locales::names();
        $default = $this->context->defaultLocale;
        $ordered = isset($names[$default]) ? [$default => $names[$default]] : [];

        foreach ($this->context->locales as $locale) {
            $ordered[$locale] ??= $names[$locale] ?? $locale;
        }

        return $ordered;
    }

    public function defaultLocale(): string
    {
        return $this->context->defaultLocale;
    }

    /** The attributes of a rich-text textarea (the Core's editor with "Insert image"). */
    public function editorAttributes(): string
    {
        $attributes = RichTextEditor::attrs($this->context->imagePicker('image'), $this->context->user);
        $html       = '';

        foreach ($attributes as $key => $value) {
            $html .= ' ' . esc($key, 'attr') . '="' . esc($value, 'attr') . '"';
        }

        return $html;
    }

    /** The file picker of an image (one file of the module's images). */
    public function picker(string $name, string $id, string $key, ?string $error = null): string
    {
        $user = $this->context->user;

        if (! $user instanceof User) {
            return '';
        }

        return $this->context->imagePicker($name)->render($user, $key === '' ? null : $key, ['id' => $id . '-picker', 'error' => $error]);
    }

    /**
     * The kinds of link target: key => name.
     *
     * @return array<string, string>
     */
    public function linkTypes(): array
    {
        return $this->links()->typeLabels();
    }

    /**
     * The choices of the module link types: key => value => label.
     *
     * @return array<string, array<int|string, string>>
     */
    public function linkChoices(): array
    {
        if ($this->choices === null) {
            $this->choices = [];

            foreach ($this->links()->types() as $key => $type) {
                $this->choices[$key] = $type->options();
            }
        }

        return $this->choices;
    }

    private function links(): SectionLinks
    {
        return $this->links ??= new SectionLinks();
    }

    private function usesIcons(): bool
    {
        foreach ($this->type->fields() as $field) {
            if ($field->type() === 'icon' || ($field instanceof ListField && array_filter($field->getFields(), static fn (Field $sub): bool => $sub->type() === 'icon') !== [])) {
                return true;
            }
        }

        return false;
    }
}
