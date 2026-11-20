<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\I18n\Locales;
use Modules\Core\Storage\Picker\PickerField;

/**
 * What the fields of a section need to validate a posted value: the user (images they may
 * choose), the image keys the section already uses (kept even if the user could no longer
 * choose them), the module's image folder and the site's languages.
 */
final class FieldContext
{
    /** Largest image, in bytes (the global `Core.uploadMaxSize` still wins when smaller). */
    public const IMAGE_MAX_SIZE = 5 * 1024 * 1024;

    /**
     * @param list<string> $locales    the site's languages (the default one first or not)
     * @param list<string> $keptImages image keys the section already had
     */
    public function __construct(
        public readonly ?User $user,
        public readonly string $module,
        public readonly array $locales,
        public readonly string $defaultLocale,
        public readonly array $keptImages = [],
    ) {
    }

    /**
     * The context of the signed-in user for a module's sections, in the site's languages.
     *
     * @param list<string> $keptImages
     */
    public static function current(string $module, array $keptImages = []): self
    {
        $user = auth()->user();

        return new self($user instanceof User ? $user : null, $module, Locales::available(), Locales::default(), $keptImages);
    }

    /**
     * The image picker of the module's sections: upload or choose an image on the public disk,
     * folder `modules/<module>`, also inside the public shared folders the user may view.
     */
    public function imagePicker(string $name): PickerField
    {
        $folder = 'modules/' . strtolower($this->module);

        return PickerField::make($name)->mode('both')->disk('public')->folder($folder)
            ->types(PickerField::IMAGES)->maxSize(self::IMAGE_MAX_SIZE)->maxFiles(1)->browseRoot([$folder, 'shared']);
    }

    /** Whether the user may use this image key (kept, uploaded through the picker or chosen). */
    public function acceptsImage(string $key): bool
    {
        if (in_array($key, $this->keptImages, true)) {
            return true;
        }

        return $this->user instanceof User && $this->imagePicker('image')->accepts($this->user, $key);
    }

    public function localeName(string $locale): string
    {
        return Locales::name($locale);
    }
}
