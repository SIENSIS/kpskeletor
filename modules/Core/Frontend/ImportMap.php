<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use Modules\Core\Theme\ThemeManager;

/**
 * The import map printed by the layouts: every published JS file with its hashed URL, so
 * imported modules are cache-busted too (D-00-26).
 *
 * - `core/<path>` → `/assets/core/js/<path>?v=…`;
 * - `<module>/<path>` → `/assets/modules/<module>/js/<path>?v=…`;
 * - `theme/<path>` → the active theme's `js/<path>` (looked up along the parent chain).
 *
 * Modules import shared code with these bare specifiers (`import { confirm } from
 * 'core/ui/confirm.js'`), never with relative paths (they would not carry the version).
 */
final class ImportMap
{
    public function __construct(
        private readonly AssetManifest $manifest,
        private readonly ?ThemeManager $theme = null,
    ) {
    }

    /**
     * @return array<string, string> specifier => URL, sorted
     */
    public function imports(): array
    {
        $imports = [];
        $themes  = [];

        foreach ($this->manifest->files() as $key => $entry) {
            [$owner, $path] = explode('::', $key, 2) + [1 => ''];

            if (! str_starts_with($path, 'js/') || ! preg_match('/\.m?js$/', $path)) {
                continue;
            }

            $url = base_url($entry['url']) . '?v=' . $entry['hash'];

            if (str_starts_with($owner, 'theme:')) {
                $themes[substr($owner, 6)][substr($path, 3)] = $url;

                continue;
            }

            $imports[$owner . '/' . substr($path, 3)] = $url;
        }

        if ($this->theme !== null) {
            // Root theme first, so the active theme's files win.
            foreach (array_reverse($this->theme->chain()) as $slug) {
                foreach ($themes[$slug] ?? [] as $path => $url) {
                    $imports['theme/' . $path] = $url;
                }
            }
        }

        ksort($imports);

        return $imports;
    }

    /**
     * The JSON of the `<script type="importmap">` element (safe inside HTML: `<`, `>` and `&`
     * are escaped).
     */
    public function json(): string
    {
        return json_encode(
            ['imports' => (object) $this->imports()],
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }
}
