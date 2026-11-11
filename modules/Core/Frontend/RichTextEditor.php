<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

/**
 * The rich-text editor of forms (docs/frontend.md, "Rich-text editor"): the attributes that turn
 * a textarea into the `core/forms/editor` component (the vendored Quill 2, BSD-3-Clause). Without
 * JavaScript the textarea stays a textarea; the server sanitises the HTML anyway
 * (`Modules\Core\Security\HtmlSanitizer`).
 *
 * kpcrudy: `$crud->fieldType('body', 'textarea', ['attrs' => RichTextEditor::attrs()])`; for a
 * field of `setTranslations()` use the type `translation` (kpcrudy's own type of translated
 * fields: another type would take the field out of the language tabs), e.g.
 * `fieldType('body', 'translation', ['attrs' => RichTextEditor::attrs()])`.
 */
final class RichTextEditor
{
    public const COMPONENT = 'core/forms/editor';

    /**
     * @return array<string, string>
     */
    public static function attrs(): array
    {
        return [
            'data-component' => self::COMPONENT,
            'data-options'   => (string) json_encode(self::options(), JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * URLs the component loads: the library, its stylesheet and the Core's theme adaptation.
     *
     * @return array{script: string, style: string, css: string}
     */
    public static function options(): array
    {
        helper('asset');

        return [
            'script' => asset('vendor::quill/quill.js'),
            'style'  => asset('vendor::quill/quill.snow.css'),
            'css'    => asset('core::css/editor.css'),
        ];
    }
}
