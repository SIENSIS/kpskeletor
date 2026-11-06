<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

/**
 * The tabs of the settings screens: one per module with settings (the Core's is "General"),
 * the theme, Settings › Security (only with `settings.security`) and the actions.
 */
final class SettingsTabs
{
    public function __construct(private readonly SettingsEditor $editor = new SettingsEditor())
    {
    }

    /**
     * @return list<array{id: string, label: string, url: string, icon: string, active: bool}>
     */
    public function build(string $current, bool $security): array
    {
        $tabs = [];

        foreach ($this->editor->modules() as $slug => $module) {
            $tabs[] = ['id' => $slug, 'label' => self::moduleLabel($module), 'url' => site_url('backend/settings/' . $slug), 'icon' => $slug === 'core' ? 'bi bi-sliders' : 'bi bi-box'];
        }

        $tabs[] = ['id' => 'theme', 'label' => lang('Settings.tabs.theme'), 'url' => site_url('backend/settings/theme'), 'icon' => 'bi bi-palette'];

        if ($security) {
            $tabs[] = ['id' => 'security', 'label' => lang('Settings.tabs.security'), 'url' => site_url('backend/settings/security'), 'icon' => 'bi bi-shield-lock'];
        }

        $tabs[] = ['id' => 'actions', 'label' => lang('Settings.tabs.actions'), 'url' => site_url('backend/settings/actions'), 'icon' => 'bi bi-tools'];

        return array_map(static fn (array $tab): array => $tab + ['active' => $tab['id'] === $current], $tabs);
    }

    /** "General" for the Core, `<Module>.module.name` when the module has it, else its name. */
    public static function moduleLabel(string $module): string
    {
        if ($module === 'Core') {
            return lang('Settings.tabs.general');
        }

        $key   = $module . '.module.name';
        $label = lang($key);

        return $label === $key ? $module : $label;
    }
}
