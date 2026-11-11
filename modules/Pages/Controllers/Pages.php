<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Content\PublishWorkflow;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Crud\AuditedCrud;
use Modules\Core\Frontend\RichTextEditor;
use Modules\Core\Security\HtmlSanitizer;
use Modules\Pages\Services\HomePageResolver;
use Modules\Pages\Services\PageCopier;
use Modules\Pages\Services\PageRenderer;
use Modules\Pages\Services\PageRepository;
use Modules\Pages\Services\PageResolver;
use Modules\Pages\Services\PageSlugs;
use Modules\Pages\Services\PageTree;
use Modules\Pages\Services\PageVisibility;
use Modules\Pages\Services\Revisions;
use RuntimeException;

/**
 * Back office of the pages (`backend/pages`, resource `pages`; docs/pages.md), a kpcrudy CRUD:
 *
 * - the list as a tree (`tree('parent_id')`, siblings by `position`), filters by status and
 *   visibility, search by title and slug; the home page is marked;
 * - multilingual texts (`setTranslations()`, Catalan required): title, slug, summary, body (the
 *   rich-text editor, sanitised with HTMLPurifier before kpcrudy reads the form), SEO fields;
 * - slugs generated from the title when empty, unique per parent and language, never a reserved
 *   first segment (PageSlugs); the parent field offers the tree without the page's subtree;
 * - publish / unpublish (`pages.publish`), preview (`show`), revisions (`show`) with restore
 *   (`edit`), duplicate (`add`). Every save records a revision (Revisions).
 *
 * Writes and POST operations are logged by the Core (`AuditedCrud`).
 */
