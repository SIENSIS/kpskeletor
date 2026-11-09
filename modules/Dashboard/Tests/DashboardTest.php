<?php

declare(strict_types=1);

namespace Modules\Dashboard\Tests;

/**
 * The home and its widgets: permission **and** group enablement, failure isolation, the async
 * data URL (403 rules, cache per user, errors), the user layout and "restore the group layout"
 * (acceptance criterion 6 for the core widgets).
 *
 * @internal
 */
final class DashboardTest extends DashboardTestCase
{
    public function testGuestsAreSentToTheLogin(): void
    {
        $this->send('GET', 'backend')->assertRedirect();
        $this->assertSame([], $this->cards($this->send('GET', 'backend')));
    }

    public function testALoginLandsOnTheDashboard(): void
    {
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com')->assertRedirectTo(site_url('backend'));
    }

    public function testWidgetsNeedTheirPermission(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->assertSame(['core.scheduled-tasks', 'core.quick-links', 'users.summary'], $this->cards($this->send('GET', 'backend')));

        $this->send('POST', 'logout');
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $this->assertSame(['core.quick-links'], $this->cards($this->send('GET', 'backend')), 'A user without schedule.list nor users-users.list only gets the quick links.');
    }

    public function testAWidgetDisabledForTheGroupIsHiddenFromItsUsers(): void
    {
        $this->setGroupWidget('admin', 'core.scheduled-tasks', false);
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->assertNotContains('core.scheduled-tasks', $this->cards($this->send('GET', 'backend')));
    }

    public function testAnotherGroupOfTheUserCanEnableIt(): void
    {
        $this->setGroupWidget('admin', 'users.summary', false);
        $boss = $this->createUser('boss@example.com', 'admin');
        $boss->addGroup('editor');
        $this->login('boss@example.com');

        $this->assertContains('users.summary', $this->cards($this->send('GET', 'backend')), 'Enabled for "editor" (no row: default) and the user holds the permission.');
    }

    public function testQuickLinksOnlyShowWhatTheUserCanOpen(): void
    {
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $body = (string) $this->send('GET', 'backend')->response()->getBody();

        $this->assertStringContainsString(site_url('backend/notifications'), $body);
        $this->assertStringNotContainsString(site_url('backend/users'), $body);
        $this->assertStringNotContainsString(site_url('backend/settings'), $body);
    }

    public function testAFailingWidgetShowsAnErrorCardAndTheRestOfThePage(): void
    {
        $this->addTestWidgets();
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $response = $this->send('GET', 'backend');
        $response->assertOK();
        $this->assertContains('dashboard.failing', $this->cards($response));
        $this->assertContains('core.quick-links', $this->cards($response));
        $response->assertSee(lang('Core.widgets.failed'));
        $response->assertDontSee('secret detail');
    }

