<?php

declare(strict_types=1);

namespace Modules\News\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Text\Slugger;
use Modules\News\Storage\CoverUploads;

/**
 * Categories of the posts (`backend/news/categories`, resource `news-categories`): translated
 * name and description (the site's default language required), a unique slug for the public URL
 * `news/category/{slug}`, order and an active flag. Deleting a category leaves its posts without
 * category (foreign key `SET NULL`). An optional **banner** (session 46: an image of the file picker,
 * public disk, folder `modules/news`) is shown as a hero on the category's page and its articles.
 */
final class Categories extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('news-categories');

        $crud->setTable('news_categories')
            ->setSubject(lang('News.category'), lang('News.categories'))
            ->setTranslations('news_category_translations', 'category_id', 'locale', ['name', 'description'], ContentTranslations::locales(), ContentTranslations::crudOptions([
                'types' => ['name' => 'text', 'description' => 'textarea'],
                'rules' => ['name' => 'required|max_length[120]', 'description' => 'permit_empty|max_length[255]'],
            ]))
            ->fieldType('active', 'boolean')
            ->callbackField('banner', static fn ($value, ?array $row, array $field): string => CoverUploads::renderPicker('banner', $value, $field))
            ->callbackColumn('banner', static fn ($value): string => Posts::coverPreview($value))
            ->fieldType('position', 'number', ['attrs' => ['min' => 0, 'max' => 9999]])
            ->columns(['name', 'slug', 'position', 'active'])
            ->fields(['name', 'description', 'slug', 'banner', 'position', 'active'])
            ->showFields(['name', 'description', 'slug', 'banner', 'position', 'active', 'created_at', 'updated_at'])
            ->searchFields(['name', 'slug'])
            ->filters(['active'])
            ->setRules([
                'slug'     => Slugger::rules(120),
                'banner'   => 'permit_empty|max_length[32]',
                'position' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
            ])
            ->setDefault('active', 1)
            ->setDefault('position', 0)
            ->setHelp('banner', lang('News.help.banner'))
            ->setHelp('slug', lang('News.help.categorySlug', [ContentTranslations::defaultName()]))
            ->displayAs([
                'name'        => lang('News.fields.name'),
                'description' => lang('News.fields.description'),
                'slug'        => lang('News.fields.slug'),
                'banner'      => lang('News.fields.banner'),
                'position'    => lang('News.fields.position'),
                'active'      => lang('News.fields.active'),
                'created_at'  => lang('News.fields.created_at'),
                'updated_at'  => lang('News.fields.updated_at'),
            ])
            ->beforeInsert(static fn (array $data): array|string => self::withSlug($data, $crud->postedTranslations(), null))
            ->beforeUpdate(static fn (array $data, string $id): array|string => self::withSlug($data, $crud->postedTranslations(), (int) $id))
            ->defaultOrder('position', 'asc');

        return $this->renderCrud($crud);
    }

    /**
     * @param array<string, mixed>                 $data
     * @param array<string, array<string, string>> $translations
     *
     * @return array<string, mixed>|string an error message when the banner may not be used
     */
    private static function withSlug(array $data, array $translations, ?int $id): array|string
    {
        // The banner: kept, uploaded through the picker or chosen inside its folders (forged keys refused).
        if (array_key_exists('banner', $data)) {
            $old            = $id === null ? null : db_connect()->table('news_categories')->select('banner')->where('id', $id)->get()->getRowArray();
            $current        = is_string($old['banner'] ?? null) ? $old['banner'] : null;
            $data['banner'] = is_string($data['banner']) && trim($data['banner']) !== '' ? trim($data['banner']) : null;

            if (! CoverUploads::accepts('banner', $data['banner'], $current)) {
                return lang('Core.picker.errors.value');
            }
        }

        $slug = trim((string) ($data['slug'] ?? ''));

        if ($id === null || array_key_exists('slug', $data)) {
            $data['slug'] = Slugger::unique('news_categories', $slug !== '' ? $slug : ContentTranslations::defaultText($translations, 'name'), $id, fallback: 'category');
        }

        $data['position'] = (int) ($data['position'] ?? 0);

        return $data;
    }
}
