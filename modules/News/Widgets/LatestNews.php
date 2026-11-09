<?php

declare(strict_types=1);

namespace Modules\News\Widgets;

use CodeIgniter\Shield\Entities\User;
use Kpcrudy\Crud;
use Modules\Core\Widgets\Widget;
use Modules\News\Services\NewsRepository;

/**
 * Widget "Latest news": the newest articles the user may read, with a link to the reader screen.
 * Needs `news-read.view`.
 */
final class LatestNews extends Widget
{
    public function id(): string
    {
        return 'news.latest';
    }

    public function title(): string
    {
        return lang('News.widgets.latest.title');
    }

    public function icon(): string
    {
        return 'bi bi-newspaper';
    }

    public function permission(): string
    {
        return 'news-read.view';
    }

    public function settings(): array
    {
        return [
            'limit' => [
                'type'    => 'int',
                'default' => 5,
                'min'     => 1,
                'max'     => 20,
                'label'   => 'News.widgets.limit',
            ],
        ];
    }

    public function render(array $options): string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return '';
        }

        $rows = (new NewsRepository())->page($user, 1, (int) $options['limit'], ['type' => 'article'])['rows'];

        if ($rows === []) {
            return $this->empty(lang('News.widgets.latest.empty'), 'bi bi-newspaper');
        }

        $items = array_map(static fn (array $row): array => [
            'title'    => (string) $row['title'],
            'category' => $row['category'] === null ? null : (string) $row['category'],
            'date'     => (string) $row['published_at'],
            'url'      => site_url('backend/news/read/' . Crud::maskId((int) $row['id'], 'news')),
        ], $rows);

        return $this->view('Modules\News\Views\widgets\latest', ['items' => $items, 'url' => site_url('backend/news')]);
    }
}
