<?php

declare(strict_types=1);

namespace Modules\News\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Kpapy\Auth\Context;
use Kpapy\Resource;
use Kpcrudy\Crud;
use Modules\Core\Security\HtmlSanitizer;
use Modules\News\Controllers\Posts as PostsCrud;
use Modules\News\Services\NewsRepository;
use Modules\News\Services\NewsVisibility;
use Modules\News\Services\ReadReceipts;
use Modules\News\Storage\CoverUploads;

/**
 * API resource `news` (`GET api/v1/news`, `GET api/v1/news/{id}`; kpapy, read-only): the posts
 * the token's user may read, with the same rules as the reader screen (`NewsVisibility::apply()`
 * through kpapy's `where(Closure)`, so list, details, counts, search and filters are all limited). Permissions
 * `news-api.list` / `news-api.show` (D-17-01), granted to every group; the scopes of a token
 * still narrow them. Ids are masked like the back office's (`app.maskKey`, context `news`).
 *
 * Session 26 (D-26-01):
 * - `POST api/v1/news/{id}/read`: marks a readable post as read (`news-api.read`; kpapy checks the
 *   masked id, the visibility `where()` and the permission first, so an unreadable post is 404);
 * - `GET api/v1/news/notices[?unread=1][&limit=]`: the user's notices like the dashboard widget
 *   (pinned first, then priority and date; `news-api.list`).
 * Read receipts are personal state and, as in the back office, not written to the activity log
 * (D-26-03).
 */
final class Posts extends Controller
{
    public function index(): ResponseInterface
    {
        $user = self::user();

        return (new Resource())
            ->setTable('news')
            ->setResource('news-api')
            ->where(static fn (BaseBuilder $builder): BaseBuilder => NewsVisibility::apply($builder, $user))
            ->setTranslations('news_translations', 'news_id', 'locale', ['title', 'summary', 'body'], array_keys(PostsCrud::LOCALES), ['required' => ['ca']])
            ->fields(['id', 'slug', 'type', 'priority', 'pinned', 'visibility', 'category', 'title', 'summary', 'body', 'cover_url', 'published_at', 'expires_at', 'read'])
            ->listFields(['id', 'slug', 'type', 'priority', 'pinned', 'visibility', 'category', 'title', 'summary', 'cover_url', 'published_at', 'expires_at', 'read'])
            ->fieldType('pinned', 'bool')
            ->callbackField('category', static fn ($value, array $row): ?string => self::categorySlug($row['category_id'] ?? null))
            ->callbackField('cover_url', static fn ($value, array $row): ?string => CoverUploads::urlOf(is_string($row['cover'] ?? null) ? $row['cover'] : null))
            ->callbackField('read', static fn ($value, array $row): bool => $user !== null && (new ReadReceipts())->isRead((int) $row['id'], (int) $user->id))
            // The body was sanitised when saved; cleaned again on output, like the reader and the
            // public pages, in case a row was written another way (one language, or all with ?lang=*).
            ->transform(static function (array $item): array {
                if (is_string($item['body'] ?? null)) {
                    $item['body'] = HtmlSanitizer::clean($item['body']);
                } elseif (is_array($item['body'] ?? null)) {
                    $item['body'] = array_map(static fn ($text): ?string => is_string($text) ? HtmlSanitizer::clean($text) : null, $item['body']);
                }

                return $item;
            })
            ->filters(['type', 'priority', 'visibility', 'pinned'])
            ->searchFields(['title', 'summary'])
            ->defaultOrder('published_at', 'desc')
            ->readOnly()
            ->unsetTrash()
            ->addOperation('read', static function (?string $id, ?array $row) use ($user): array {
                $marked = $user !== null && is_array($row) && (new ReadReceipts())->markRead((int) $row['id'], (int) $user->id);

                return ['read' => true, 'marked' => $marked];
            }, ['description' => lang('News.api.read')])
            ->addOperation('notices', static fn (): array => $user === null ? [] : self::notices($user), [
                'row' => false, 'method' => 'get', 'permission' => 'list', 'description' => lang('News.api.notices'),
            ])
            ->describe(lang('News.api.summary'), lang('News.api.description'))
            ->render();
    }

    /**
     * The user's notices (`?unread=1` only unread ones, `?limit=` 1–50, default 10).
     *
     * @return list<array<string, mixed>>
     */
    private static function notices(User $user): array
    {
        $request = service('request');
        $limit   = $request instanceof IncomingRequest ? (int) ($request->getGet('limit') ?? 10) : 10;
        $unread  = $request instanceof IncomingRequest && in_array((string) $request->getGet('unread'), ['1', 'true'], true);
        $rows    = (new NewsRepository())->notices($user, max(1, min(50, $limit)), $unread);
        $read    = (new ReadReceipts())->readAmong(array_map(static fn (array $row): int => (int) $row['id'], $rows), (int) $user->id);
        $list    = [];

        foreach ($rows as $row) {
            $list[] = [
                'id'           => Crud::maskId((int) $row['id'], 'news'),
                'slug'         => (string) $row['slug'],
                'priority'     => (string) $row['priority'],
                'pinned'       => (int) $row['pinned'] === 1,
                'title'        => (string) $row['title'],
                'summary'      => (string) $row['summary'],
                'published_at' => self::iso($row['published_at'] ?? null),
                'expires_at'   => self::iso($row['expires_at'] ?? null),
                'read'         => isset($read[(int) $row['id']]),
            ];
        }

        return $list;
    }

    private static function iso(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? Time::parse($value)->format(DATE_ATOM) : null;
    }

    /**
     * The token's user as a Shield entity (null without one: the `kpapy` filter refuses it first).
     */
    private static function user(): ?User
    {
        $user = Context::user();

        if ($user === null || $user instanceof User) {
            return $user;
        }

        $id = $user->id ?? null;

        return is_numeric($id) ? model(UserModel::class)->findById((int) $id) : null;
    }

    private static function categorySlug(mixed $id): ?string
    {
        if (! is_numeric($id)) {
            return null;
        }

        $row = db_connect()->table('news_categories')->select('slug')->where('id', (int) $id)->get()->getRowArray();

        return is_array($row) ? (string) $row['slug'] : null;
    }
}
