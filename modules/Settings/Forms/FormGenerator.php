<?php

declare(strict_types=1);

namespace Modules\Settings\Forms;

/**
 * Generates the settings forms from field definitions and checks the posted values.
 *
 * - render(): one view per kind of field (`Views/fields/…`, overridable by a theme): text-like
 *   inputs, textarea (`text`, `json`, `list`), switch (`bool`) and select; grouped under
 *   headings; read-only fields are disabled.
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
            default                  => 'input',
        };

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
