<?php

declare(strict_types=1);

namespace Modules\Tasks\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Kpapy\Resource;
use Modules\Core\Content\ContentTranslations;

/**
 * API resource `api/v1/tasks/projects` (tutorial 11, kpapy): the projects with the same
 * permissions as their CRUD (`setResource('tasks-projects')`), the name and description in the
 * requested language (`?lang=ca|es|en|*`, Catalan required). No trash (like the CRUD).
 */
final class Projects extends Controller
{
    public function index(): ResponseInterface
    {
        return (new Resource())
            ->setTable('task_projects')
            ->setResource('tasks-projects')
            ->setTranslations('task_project_translations', 'project_id', 'locale', ['name', 'description'], array_keys(ContentTranslations::LOCALES), ['required' => ['ca']])
            ->fieldType('active', 'bool')
            ->fieldType('ends_on', 'date')
            ->fields(['id', 'slug', 'name', 'description', 'color', 'ends_on', 'active', 'created_at', 'updated_at'])
            ->listFields(['id', 'slug', 'name', 'color', 'ends_on', 'active'])
            ->writeFields(['slug', 'name', 'description', 'color', 'ends_on', 'active'])
            ->requiredFields(['slug', 'color'])
            ->setRules([
                'slug'  => ['max_length[180]', 'regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]', 'is_unique[task_projects.slug,id,{id}]'],
                'color' => ['regex_match[/^#[0-9a-fA-F]{6}$/]'],
            ])
            ->filters(['active'])
            ->searchFields(['name', 'slug'])
            ->defaultOrder('id', 'desc')
            ->unsetTrash()
            ->describe(lang('Tasks.api.projectsSummary'), lang('Tasks.api.projectsDescription'))
            ->render();
    }
}
