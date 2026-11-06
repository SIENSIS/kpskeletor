<?php

declare(strict_types=1);

namespace Modules\Settings\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Filters\Can;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Settings\Forms\Field;
use Modules\Settings\Forms\FormGenerator;
use Modules\Settings\Services\SettingsEditor;
use Modules\Settings\Services\SettingsTabs;
use Modules\Settings\Services\ThemeSettings;

/**
 * The settings screens (docs/settings.md): one tab per module with settings, the theme tab and
 * Settings › Security, all generated from the declarations by FormGenerator.
 *
 * Every route needs `settings.update` (Security: `settings.security` + re-authentication).
 * A tab is shown read-only without its own permission (`settings.<module>`; the theme tab:
 * `settings.core`), and saving it is refused (403, security log) whatever the form posted.
 */
final class Settings extends BackendController
{
    private readonly SettingsEditor $editor;

    private readonly FormGenerator $forms;

    public function __construct()
    {
        $this->editor = new SettingsEditor();
        $this->forms  = new FormGenerator();
    }

    public function index(): RedirectResponse
    {
        $tabs = $this->tabs('');

        return redirect()->to($tabs[0]['url']);
    }

    public function module(string $slug): string
    {
        $module = $this->moduleOf($slug);

        return $this->moduleForm($module, $this->editor->moduleFields($module, ! $this->can('settings.' . $slug)));
    }

    public function saveModule(string $slug): ResponseInterface
    {
        $module = $this->moduleOf($slug);

        if (($denied = $this->deny('settings.' . $slug)) !== null) {
            return $denied;
        }

        $groups = $this->editor->moduleFields($module);
        $fields = array_merge(...array_values($groups));
        $result = $this->forms->validate($fields, $this->post());

        if ($result['errors'] !== []) {
            return $this->invalid($this->moduleForm($module, $groups, $result['errors']));
        }

        $changes = $this->editor->save($fields, $result['values']);
        $this->logged('settings_changed', $module, $changes);

        return $this->saved(site_url('backend/settings/' . $slug), $changes);
    }

    public function security(): string
    {
        return $this->securityForm($this->editor->securityFields());
    }

    public function saveSecurity(): ResponseInterface
    {
        $modules = $this->editor->securityFields();
        $fields  = [];

        foreach ($modules as $groups) {
            $fields = [...$fields, ...array_merge(...array_values($groups))];
        }

        $result = $this->forms->validate($fields, $this->post());

        if ($result['errors'] !== []) {
            security_log('Settings', 'security_settings_refused', [
                'resource' => 'settings',
                'changes'  => ['fields' => array_keys($result['errors'])],
            ]);

            return $this->invalid($this->securityForm($modules, $result['errors']));
        }

        $changes = $this->editor->save($fields, $result['values']);

        if ($changes['after'] !== []) {
            security_log('Settings', 'security_settings_changed', [
                'resource' => 'settings',
                'summary'  => implode(', ', array_keys($changes['after'])),
                'before'   => $changes['before'],
                'after'    => $changes['after'],
            ]);
        }

        return $this->saved(site_url('backend/settings/security'), $changes);
    }

    public function theme(): string
    {
        return $this->themeForm($this->selectedTheme());
    }

    public function saveTheme(): ResponseInterface
    {
        if (($denied = $this->deny('settings.core')) !== null) {
            return $denied;
        }

        $themes = new ThemeSettings();
        $slug   = $this->selectedTheme();
        $areas  = $themes->areaFields();
        $option = $themes->optionFields($slug);
        $post   = $this->post();

        $areaResult   = $this->forms->validate($areas, $post);
        $optionResult = $this->forms->validate($option, $post);
        $errors       = [...$areaResult['errors'], ...$optionResult['errors']];

        if ($errors !== []) {
            return $this->invalid($this->themeForm($slug, $errors));
        }

        $changes = $this->editor->save($areas, $areaResult['values']);
        $options = $this->editor->save($option, $optionResult['values'], 'theme:' . $slug);
        $changes = [
            'before' => [...$changes['before'], ...$options['before']],
            'after'  => [...$changes['after'], ...$options['after']],
        ];

        if ($changes['after'] !== []) {
            service('theme')->reset();
            (new CacheInvalidator())->invalidate(['menus', 'assets']);
        }

        $this->logged('settings_changed', 'theme:' . $slug, $changes);

        return $this->saved(site_url('backend/settings/theme') . '?theme=' . rawurlencode($slug), $changes);
    }

    /**
     * @return list<array{id: string, label: string, url: string, icon: string, active: bool}>
     */
    private function tabs(string $current): array
    {
        return (new SettingsTabs($this->editor))->build($current, $this->can('settings.security'));
    }

