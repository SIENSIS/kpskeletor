<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

use CodeIgniter\View\Exceptions\ViewException;

/**
 * Resolves a view name to the file that renders it, following the theme chain.
 *
 * - Module views (`Modules\News\Views\posts/index`): active theme
 *   `Views/overrides/News/posts/index.php` → each parent theme's override → the module's own
 *   view → the Core standard view with the same path.
 * - Standard views (no namespace: `components/badge`, `layouts/backend`, `errors/404`): active
 *   theme `Views/<path>.php` → each parent theme → the Core standard view
 *   (`modules/Core/Views/<path>.php`).
 * - Any other namespaced view (`App\…`, a library's views) is returned unchanged.
 *
 * The result is a namespaced view name that CodeIgniter's `view()` understands.
 */
final class ViewResolver
{
    /** @var array<string, string> */
    private array $resolved = [];

    /**
     * @param list<array{namespace: string, path: string}> $themes the theme chain, active theme first
     * @param string                                       $corePath folder of the Core standard views
     */
    public function __construct(
        private readonly array $themes,
        private readonly string $corePath = __DIR__ . '/../Views',
    ) {
    }

    /**
     * @throws ViewException when a standard view exists nowhere
     */
    public function resolve(string $view): string
    {
        return $this->resolved[$view] ??= $this->find($view);
    }

    /**
     * Every candidate checked for a view, in order (for docs and debugging).
     *
     * @return list<array{name: string, file: string}>
     */
    public function candidates(string $view): array
    {
        $view = (string) preg_replace('/\.php$/', '', str_replace('\\', '/', ltrim($view, '\\/')));

        if (preg_match('#^Modules/([A-Z][A-Za-z0-9]*)/Views/(.+)$#', $view, $match) === 1) {
            [, $module, $relative] = $match;
            $candidates = [];

            foreach ($this->themes as $theme) {
                $candidates[] = $this->candidate($theme, 'overrides/' . $module . '/' . $relative);
            }

            $candidates[] = ['name' => 'Modules\\' . $module . '\\Views\\' . $relative, 'file' => ''];
            $candidates[] = $this->coreCandidate($relative);

            return $candidates;
        }

        if (str_contains($view, '/') && preg_match('#^[A-Z][A-Za-z0-9]*/#', $view) === 1) {
            return [['name' => str_replace('/', '\\', $view), 'file' => '']];
        }

        $candidates = [];

        foreach ($this->themes as $theme) {
            $candidates[] = $this->candidate($theme, $view);
        }

        $candidates[] = $this->coreCandidate($view);

        return $candidates;
    }

    private function find(string $view): string
    {
        $this->assertSafe($view);

        $candidates = $this->candidates($view);

        foreach ($candidates as $candidate) {
            if ($candidate['file'] === '') {
                if (count($candidates) === 1 || $this->moduleViewExists($candidate['name'])) {
                    return $candidate['name'];
                }

                continue;
            }

            if (is_file($candidate['file'])) {
                return $candidate['name'];
            }
        }

        throw ViewException::forInvalidFile($view);
    }

    /**
     * @param array{namespace: string, path: string} $theme
     *
     * @return array{name: string, file: string}
     */
    private function candidate(array $theme, string $relative): array
    {
        return [
            'name' => $theme['namespace'] . '\\Views\\' . str_replace('/', '\\', $relative),
            'file' => $theme['path'] . '/Views/' . $relative . '.php',
        ];
    }

    /**
     * @return array{name: string, file: string}
     */
    private function coreCandidate(string $relative): array
    {
        return [
            'name' => 'Modules\\Core\\Views\\' . str_replace('/', '\\', $relative),
            'file' => $this->corePath . '/' . $relative . '.php',
        ];
    }

    private function moduleViewExists(string $name): bool
    {
        return service('locator')->locateFile($name, 'Views') !== false;
    }

    /**
     * View names come from code, never from requests; refuse path tricks anyway.
     */
    private function assertSafe(string $view): void
    {
        if ($view === '' || str_contains($view, '..') || str_contains($view, "\0")) {
            throw ViewException::forInvalidFile($view);
        }
    }
}
