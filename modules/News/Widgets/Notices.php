<?php

declare(strict_types=1);

namespace Modules\News\Widgets;

use CodeIgniter\Shield\Entities\User;
use Kpcrudy\Crud;
use Modules\Core\Widgets\Widget;
use Modules\Core\Widgets\WidgetSizes;
use Modules\News\Services\NewsRepository;
use Modules\News\Services\ReadReceipts;

/**
 * Widget "Notices": the notices the user may read (pinned first, then by priority and date),
 * each one with "mark as read". Needs `news-read.view`; every query goes through the visibility
 * rules, so a user never sees a notice of another group.
 */
final class Notices extends Widget
{
    public function id(): string
    {
        return 'news.notices';
    }

    public function title(): string
    {
        return lang('News.widgets.notices.title');
    }

    public function icon(): string
    {
        return 'bi bi-megaphone';
    }

    public function permission(): string
    {
        return 'news-read.view';
    }

    public function sizes(): array
    {
        return [WidgetSizes::MEDIUM, WidgetSizes::LARGE, WidgetSizes::FULL, WidgetSizes::SMALL];
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
            'unreadOnly' => [
                'type'    => 'bool',
                'default' => false,
                'label'   => 'News.widgets.notices.unreadOnly',
            ],
        ];
    }

    public function render(array $options): string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return '';
        }

        $notices = (new NewsRepository())->notices($user, (int) $options['limit'], (bool) $options['unreadOnly']);

        if ($notices === []) {
            return $this->empty(lang('News.widgets.notices.empty'), 'bi bi-megaphone');
        }

        $read  = (new ReadReceipts())->readAmong(array_map(static fn (array $row): int => (int) $row['id'], $notices), (int) $user->id);
        $items = [];

        foreach ($notices as $notice) {
            $code    = Crud::maskId((int) $notice['id'], 'news');
            $items[] = [
                'title'    => (string) $notice['title'],
                'summary'  => (string) $notice['summary'],
                'priority' => (string) $notice['priority'],
                'pinned'   => (int) $notice['pinned'] === 1,
                'date'     => (string) $notice['published_at'],
                'read'     => isset($read[(int) $notice['id']]),
                'url'      => site_url('backend/news/read/' . $code),
                'markUrl'  => site_url('backend/news/read/' . $code . '/mark'),
            ];
        }

        return $this->view('Modules\News\Views\widgets\notices', [
            'items' => $items,
            'url'   => site_url('backend/news?type=notice'),
        ]);
    }
}
