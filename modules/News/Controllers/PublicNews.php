<?php

declare(strict_types=1);

namespace Modules\News\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\FrontendController;
use Modules\Core\Security\HtmlSanitizer;
use Modules\News\Services\FeedWriter;
use Modules\News\Services\ListPage;
use Modules\News\Services\NewsRepository;
use Modules\News\Storage\CoverUploads;

/**
 * The public site of the news: **only `public` posts** that are published and not expired,
 * whoever is signed in (private and group posts are read in the back office, D-00-42).
 *
 * - `GET news` (`?page=`), `GET news/category/{slug}`, `GET news/{slug}`;
 * - `GET news/feed.xml`: RSS 2.0 of the latest public posts (`News.feedItems`).
 *
 * Page size: `News.perPage`. Anything not public is a 404. When a page is marked as the main news
 * list (`News.listPage`, D-46-03), `GET news` redirects to it (301); categories and articles keep
 * their URLs.
 */
final class PublicNews extends FrontendController
{
    public function index(): RedirectResponse|string
    {
        // A page marked as the main news list (D-46-03): `/news` sends the visitor there.
        $page = (new ListPage())->url();

        if ($page !== null) {
            return redirect()->to($page, 301);
        }

        return $this->listing(null);
    }

    public function category(string $slug): string
    {
        $category = (new NewsRepository())->categoryBySlug($slug);

        if ($category === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->listing($category);
    }

    public function show(string $slug): string
    {
        $post = (new NewsRepository())->find(null, $slug, true);

        if ($post === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $cover = CoverUploads::urlOf(is_string($post['cover']) ? $post['cover'] : null);

        $this->meta([
            'description' => (string) $post['summary'],
            'canonical'   => site_url('news/' . $post['slug']),
            'og'          => true,
            'type'        => 'article',
            'image'       => $cover,
        ]);

        return $this->render('Modules\News\Views\public\show', [
            'title'       => (string) $post['title'],
            'post'        => $post,
            'body'        => HtmlSanitizer::clean((string) $post['body']),
            'cover'       => $cover,
            'bannerUrl'   => CoverUploads::urlOf(is_string($post['category_banner'] ?? null) ? $post['category_banner'] : null),
            'breadcrumbs' => [['label' => lang('News.publicTitle'), 'url' => site_url('news')], ['label' => (string) $post['title']]],
        ]);
    }

    public function feed(): ResponseInterface
    {
        $limit = max(1, min(100, (int) setting('News.feedItems')));
        $rows  = (new NewsRepository())->page(null, 1, $limit, [], true)['rows'];

        $xml = FeedWriter::rss(
            site_name() . ' · ' . lang('News.publicTitle'),
            site_url('news'),
            site_url('news/feed.xml'),
            lang('News.publicDescription'),
            service('request')->getLocale(),
            $rows,
        );

        return $this->response->setContentType('application/rss+xml', 'UTF-8')->setBody($xml);
    }

    /**
     * @param array{id: int, slug: string, name: string, banner: string|null}|null $category
     */
    private function listing(?array $category): string
    {
        $perPage = max(1, min(100, (int) setting('News.perPage')));
        $page    = max(1, (int) $this->request->getGet('page'));
        $repo    = new NewsRepository();
        $result  = $repo->page(null, $page, $perPage, $category === null ? [] : ['category' => $category['id']], true);

        if ($page > 1 && $result['rows'] === []) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path  = $category === null ? 'news' : 'news/category/' . $category['slug'];
        $pager = service('pager');
        $pager->setPath($path);

        $title = $category === null ? lang('News.publicTitle') : $category['name'];

        $this->meta([
            'description' => lang('News.publicDescription'),
            'canonical'   => site_url($path) . ($page > 1 ? '?page=' . $page : ''),
        ]);

        $crumbs = $category === null ? [] : [['label' => lang('News.publicTitle'), 'url' => site_url('news')], ['label' => $title]];
        $banner = $category === null ? null : CoverUploads::urlOf($category['banner']);

        return $this->render('Modules\News\Views\public\index', [
            'title'       => $title,
            'items'       => $result['rows'],
            'categories'  => $repo->categories(),
            'current'     => $category['slug'] ?? null,
            'pager'       => $result['total'] > $perPage ? $pager->makeLinks($page, $perPage, $result['total']) : '',
            'feedUrl'     => site_url('news/feed.xml'),
            'bannerUrl'   => $banner,
            // With a banner, its title is the page's <h1> and the view draws the breadcrumbs.
            'trail'       => $banner === null ? [] : $crumbs,
            'breadcrumbs' => $banner === null ? $crumbs : [],
        ]);
    }
}
