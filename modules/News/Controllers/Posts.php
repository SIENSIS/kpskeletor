<?php

declare(strict_types=1);

namespace Modules\News\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Crud\AuditedCrud;
use Modules\News\Services\BodySanitizer;
use Modules\News\Services\NewsRepository;
use Modules\News\Services\NewsVisibility;
use Modules\News\Services\Publisher;
use Modules\News\Services\ReadReceipts;
use Modules\News\Services\Slugger;
use Modules\News\Storage\CoverUploads;

/**
 * Back office of the posts, two kpcrudy CRUDs over the same table (docs/news.md):
 *
 * - `backend/news/posts` (`news-posts`): every post. Without `news-posts.author` the author
 *   fields are hidden (`hideFields()`) and a user only edits, deletes and publishes **their own**
 *   posts (per-record `setPermission()` rules);
 * - `backend/news/mine` (`news-mine`, "My posts"): the `where('author_id', me)` pattern: only the
 *   user's posts, a forged id is "not found", new posts are always theirs.
 *
 * Both: multilingual title/summary/body (`setTranslations()`, Catalan required), groups with
 * `setRelationNN()` (`news_groups`), the body sanitised with HTMLPurifier before kpcrudy reads the
 * form, a unique slug, a cover through the Storage service, the `publish` / `unpublish`
 * operations (and bulk action) and, on `news-posts`, the readers page (`news-posts.readers`).
 * Writes and operations are logged by the Core (`AuditedCrud`).
 */
final class Posts extends BackendController
{
    /** Languages of the content: Catalan (required, the default), Spanish, English. */
    public const LOCALES = ['ca' => 'Català', 'es' => 'Español', 'en' => 'English'];

    public function index(): ResponseInterface|string
    {
        $crud = $this->configure($this->crud('news-posts'), false);
        $me   = (int) auth()->id();

        if (! $this->can('news-posts.author')) {
            // Authors without `author` see every post but only change their own.
            $own = static fn (?array $row): bool => $row === null || (int) ($row['author_id'] ?? 0) === $me;

            foreach (['edit', 'delete', 'trash', 'restore', 'purge', 'publish', 'unpublish'] as $operation) {
                $crud->setPermission($operation, $own);
            }

            $crud->hideFields(['author_id']);
        }

        $crud->addOperation('readers', fn (string $id, array $row, Crud $crud): ResponseInterface => $this->readers($id, $row, $crud), [
            'method'     => 'get',
            'permission' => 'readers',
            'label'      => lang('News.readers'),
            'icon'       => 'bi bi-people',
        ]);

        return $this->renderCrud($crud);
    }

    public function mine(): ResponseInterface|string
    {
        $crud = $this->configure($this->crud('news-mine'), true)
            ->where('author_id', (int) auth()->id())
            ->setSubject(lang('News.post'), lang('News.minePosts'))
            ->unsetExport()
            ->unsetTrash()
            ->unsetPurge()
            ->setPermission('restore', false)
            ->hideFields(['author_id']);

        return $this->renderCrud($crud);
    }

    /**
     * Options of the category field: every category (inactive ones too), by name.
     *
     * @return array<int, string>
     */
    public static function categoryOptions(): array
    {
        $rows  = db_connect()->table('news_categories')->select('id, slug')->orderBy('position')->orderBy('id')->get()->getResultArray();
        $names = db_connect()->table('news_category_translations')->select('category_id, locale, name')->get()->getResultArray();
        $texts = [];

        foreach ($names as $name) {
            $texts[(int) $name['category_id']][(string) $name['locale']] = (string) $name['name'];
        }

        $locale  = service('request')->getLocale();
        $options = [];

        foreach ($rows as $row) {
            $id           = (int) $row['id'];
            $options[$id] = $texts[$id][$locale] ?? $texts[$id][NewsRepository::FALLBACK_LOCALE] ?? (string) $row['slug'];
        }

        return $options;
    }

