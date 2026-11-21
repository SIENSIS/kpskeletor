<?php

declare(strict_types=1);

namespace Modules\Files\Sections;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Files\Services\FolderAcl;
use Modules\Files\Services\Rights;
use Modules\Files\Services\Spaces;

/**
 * The folder of a gallery: a shared folder (`shared/…`) of either disk, stored as
 * `<disk>:<path>`. The form offers only the folders the signed-in editor may view (folder ACL);
 * the section checks it again on save (`GallerySection::check()`) and the visitor's rights when
 * it is shown.
 */
final class FolderField extends SelectField
{
    public static function folders(): self
    {
        return self::make('folder')->optionsFrom(static function (): array {
            $options = ['' => ''];
            $acl     = new FolderAcl();

            foreach (Spaces::DISKS as $disk) {
                foreach ($acl->folders()->below($disk, 'shared') as $path => $folder) {
                    $options[$disk . ':' . $path] = lang('Files.sections.gallery.folderOption', [lang('Files.sections.gallery.disks.' . $disk), $path]);
                }
            }

            return $options;
        });
    }

    /**
     * The choices of the form: the folders the signed-in user may view.
     *
     * @return array<int|string, string>
     */
    public function getOptions(): array
    {
        $user    = auth()->user();
        $acl     = new FolderAcl();
        $options = ['' => lang('Files.sections.gallery.chooseFolder')];

        foreach (parent::getOptions() as $value => $label) {
            [$disk, $path] = array_pad(explode(':', (string) $value, 2), 2, '');

            if ($path !== '' && $user instanceof User && $acl->can($user, $disk, $path, Rights::VIEW)) {
                $options[(string) $value] = $label;
            }
        }

        return $options;
    }

    /**
     * Disk and path of a stored value, or null.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function split(mixed $value): ?array
    {
        if (! is_string($value) || ! str_contains($value, ':')) {
            return null;
        }

        [$disk, $path] = explode(':', $value, 2);

        return in_array($disk, Spaces::DISKS, true) && Spaces::of($disk, $path) === Spaces::SHARED ? [$disk, $path] : null;
    }
}
