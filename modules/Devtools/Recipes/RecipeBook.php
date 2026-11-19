<?php

declare(strict_types=1);

namespace Modules\Devtools\Recipes;

/**
 * The CRUD recipes of Devtools (`backend/devtools/recipes`, D-41-01): one card per variant of a
 * back-office CRUD or API resource, with its code copied from a real file of the skeleton
 * (`source`, relative to the project root) and the section of the docs that explains it
 * (`doc`: file under `docs/` without `.md`, `anchor`: GitHub-style slug of the heading). The
 * texts (title, what it is, when to use it) are `Devtools.recipes.<id>.*` in the language files.
 * `RecipeBookTest` checks that every source file and doc heading exists.
 */
final class RecipeBook
{
    /**
     * @return list<array{id: string, icon: string, source: string, doc: string, anchor: string, code: string}>
     */
    public function all(): array
    {
        return [
            [
                'id'     => 'plain',
                'icon'   => 'bi bi-table',
                'source' => 'modules/Tasks/Controllers/Projects.php',
                'doc'    => 'cruds',
                'anchor' => 'a-crud-in-a-module',
                'code'   => <<<'PHP'
                    // Config/Routes.php: kpcrudy's routes with the `backend` chain and `can:tasks-projects.list`
                    CrudFactory::routes($routes, 'tasks-projects', backend_path('tasks/projects'), '\Modules\Tasks\Controllers\Projects::index');

                    // Controllers/Projects.php (extends BackendController)
                    public function index(): ResponseInterface|string
                    {
                        $crud = $this->crud('tasks-projects');   // never new Crud()

                        $crud->setTable('task_projects')
                            ->setSubject(lang('Tasks.project'), lang('Tasks.projects'))
                            ->columns(['name', 'color', 'ends_on', 'active'])
                            ->fields(['name', 'description', 'slug', 'color', 'ends_on', 'active'])
                            ->searchFields(['name', 'slug'])
                            ->filters(['active']);

                        return $this->renderCrud($crud);
                    }
                    PHP,
            ],
            [
                'id'     => 'readonly',
                'icon'   => 'bi bi-eye',
                'source' => 'modules/Activity/Config/Module.php',
                'doc'    => 'cruds',
                'anchor' => 'permissions',
                'code'   => <<<'PHP'
                    // Config/Module.php: only the operations the screen offers
                    'log' => [
                        'label'  => 'Activity.res.log',
                        'type'   => 'crud',
                        'except' => ['add', 'edit', 'delete', 'import', 'trash', 'restore', 'purge'],
                    ],

                    // Controller: switch off what the resource does not declare
                    $crud->unsetAdd()->unsetEdit()->unsetDelete()->unsetImport();
                    PHP,
            ],
            [
                'id'     => 'operation',
                'icon'   => 'bi bi-lightning',
                'source' => 'modules/Tasks/Controllers/Tasks.php',
                'doc'    => 'cruds',
                'anchor' => 'permissions',
                'code'   => <<<'PHP'
                    // Config/Module.php: 'extra' => ['complete' => 'Tasks.perm.complete'] → tasks-tasks.complete
                    $crud->addOperation('complete', static function (string $id): string {
                        (new TaskRepository())->setStatus((int) $id, 'done');

                        return lang('Tasks.completedOk');
                    }, [
                        'label'      => lang('Tasks.complete'),
                        'icon'       => 'bi bi-check2-circle',
                        'confirm'    => lang('Tasks.completeConfirm'),
                        'permission' => 'complete',
                        'visible'    => static fn (?array $row): bool => $row === null || ($row['status'] ?? '') !== 'done',
                    ]);

                    // The same permission for a bulk action
                    $crud->addBulkAction('complete', lang('Tasks.complete'), $callback, ['permission' => 'complete']);
                    PHP,
            ],
            [
                'id'     => 'reauth',
                'icon'   => 'bi bi-shield-lock',
                'source' => 'modules/Backup/Config/Routes.php',
                'doc'    => 'cruds',
                'anchor' => 'a-crud-in-a-module',
                'code'   => <<<'PHP'
                    // The whole CRUD behind re-authentication: extra filters are appended
                    CrudFactory::routes($routes, 'news-posts', backend_path('news/posts'), '\Modules\News\Controllers\Posts::index', ['filter' => 'reauth']);

                    // Only the sensitive routes (modules/Backup/Config/Routes.php)
                    $routes->post('recover/(:segment)', 'Backups::restore/$1', ['filter' => ['can:backup.restore', 'reauth', 'ratelimit:forms']]);
                    PHP,
            ],
            [
                'id'     => 'recordRules',
                'icon'   => 'bi bi-person-check',
                'source' => 'modules/News/Controllers/Posts.php',
                'doc'    => 'permissions',
                'anchor' => 'own-records-record-rules',
                'code'   => <<<'PHP'
                    $me = (int) auth()->id();

                    if (! $this->can('news-posts.author')) {
                        // Authors without `author` see every post but only change their own.
                        $own = static fn (?array $row): bool => $row === null || (int) ($row['author_id'] ?? 0) === $me;

                        foreach (['edit', 'delete', 'trash', 'restore', 'purge', 'publish', 'unpublish'] as $operation) {
                            $crud->setPermission($operation, $own);
                        }
                    }
                    PHP,
            ],
            [
                'id'     => 'ownRows',
                'icon'   => 'bi bi-person-lines-fill',
                'source' => 'modules/News/Controllers/Posts.php',
                'doc'    => 'permissions',
                'anchor' => 'personal-screens-granted-to-every-group',
                'code'   => <<<'PHP'
                    // A personal screen: its own resource (news-mine), granted to every group
                    public function mine(): ResponseInterface|string
                    {
                        $crud = $this->configure($this->crud('news-mine'), true)
                            ->where('author_id', (int) auth()->id())   // list, show, edit, delete, bulk, export
                            ->unsetExport()
                            ->hideFields(['author_id']);

                        return $this->renderCrud($crud);
                    }
                    PHP,
            ],
            [
                'id'     => 'hiddenFields',
                'icon'   => 'bi bi-eye-slash',
                'source' => 'modules/Tasks/Controllers/Tasks.php',
                'doc'    => 'permissions',
                'anchor' => 'hidden-fields',
                'code'   => <<<'PHP'
                    // Config/Module.php: 'extra' => ['estimates' => 'Tasks.perm.estimates']
                    // The estimate exists only for users with `tasks-tasks.estimates`: hidden from the
                    // list, details, forms, search, export and import, and ignored in forged posts.
                    if (! $this->can('tasks-tasks.estimates')) {
                        $crud->hideFields(['estimate_hours']);
                    }
                    PHP,
            ],
            [
                'id'     => 'translations',
                'icon'   => 'bi bi-translate',
                'source' => 'modules/Tasks/Controllers/Projects.php',
                'doc'    => 'i18n',
                'anchor' => 'content-languages',
                'code'   => <<<'PHP'
                    use Modules\Core\Content\ContentTranslations;

                    $crud->setTranslations('task_project_translations', 'project_id', 'locale', ['name', 'description'], ContentTranslations::locales(), ContentTranslations::crudOptions([
                        'types' => ['name' => 'text', 'description' => 'textarea'],
                        'rules' => ['name' => 'required|max_length[120]', 'description' => 'permit_empty|max_length[2000]'],
                    ]));
                    PHP,
            ],
            [
                'id'     => 'richText',
                'icon'   => 'bi bi-type-bold',
                'source' => 'modules/News/Controllers/Posts.php',
                'doc'    => 'pages',
                'anchor' => 'rich-text',
                'code'   => <<<'PHP'
                    use Modules\Core\Security\HtmlSanitizer;

                    // The Core's editor on a translated field (the `attrs` option of setTranslations())
                    ContentTranslations::crudOptions([
                        'types' => ['body' => 'textarea'],
                        'attrs' => ['body' => RichTextEditor::attrs(RichTextEditor::images('news'))],
                    ]);

                    // Sanitised before storing (each language of the posted translations) and when shown
                    $post['kp_tr'][$locale]['body'] = is_string($fields['body']) ? HtmlSanitizer::clean($fields['body']) : '';
                    PHP,
            ],
            [
                'id'     => 'uploads',
                'icon'   => 'bi bi-paperclip',
                'source' => 'modules/Tasks/Storage/AttachmentUploads.php',
                'doc'    => 'files',
                'anchor' => 'file-picker-session-23',
                'code'   => <<<'PHP'
                    // Storage/AttachmentUploads.php: final class AttachmentUploads extends CrudUploadHandler

                    // Controllers/Tasks.php: the Core's file picker as the field, links in the list
                    $crud->callbackField('attachments', static fn ($value, ?array $row, array $field): string => AttachmentUploads::renderPicker('attachments', $value, $field))
                        ->callbackColumn('attachments', static fn ($value): string => self::attachmentLinks(is_string($value) ? $value : null))
                        ->afterWrite(static function (string $operation, string $id): void {
                            AttachmentUploads::sync((int) $id);   // the module's index of the files
                        });
                    PHP,
            ],
            [
                'id'     => 'export',
                'icon'   => 'bi bi-filetype-csv',
                'source' => 'modules/Tasks/Controllers/Requests.php',
                'doc'    => 'cruds',
                'anchor' => 'permissions',
                'code'   => <<<'PHP'
                    // Export (permission <resource>.export): the columns and their raw values
                    $crud->exportColumns(['title', 'details', 'name', 'email', 'locale', 'status', 'created_at'])
                        ->callbackExport('status', static fn ($value): string => (string) $value);

                    // Import (permission <resource>.import): keep 'import' out of 'except' in the
                    // manifest; otherwise switch it off
                    $crud->unsetImport();
                    PHP,
            ],
            [
                'id'     => 'trash',
                'icon'   => 'bi bi-trash3',
                'source' => 'modules/News/Controllers/Posts.php',
                'doc'    => 'cruds',
                'anchor' => 'permissions',
                'code'   => <<<'PHP'
                    // A table with `deleted_at` (migration: 'deleted_at' => ['type' => 'DATETIME', 'null' => true])
                    // gets a trash: operations trash, restore and purge (declared unless in 'except').
                    // Without them in the manifest, switch them off:
                    $crud->unsetTrash()
                        ->unsetPurge()
                        ->setPermission('restore', false);
                    PHP,
            ],
            [
                'id'     => 'maskedIds',
                'icon'   => 'bi bi-incognito',
                'source' => 'app/Config/Kpcrudy.php',
                'doc'    => 'cruds',
                'anchor' => 'configuration-appconfigkpcrudyphp',
                'code'   => <<<'PHP'
                    // app/Config/Kpcrudy.php: every CRUD masks its ids with app.maskKey (shared with kpapy)
                    public bool $maskIds = true;

                    // .env (64 random hex characters; changing it breaks every masked or signed link)
                    app.maskKey = <php -r "echo bin2hex(random_bytes(32)), PHP_EOL;">

                    // Masked ids are not access control: keep the permissions and record rules.
                    PHP,
            ],
            [
                'id'     => 'activity',
                'icon'   => 'bi bi-journal-text',
                'source' => 'modules/Core/Controllers/Notifications.php',
                'doc'    => 'cruds',
                'anchor' => 'activity-log',
                'code'   => <<<'PHP'
                    // $this->crud() returns an AuditedCrud: writes, POST operations, bulk actions,
                    // imports and exports are logged by the Core. Do not log them again.

                    // Leave out operations that only change the user's own view
                    $crud->skipActivity('read', 'readall');

                    // Anything else: activity_log('<Module>', '<action>', [...]) with its label
                    // in <Module>.activity.<action>
                    PHP,
            ],
            [
                'id'     => 'api',
                'icon'   => 'bi bi-braces',
                'source' => 'modules/Tasks/Controllers/Api/Tasks.php',
                'doc'    => 'api',
                'anchor' => 'a-modules-resources',
                'code'   => <<<'PHP'
                    // Config/Module.php: same resource name as the CRUD → same permissions (scopes)
                    public array $api = [
                        'tasks' => ['path' => 'tasks/tasks', 'handler' => '\Modules\Tasks\Controllers\Api\Tasks::index'],
                    ];

                    // Config/Routes.php
                    Resource::routes($routes, 'api/v1/tasks/tasks', '\Modules\Tasks\Controllers\Api\Tasks::index', ['filter' => 'kpapy']);

                    // Controllers/Api/Tasks.php
                    $api->setResource('tasks-tasks')
                        ->addOperation('complete', $callback, ['description' => lang('Tasks.api.complete')]);
                    PHP,
            ],
            [
                'id'     => 'menu',
                'icon'   => 'bi bi-list-nested',
                'source' => 'modules/Tasks/Config/Module.php',
                'doc'    => 'menus',
                'anchor' => 'declaring-entries-menu-in-configmodulephp',
                'code'   => <<<'PHP'
                    public array $defaultGrants = [
                        'admin'  => ['tasks-tasks.*', 'tasks-projects.*'],
                        'editor' => ['tasks-tasks.list', 'tasks-tasks.show', 'tasks-tasks.add', 'tasks-tasks.edit'],
                    ];

                    public array $menu = [
                        ['resource' => 'board', 'label' => 'Tasks.menu.board', 'icon' => 'bi bi-kanban', 'order' => 40],
                        ['resource' => 'tasks', 'label' => 'Tasks.menu.tasks', 'icon' => 'bi bi-check2-square', 'order' => 1, 'parent' => 'board'],
                    ];
                    PHP,
            ],
        ];
    }
}
