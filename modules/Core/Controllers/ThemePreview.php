<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Theme as ThemeConfig;
use Modules\Core\Theme\ErrorPage;
use Modules\Core\Theme\ThemeManifest;
use Modules\Core\Theme\ThemeNotFoundException;

/**
 * Theme preview for theme authors: every layout with the standard views and components and sample
 * content (`/theme-preview/<layout>?theme=<slug>`, or `errors-403|404|500`).
 *
 * Answers 404 unless the environment is development and Config\Theme::$preview is enabled: no
 * data is read, but it is not a page for production.
 */
final class ThemePreview extends BaseController
{
    public function show(string $layout = 'backend'): string
    {
        if (ENVIRONMENT !== 'development' || ! config(ThemeConfig::class)->preview) {
            throw PageNotFoundException::forPageNotFound();
        }

        $theme = service('theme');
        $area  = $layout === 'backend' ? 'backend' : 'public';
        $theme->setArea($area);

        $slug = $this->request->getGet('theme');

        if (is_string($slug) && $slug !== '') {
            try {
                $theme->force($area, $slug);
            } catch (ThemeNotFoundException) {
                throw PageNotFoundException::forPageNotFound();
            }
        }

        if (preg_match('/^errors-(403|404|500)$/', $layout, $match) === 1) {
            $code = (int) $match[1];
            $this->response->setStatusCode($code);

            return ErrorPage::render($code);
        }

        if (! in_array($layout, ThemeManifest::LAYOUTS, true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $themes = array_map(
            static fn (string $s): array => ['slug' => $s, 'name' => $theme->registry()->get($s)->name],
            $theme->chain(),
        );

        if ($layout === 'backend') {
            $theme->regions()->add('sidebar', $theme->render('preview/sidebar', [], ['saveData' => false]));
        }

        if ($layout === 'public') {
            $theme->regions()->add('header', $theme->render('preview/public_menu', [], ['saveData' => false]));
            $theme->regions()->add('sidebar', $theme->render('preview/public_sidebar', [], ['saveData' => false]));
        }

        return $theme->render('preview/' . ($layout === 'backend' ? 'backend' : 'page'), [
            'layout'      => $theme->layout($layout),
            'title'       => lang('Core.preview.title'),
            'themes'      => $themes,
            'slug'        => $theme->slug(),
            'pageHeader'  => $layout === 'backend' ? [
                'title'       => lang('Core.preview.title'),
                'subtitle'    => lang('Core.preview.subtitle', [implode(' → ', array_column($themes, 'name'))]),
                'icon'        => 'bi-palette',
                'breadcrumbs' => [
                    ['label' => lang('Core.ui.backOffice'), 'url' => base_url('backend')],
                    ['label' => lang('Core.preview.themes'), 'url' => null],
                    ['label' => lang('Core.preview.title'), 'url' => null],
                ],
                'actions' => [
                    ['label' => lang('Core.preview.add'), 'url' => '#', 'icon' => 'bi-plus-lg'],
                    ['label' => lang('Core.preview.export'), 'url' => '#', 'icon' => 'bi-download', 'variant' => 'secondary'],
                ],
            ] : null,
            'meta' => ['description' => lang('Core.preview.subtitle', [$theme->slug()]), 'robots' => 'noindex'],
        ]);
    }
}