    private function configure(AuditedCrud $crud, bool $mine): AuditedCrud
    {
        $this->sanitizePostedBody();

        $me       = (int) auth()->id();
        $statuses = $this->options('statuses', NewsVisibility::STATUSES);

        $crud->setTable('news')
            ->setSubject(lang('News.post'), lang('News.posts'))
            ->setTranslations('news_translations', 'news_id', 'locale', ['title', 'summary', 'body'], self::LOCALES, [
                'required'  => ['ca'],
                'types'     => ['title' => 'text', 'summary' => 'textarea', 'body' => 'textarea'],
                'rules'     => ['title' => 'required|max_length[190]', 'summary' => 'permit_empty|max_length[500]', 'body' => 'permit_empty|max_length[200000]'],
                'fallbacks' => ['*' => ['ca']],
            ])
            ->setRelationNN('groups', 'news_groups', 'groups', 'news_id', 'group_id', 'title', ['order' => 'title'])
            ->setRelation('author_id', 'users', 'username')
            ->fieldType('type', 'select', ['options' => $this->options('types', NewsVisibility::TYPES)])
            ->fieldType('priority', 'select', ['options' => $this->options('priorities', NewsVisibility::PRIORITIES)])
            ->fieldType('visibility', 'select', ['options' => $this->options('visibilities', NewsVisibility::VISIBILITIES)])
            ->fieldType('status', 'readonly')
            ->fieldType('category_id', 'select', ['options' => self::categoryOptions()])
            ->fieldType('pinned', 'boolean')
            ->fieldType('published_at', 'datetime')
            ->fieldType('expires_at', 'datetime')
            ->fieldType('cover', 'image', ['handler' => new CoverUploads(), 'allowed' => CoverUploads::TYPES, 'maxSize' => CoverUploads::MAX_SIZE / 1024])
            ->columns(['title', 'type', 'category_id', 'visibility', 'status', 'published_at', 'author_id'])
            ->fields(['title', 'summary', 'body', 'type', 'priority', 'pinned', 'category_id', 'visibility', 'groups', 'status', 'published_at', 'expires_at', 'slug', 'cover', 'author_id'])
            ->showFields(['title', 'summary', 'body', 'type', 'priority', 'pinned', 'category_id', 'visibility', 'groups', 'status', 'published_at', 'expires_at', 'slug', 'cover', 'author_id', 'created_at', 'updated_at'])
            ->exportColumns(['title', 'summary', 'type', 'priority', 'pinned', 'category_id', 'visibility', 'groups', 'status', 'published_at', 'expires_at', 'slug', 'author_id'])
            ->searchFields(['title', 'summary', 'slug'])
            ->filters(['type', 'category_id', 'visibility', 'status', 'groups', 'author_id'])
            ->setRules([
                'slug'         => ['permit_empty', 'max_length[180]', 'regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'],
                'published_at' => 'permit_empty|valid_date',
                'expires_at'   => 'permit_empty|valid_date',
            ])
            ->setDefault('type', 'article')
            ->setDefault('priority', 'info')
            ->setDefault('visibility', 'private')
            ->setDefault('author_id', $me)
            ->setHelp('slug', lang('News.help.slug'))
            ->setHelp('visibility', lang('News.help.visibility'))
            ->setHelp('groups', lang('News.help.groups'))
            ->setHelp('priority', lang('News.help.priority'))
            ->setHelp('expires_at', lang('News.help.expiresAt'))
            ->setHelp('published_at', lang('News.help.publishedAt'))
            ->setHelp('cover', lang('News.help.cover'))
            ->displayAs($this->labels())
            ->callbackColumn('status', static fn ($value): string => self::statusBadge((string) $value))
            ->callbackColumn('type', static fn ($value): string => esc(lang('News.types.' . (in_array($value, NewsVisibility::TYPES, true) ? $value : 'article'))))
            ->callbackColumn('visibility', static fn ($value): string => self::visibilityBadge((string) $value))
            ->callbackColumn('published_at', static fn ($value): string => ui_date(is_string($value) ? $value : null, 'datetime'))
            ->callbackExport('status', static fn ($value): string => (string) $value)
            ->callbackExport('type', static fn ($value): string => (string) $value)
            ->callbackExport('visibility', static fn ($value): string => (string) $value)
            ->callbackExport('published_at', static fn ($value): string => (string) $value)
            ->beforeInsert(fn (array $data): array|string => $this->beforeSave($data, $crud, null, $mine ? $me : null))
            ->beforeUpdate(fn (array $data, string $id, array $old): array|string => $this->beforeSave($data, $crud, (int) $id, null))
            ->defaultOrder('id', 'desc');

        $crud->addOperation('publish', static function (string $id, array $row): string {
            $status = (new Publisher())->publish($row);

            return lang($status === 'scheduled' ? 'News.scheduledOk' : 'News.publishedOk');
        }, [
            'label'      => lang('News.publish'),
            'icon'       => 'bi bi-send',
            'class'      => 'btn-outline-success',
            'confirm'    => lang('News.publishConfirm'),
            'permission' => 'publish',
            'visible'    => static fn (?array $row): bool => $row === null || in_array($row['status'] ?? '', ['draft', 'expired'], true),
        ]);

        $crud->addOperation('unpublish', static function (string $id): string {
            db_connect()->table('news')->where('id', (int) $id)->update(['status' => 'draft']);

            return lang('News.unpublishedOk');
        }, [
            'label'      => lang('News.unpublish'),
            'icon'       => 'bi bi-arrow-counterclockwise',
            'confirm'    => lang('News.unpublishConfirm'),
            'permission' => 'publish',
            'visible'    => static fn (?array $row): bool => $row === null || in_array($row['status'] ?? '', ['published', 'scheduled'], true),
        ]);

        $crud->addBulkAction('publish', lang('News.publish'), static function (array $ids): string {
            $publisher = new Publisher();
            $rows      = db_connect()->table('news')->whereIn('id', array_map('intval', $ids))->whereIn('status', ['draft', 'expired'])->get()->getResultArray();

            foreach ($rows as $row) {
                $publisher->publish($row);
            }

            return lang('News.bulkPublished', [count($rows)]);
        }, ['permission' => 'publish', 'confirm' => lang('News.publishConfirm')]);

        return $crud;
    }

