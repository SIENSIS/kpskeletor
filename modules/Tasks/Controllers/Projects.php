<?php

declare(strict_types=1);

namespace Modules\Tasks\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Text\Slugger;

/**
 * Back office of the projects, `backend/tasks/projects` (resource `tasks-projects`). The name and
 * description are translated (tutorial 2: `setTranslations()` over `task_project_translations`,
 * Catalan required, Catalan fallback in the list); the slug is the public URL
 * `tasks/project/<slug>` (generated from the Catalan name when empty); `ends_on` is the deadline
 * shown in the calendar by the provider `ProjectDeadlines` (pull).
 */
final class Projects extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('tasks-projects');

        $crud->setTable('task_projects')
            ->setSubject(lang('Tasks.project'), lang('Tasks.projects'))
            ->setTranslations('task_project_translations', 'project_id', 'locale', ['name', 'description'], ContentTranslations::LOCALES, [
                'required'  => ['ca'],
                'types'     => ['name' => 'text', 'description' => 'textarea'],
                'rules'     => ['name' => 'required|max_length[120]', 'description' => 'permit_empty|max_length[2000]'],
                'fallbacks' => ['*' => ['ca']],
            ])
            ->fieldType('color', 'color')
            ->fieldType('ends_on', 'date')
            ->fieldType('active', 'boolean')
            ->columns(['name', 'color', 'ends_on', 'active'])
            ->fields(['name', 'description', 'slug', 'color', 'ends_on', 'active'])
            ->showFields(['name', 'description', 'slug', 'color', 'ends_on', 'active', 'created_at', 'updated_at'])
            ->exportColumns(['name', 'slug', 'color', 'ends_on', 'active'])
            ->searchFields(['name', 'slug'])
            ->filters(['active'])
            ->setRules([
                'slug'    => Slugger::rules(),
                'color'   => ['required', 'regex_match[/^#[0-9a-fA-F]{6}$/]'],
                'ends_on' => 'permit_empty|valid_date[Y-m-d]',
            ])
            ->setDefault('color', '#0d6efd')
            ->setDefault('active', 1)
            ->setHelp('slug', lang('Tasks.help.slug'))
            ->setHelp('ends_on', lang('Tasks.help.endsOn'))
            ->displayAs([
                'name'        => lang('Tasks.fields.name'),
                'description' => lang('Tasks.fields.description'),
                'slug'        => lang('Tasks.fields.slug'),
                'color'       => lang('Tasks.fields.color'),
                'ends_on'     => lang('Tasks.fields.ends_on'),
                'active'      => lang('Tasks.fields.active'),
                'created_at'  => lang('Tasks.fields.created_at'),
                'updated_at'  => lang('Tasks.fields.updated_at'),
            ])
            ->beforeInsert(static fn (array $data): array => self::slug($data, null, (string) ($crud->postedTranslations()['ca']['name'] ?? '')))
            ->beforeUpdate(static fn (array $data, string $id): array => self::slug($data, (int) $id, (string) ($crud->postedTranslations()['ca']['name'] ?? '')))
            ->defaultOrder('id', 'desc');

        return $this->renderCrud($crud);
    }

    /**
     * A unique slug: the one given, or one made from the Catalan name.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function slug(array $data, ?int $id, string $name): array
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($id === null || array_key_exists('slug', $data)) {
            $data['slug'] = Slugger::unique('task_projects', $slug !== '' ? $slug : $name, $id, fallback: 'project');
        }

        return $data;
    }
}
