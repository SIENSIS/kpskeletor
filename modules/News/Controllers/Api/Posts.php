<?php

declare(strict_types=1);

namespace Modules\News\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Kpapy\Auth\Context;
use Kpapy\Resource;
use Modules\News\Controllers\Posts as PostsCrud;
use Modules\News\Services\NewsVisibility;
use Modules\News\Services\ReadReceipts;
use Modules\News\Storage\CoverUploads;

/**
 * API resource `news` (`GET api/v1/news`, `GET api/v1/news/{id}`; kpapy, read-only): the posts
 * the token's user may read, with the same rules as the reader screen (`NewsVisibility`, through
 * a `where()` condition, so list, details, search and filters are all limited). Permissions
 * `news-read.list` / `news-read.show` (D-17-01), granted to every group; the scopes of a token
 * still narrow them. Ids are masked like the back office's (`app.maskKey`, context `news`).
 *
 * Read receipts and the notices endpoint arrive in session 26.
 */
final class Posts extends Controller
{
    public function index(): ResponseInterface
    {
        $user                = self::user();
        [$column, $readable] = NewsVisibility::condition($user);

        return (new Resource())
            ->setTable('news')
            ->setResource('news-read')
            ->where($column, $readable)
            ->setTranslations('news_translations', 'news_id', 'locale', ['title', 'summary', 'body'], array_keys(PostsCrud::LOCALES), ['required' => ['ca']])
            ->fields(['id', 'slug', 'type', 'priority', 'pinned', 'visibility', 'category', 'title', 'summary', 'body', 'cover_url', 'published_at', 'expires_at', 'read'])
            ->listFields(['id', 'slug', 'type', 'priority', 'pinned', 'visibility', 'category', 'title', 'summary', 'cover_url', 'published_at', 'expires_at', 'read'])
            ->fieldType('pinned', 'boolean')
            ->callbackField('category', static fn ($value, array $row): ?string => self::categorySlug($row['category_id'] ?? null))
            ->callbackField('cover_url', static fn ($value, array $row): ?string => CoverUploads::urlOf(is_string($row['cover'] ?? null) ? $row['cover'] : null))
            ->callbackField('read', static fn ($value, array $row): bool => $user !== null && (new ReadReceipts())->isRead((int) $row['id'], (int) $user->id))
            ->filters(['type', 'priority', 'visibility', 'pinned'])
            ->searchFields(['title', 'summary'])
            ->defaultOrder('published_at', 'desc')
            ->readOnly()
            ->unsetTrash()
            ->describe(lang('News.api.summary'), lang('News.api.description'))
            ->render();
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
