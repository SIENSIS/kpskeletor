<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

use Config\Services;
use Modules\Core\Theme\ThemeRegistry;

/**
 * The child theme of tutorial 9, `themes/tasks-demo` (child of `default`): it overrides the Tasks
 * public page, changes the primary colour and the logo, and keeps the parent's layout and CSS;
 * no inline code (strict CSP).
 *
 * @internal
 */
final class ChildThemeTest extends TasksTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ThemeRegistry::reset();
        setting()->set('Theme.public', 'tasks-demo');
        Services::resetSingle('theme');
    }

    protected function tearDown(): void
    {
        setting()->forget('Theme.public');
        Services::resetSingle('theme');
        ThemeRegistry::reset();
        parent::tearDown();
    }

    public function testTheThemeIsAValidChildOfTheDefaultTheme(): void
    {
        $this->assertFileExists(ROOTPATH . 'themes/tasks-demo/Theme.php');
        $this->assertFileExists(ROOTPATH . 'themes/tasks-demo/Views/overrides/Tasks/public/index.php');
        $this->assertFileDoesNotExist(ROOTPATH . 'themes/tasks-demo/Views/layouts/public.php', 'Layouts come from the parent.');

        $registry = new ThemeRegistry();
        $this->assertSame('default', $registry->get('tasks-demo')->parent);
        $this->assertSame(['tasks-demo', 'default'], $registry->chain('tasks-demo'));
    }

    public function testItOverridesTheTasksPublicPageAndItsColour(): void
    {
        $this->task('Pública', ['visibility' => 'public']);

        $response = $this->send('GET', 'tasks');
        $response->assertOK();
        $html = (string) $response->response()->getBody();

        $this->assertStringContainsString('tasks-demo-board', $html, 'The theme\'s view of the Tasks page.');
        $this->assertStringContainsString('Pública', $html);
        $this->assertStringContainsString('--bs-primary: #6f42c1;', $html);
        $this->assertStringContainsString('themes/tasks-demo/img/logo.svg', $html);
        $this->assertStringContainsString('themes/tasks-demo/css/theme.css', $html);
        $this->assertStringContainsString('themes/default/css/theme.css', $html);
        $this->assertStringContainsString('class="layout-public', $html);

        // A Tasks page it does not override keeps the module's view.
        $this->assertStringNotContainsString('tasks-demo-board', $this->body('GET', 'tasks/request'));

        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bnonce=)(?![^>]*\bsrc=)(?![^>]*type="application\/json")[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $html);
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/', $html);
    }
}