    /**
     * Slug (generated from the Catalan title or checked when given), the author of new posts,
     * new posts start as drafts. `$author`: forced author ("My posts").
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|string
     */
    private function beforeSave(array $data, Crud $crud, ?int $id, ?int $author): array|string
    {
        $title = (string) ($crud->postedTranslations()['ca']['title'] ?? '');
        $slug  = trim((string) ($data['slug'] ?? ''));

        if ($id === null || array_key_exists('slug', $data)) {
            $data['slug'] = Slugger::unique('news', $slug !== '' ? $slug : $title, $id);
        }

        if ($id === null) {
            $data['status'] = 'draft';

            if ($author !== null || ! array_key_exists('author_id', $data) || $data['author_id'] === null || $data['author_id'] === '') {
                $data['author_id'] = $author ?? (int) auth()->id();
            }
        }

        foreach (['published_at', 'expires_at'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (($data['published_at'] ?? null) !== null && ($data['expires_at'] ?? null) !== null
            && strtotime((string) $data['expires_at']) <= strtotime((string) $data['published_at'])) {
            return lang('News.errors.expiresBeforePublished');
        }

        return $data;
    }

    /**
     * Sanitises the posted bodies (`kp_tr[<locale>][body]`) with HTMLPurifier before kpcrudy reads
     * the form: its hooks only receive the main table's data (D-17-02).
     */
    private function sanitizePostedBody(): void
    {
        $post = $this->request->getPost();

        if (! is_array($post['kp_tr'] ?? null)) {
            return;
        }

        foreach ($post['kp_tr'] as $locale => $fields) {
            if (is_array($fields) && array_key_exists('body', $fields)) {
                $post['kp_tr'][$locale]['body'] = is_string($fields['body']) ? BodySanitizer::clean($fields['body']) : '';
            }
        }

        $this->request->setGlobal('post', $post);
    }

    /**
     * The readers page of a post: how many users read it and who (`news-posts.readers`).
     *
     * @param array<string, mixed> $row
     */
    private function readers(string $id, array $row, Crud $crud): ResponseInterface
    {
        $receipts = new ReadReceipts();
        $titles   = array_column(db_connect()->table('news_translations')->select('locale, title')->where('news_id', (int) $id)->get()->getResultArray(), 'title', 'locale');
        $title    = (string) ($titles[NewsRepository::FALLBACK_LOCALE] ?? reset($titles) ?: '');

        $html = theme_view('Modules\News\Views\readers', [
            'title'   => $title,
            'count'   => $receipts->count((int) $id),
            'readers' => $receipts->readers((int) $id),
            'backUrl' => $crud->url('list'),
        ]);

        return service('response')->setBody($crud->page($html, lang('News.readersOf', [$title])));
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
            $options[$value] = lang('News.' . $group . '.' . $value);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function labels(): array
    {
        $labels = [];

        foreach (['title', 'summary', 'body', 'type', 'priority', 'pinned', 'category_id', 'visibility', 'groups', 'status', 'published_at', 'expires_at', 'slug', 'cover', 'author_id', 'created_at', 'updated_at'] as $field) {
            $labels[$field] = lang('News.fields.' . $field);
        }

        return $labels;
    }

    public static function statusBadge(string $status): string
    {
        $variants = ['draft' => 'secondary', 'scheduled' => 'info', 'published' => 'success', 'expired' => 'warning'];

        return isset($variants[$status]) ? ui_badge(lang('News.statuses.' . $status), $variants[$status]) : esc($status);
    }

    public static function visibilityBadge(string $visibility): string
    {
        $icons = ['public' => 'bi bi-globe', 'private' => 'bi bi-lock', 'groups' => 'bi bi-people'];

        return isset($icons[$visibility]) ? ui_badge(lang('News.visibilities.' . $visibility), 'light', $icons[$visibility]) : esc($visibility);
    }
}