    public function testTheAsyncUrlAnswersTheWidgetData(): void
    {
        $this->addTestWidgets();
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->assertStringContainsString(esc(site_url('backend/dashboard/widgets/dashboard.counting/data'), 'attr'), (string) $this->send('GET', 'backend')->response()->getBody());
        $response = $this->send('GET', 'backend/dashboard/widgets/dashboard.counting/data');
        $response->assertOK();
        $data     = json_decode((string) $response->response()->getBody(), true);

        $this->assertSame('dashboard.counting', $data['widget']);
        $this->assertSame(1, $data['data']['calls']);
        $this->assertSame('<p class="counting">calls: 1</p>', $data['html']);
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));

        $users = $this->send('GET', 'backend/dashboard/widgets/users.summary/data');
        $users->assertOK();
        $this->assertSame(1, json_decode((string) $users->response()->getBody(), true)['data']['active']);
    }

    public function testTheAsyncDataIsCachedPerUser(): void
    {
        $this->addTestWidgets();
        $this->createUser('boss@example.com', 'admin');
        $this->createUser('chief@example.com', 'admin');

        $this->login('boss@example.com');
        $this->send('GET', 'backend/dashboard/widgets/dashboard.counting/data')->assertOK();

        $second = json_decode((string) $this->send('GET', 'backend/dashboard/widgets/dashboard.counting/data')->response()->getBody(), true);
        $this->assertSame(1, $second['data']['calls'], 'Second request served from the cache.');

        $this->send('POST', 'logout');
        $this->login('chief@example.com');
        $other = json_decode((string) $this->send('GET', 'backend/dashboard/widgets/dashboard.counting/data')->response()->getBody(), true);
        $this->assertSame(2, $other['data']['calls'], 'Another user never gets the first user\'s data.');
    }

    public function testTheAsyncUrlIsForbiddenWithoutThePermission(): void
    {
        $this->addTestWidgets();
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->send('GET', 'backend/dashboard/widgets/dashboard.counting/data')->assertStatus(403);
        $this->send('GET', 'backend/dashboard/widgets/users.summary/data')->assertStatus(403);
        $this->assertSame(0, \Modules\Dashboard\Tests\Fixtures\CountingWidget::$calls);
        $this->assertCount(2, $this->entries('widget_forbidden'));
    }

    public function testTheAsyncUrlIsForbiddenWhenTheGroupDoesNotEnableIt(): void
    {
        $this->addTestWidgets();
        $this->setGroupWidget('admin', 'dashboard.counting', false);
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->assertNotContains('dashboard.counting', $this->cards($this->send('GET', 'backend')));
        $this->send('GET', 'backend/dashboard/widgets/dashboard.counting/data')->assertStatus(403);
    }

    public function testUnknownAndServerSideWidgetsHaveNoDataUrl(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('GET', 'backend/dashboard/widgets/core.quick-links/data')->assertStatus(403);
        $this->send('GET', 'backend/dashboard/widgets/nope.nothing/data')->assertStatus(403);
        $this->send('GET', 'backend/dashboard/widgets/Core.Quick_Links/data')->assertStatus(403);
    }

    public function testAFailingAsyncWidgetAnswersAGenericError(): void
    {
        $this->addTestWidgets();
        \Modules\Dashboard\Tests\Fixtures\CountingWidget::$fail = true;
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $response = $this->send('GET', 'backend/dashboard/widgets/dashboard.counting/data');
        $response->assertStatus(500);
        $this->assertSame(['error' => lang('Core.widgets.failed')], json_decode((string) $response->response()->getBody(), true));
    }

    public function testAUserReordersResizesAndHidesTheirWidgets(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->assertStringContainsString(esc('widgets[users.summary]', 'attr') . '[position]', (string) $this->send('GET', 'backend/dashboard/customize')->response()->getBody());
        $this->send('POST', 'backend/dashboard/layout', ['widgets' => [
            'core.scheduled-tasks' => ['position' => '30', 'size' => 'full', 'visible' => '1'],
            'core.quick-links'     => ['position' => '20', 'size' => 'small', 'visible' => '0'],
            'users.summary'        => ['position' => '10', 'size' => 'giant', 'visible' => '1'],
        ]])->assertRedirectTo(site_url('backend'));

        $page = $this->send('GET', 'backend');
        $this->assertSame(['users.summary', 'core.scheduled-tasks'], $this->cards($page));
        $this->assertStringContainsString('data-hidden-widget="core.quick-links"', (string) $page->response()->getBody());
        $this->assertStringContainsString('data-widget="core.scheduled-tasks" data-size="full"', (string) $page->response()->getBody());
        $this->assertStringContainsString('data-widget="users.summary" data-size="medium"', (string) $page->response()->getBody(), 'A size the widget does not allow falls back to its default.');
    }

    public function testTheScriptSavesJsonAndForgedWidgetsAreIgnored(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->postJson('backend/dashboard/layout', ['widgets' => [
            ['id' => 'users.summary', 'size' => 'full', 'hidden' => false],
            ['id' => 'core.quick-links', 'size' => 'large', 'hidden' => false],
        ]])->assertOK();

        $row = db_connect()->table('dashboard_user_layouts')->where('user_id', $ana->id)->get()->getRowArray();
        $this->assertSame([['id' => 'core.quick-links', 'size' => 'large', 'hidden' => false]], json_decode((string) $row['layout'], true), 'Widgets the user cannot see are never stored.');
        $this->send('GET', 'backend/dashboard/widgets/users.summary/data')->assertStatus(403);

        $this->postJson('backend/dashboard/layout', ['nonsense' => true])->assertStatus(422);
    }

    public function testRestoringTheGroupLayoutForgetsTheUsersOwn(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->send('POST', 'backend/dashboard/layout', ['widgets' => [
            'users.summary'        => ['position' => '1', 'size' => 'medium', 'visible' => '1'],
            'core.scheduled-tasks' => ['position' => '2', 'size' => 'medium', 'visible' => '0'],
        ]]);
        $this->assertSame(['users.summary', 'core.quick-links'], $this->cards($this->send('GET', 'backend')));
        $this->assertStringContainsString(lang('Dashboard.restore'), (string) $this->send('GET', 'backend')->response()->getBody());

        $this->send('POST', 'backend/dashboard/layout/reset')->assertRedirectTo(site_url('backend'));

        $this->assertSame(['core.scheduled-tasks', 'core.quick-links', 'users.summary'], $this->cards($this->send('GET', 'backend')));
        $this->assertSame(0, db_connect()->table('dashboard_user_layouts')->countAllResults());
    }

    public function testTheGroupOrderAppliesUntilTheUserChangesIt(): void
    {
        db_connect()->table('dashboard_group_widgets')->insertBatch([
            ['group_name' => 'admin', 'widget' => 'users.summary', 'enabled' => 1, 'position' => 1, 'size' => 'full', 'options' => '[]'],
            ['group_name' => 'admin', 'widget' => 'core.quick-links', 'enabled' => 1, 'position' => 2, 'size' => 'small', 'options' => '{"links":["settings"]}'],
        ]);
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $page = $this->send('GET', 'backend');
        $this->assertSame(['users.summary', 'core.quick-links', 'core.scheduled-tasks'], $this->cards($page));
        $this->assertStringContainsString('data-widget="users.summary" data-size="full"', (string) $page->response()->getBody());
        $this->assertStringContainsString('href="' . esc(site_url('backend/settings'), 'attr') . '"', (string) $page->response()->getBody());
        $this->assertStringNotContainsString('gap-2" href="' . esc(site_url('backend/notifications'), 'attr') . '"', (string) $page->response()->getBody());
    }

    public function testTheMenuMarksTheHomeOnlyOnItself(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->assertMatchesRegularExpression('/class="nav-link[^"]*active"[^>]*href="' . preg_quote(site_url('backend'), '/') . '"/', (string) $this->send('GET', 'backend')->response()->getBody());
        $this->assertDoesNotMatchRegularExpression('/class="nav-link[^"]*active"[^>]*href="' . preg_quote(site_url('backend'), '/') . '"/', (string) $this->send('GET', 'backend/dashboard/groups')->response()->getBody());
    }
}
