<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Packages\PackageRefused;
use Modules\Core\Packages\StagedPackages;
use Modules\Core\Theme\ThemeManager;
use Modules\Core\Theme\ThemeOptions;
use Modules\Core\Theme\ThemeRegistry;
use Throwable;

/**
 * Themes tab of the Modules screen (`backend/modules/themes`, docs/installing-modules.md): installed
 * themes with a preview (the theme's `Assets/img/screenshot.png` and its colours), the active
 * theme of the back office and of the public site (switch with one click, `modules.toggle`; the
 * same settings as Settings › Theme), and the uninstall request (`modules.uninstall` +
 * re-authentication). Installing a theme is `themes:install` or a staged upload.
 */
final class Themes extends BackendController
{
    public function index(): string
    {
        $this->breadcrumb(lang('Core.modules.title'), site_url('backend/modules'))->breadcrumb(lang('Core.modules.tabs.themes'));

        return $this->render('Modules\Core\Views\modules\themes', [
            'title'  => lang('Core.modules.title'),
            'icon'   => 'bi bi-boxes',
            'tab'    => 'themes',
            'themes' => $this->themes(),
            'can'    => [
                'toggle'    => $this->can('modules.toggle'),
                'uninstall' => $this->can('modules.uninstall'),
                'options'   => $this->can('settings.core'),
            ],
        ]);
    }

    public function activate(): RedirectResponse
    {
        $area = (string) $this->request->getPost('area');
        $slug = (string) $this->request->getPost('theme');
        $back = redirect()->to(site_url('backend/modules/themes'));

        if (! in_array($area, ThemeManager::AREAS, true) || ! isset($this->themes()[$slug]) || $this->themes()[$slug]['error'] !== null) {
            return $back->with('error', lang('Core.themes.invalid'));
        }

        $key    = 'Theme.' . $area;
        $before = (string) (setting($key) ?? config('Theme')->{$area});
        setting()->set($key, $slug);

        /** @var ThemeManager $theme */
        $theme = service('theme');
        $theme->reset();
        (new CacheInvalidator())->invalidate(['menus', 'assets']);
        activity_log('Core', 'theme_activated', ['resource' => 'modules', 'summary' => $area . ': ' . $slug, 'changes' => ['before' => [$key => $before], 'after' => [$key => $slug]]]);

        return $back->with('success', lang('Core.themes.activated', [$this->themes()[$slug]['name'], lang('Core.themes.areas.' . $area)]));
    }

    public function uninstallForm(string $slug): string
    {
        return $this->uninstallPage($slug);
    }

    public function uninstall(string $slug): ResponseInterface|string
    {
        try {
            (new StagedPackages())->requestUninstall(PackageInfo::THEME, $slug, false, (int) auth()->id());
        } catch (PackageRefused $e) {
            return $this->uninstallPage($slug, $e->getMessage());
        }

        return redirect()->to(site_url('backend/modules/packages'))->with('success', lang('Core.packages.uninstallRequested', [$slug]));
    }

    private function uninstallPage(string $slug, ?string $error = null): string
    {
        $themes = $this->themes();

        if (! isset($themes[$slug])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->breadcrumb(lang('Core.modules.title'), site_url('backend/modules'))
            ->breadcrumb(lang('Core.modules.tabs.themes'), site_url('backend/modules/themes'))
            ->breadcrumb(lang('Core.modules.uninstallTitle', [$themes[$slug]['name']]));

        return $this->render('Modules\Core\Views\modules\uninstall', [
            'title'    => lang('Core.modules.uninstallTitle', [$themes[$slug]['name']]),
            'icon'     => 'bi bi-trash',
            'module'   => ['name' => $themes[$slug]['name'], 'version' => $themes[$slug]['version'], 'description' => '', 'dependants' => []],
            'action'   => site_url('backend/modules/themes/uninstall/' . $slug),
            'cancel'   => site_url('backend/modules/themes'),
            'keepData' => false,
            'error'    => $error,
        ]);
    }

    /**
     * @return array<string, array{slug: string, name: string, version: string, author: string, parent: string|null, backend: bool, public: bool, fallback: bool, screenshot: string|null, colors: list<string>, error: string|null}>
     */
    private function themes(): array
    {
        $registry = new ThemeRegistry();
        $config   = config('Theme');
        $backend  = (string) (setting('Theme.backend') ?? $config->backend);
        $public   = (string) (setting('Theme.public') ?? $config->public);
        $themes   = [];

        foreach ($registry->slugs() as $slug) {
            $row = [
                'slug' => $slug, 'name' => $slug, 'version' => '', 'author' => '', 'parent' => null,
                'backend' => $slug === $backend, 'public' => $slug === $public, 'fallback' => $slug === $config->fallback,
                'screenshot' => null, 'colors' => [], 'error' => null,
            ];

            try {
                $chain    = array_map(static fn (string $s) => $registry->get($s), $registry->chain($slug));
                $manifest = $chain[0];
                $row      = ['name' => $manifest->name !== '' ? $manifest->name : $slug, 'version' => $manifest->version, 'author' => $manifest->author, 'parent' => $manifest->parent] + $row;

                foreach ((new ThemeOptions($slug, $chain))->cssVariables() as $value) {
                    if (preg_match('/^#[0-9a-f]{3,8}$/i', (string) $value) === 1 && count($row['colors']) < 4) {
                        $row['colors'][] = (string) $value;
                    }
                }

                foreach (['png', 'webp', 'jpg'] as $extension) {
                    if (is_file(FCPATH . 'themes/' . $slug . '/img/screenshot.' . $extension)) {
                        $row['screenshot'] = base_url('themes/' . $slug . '/img/screenshot.' . $extension);
                        break;
                    }
                }
            } catch (Throwable $e) {
                $row['error'] = $e->getMessage();
            }

            $themes[$slug] = $row;
        }

        return $themes;
    }
}
