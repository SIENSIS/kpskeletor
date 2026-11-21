<?php

declare(strict_types=1);

namespace Modules\Files\Sections;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\Fields\BooleanField;
use Modules\Core\Sections\Fields\NumberField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;
use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionOptions;
use Modules\Core\Sections\SectionType;
use Modules\Core\Sections\SectionValidationException;
use Modules\Core\Storage\StoredFile;
use Modules\Files\Services\FileRepository;
use Modules\Files\Services\FolderAcl;
use Modules\Files\Services\Rights;

/**
 * Gallery `files.gallery` (session 46; docs/files.md, "Galleries in pages"): the images (JPG, PNG,
 * WebP, GIF; never SVG) of one shared folder, as a grid or a masonry, with a lightbox
 * (`files/gallery`: keyboard, captions).
 *
 * Who sees what (D-46-04): a folder of the **public disk** is public by nature (its files are
 * served by the web server), so everyone who may read the page sees it; a folder of the
 * **private disk** is shown only to signed-in visitors with the `view` right of the folder ACL,
 * never to guests, and its images are served by `files/private/{key}`, which checks the ACL
 * again. The editor must be able to view the folder to choose it.
 */
final class GallerySection extends SectionType
{
    /** Image extensions shown (SVG is never shown: it can carry scripts). */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function key(): string
    {
        return 'files.gallery';
    }

    public function label(): string
    {
        return lang('Files.sections.gallery.label');
    }

    public function description(): string
    {
        return lang('Files.sections.gallery.description');
    }

    public function icon(): string
    {
        return 'bi bi-images';
    }

    public function thumbnail(): string
    {
        helper('asset');

        return asset('files::img/sections/gallery.svg');
    }

    public function styles(): array
    {
        return ['grid' => 'Files.sections.gallery.styles.grid', 'masonry' => 'Files.sections.gallery.styles.masonry'];
    }

    public function backgrounds(): array
    {
        return ['none', 'light', 'dark', 'primary'];
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Files.sections.fields.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('Files.sections.fields.intro')->translated()->maxLength(400),
            FolderField::folders()->label('Files.sections.fields.folder')->help('Files.sections.help.folder')->required(),
            SelectField::make('columns')->label('Files.sections.fields.columns')->options(['2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'])->default('4'),
            NumberField::make('limit')->label('Files.sections.fields.limit')->between(1, 60)->default(24),
            SelectField::make('order')->label('Files.sections.fields.order')->options([
                'name' => 'Files.sections.gallery.orders.name',
                'date' => 'Files.sections.gallery.orders.date',
            ]),
            BooleanField::make('captions')->label('Files.sections.fields.captions')->default(true)->help('Files.sections.help.captions'),
        ];
    }

    /**
     * The editor may only choose a folder they may view.
     */
    public function check(SectionData $section, FieldContext $context): void
    {
        $folder = FolderField::split($section->values['folder'] ?? null);

        if ($folder === null || ! $context->user instanceof User || ! (new FolderAcl())->can($context->user, $folder[0], $folder[1], Rights::VIEW)) {
            throw SectionValidationException::at('folder', lang('Files.sections.gallery.folderDenied'));
        }
    }

    public function warnings(SectionData $section, ?User $user = null): array
    {
        $folder = FolderField::split($section->values['folder'] ?? null);

        if ($folder === null || (new FolderAcl())->folders()->find($folder[0], $folder[1]) === null) {
            return [['message' => lang('Files.sections.gallery.folderGone')]];
        }

        return $folder[0] === 'private' ? [['message' => lang('Files.sections.gallery.privateNotice')]] : [];
    }

    /**
     * The images the visitor may see, or [] (folder gone, private folder for a guest or without
     * the `view` right).
     *
     * @return list<array{url: string, width: int|null, height: int|null, caption: string, alt: string}>
     */
    public function images(SectionData $section, ?User $user, int $limit, string $order): array
    {
        $folder = FolderField::split($section->values['folder'] ?? null);

        if ($folder === null) {
            return [];
        }

        [$disk, $path] = $folder;
        $acl           = new FolderAcl();

        if ($acl->folders()->find($disk, $path) === null) {
            return [];
        }

        if ($disk === 'private' && ($user === null || ! $acl->can($user, $disk, $path, Rights::VIEW))) {
            return [];
        }

        $rows   = (new FileRepository())->inFolder($disk, $path, ['type' => 'image', 'sort' => $order === 'date' ? 'date' : 'name']);
        $images = [];

        foreach ($rows as $row) {
            if (! in_array(strtolower((string) $row['extension']), self::EXTENSIONS, true) || (string) $row['disk'] !== $disk) {
                continue;
            }

            $file = service('storage')->find((string) $row['file_key']);

            if (! $file instanceof StoredFile) {
                continue;
            }

            $alt      = trim((string) ($row['alt'] ?? ''));
            $images[] = [
                'url'     => service('storage')->url($file),
                'width'   => $file->width,
                'height'  => $file->height,
                'alt'     => $alt,
                'caption' => $alt !== '' ? $alt : (string) pathinfo((string) $row['name'], PATHINFO_FILENAME),
            ];

            if (count($images) >= $limit) {
                break;
            }
        }

        return $images;
    }

    public function render(SectionData $section, RenderContext $context): string
    {
        $values  = $this->resolve($section, $context->locale, $context->defaultLocale);
        $options = SectionOptions::normalise($section->options, $this);
        $images  = $this->images($section, $context->user, max(1, min(60, (int) ($values['limit'] ?? 24))), (string) $values['order']);

        if ($images === []) {
            return ''; // nothing the visitor may see: the section is left out
        }

        return theme_view($this->view(), [
            'section' => $section,
            'values'  => $values,
            'options' => $options,
            'style'   => $options['style'],
            'context' => $context,
            'images'  => $images,
        ]);
    }
}
