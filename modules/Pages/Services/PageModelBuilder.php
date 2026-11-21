<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\I18n\Locales;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\PageModel;
use Modules\Core\Sections\PageModelContext;
use Modules\Core\Sections\PlaceholderImage;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionForm;
use Modules\Core\Sections\SectionOptions;
use Modules\Core\Sections\SectionRegistry;
use Modules\Core\Sections\SectionValidationException;
use Modules\Core\Storage\UploadRules;

/**
 * "New page from a model" (D-47-02; docs/pages.md): creates a draft page in `sections` layout
 * with a model's sections. The placeholder images are drawn (`PlaceholderImage`) and stored on
 * the public disk, folder `modules/pages`; every section goes through its type's form
 * (`SectionForm::validate()`), exactly as if an editor had filled it in, so a model can never
 * store what the editor would refuse. Sections of a type that is not offered (a disabled
 * module) are left out; a section the form refuses stops the creation (a broken model).
 */
final class PageModelBuilder
{
    /** @var list<int> ids of the stored files made for the current page */
    private array $files = [];

    /**
     * Returns the new page's id.
     *
     * @throws SectionValidationException when a section of the model is not valid
     */
    public function create(PageModel $model, ?int $userId): int
    {
        $locales  = Locales::available();
        $default  = Locales::default();
        $context  = new PageModelContext($locales, $default, function (string $name, int $width, int $height, array $from, array $to, string $kind): string {
            $stored        = service('storage')->storeContents(PlaceholderImage::png($name, $width, $height, $from, $to, $kind), $name, 'public', 'modules/pages', UploadRules::make(['png'], 4 * 1024 * 1024));
            $this->files[] = (int) $stored->id;

            return $stored->key;
        });
        $sections = $this->validate($model->sections($context), $context);

        $db  = db_connect();
        $now = Time::now()->toDateTimeString();
        $db->transException(true)->transStart();

        try {
            $db->table('pages')->insert([
                'parent_id'  => null,
                'position'   => 0,
                'template'   => 'default',
                'layout'     => 'sections',
                'status'     => 'draft',
                'visibility' => 'public',
                'noindex'    => 0,
                'author_id'  => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id    = (int) $db->insertID();
            $texts = [];

            foreach ($locales as $locale) {
                $texts[$locale] = ['title' => mb_substr($model->title($locale), 0, 190), 'slug' => ''];
            }

            foreach ((new PageSlugs())->fill($texts, null, $id) as $locale => $fields) {
                $db->table('page_translations')->insert([
                    'page_id' => $id, 'locale' => (string) $locale, 'title' => (string) $fields['title'], 'slug' => (string) $fields['slug'],
                    'summary' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '', 'canonical' => '',
                ]);
            }

            $storage = new PageSections();

            foreach ($sections as $section) {
                $storage->save($id, $section);
            }

            $db->transComplete();
        } finally {
            $db->transException(false);
        }

        (new Revisions())->record($id, $userId, 'create');

        return $id;
    }

    /**
     * The model's sections as the forms of their types accept them.
     *
     * @param list<SectionData> $sections
     *
     * @return list<SectionData>
     *
     * @throws SectionValidationException
     */
    public function validate(array $sections, PageModelContext $context): array
    {
        $registry = new SectionRegistry();
        $valid    = [];

        foreach ($sections as $section) {
            $type = $registry->get($section->type);

            if ($type === null) {
                continue;
            }

            $form    = new SectionForm($type, new FieldContext(auth()->user(), 'Pages', $context->locales, $context->defaultLocale, $context->images()));
            $options = array_merge(SectionOptions::defaults($type), $section->options);
            $valid[] = $form->validate($form->formValues($section), $options, $section->hidden);
        }

        return $valid;
    }

    /**
     * Ids of the stored files the last `create()` made.
     *
     * @return list<int>
     */
    public function files(): array
    {
        return $this->files;
    }
}
