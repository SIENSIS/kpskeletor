<?php

declare(strict_types=1);

namespace Modules\Events\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Text\Slugger;
use Modules\Events\Services\EventRepository;

/**
 * Categories of the events (`backend/events/categories`, resource `events-categories`):
 * translated name and description (Catalan required), a colour for the calendar, a unique slug
 * (the public calendar's filter `calendar?category=<slug>`), order and an active flag. Deleting a
 * category leaves its events without category (foreign key `SET NULL`).
 */
final class Categories extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('events-categories');

        $crud->setTable('event_categories')
            ->setSubject(lang('Events.category'), lang('Events.categories'))
            ->setTranslations('event_category_translations', 'category_id', 'locale', ['name', 'description'], ContentTranslations::LOCALES, [
                'required'  => ['ca'],
                'types'     => ['name' => 'text', 'description' => 'textarea'],
                'rules'     => ['name' => 'required|max_length[120]', 'description' => 'permit_empty|max_length[255]'],
                'fallbacks' => ['*' => ['ca']],
            ])
            ->fieldType('active', 'boolean')
            ->fieldType('color', 'color')
            ->fieldType('position', 'number', ['attrs' => ['min' => 0, 'max' => 9999]])
            ->columns(['name', 'color', 'slug', 'position', 'active'])
            ->fields(['name', 'description', 'color', 'slug', 'position', 'active'])
            ->showFields(['name', 'description', 'color', 'slug', 'position', 'active', 'created_at', 'updated_at'])
            ->searchFields(['name', 'slug'])
            ->filters(['active'])
            ->setRules([
                'slug'     => Slugger::rules(120),
                'color'    => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
                'position' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
            ])
            ->setDefault('active', 1)
            ->setDefault('position', 0)
            ->setDefault('color', '#0d6efd')
            ->setHelp('slug', lang('Events.help.categorySlug'))
            ->displayAs([
                'name'        => lang('Events.fields.name'),
                'description' => lang('Events.fields.description'),
                'color'       => lang('Events.fields.color'),
                'slug'        => lang('Events.fields.slug'),
                'position'    => lang('Events.fields.position'),
                'active'      => lang('Events.fields.active'),
                'created_at'  => lang('Events.fields.created_at'),
                'updated_at'  => lang('Events.fields.updated_at'),
            ])
            ->beforeInsert(static fn (array $data): array => self::normalise($data, $crud->postedTranslations(), null))
            ->beforeUpdate(static fn (array $data, string $id): array => self::normalise($data, $crud->postedTranslations(), (int) $id))
            ->defaultOrder('position', 'asc');

        return $this->renderCrud($crud);
    }

    /**
     * @param array<string, mixed>                 $data
     * @param array<string, array<string, string>> $translations
     *
     * @return array<string, mixed>
     */
    private static function normalise(array $data, array $translations, ?int $id): array
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($id === null || array_key_exists('slug', $data)) {
            $data['slug'] = Slugger::unique('event_categories', $slug !== '' ? $slug : (string) ($translations['ca']['name'] ?? ''), $id, fallback: 'category');
        }

        if (array_key_exists('color', $data)) {
            $data['color'] = EventRepository::color($data['color']);
        }

        $data['position'] = (int) ($data['position'] ?? 0);

        return $data;
    }
}
