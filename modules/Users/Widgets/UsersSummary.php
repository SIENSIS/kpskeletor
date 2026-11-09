<?php

declare(strict_types=1);

namespace Modules\Users\Widgets;

use CodeIgniter\I18n\Time;
use Modules\Core\Widgets\AsyncWidget;
use Modules\Core\Widgets\WidgetSizes;

/**
 * Users widget "Users summary" (async, cached per user for two minutes): active accounts, users
 * seen in the last minutes (open sessions of Auth's `user_sessions`) and active accounts without
 * two-factor authentication. Needs `users-users.list`.
 */
final class UsersSummary extends AsyncWidget
{
    public function id(): string
    {
        return 'users.summary';
    }

    public function title(): string
    {
        return lang('Users.widget.title');
    }

    public function icon(): string
    {
        return 'bi bi-people';
    }

    public function permission(): string
    {
        return 'users-users.list';
    }

    public function sizes(): array
    {
        return [WidgetSizes::MEDIUM, WidgetSizes::SMALL, WidgetSizes::LARGE, WidgetSizes::FULL];
    }

    public function settings(): array
    {
        return [
            'onlineMinutes' => [
                'type'    => 'int',
                'default' => 5,
                'min'     => 1,
                'max'     => 60,
                'label'   => 'Users.widget.onlineMinutes',
            ],
        ];
    }

    public function cacheSeconds(): int
    {
        return 120;
    }

    public function refreshSeconds(): int
    {
        return 120;
    }

    public function data(array $options): array
    {
        $db    = db_connect();
        $since = Time::now()->subMinutes((int) $options['onlineMinutes'])->format('Y-m-d H:i:s');

        $active = $db->table('users')->where('active', 1)->groupStart()->where('status', null)->orWhere('status !=', 'banned')->groupEnd()->countAllResults();

        $online = $db->table('user_sessions')->select('user_id')->distinct()
            ->where('ended_at', null)->where('last_seen_at >=', $since)
            ->get()->getNumRows();

        $withTwoFactor = $db->table('user_two_factor')->select('user_two_factor.user_id')->distinct()
            ->join('users', 'users.id = user_two_factor.user_id')
            ->where('user_two_factor.enabled_at IS NOT NULL')->where('users.active', 1)
            ->groupStart()->where('users.status', null)->orWhere('users.status !=', 'banned')->groupEnd()
            ->get()->getNumRows();

        return [
            'active'        => $active,
            'online'        => $online,
            'withoutTwoFactor' => max(0, $active - $withTwoFactor),
            'onlineMinutes' => (int) $options['onlineMinutes'],
        ];
    }

    public function renderData(array $data, array $options): string
    {
        return $this->view('Modules\Users\Views\widgets\summary', $data + ['url' => site_url('backend/users')]);
    }
}