final class Pages extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('pages');
        $this->prepareForm();

        $me     = (int) auth()->id();
        $locale = $this->request->getLocale();
        $tree   = new PageTree();
        $home   = (new HomePageResolver())->id();

        $crud->setTable('pages')
            ->setSubject(lang('Pages.page'), lang('Pages.pages'))
            ->setTranslations('page_translations', 'page_id', 'locale', PageRepository::TEXTS, ContentTranslations::LOCALES, [
                'required' => ['ca'],
                'types'    => [
                    'title' => 'text', 'slug' => 'text', 'summary' => 'textarea', 'body' => 'textarea',
                    'meta_title' => 'text', 'meta_description' => 'textarea', 'canonical' => 'text',
                ],
                'rules' => [
                    'title'            => 'required|max_length[190]',
                    'slug'             => ['permit_empty', 'max_length[180]', 'regex_match[' . PageResolver::SLUG . ']'],
                    'summary'          => 'permit_empty|max_length[500]',
                    'body'             => 'permit_empty|max_length[500000]',
                    'meta_title'       => 'permit_empty|max_length[190]',
                    'meta_description' => 'permit_empty|max_length[300]',
                    'canonical'        => 'permit_empty|max_length[500]|valid_url_strict[https]',
                ],
                'fallbacks' => ['*' => ['ca']],
            ])
            ->fieldType('body', 'translation', ['attrs' => RichTextEditor::attrs()])
            ->setRelationNN('groups', 'page_groups', 'groups', 'page_id', 'group_id', 'title', ['order' => 'title'])
            ->setRelation('author_id', 'users', 'username')
            ->fieldType('parent_id', 'select', ['options' => $tree->options($this->editedId(), $locale)])
            ->fieldType('template', 'select', ['options' => $this->templates()])
            ->fieldType('visibility', 'select', ['options' => $this->options('visibilities', PageVisibility::VISIBILITIES)])
            ->fieldType('status', 'readonly')
            ->fieldType('publish_at', 'datetime')
            ->fieldType('noindex', 'boolean')
            ->fieldType('position', 'number', ['attrs' => ['min' => 0, 'max' => 9999]])
            ->fieldType('featured_image', 'image', \Modules\Pages\Storage\FeaturedImages::fieldOptions())
            ->tree('parent_id', ['order' => 'position', 'label' => 'title'])
            ->columns(['title', 'path', 'status', 'visibility', 'publish_at', 'updated_at'])
            ->addFields(['title', 'slug', 'summary', 'body', 'meta_title', 'meta_description', 'canonical', 'parent_id', 'position', 'template', 'visibility', 'groups', 'publish_at', 'noindex', 'featured_image'])
            ->editFields(['title', 'slug', 'summary', 'body', 'meta_title', 'meta_description', 'canonical', 'parent_id', 'position', 'template', 'visibility', 'groups', 'status', 'publish_at', 'noindex', 'featured_image', 'author_id'])
            ->readonlyFields(['author_id'])
            ->rowActionsMenu()
            ->showFields(['title', 'path', 'slug', 'summary', 'body', 'meta_title', 'meta_description', 'canonical', 'parent_id', 'position', 'template', 'visibility', 'groups', 'status', 'publish_at', 'noindex', 'featured_image', 'author_id', 'created_at', 'updated_at'])
            ->exportColumns(['title', 'path', 'slug', 'summary', 'parent_id', 'position', 'template', 'visibility', 'groups', 'status', 'publish_at', 'noindex', 'author_id', 'created_at', 'updated_at'])
            ->searchFields(['title', 'slug'])
            ->filters(['status', 'visibility'])
            ->setRules([
                'publish_at' => 'permit_empty|valid_date',
                'position'   => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
                'template'   => 'required|in_list[' . implode(',', array_keys($this->templates())) . ']',
                'visibility' => 'required|in_list[' . implode(',', PageVisibility::VISIBILITIES) . ']',
            ])
            ->setDefault('template', 'default')
            ->setDefault('visibility', 'public')
            ->setDefault('position', 0)
            ->setHelp('slug', lang('Pages.help.slug'))
            ->setHelp('parent_id', lang('Pages.help.parent'))
            ->setHelp('visibility', lang('Pages.help.visibility'))
            ->setHelp('groups', lang('Pages.help.groups'))
            ->setHelp('publish_at', lang('Pages.help.publishAt'))
            ->setHelp('noindex', lang('Pages.help.noindex'))
            ->setHelp('canonical', lang('Pages.help.canonical'))
            ->setHelp('template', lang('Pages.help.template'))
            ->setHelp('featured_image', lang('Pages.help.featuredImage'))
            ->displayAs($this->labels())
            ->callbackColumn('path', fn ($value, array $row): string => $this->pathColumn($tree, (int) $row['id'], $home, $locale))
            ->callbackColumn('status', static fn ($value, array $row): string => self::statusBadge(PageVisibility::displayStatus($row)))
            ->callbackColumn('visibility', static fn ($value): string => self::visibilityBadge((string) $value))
            ->callbackColumn('template', fn ($value): string => esc($this->templates()[(string) $value] ?? (string) $value))
            ->callbackColumn('publish_at', static fn ($value): string => ui_date(is_string($value) ? $value : null, 'datetime'))
            ->callbackColumn('updated_at', static fn ($value): string => ui_date(is_string($value) ? $value : null, 'relative'))
            ->callbackExport('path', static fn ($value, array $row): string => (string) $tree->path((int) $row['id'], $locale))
            ->callbackExport('status', static fn ($value, array $row): string => PageVisibility::displayStatus($row))
            ->callbackExport('visibility', static fn ($value): string => (string) $value)
            ->callbackExport('template', static fn ($value): string => (string) $value)
            ->callbackExport('publish_at', static fn ($value): string => (string) $value)
            ->callbackExport('updated_at', static fn ($value): string => (string) $value)
            ->unsortable(['path'])
            ->beforeInsert(fn (array $data): array|string => $this->beforeSave($data, $crud, null, null))
            ->beforeUpdate(fn (array $data, string $id, array $old): array|string => $this->beforeSave($data, $crud, (int) $id, $old))
            ->afterInsert(static function (string $id) use ($me): void {
                (new Revisions())->record((int) $id, $me, 'create');
            })
            ->afterUpdate(static function (string $id) use ($me): void {
                (new Revisions())->record((int) $id, $me);
            })
            ->afterDelete(static function (string $id, array $row, bool $purge): void {
                if ($purge) {
                    // Portable clean-up (foreign keys are not enforced on every database).
                    db_connect()->table('page_revisions')->where('page_id', (int) $id)->delete();
                    db_connect()->table('pages')->where('parent_id', (int) $id)->update(['parent_id' => null]);
                }
            })
            ->defaultOrder('position', 'asc');

        $this->operations($crud);

        return $this->renderCrud($crud);
    }

    private function operations(AuditedCrud $crud): void
    {
        $me = (int) auth()->id();

        $crud->addOperation('publish', static function (string $id, array $row): string {
            ['status' => $status, 'at' => $at] = PublishWorkflow::statusFor(is_string($row['publish_at'] ?? null) ? $row['publish_at'] : null);
            db_connect()->table('pages')->where('id', (int) $id)->update(['status' => $status, 'publish_at' => $at->toDateTimeString(), 'updated_at' => date('Y-m-d H:i:s')]);

            return lang($status === 'scheduled' ? 'Pages.scheduledOk' : 'Pages.publishedOk');
        }, [
            'label'      => lang('Pages.publish'),
            'icon'       => 'bi bi-send',
            'class'      => 'btn-outline-success',
            'confirm'    => lang('Pages.publishConfirm'),
            'permission' => 'publish',
            'visible'    => static fn (?array $row): bool => $row === null || ($row['status'] ?? '') === 'draft',
        ]);

        $crud->addOperation('unpublish', static function (string $id): string {
            db_connect()->table('pages')->where('id', (int) $id)->update(['status' => 'draft', 'updated_at' => date('Y-m-d H:i:s')]);

            return lang('Pages.unpublishedOk');
        }, [
            'label'      => lang('Pages.unpublish'),
            'icon'       => 'bi bi-arrow-counterclockwise',
            'confirm'    => lang('Pages.unpublishConfirm'),
            'permission' => 'publish',
            'visible'    => static fn (?array $row): bool => $row === null || in_array($row['status'] ?? '', ['published', 'scheduled'], true),
        ]);

        $crud->addBulkAction('publish', lang('Pages.publish'), static function (array $ids): string {
            $rows = db_connect()->table('pages')->whereIn('id', array_map('intval', $ids))->where('status', 'draft')->where('deleted_at', null)->get()->getResultArray();

            foreach ($rows as $row) {
                ['status' => $status, 'at' => $at] = PublishWorkflow::statusFor(is_string($row['publish_at']) ? $row['publish_at'] : null);
                db_connect()->table('pages')->where('id', (int) $row['id'])->update(['status' => $status, 'publish_at' => $at->toDateTimeString(), 'updated_at' => date('Y-m-d H:i:s')]);
            }

            return lang('Pages.bulkPublished', [count($rows)]);
        }, ['permission' => 'publish', 'confirm' => lang('Pages.publishConfirm')]);

        $crud->addOperation('preview', function (string $id): ResponseInterface {
            $page = (new PageRepository())->find((int) $id);

            if ($page === null) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }

            return service('response')
                ->setHeader('X-Robots-Tag', 'noindex, nofollow')
                ->setHeader('Cache-Control', 'private, no-store')
                ->setBody((new PageRenderer())->render($page, $this->currentUser(), false, true));
        }, [
            'method'     => 'get',
            'permission' => 'show',
            'label'      => lang('Pages.preview'),
            'icon'       => 'bi bi-eye',
            'target'     => '_blank',
        ]);

        $crud->addOperation('revisions', fn (string $id, array $row, Crud $crud): ResponseInterface => $this->revisions((int) $id, $crud), [
            'method'     => 'get',
            'permission' => 'show',
            'label'      => lang('Pages.revisions'),
            'icon'       => 'bi bi-clock-history',
        ]);

        $crud->addOperation('restorerevision', function (string $id, array $row, Crud $crud) use ($me): ResponseInterface|string {
            $revision = (int) $this->request->getPost('revision');

            try {
                (new Revisions())->restore((int) $id, $revision, $me);
            } catch (RuntimeException $e) {
                return $crud->backToList('danger', $e->getMessage());
            }

            return lang('Pages.restoredOk');
        }, [
            'permission' => 'edit',
            'button'     => false,
            'label'      => lang('Pages.restore'),
        ]);

        $crud->addOperation('duplicate', static function (string $id, array $row, Crud $crud) use ($me): ResponseInterface {
            $copy = (new PageCopier())->duplicate((int) $id, $me);
            session()->setFlashdata('message', lang('Pages.duplicatedOk'));

            return redirect()->to($crud->url('edit', (string) $copy));
        }, [
            'permission' => 'add',
            'label'      => lang('Pages.duplicate'),
            'icon'       => 'bi bi-copy',
            'confirm'    => lang('Pages.duplicateConfirm'),
        ]);
    }

    /**
     * The revisions page of a page: who, when, what changed, and restore buttons.
     */
    private function revisions(int $id, Crud $crud): ResponseInterface
    {
        $revisions = new Revisions();
        $history   = $revisions->history($id);
        $title     = (new PageTree())->title($id, $this->request->getLocale());
        $selected  = (int) $this->request->getGet('revision');
        $selected  = in_array($selected, array_column($history, 'id'), true) ? $selected : ($history[0]['id'] ?? 0);

        $html = theme_view('Modules\Pages\Views\revisions', [
            'history'    => $history,
            'selected'   => $selected,
            'diff'       => $selected > 0 ? $revisions->diff($id, $selected) : [],
            'canRestore' => $crud->isAllowed('edit', ['id' => $id]),
            'restoreUrl' => $crud->url('restorerevision', (string) $id),
            'pageUrl'    => $crud->url('revisions', (string) $id),
            'backUrl'    => $crud->url('list'),
            'labels'     => $this->labels(),
        ]);

        return service('response')->setBody($crud->page($html, lang('Pages.revisionsOf', [$title])));
    }

    /**
     * Before kpcrudy reads the form: sanitise the posted bodies with HTMLPurifier and fill the
     * empty slugs from the titles (kpcrudy's hooks only receive the main table's data).
     */
    private function prepareForm(): void
    {
        $post = $this->request->getPost();

        if (! is_array($post['kp_tr'] ?? null)) {
            return;
        }

        foreach ($post['kp_tr'] as $locale => $fields) {
            if (is_array($fields) && array_key_exists('body', $fields)) {
                $post['kp_tr'][$locale]['body'] = is_string($fields['body']) ? HtmlSanitizer::clean($fields['body']) : '';
            }
        }

        $parent       = is_numeric($post['parent_id'] ?? null) ? (int) $post['parent_id'] : null;
        $post['kp_tr'] = (new PageSlugs())->fill($post['kp_tr'], $parent, $this->editedId());

        $this->request->setGlobal('post', $post);
    }

    /**
     * The id of the page being edited (from the CRUD's masked URL), or null.
     */
    private function editedId(): ?int
    {
        $segments = $this->request->getUri()->getSegments();
        $index    = array_search('pages', $segments, true);

        if ($index === false || ($segments[$index + 1] ?? '') !== 'edit' || ! isset($segments[$index + 2])) {
            return null;
        }

        $id = Crud::idFromUrl($segments[$index + 2], 'pages');

        return $id !== null && ctype_digit($id) ? (int) $id : null;
    }

    /**
     * New pages start as drafts by the current user; slugs must not clash; a parent must exist.
     *
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $old
     *
     * @return array<string, mixed>|string
     */
    private function beforeSave(array $data, Crud $crud, ?int $id, ?array $old): array|string
    {
        if (array_key_exists('parent_id', $data) && ($data['parent_id'] === '' || $data['parent_id'] === null)) {
            $data['parent_id'] = null;
        }

        $parent = array_key_exists('parent_id', $data) ? $data['parent_id'] : ($old['parent_id'] ?? null);
        $parent = $parent === null ? null : (int) $parent;

        if ($parent !== null && ($parent === $id || db_connect()->table('pages')->where('id', $parent)->countAllResults() === 0)) {
            return lang('Pages.errors.parent');
        }

        $error = (new PageSlugs())->clash($crud->postedTranslations(), $parent, $id);

        if ($error !== null) {
            return $error;
        }

        if ($id === null) {
            $data['status']    = 'draft';
            $data['author_id'] = (int) auth()->id();
        }

        if (array_key_exists('publish_at', $data) && $data['publish_at'] === '') {
            $data['publish_at'] = null;
        }

        if (array_key_exists('template', $data) && ! array_key_exists((string) $data['template'], $this->templates())) {
            $data['template'] = 'default';
        }

        return $data;
    }

    private function pathColumn(PageTree $tree, int $id, ?int $home, string $locale): string
    {
        $path = $tree->path($id, $locale);
        $html = $path === null ? '' : '<code class="text-break">/' . esc($path) . '</code>';

        return $id === $home ? $html . ' ' . ui_badge(lang('Pages.homeBadge'), 'primary', 'bi bi-house') : $html;
    }

    /**
     * Templates of the public theme (key => label).
     *
     * @return array<string, string>
     */
    private function templates(): array
    {
        $templates = [];

        foreach (service('theme')->pageTemplates('public') as $key => $label) {
            $templates[$key] = lang($label);
        }

        return $templates === [] ? ['default' => lang('Pages.defaultTemplate')] : $templates;
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, string>
     */
    private function options(string $group, array $values): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = lang('Pages.' . $group . '.' . $value);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function labels(): array
    {
        $labels = [];

        foreach (['title', 'path', 'slug', 'summary', 'body', 'meta_title', 'meta_description', 'canonical', 'parent_id', 'position', 'template', 'visibility', 'groups', 'status', 'publish_at', 'noindex', 'featured_image', 'author_id', 'created_at', 'updated_at'] as $field) {
            $labels[$field] = lang('Pages.fields.' . $field);
        }

        return $labels;
    }

    public static function statusBadge(string $status): string
    {
        $variants = ['draft' => 'secondary', 'scheduled' => 'info', 'published' => 'success'];

        return isset($variants[$status]) ? ui_badge(lang('Pages.statuses.' . $status), $variants[$status]) : esc($status);
    }

    public static function visibilityBadge(string $visibility): string
    {
        $icons = ['public' => 'bi bi-globe', 'private' => 'bi bi-lock', 'groups' => 'bi bi-people'];

        return isset($icons[$visibility]) ? ui_badge(lang('Pages.visibilities.' . $visibility), 'light', $icons[$visibility]) : esc($visibility);
    }
}
