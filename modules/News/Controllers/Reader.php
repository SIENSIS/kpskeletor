<?php

declare(strict_types=1);

namespace Modules\News\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use Kpcrudy\Crud;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Security\HtmlSanitizer;
use Modules\News\Services\NewsRepository;
use Modules\News\Services\NewsVisibility;
use Modules\News\Services\ReadReceipts;
use Modules\News\Storage\CoverUploads;

/**
 * The reader screen (resource `news-read`, `news-read.view`, every group; D-00-42): the posts the
 * signed-in user may read (public, private and their groups'), the details page and "mark as
 * read". Ordinary users never need `news-posts.list`.
 *
 * - `GET backend/news` (`?type=article|notice`, `?category=<id>`, `?unread=1`, `?page=`);
 * - `GET backend/news/read/{code}` (masked id; anything not readable is a 404);
 * - `POST backend/news/read/{code}/mark` (`return`: an internal path, `safe_redirect()`).
 */
final class Reader extends BackendController
{
    private const PER_PAGE = 12;

    public function index(): string
    {
        $user    = $this->currentUser();
        $type    = (string) $this->request->getGet('type');
        $filters = [
            'type'     => in_array($type, NewsVisibility::TYPES, true) ? $type : '',
            'category' => max(0, (int) $this->request->getGet('category')),
            'unread'   => $this->request->getGet('unread') === '1',
        ];
        $page   = max(1, (int) $this->request->getGet('page'));
        $result = (new NewsRepository())->page($user, $page, self::PER_PAGE, $filters);
        $read   = (new ReadReceipts())->readAmong(array_map(static fn (array $row): int => (int) $row['id'], $result['rows']), (int) $user->id);

        $items = array_map(static fn (array $row): array => self::item($row, isset($read[(int) $row['id']])), $result['rows']);
        $pager = service('pager');
        $pager->setPath('backend/news');

        return $this->render('Modules\News\Views\reader\index', [
            'title'      => lang('News.readerTitle'),
            'icon'       => 'bi bi-newspaper',
            'items'      => $items,
            'filters'    => $filters,
            'categories' => (new NewsRepository())->categories(),
            'pager'      => $result['total'] > self::PER_PAGE ? $pager->makeLinks($page, self::PER_PAGE, $result['total']) : '',
            'total'      => $result['total'],
        ]);
    }

    public function show(string $code): string
    {
        $user = $this->currentUser();
        $row  = $this->readable($code, $user);
        $read = (new ReadReceipts())->isRead((int) $row['id'], (int) $user->id);

        $this->breadcrumb(lang('News.readerTitle'), site_url('backend/news'));

        return $this->render('Modules\News\Views\reader\show', [
            'title' => (string) $row['title'],
            // Sanitised when saved; cleaned again here in case a row was written another way.
            'post'  => self::item($row, $read) + ['body' => HtmlSanitizer::clean((string) $row['body'])],
            'cover' => CoverUploads::urlOf(is_string($row['cover']) ? $row['cover'] : null),
        ]);
    }

    public function mark(string $code): RedirectResponse
    {
        $user = $this->currentUser();
        $row  = $this->readable($code, $user);

        (new ReadReceipts())->markRead((int) $row['id'], (int) $user->id);
        $this->flash(lang('News.markedRead'), 'success');

        $return = $this->request->getPost('return');

        return safe_redirect(is_string($return) ? $return : null, 'backend/news/read/' . $code);
    }

    /**
     * The view data of a post in lists.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function item(array $row, bool $read): array
    {
        $code = Crud::maskId((int) $row['id'], 'news');

        return [
            'title'      => (string) $row['title'],
            'summary'    => (string) $row['summary'],
            'type'       => (string) $row['type'],
            'priority'   => (string) $row['priority'],
            'pinned'     => (int) $row['pinned'] === 1,
            'visibility' => (string) $row['visibility'],
            'category'   => $row['category'] === null ? null : (string) $row['category'],
            'date'       => (string) $row['published_at'],
            'expires'    => $row['expires_at'] === null ? null : (string) $row['expires_at'],
            'read'       => $read,
            'url'        => site_url('backend/news/read/' . $code),
            'markUrl'    => site_url('backend/news/read/' . $code . '/mark'),
        ];
    }

    /**
     * The post behind a masked id if the user may read it; otherwise a 404 (the existence of a
     * post the user may not read is not revealed).
     *
     * @return array<string, mixed>
     */
    private function readable(string $code, User $user): array
    {
        $id  = Crud::unmaskId($code, 'news');
        $row = $id !== null && ctype_digit($id) ? (new NewsRepository())->find($user, (int) $id) : null;

        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $row;
    }
}
