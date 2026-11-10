<?php

declare(strict_types=1);

namespace Modules\Dashboard\Tests;

use CodeIgniter\Test\TestResponse;
use Config\Services;
use Modules\Core\Cache\CoreCache;
use Modules\Dashboard\Tests\Fixtures\CountingWidget;
use Modules\Dashboard\Tests\Fixtures\FailingWidget;
use Modules\Settings\Tests\SettingsTestCase;

/**
 * Base of the Dashboard tests: the Auth fixture plus every manifest's declarations (the Core
 * widgets and Users' summary) and, on demand, two test widgets (failing, async counting).
 */
abstract class DashboardTestCase extends SettingsTestCase
{
    /**
     * Modules whose widgets the tests see: the Dashboard tests count exact widget lists, so the
     * widgets of modules added later (News…) are left out; their own tests add their module.
     *
     * @var list<string>
     */
    protected array $widgetModules = ['Core', 'Dashboard', 'Users'];

    protected function setUp(): void
    {
        parent::setUp();
        db_connect()->table('module_declarations')->where('kind', 'widget')->whereNotIn('module', $this->widgetModules)->delete();
        CountingWidget::$calls = 0;
        CountingWidget::$fail  = false;
        (new CoreCache())->invalidate('widgets');
    }

    /**
     * Declares the test widgets as if the Dashboard module listed them in its manifest.
     */
    protected function addTestWidgets(): void
    {
        foreach ([FailingWidget::class, CountingWidget::class] as $class) {
            db_connect()->table('module_declarations')->insert([
                'module' => 'Dashboard', 'kind' => 'widget', 'key' => $class, 'definition' => '[]',
            ]);
        }

        (new CoreCache())->invalidate('widgets');
    }

    /**
     * The ids of the widget cards on the dashboard, in order.
     *
     * @return list<string>
     */
    protected function cards(TestResponse $response): array
    {
        preg_match_all('/data-widget="([a-z0-9.-]+)"/', (string) $response->response()->getBody(), $matches);

        return $matches[1];
    }

    /**
     * Disables or enables a widget for a group as the group layout screen would.
     */
    protected function setGroupWidget(string $group, string $widget, bool $enabled): void
    {
        db_connect()->table('dashboard_group_widgets')->insert([
            'group_name' => $group, 'widget' => $widget, 'enabled' => $enabled ? 1 : 0,
            'position' => 10, 'size' => 'medium', 'options' => '[]',
        ]);
    }

    /**
     * A JSON POST from the dashboard script (CSRF header, Accept: application/json).
     *
     * @param array<string, mixed> $json
     */
    protected function postJson(string $path, array $json): TestResponse
    {
        $session                   = $_SESSION ?? [];
        $session['csrf_test_name'] = 'test-csrf-token';

        Services::resetSingle('security');
        Services::resetSingle('response');
        Services::resetSingle('auth');
        service('superglobals')->setCookieArray($this->cookies);

        return $this->withSession($session)
            ->withHeaders(['X-CSRF-TOKEN' => 'test-csrf-token', 'Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->withBody(json_encode($json, JSON_THROW_ON_ERROR))
            ->call('POST', $path);
    }
}
