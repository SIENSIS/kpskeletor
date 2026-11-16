<?php

declare(strict_types=1);

namespace Modules\News\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;
use Kpapy\Resource;
use Modules\Core\Security\HtmlSanitizer;
use Modules\News\Controllers\Posts as PostsCrud;
use Modules\News\Services\NewsVisibility;
use Modules\News\Storage\CoverUploads;

/**
 * Public API resource `public/news` (`GET api/v1/public/news`, `GET api/v1/public/news/{id}`;
 * kpapy, `kpapy:public` filter, D-26-01): the **public** posts, the same ones as the public site
 * and the RSS feed (`NewsVisibility::apply(..., publicOnly: true)`), without a token. Only `GET`
 * routes exist; the resource is read-only, asks no permission resolver (nobody is signed in) and
 * allows only `list` and `show`. Rate-limited per IP (`Core.rateLimitApiIp`). Ids are the same
 * masked codes as in `api/v1/news` and the back office (context `news`).
 */
final class PublicNews extends Controller
{
    public const PATH = 'api/v1/public/news';

    public function index(): ResponseInterface
    {
        return (new Resource())
            ->setBasePath(self::PATH)
            ->setTable('news')
            ->setResource('news-public')
            ->setPermissionResolver(null)
            ->setPermission('list', true)
            ->setPermission('show', true)
            ->where(static fn (BaseBuilder $builder): BaseBuilder => NewsVisibility::apply($builder, null, 'news', true))
            ->setTranslations('news_translations', 'news_id', 'locale', ['title', 'summary', 'body'], array_keys(PostsCrud::LOCALES), ['required' => ['ca']])
            ->fields(['id', 'slug', 'type', 'priority', 'pinned', 'category', 'title', 'summary', 'body', 'cover_url', 'url', 'published_at', 'expires_at'])
            ->listFields(['id', 'slug', 'type', 'priority', 'pinned', 'category', 'title', 'summary', 'cover_url', 'url', 'published_at', 'expires_at'])
            ->fieldType('pinned', 'bool')
            ->callbackField('category', static fn ($value, array $row): ?string => self::categorySlug($row['category_id'] ?? null))
            ->callbackField('cover_url', static fn ($value, array $row): ?string => CoverUploads::urlOf(is_string($row['cover'] ?? null) ? $row['cover'] : null))
            ->callbackField('url', static fn ($value, array $row): string => site_url('news/' . rawurlencode((string) ($row['slug'] ?? ''))))
            ->transform(static function (array $item): array {
                if (is_string($item['body'] ?? null)) {
                    $item['body'] = HtmlSanitizer::clean($item['body']);
                } elseif (is_array($item['body'] ?? null)) {
                    $item['body'] = array_map(static fn ($text): ?string => is_string($text) ? HtmlSanitizer::clean($text) : null, $item['body']);
                }

                return $item;
            })
            ->filters(['type', 'priority', 'pinned'])
            ->searchFields(['title', 'summary'])
            ->defaultOrder('published_at', 'desc')
            ->readOnly()
            ->unsetTrash()
            ->describe(lang('News.api.publicSummary'), lang('News.api.publicDescription'))
            ->render();
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
