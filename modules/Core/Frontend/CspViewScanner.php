<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Checks the project's views for code the strict CSP would block or that breaks the "no
 * JavaScript inside the HTML" rule (`php spark csp:check`, run in CI by the test suite):
 *
 * - `<script>` elements with inline code (only `src=…`, `type="application/json"` data blocks and
 *   the nonce'd `type="importmap"` are allowed);
 * - `on*=""` event handler attributes, `style=""` attributes and `javascript:` URLs;
 * - `<style>` elements, except the nonce'd theme-variables block of a theme's
 *   `Views/partials/head.php` (D-00-25).
 *
 * Scope: `app/Views`, `modules/<Name>/Views`, `themes/<slug>/Views` and the view templates of the
 * generators. Library views (kpcrudy, kpapy in `vendor/`) are out of scope (D-00-24).
 */
final class CspViewScanner
{
    /**
     * Files allowed to break the rules, with the reason (relative to the project root).
     */
    public const EXCEPTIONS = [
        // CodeIgniter's development error page (never shown in production): nonce'd blocks, but
        // its "arguments" toggles keep their onclick handlers (they stay inert under the CSP).
        'app/Views/errors/html/error_exception.php' => 'CodeIgniter debug page (development only)',
    ];

    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? ROOTPATH, '/\\');
    }

    /**
     * @return list<string> folders and files scanned (relative to the root)
     */
    public function targets(): array
    {
        $targets = ['app/Views'];

        foreach (glob($this->root . '/modules/*/Views', GLOB_ONLYDIR) ?: [] as $folder) {
            $targets[] = substr($folder, strlen($this->root) + 1);
        }

        foreach (glob($this->root . '/themes/*/Views', GLOB_ONLYDIR) ?: [] as $folder) {
            $targets[] = substr($folder, strlen($this->root) + 1);
        }

        foreach (['modules/Core/Modules/Templates', 'modules/Core/Theme/Templates'] as $folder) {
            if (is_dir($this->root . '/' . $folder)) {
                $targets[] = $folder;
            }
        }

        return $targets;
    }

    /**
     * Every violation: "path:line: message".
     *
     * @return list<string>
     */
    public function scan(): array
    {
        $violations = [];

        foreach ($this->targets() as $target) {
            $path = $this->root . '/' . $target;

            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                /** @var SplFileInfo $file */
                $name = $file->getFilename();

                if (! $file->isFile() || ! (str_ends_with($name, '.php') || str_ends_with($name, '.tpl') || str_ends_with($name, '.html'))) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));

                if (isset(self::EXCEPTIONS[$relative])) {
                    continue;
                }

                foreach ($this->check((string) file_get_contents($file->getPathname()), $relative) as $violation) {
                    $violations[] = $violation;
                }
            }
        }

        return $violations;
    }

    /**
     * Violations of one view's source.
     *
     * @return list<string>
     */
    public function check(string $source, string $relative = 'view'): array
    {
        $html       = self::stripPhp($source);
        $violations = [];
        $allowStyle = preg_match('#^themes/[^/]+/Views/partials/head\.php$#', $relative) === 1;

        if (preg_match_all('#<script\b([^>]*)>(.*?)</script\s*>#is', $html, $scripts, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) !== false) {
            foreach ($scripts as $script) {
                $attributes = $script[1][0];
                $body       = trim($script[2][0]);
                $line       = self::line($html, $script[0][1]);

                if (preg_match('/\bsrc\s*=/i', $attributes) === 1) {
                    if ($body !== '') {
                        $violations[] = "{$relative}:{$line}: " . lang('Core.csp.scriptWithBody');
                    }

                    continue;
                }

                if (preg_match('/\btype\s*=\s*["\']application\/(?:ld\+)?json["\']/i', $attributes) === 1) {
                    continue;
                }

                if (preg_match('/\btype\s*=\s*["\']importmap["\']/i', $attributes) === 1 && str_contains($attributes, '__NONCE__')) {
                    continue;
                }

                $violations[] = "{$relative}:{$line}: " . lang('Core.csp.inlineScript');
            }
        }

        if (preg_match_all('#<style\b([^>]*)>#i', $html, $styles, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) !== false) {
            foreach ($styles as $style) {
                if (! $allowStyle || ! str_contains($style[1][0], '__NONCE__')) {
                    $violations[] = "{$relative}:" . self::line($html, $style[0][1]) . ': ' . lang('Core.csp.inlineStyle');
                }
            }
        }

        $rules = [
            '/\son[a-z]+\s*=/i'                                => 'Core.csp.handler',
            '/\sstyle\s*=/i'                                   => 'Core.csp.styleAttribute',
            '/\s(?:href|src|action|formaction)\s*=\s*["\']?\s*javascript:/i' => 'Core.csp.javascriptUrl',
        ];

        if (preg_match_all('#<[a-zA-Z][a-zA-Z0-9-]*\b[^>]*>#', $html, $tags, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($tags[0] as [$tag, $offset]) {
                foreach ($rules as $pattern => $message) {
                    if (preg_match($pattern, $tag) === 1) {
                        $violations[] = "{$relative}:" . self::line($html, $offset) . ': ' . lang($message);
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * Replaces PHP blocks with neutral text (keeping the line breaks); the nonce helpers become
     * the marker __NONCE__.
     */
    private static function stripPhp(string $source): string
    {
        return (string) preg_replace_callback('/<\?(?:php|=)?(.*?)(?:\?>|$)/s', static function (array $match): string {
            $code = $match[1];

            if (preg_match('/csp_(?:script|style)_nonce\s*\(\s*\)/', $code) === 1) {
                return '__NONCE__';
            }

            return 'x' . str_repeat("\n", substr_count($match[0], "\n"));
        }, $source);
    }

    private static function line(string $text, int $offset): int
    {
        return substr_count(substr($text, 0, $offset), "\n") + 1;
    }
}
