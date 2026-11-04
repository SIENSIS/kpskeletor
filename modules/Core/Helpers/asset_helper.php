<?php

declare(strict_types=1);

/**
 * Asset helpers (see docs/frontend.md). Loaded by app/Config/Autoload.php ($helpers).
 */

use Modules\Core\Frontend\AssetManifest;
use Modules\Core\Frontend\ImportMap;

if (! function_exists('asset')) {
    /**
     * Versioned URL of a published asset: `asset('core::css/app.css')`,
     * `asset('events::js/calendar.js')`, `asset('theme::img/logo.png')` (active theme chain) or
     * `asset('vendor::bootstrap/css/bootstrap.min.css')`. A file missing from the manifest gets
     * its unversioned URL (and a log line) so a forgotten `assets:publish` is visible.
     */
    function asset(string $spec): string
    {
        if (preg_match('/^([a-z][a-z0-9-]*(?::[a-z0-9-]+)?)::(.+)$/', $spec, $match) !== 1 || str_contains($match[2], '..')) {
            throw new InvalidArgumentException(lang('Core.assets.invalid', [$spec]));
        }

        [, $owner, $path] = $match;
        $path             = ltrim($path, '/');

        if ($owner === 'vendor') {
            return service('assets')->vendorUrl($path) ?? base_url('assets/vendor/' . $path);
        }

        if ($owner === 'theme') {
            return theme()->assetUrl($path) ?? base_url('themes/' . theme()->slug() . '/' . $path);
        }

        $url = service('assets')->url($owner . '::' . $path);

        if ($url !== null) {
            return $url;
        }

        log_message('debug', 'Asset {asset} is not in the asset manifest (run php spark assets:publish).', ['asset' => $spec]);

        if (str_starts_with($owner, 'theme:')) {
            return base_url('themes/' . substr($owner, 6) . '/' . $path);
        }

        return base_url(($owner === 'core' ? 'assets/core/' : 'assets/modules/' . $owner . '/') . $path);
    }
}

if (! function_exists('import_map')) {
    /**
     * The import map of the page (every published JS file with its hashed URL).
     */
    function import_map(): ImportMap
    {
        return new ImportMap(service('assets'), theme());
    }
}

if (! function_exists('asset_manifest')) {
    function asset_manifest(): AssetManifest
    {
        return service('assets');
    }
}

if (! function_exists('page_config_json')) {
    /**
     * JSON of the page configuration block `<script type="application/json" id="kp-config">`
     * read by `core/config.js`: base URL, locale and the UI texts of the scripts (`Core.js.*`).
     * Escaped for HTML (`<`, `>`, `&` and quotes).
     */
    function page_config_json(): string
    {
        $texts = lang('Core.js');
        $flat  = [];

        $walk = static function (mixed $value, string $prefix) use (&$walk, &$flat): void {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $walk($item, $prefix === '' ? (string) $key : $prefix . '.' . $key);
                }
            } elseif (is_string($value)) {
                $flat[$prefix] = $value;
            }
        };
        $walk(is_array($texts) ? $texts : [], '');

        return json_encode([
            'baseUrl' => rtrim(base_url('/'), '/') . '/',
            'locale'  => service('request')->getLocale(),
            'i18n'    => $flat,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }
}

if (! function_exists('pwa_enabled')) {
    /**
     * Whether the minimal PWA is on (setting `Core.pwa`).
     */
    function pwa_enabled(): bool
    {
        return Modules\Core\Frontend\Pwa::enabled();
    }
}

if (! function_exists('pwa_theme_colors')) {
    /**
     * @return array{light: string, dark: string}
     */
    function pwa_theme_colors(): array
    {
        return (new Modules\Core\Frontend\Pwa(theme()))->themeColors();
    }
}

if (! function_exists('pwa_icon_url')) {
    function pwa_icon_url(int $size): string
    {
        return base_url('pwa/icon-' . $size . '.png') . '?v=' . (new Modules\Core\Frontend\Pwa(theme()))->hash($size);
    }
}
