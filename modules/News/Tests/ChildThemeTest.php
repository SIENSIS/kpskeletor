<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use Config\Services;
use Modules\Core\Tests\ThemeFixturesTrait;
use Modules\Core\Theme\ThemeRegistry;

/**
 * Acceptance criterion 7 through a real request: a child of the default theme that overrides
 * one module view (`overrides/News/public/index.php`) and one colour changes the public news
 * page, and everything else (layout, partials, other views, the theme's CSS) is inherited
 * without copying it. The page has no inline script without a nonce.
 *
 * @internal
 */
final class ChildThemeTest extends NewsTestCase
{
    use ThemeFixturesTrait;

    private string $child = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->child = $this->uniqueSlug('corp');
        $this->makeDefaultChild($this->child, "public array \$options = ['primaryColor' => ['default' => '#6f42c1']];", [
            'Views/overrides/News/public/index.php' => <<<'PHP'
                <?= $this->extend($layout) ?>
                <?= $this->section('content') ?>
                <h1 class="child-news"><?= esc($title) ?></h1>
                <ul><?php foreach ($items as $item): ?><li class="child-item"><?= esc($item['title']) ?></li><?php endforeach ?></ul>
                <?= $this->endSection() ?>
                PHP,
        ]);
        ThemeRegistry::reset();
        setting()->set('Theme.public', $this->child);
        Services::resetSingle('theme');
    }

    protected function tearDown(): void
    {
        setting()->forget('Theme.public');
        Services::resetSingle('theme');
        $this->removeThemeFixtures();
        parent::tearDown();
    }

    public function testTheChildThemeOverridesOneViewAndOneColourOfARealPage(): void
    {
        $this->newsPost('Notícia pública', ['visibility' => 'public', 'slug' => 'noticia-publica']);

        $list = $this->send('GET', 'news');
        $list->assertOK();
        $html = (string) $list->response()->getBody();

        // The child's view of the News list…
        $this->assertStringContainsString('<h1 class="child-news">', $html);
        $this->assertStringContainsString('<li class="child-item">Notícia pública</li>', $html);
        $this->assertStringNotContainsString('bi-rss', $html, 'The module view was replaced.');
        // …and its colour…
        $this->assertStringContainsString('--bs-primary: #6f42c1;', $html);
        // …inside the parent's public layout, partials and CSS (nothing copied).
        $this->assertStringContainsString('class="layout-public', $html);
        $this->assertStringContainsString('themes/default/css/theme.css', $html);
        $this->assertFileDoesNotExist(ROOTPATH . 'themes/' . $this->child . '/Views/layouts/public.php');

        // A page the child does not override keeps the module's view, with the child's colour.
        $post = $this->send('GET', 'news/noticia-publica');
        $post->assertOK();
        $post->assertSee('Notícia pública');
        $this->assertStringContainsString('--bs-primary: #6f42c1;', (string) $post->response()->getBody());

        // No inline script or style without the nonce (strict CSP).
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bnonce=)(?![^>]*\bsrc=)(?![^>]*type="application\/json")[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $html);
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/', $html);
    }
}
