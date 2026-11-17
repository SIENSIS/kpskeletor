<?php

declare(strict_types=1);

namespace Modules\Settings\Forms;

use Modules\Core\I18n\Locales;

/**
 * Generates the settings forms from field definitions and checks the posted values.
 *
 * - render(): one view per kind of field (`Views/fields/…`, overridable by a theme): text-like
 *   inputs, textarea (`text`, `json`, `list`), switch (`bool`), select, checkboxes (`multiselect`) and one
 *   input per available locale (`translated`); grouped under headings; read-only fields are disabled.
 * - validate(): the field's rules (declared + type, Field::validationRules()) through
 *   CodeIgniter's validation, then the posted text cast to the setting's type.
 */
final class FormGenerator
{
    /**
     * @param array<string, list<Field>> $groups  heading (already translated, '' for none) => fields
     * @param array<string, mixed>       $old     posted values to show again after an error
     * @param array<string, string>      $errors  field name => message
     */
    public function render(array $groups, array $old = [], array $errors = []): string
    {
        $html = '';

        foreach ($groups as $heading => $fields) {
            $body = '';

            foreach ($fields as $field) {
                $body .= $this->field($field, $old, $errors);
            }

            $html .= view('Modules\Settings\Views\fields\group', ['heading' => (string) $heading, 'body' => $body], ['debug' => false]);
        }

        return $html;
    }

    /**
     * @param array<string, mixed>  $old
     * @param array<string, string> $errors
     */
    public function field(Field $field, array $old = [], array $errors = []): string
    {
        $view = match ($field->type) {
            'bool'                   => 'checkbox',
            'select'                 => 'select',
            'text', 'json', 'list'   => 'textarea',
            'translated'             => 'translated',
            'multiselect'            => 'multiselect',
            default                  => 'input',
        };

        if ($field->type === 'multiselect') {
            $posted = $old[$field->name] ?? null;

            return theme_view('Modules\\Settings\\Views\\fields\\multiselect', [
                'field'   => $field,
                'id'      => 'setting-' . $field->name,
                'checked' => array_key_exists($field->name, $old) ? array_map('strval', array_filter(is_array($posted) ? $posted : [], 'is_scalar')) : array_map('strval', is_array($field->value) ? $field->value : []),
                'error'   => $errors[$field->name] ?? null,
            ], ['debug' => false]);
        }

        if ($field->type === 'translated') {
            $posted = $old[$field->name] ?? null;
            $texts  = [];
            $locErr = [];

            foreach (Locales::available() as $locale) {
                $texts[$locale]  = is_array($posted) ? (is_scalar($posted[$locale] ?? null) ? (string) $posted[$locale] : '') : $field->translation($locale);
                $locErr[$locale] = $errors[$field->name . '.' . $locale] ?? null;
            }

            return theme_view('Modules\Settings\Views\fields\translated', [
                'field'  => $field,
                'id'     => 'setting-' . $field->name,
                'texts'  => $texts,
                'errors' => $locErr,
                'error'  => $errors[$field->name] ?? null,
            ], ['debug' => false]);
        }

        $value = array_key_exists($field->name, $old) && is_scalar($old[$field->name]) ? (string) $old[$field->name] : $field->formValue();

        return theme_view('Modules\Settings\Views\fields\\' . $view, [
            'field'     => $field,
            'id'        => 'setting-' . $field->name,
            'value'     => $value,
            'error'     => $errors[$field->name] ?? null,
            'inputType' => match ($field->type) {
                'int', 'float' => 'number',
                'email'        => 'email',
                'url'          => 'url',
                'color'        => 'color',
                default        => 'text',
            },
        ], ['debug' => false]);
    }

    /**
     * Validates the posted values of the editable fields.
     *
     * @param list<Field>          $fields
     * @param array<string, mixed> $post
     *
     * @return array{errors: array<string, string>, values: array<string, mixed>} values: setting => typed value
     */
    public function validate(array $fields, array $post): array
    {
        $rules = [];
        $data  = [];

        foreach ($fields as $field) {
            if ($field->readOnly) {
                continue;
            }

            $raw = $post[$field->name] ?? ($field->type === 'bool' ? '0' : '');

            if ($field->type === 'translated') {
                // One text per available locale; anything else posted is ignored.
                $raw = is_array($raw) ? $raw : [];
                $data[$field->name] = [];

                foreach (Locales::available() as $locale) {
                    $data[$field->name][$locale]       = is_scalar($raw[$locale] ?? null) ? (string) $raw[$locale] : '';
                    $rules[$field->name . '.' . $locale] = ['label' => lang($field->label) . ' (' . Locales::name($locale) . ')', 'rules' => $field->validationRules()];
                }

                continue;
            }

            if ($field->type === 'multiselect') {
                // Checkboxes post a list: only known options are kept, then the declared rules
                // check the chosen values joined with commas (e.g. `required`).
                $chosen = $field->cast(is_array($raw) ? $raw : []);
                $data[$field->name]  = implode(',', $chosen);
                $rules[$field->name] = ['label' => lang($field->label), 'rules' => $field->validationRules()];

                continue;
            }

            $raw = is_array($raw) ? '' : $raw; // a field is never an array: forged input is emptied

            $data[$field->name]  = $raw;
            $rules[$field->name] = ['label' => lang($field->label), 'rules' => $field->validationRules()];
        }

        $validation = service('validation', null, false);
        $validation->setRules($rules);

        if (! $validation->run($data)) {
            return ['errors' => $validation->getErrors(), 'values' => []];
        }

        $values = [];

        foreach ($fields as $field) {
            if (! $field->readOnly) {
                $values[$field->setting] = $field->cast($data[$field->name]);
            }
        }

        return ['errors' => [], 'values' => $values];
    }
}