    /**
     * @param array<string, list<Field>> $groups
     * @param array<string, string>      $errors
     */
    private function moduleForm(string $module, array $groups, array $errors = []): string
    {
        $slug     = strtolower($module);
        $readOnly = ! $this->can('settings.' . $slug);
        $headed   = [];

        foreach ($groups as $group => $fields) {
            $headed[$this->groupLabel($module, $group)] = $fields;
        }

        return $this->page($slug, SettingsTabs::moduleLabel($module), [
            'action'   => site_url('backend/settings/' . $slug),
            'fields'   => $this->forms->render($headed, $errors === [] ? [] : $this->post(), $errors),
            'readOnly' => $readOnly,
            'intro'    => lang($readOnly ? 'Settings.ui.readOnly' : 'Settings.ui.intro'),
        ]);
    }

    /**
     * @param array<string, array<string, list<Field>>> $modules
     * @param array<string, string>                     $errors
     */
    private function securityForm(array $modules, array $errors = []): string
    {
        $sections = [];

        foreach ($modules as $module => $groups) {
            $headed = [];

            foreach ($groups as $group => $fields) {
                $headed[$this->groupLabel($module, $group)] = $fields;
            }

            $sections[] = [
                'title'  => SettingsTabs::moduleLabel($module),
                'fields' => $this->forms->render($headed, $errors === [] ? [] : $this->post(), $errors),
            ];
        }

        return $this->page('security', lang('Settings.tabs.security'), [
            'action'   => site_url('backend/settings/security'),
            'sections' => $sections,
            'readOnly' => false,
            'intro'    => lang('Settings.ui.securityIntro'),
        ]);
    }

    /**
     * @param array<string, string> $errors
     */
    private function themeForm(string $slug, array $errors = []): string
    {
        $themes   = new ThemeSettings();
        $readOnly = ! $this->can('settings.core');
        $old      = $errors === [] ? [] : $this->post();
        $fields   = $this->forms->render([lang('Settings.theme.areas') => $themes->areaFields($readOnly)], $old, $errors)
            . $this->forms->render([lang('Settings.theme.options', [$themes->themes()[$slug] ?? $slug]) => $themes->optionFields($slug, $readOnly)], $old, $errors);

        return $this->page('theme', lang('Settings.tabs.theme'), [
            'action'   => site_url('backend/settings/theme') . '?theme=' . rawurlencode($slug),
            'fields'   => $fields,
            'readOnly' => $readOnly,
            'intro'    => lang($readOnly ? 'Settings.ui.readOnly' : 'Settings.theme.intro'),
            'themes'   => $themes->themes(),
            'theme'    => $slug,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $tab, string $title, array $data): string
    {
        $this->breadcrumb(lang('Settings.title'), site_url('backend/settings'))->breadcrumb($title);

        return $this->render('Modules\Settings\Views\form', $data + [
            'title' => lang('Settings.title'),
            'icon'  => 'bi bi-gear',
            'tabs'  => $this->tabs($tab),
            'tab'   => $tab,
        ]);
    }

    /** The theme whose options are edited (`?theme=`), the back office's by default. */
    private function selectedTheme(): string
    {
        $requested = $this->request->getGet('theme');
        $themes    = (new ThemeSettings())->themes();

        if (is_string($requested) && isset($themes[$requested])) {
            return $requested;
        }

        return service('theme')->slug('backend');
    }

    private function moduleOf(string $slug): string
    {
        $module = $this->editor->modules()[$slug] ?? null;

        if ($module === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $module;
    }

    private function groupLabel(string $module, string $group): string
    {
        if ($group === '') {
            return '';
        }

        $segment = substr($group, (int) strrpos('.' . $group, '.'));

        foreach ([$module . '.settingGroups.' . $segment, 'Settings.groups.' . $segment] as $key) {
            if (lang($key) !== $key) {
                return lang($key);
            }
        }

        return ucfirst($segment);
    }

    /**
     * The `can:` filter's refusal (403 page, `forbidden` security entry) when the user lacks it.
     */
    private function deny(string $permission): ?ResponseInterface
    {
        if ($this->can($permission)) {
            return null;
        }

        $response = (new Can())->before($this->request, [$permission]);

        return $response instanceof ResponseInterface ? $response : service('response')->setStatusCode(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function post(): array
    {
        return (array) $this->request->getPost();
    }

    private function invalid(string $html): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setBody($html);
    }

    /**
     * @param array{before: array<string, mixed>, after: array<string, mixed>} $changes
     */
    private function logged(string $action, string $summary, array $changes): void
    {
        if ($changes['after'] !== []) {
            activity_log('Settings', $action, [
                'resource' => 'settings',
                'summary'  => $summary,
                'before'   => $changes['before'],
                'after'    => $changes['after'],
            ]);
        }
    }

    /**
     * @param array{before: array<string, mixed>, after: array<string, mixed>} $changes
     */
    private function saved(string $url, array $changes): RedirectResponse
    {
        return redirect()->to($url)->with(
            $changes['after'] === [] ? 'info' : 'success',
            lang($changes['after'] === [] ? 'Settings.ui.unchanged' : 'Settings.ui.saved', [count($changes['after'])]),
        );
    }
}
