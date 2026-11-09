<?php

declare(strict_types=1);

namespace Modules\Dashboard\Tests;

/**
 * Dashboard › Group layouts: `dashboard.configure` only, validation of sizes, positions and
 * options, the activity entry and the effect on the group's users.
 *
 * @internal
 */
final class GroupLayoutTest extends DashboardTestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private static function form(): array
    {
        return [
            'core.scheduled-tasks' => ['enabled' => '1', 'position' => '20', 'size' => 'small', 'options' => ['limit' => '3']],
            'core.quick-links'     => ['enabled' => '0', 'position' => '10', 'size' => 'small', 'options' => ['links' => [['ref' => 'menu:settings', 'position' => '10']]]],
            'users.summary'        => ['enabled' => '1', 'position' => '5', 'size' => 'full', 'options' => ['onlineMinutes' => '10']],
        ];
    }

    public function testOnlyConfiguratorsOpenIt(): void
    {
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->send('GET', 'backend/dashboard/groups')->assertStatus(403);
        $this->send('GET', 'backend/dashboard/groups/user')->assertStatus(403);
        $this->send('POST', 'backend/dashboard/groups/user', ['widgets' => self::form()])->assertStatus(403);
        $this->assertSame(0, db_connect()->table('dashboard_group_widgets')->countAllResults(), 'A forged POST changes nothing.');
    }

    public function testAnAdminHidesAWidgetForAGroupAndItsUsersNoLongerSeeIt(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->createUser('chief@example.com', 'admin');
        $this->login('boss@example.com');

        $page = (string) $this->send('GET', 'backend/dashboard/groups/admin')->response()->getBody();
        $this->assertStringContainsString(lang('Core.widgets.quickLinks.title'), $page);
        $this->assertStringContainsString('schedule.list', $page);

        $this->send('POST', 'backend/dashboard/groups/admin', ['widgets' => self::form()])->assertRedirectTo(site_url('backend/dashboard/groups/admin'));
        $this->assertCount(1, $this->entries('group_layout_saved'));

        $this->send('POST', 'logout');
        $this->login('chief@example.com');
        $this->assertSame(['users.summary', 'core.scheduled-tasks'], $this->cards($this->send('GET', 'backend')));
    }

    public function testInvalidValuesAreRefused(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        foreach ([
            ['users.summary', 'size', 'tiny'],
            ['users.summary', 'position', '-1'],
            ['core.scheduled-tasks', 'options', ['limit' => '500']],
            ['core.quick-links', 'options', ['links' => [['ref' => 'menu:evil.example']]]],
            ['core.quick-links', 'options', ['links' => [['ref' => 'url', 'label' => 'Evil', 'url' => 'javascript:alert(1)']]]],
            ['core.quick-links', 'options', ['links' => [['ref' => 'url', 'label' => 'Plain', 'url' => 'http://example.com/']]]],
            ['core.quick-links', 'options', ['links' => [['ref' => 'url', 'label' => 'Other host', 'url' => '//evil.example/x']]]],
            ['core.quick-links', 'options', ['links' => [['ref' => 'url', 'label' => 'Creds', 'url' => 'https://user:pw@example.com/']]]],
            ['core.quick-links', 'options', ['links' => [['ref' => 'url', 'label' => '', 'url' => 'https://example.com/']]]],
            ['core.quick-links', 'options', ['links' => array_fill(0, 21, ['ref' => 'menu:settings'])]],
        ] as [$widget, $key, $value]) {
            $form                  = self::form();
            $form[$widget][$key]   = $value;
            $this->send('POST', 'backend/dashboard/groups/admin', ['widgets' => $form])->assertRedirectTo(site_url('backend/dashboard/groups/admin'));
            $this->assertSame(0, db_connect()->table('dashboard_group_widgets')->countAllResults(), "{$widget} {$key} refused");
        }
    }

    public function testFreeLinksAndTheOrderChosenByTheAdministrator(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->createUser('ana@example.com', 'user');
        $this->login('boss@example.com');

        $form                                    = self::form();
        $form['core.quick-links']['enabled']     = '1';
        $form['core.quick-links']['options']     = ['links' => [
            ['ref' => 'menu:Core.notifications', 'position' => '30'],
            ['ref' => 'url', 'label' => 'Moodle de l\'institut', 'url' => 'https://moodle.example.org/course?id=3', 'position' => '10'],
            ['ref' => '', 'label' => 'ignored', 'url' => 'nothing', 'position' => '5'],
            ['ref' => 'menu:settings', 'position' => '40'],
            ['ref' => 'url', 'label' => 'Intern', 'url' => '/backend/profile', 'position' => '20'],
        ]];
        $this->send('POST', 'backend/dashboard/groups/user', ['widgets' => $form])->assertRedirectTo(site_url('backend/dashboard/groups/user'));

        $row = db_connect()->table('dashboard_group_widgets')->where('group_name', 'user')->where('widget', 'core.quick-links')->get()->getRowArray();
        $this->assertSame(['links' => [
            ['label' => 'Moodle de l\'institut', 'url' => 'https://moodle.example.org/course?id=3'],
            ['label' => 'Intern', 'url' => '/backend/profile'],
            ['menu' => 'Core.notifications'],
            ['menu' => 'settings'],
        ]], json_decode((string) $row['options'], true));

        $this->send('POST', 'logout');
        $this->login('ana@example.com');
        $body = (string) $this->send('GET', 'backend')->response()->getBody();

        preg_match_all('/gap-2" href="([^"]+)"/', $body, $links);
        $this->assertSame([
            esc('https://moodle.example.org/course?id=3', 'attr'),
            esc(site_url('backend/profile'), 'attr'),
            esc(site_url('backend/notifications'), 'attr'),
        ], $links[1], 'In the administrator\'s order; "settings" is hidden from a user who cannot open it.');
        $this->assertStringContainsString('href="' . esc('https://moodle.example.org/course?id=3', 'attr') . '" target="_blank" rel="noopener noreferrer"', $body);
        $this->assertStringContainsString(esc("Moodle de l'institut"), $body);
    }

    public function testUnknownGroupsAreNotFound(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        foreach (['GET', 'POST'] as $method) {
            try {
                $this->send($method, 'backend/dashboard/groups/nobody', ['widgets' => self::form()])->assertStatus(404);
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, db_connect()->table('dashboard_group_widgets')->countAllResults());
    }

    public function testTheScreenSaysWhenTheGroupLacksAWidgetsPermission(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $page = (string) $this->send('GET', 'backend/dashboard/groups/user')->response()->getBody();
        $this->assertStringContainsString(esc(lang('Dashboard.groups.noPermission', ['users-users.list'])), $page);
    }
}
