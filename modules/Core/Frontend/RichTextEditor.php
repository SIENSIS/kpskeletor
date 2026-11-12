<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Storage\Picker\PickerField;

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
 *
 * "Insert image" (D-17-08): pass the module's image field, e.g.
 * `RichTextEditor::attrs(RichTextEditor::images('news'))`: upload or choose a JPG, PNG, WebP or GIF
 * on the public disk, folder `modules/<module>` (the Storage service's rules and re-encoding);
 * pasted and dropped images use the same signed upload. `HtmlSanitizer` keeps only the site's
 * own images.
 */
final class RichTextEditor
{
    public const COMPONENT = 'core/forms/editor';

    /**
     * @return array<string, string>
     */
    public static function attrs(?PickerField $images = null, ?User $user = null): array
    {
        $options = self::options();
        $user ??= auth()->user();

        if ($images !== null && $user instanceof User) {
            $options['images'] = $images->options($user);
        }

        return [
            'data-component' => self::COMPONENT,
            'data-options'   => (string) json_encode($options, JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * The image field of a module's rich text: upload or choose, images only, public disk, folder
     * `modules/<module>` (and the public shared folders the user may view).
     */
    public static function images(string $module, int $maxSize = 5 * 1024 * 1024): PickerField
    {
        $folder = 'modules/' . strtolower($module);

        return PickerField::make('image')->mode('both')->disk('public')->folder($folder)
            ->types(PickerField::IMAGES)->maxSize($maxSize)->browseRoot([$folder, 'shared']);
    }

    /**
     * URLs the component loads: the library, its stylesheet and the Core's theme adaptation.
     *
     * @return array<string, mixed>
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
