<?php

declare(strict_types=1);

namespace Modules\News\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\BackendController;
use Modules\News\Services\Slugger;

/**
 * Categories of the posts (`backend/news/categories`, resource `news-categories`): translated
 * name and description (Catalan required), a unique slug for the public URL
 * `news/category/{slug}`, order and an active flag. Deleting a category leaves its posts without
 * category (foreign key `SET NULL`).
 */
final class Categories extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('news-categories');

        $crud->setTable('news_categories')
            ->setSubject(lang('News.category'), lang('News.categories'))
            ->setTranslations('news_category_translations', 'category_id', 'locale', ['name', 'description'], Posts::LOCALES, [
                'required'  => ['ca'],
                'types'     => ['name' => 'text', 'description' => 'textarea'],
                'rules'     => ['name' => 'required|max_length[120]', 'description' => 'permit_empty|max_length[255]'],
                'fallbacks' => ['*' => ['ca']],
            ])
            ->fieldType('active', 'boolean')
            ->fieldType('position', 'number', ['attrs' => ['min' => 0, 'max' => 9999]])
            ->columns(['name', 'slug', 'position', 'active'])
            ->fields(['name', 'description', 'slug', 'position', 'active'])
            ->showFields(['name', 'description', 'slug', 'position', 'active', 'created_at', 'updated_at'])
            ->searchFields(['name', 'slug'])
            ->filters(['active'])
            ->setRules([
                'slug'     => ['permit_empty', 'max_length[120]', 'regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'],
                'position' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
            ])
            ->setDefault('active', 1)
            ->setDefault('position', 0)
            ->setHelp('slug', lang('News.help.categorySlug'))
            ->displayAs([
                'name'        => lang('News.fields.name'),
                'description' => lang('News.fields.description'),
                'slug'        => lang('News.fields.slug'),
                'position'    => lang('News.fields.position'),
                'active'      => lang('News.fields.active'),
                'created_at'  => lang('News.fields.created_at'),
                'updated_at'  => lang('News.fields.updated_at'),
            ])
            ->beforeInsert(static fn (array $data): array => self::withSlug($data, $crud->postedTranslations(), null))
            ->beforeUpdate(static fn (array $data, string $id): array => self::withSlug($data, $crud->postedTranslations(), (int) $id))
            ->defaultOrder('position', 'asc');

        return $this->renderCrud($crud);
    }

    /**
     * @param array<string, mixed>                 $data
     * @param array<string, array<string, string>> $translations
     *
     * @return array<string, mixed>
     */
    private static function withSlug(array $data, array $translations, ?int $id): array
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($id === null || array_key_exists('slug', $data)) {
            $data['slug'] = Slugger::unique('news_categories', $slug !== '' ? $slug : (string) ($translations['ca']['name'] ?? ''), $id);
        }

        $data['position'] = (int) ($data['position'] ?? 0);

        return $data;
    }
}
