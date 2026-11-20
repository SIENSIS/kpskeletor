<?php

declare(strict_types=1);

namespace Tests\Unit\Docs;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * The documentation matches the code (acceptance criterion 13, session 33): every document the
 * specification lists exists, relative links resolve, `docs/commands.md` lists exactly the
 * skeleton's commands and only their real options, every `php spark` command and option written
 * in the docs exists, and every `backend/…` or `api/v1/…` URL they name is a route.
 *
 * @internal
 */
final class DocumentationTest extends CIUnitTestCase
{
    /**
     * Documents listed in the specification ("Documentation (English)"), plus the area documents
     * the conventions in CLAUDE.md point at.
     */
    private const REQUIRED = [
        'README.md', 'CHANGELOG.md', 'LICENSE',
        'docs/architecture.md', 'docs/creating-a-module.md', 'docs/installing-modules.md',
        'docs/permissions.md', 'docs/themes.md', 'docs/frontend.md', 'docs/scheduler.md',
        'docs/notifications.md', 'docs/widgets.md', 'docs/api.md', 'docs/commands.md',
        'docs/calendar.md', 'docs/files.md', 'docs/backup-restore.md', 'docs/updates.md',
        'docs/security.md', 'docs/outbound-requests.md', 'docs/dependencies.md',
        'docs/user-guide.md', 'docs/ca/user-guide.md', 'docs/es/user-guide.md',
        'docs/development.md', 'docs/web-server.md', 'docs/cruds.md', 'docs/activity.md',
        'docs/settings.md', 'docs/menus.md', 'docs/storage.md', 'docs/pages.md', 'docs/i18n.md',
        'docs/auth.md', 'docs/library-requests.md',
    ];

    /**
     * Historical records: they quote names as they were when written.
     */
    private const HISTORICAL = [
        'docs/decisions.md', 'docs/plan.md', 'docs/progress.md', 'docs/specification.md',
    ];

    /**
     * URLs of hypothetical modules used as examples.
     */
    private const EXAMPLE_URLS = [
        'api/v1/library/books', 'api/v1/books/items',
        // The scratch module the tutorials build (docs/tutorials/README.md, "Two ways to follow a tutorial").
        'backend/tasks2/tasks', 'backend/tasks2/projects', 'api/v1/tasks2/tasks',
    ];

    /**
     * Options every command accepts (CodeIgniter's own).
     */
    private const GLOBAL_OPTIONS = ['--help', '--no-header'];

    /**
     * @var array<string, BaseCommand>|null
     */
    private static ?array $commands = null;

    public function testEveryListedDocumentExists(): void
    {
        foreach (self::REQUIRED as $file) {
            $this->assertFileExists(ROOTPATH . $file);
            $this->assertGreaterThan(200, filesize(ROOTPATH . $file), $file . ' is not empty');
        }
    }

    public function testEveryCodeMentionOfADocumentPointsAtAnExistingFile(): void
    {
        $missing = [];

        foreach ($this->documents(false) as $file => $text) {
            preg_match_all('#`(docs/[A-Za-z0-9_./-]+\.md)`#', $text, $m);

            foreach (array_unique($m[1]) as $path) {
                // The libraries' own documents (`vendor/siensis/<lib>/docs/…`) are named the same way.
                if (! is_file(ROOTPATH . $path) && glob(ROOTPATH . 'vendor/siensis/*/' . $path) === []) {
                    $missing[] = $file . ': ' . $path;
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function testRelativeLinksResolve(): void
    {
        $broken = [];

        foreach ($this->documents(true) as $file => $text) {
            $text = $this->withoutFencedCode($text);
            preg_match_all('#\]\(([^)\s]+)(?:\s+"[^"]*")?\)#', $text, $m);

            foreach ($m[1] as $target) {
                if (preg_match('#^([a-z][a-z0-9+.-]*:|\#)#i', $target) === 1) {
                    continue; // external URL, mailto: or an anchor in the same page
                }

                [$path, $anchor] = array_pad(explode('#', $target, 2), 2, '');
                $path            = rawurldecode(explode('?', $path, 2)[0]);
                $resolved        = dirname(ROOTPATH . $file) . '/' . $path;

                if (! file_exists($resolved)) {
                    $broken[] = $file . ': ' . $target;
                } elseif ($anchor !== '' && str_ends_with($path, '.md')
                    && ! in_array($anchor, $this->anchors((string) file_get_contents($resolved)), true)) {
                    $broken[] = $file . ': ' . $target . ' (no such heading)';
                }
            }
        }

        $this->assertSame([], $broken);
    }

    public function testTheCommandsReferenceListsExactlyTheSkeletonsCommands(): void
    {
        $documented = array_keys($this->commandRows());
        $skeleton   = [];

        foreach ($this->commands() as $name => $command) {
            if (str_starts_with($command::class, 'Modules\\') || str_starts_with($command::class, 'App\\')) {
                $skeleton[] = $name;
            }
        }

        sort($documented);
        sort($skeleton);

        $this->assertSame($skeleton, $documented);
    }

    public function testTheCommandsReferenceGivesOnlyRealOptions(): void
    {
        $wrong = [];

        foreach ($this->commandRows() as $name => $row) {
            // Only the command's own column: the description may mention other commands' options.
            $usage = explode('|', trim($row, '| '), 2)[0];

            foreach ($this->optionsIn($usage) as $option) {
                if (! $this->hasOption($name, $option)) {
                    $wrong[] = $name . ' ' . $option;
                }
            }
        }

        $this->assertSame([], $wrong);
    }

    public function testEverySparkCommandWrittenInTheDocsExists(): void
    {
        $wrong = [];

        foreach ($this->documents(false) as $file => $text) {
            foreach ($this->sparkCalls($text) as [$name, $arguments]) {
                if (! isset($this->commands()[$name])) {
                    $wrong[] = $file . ': php spark ' . $name;

                    continue;
                }

                foreach ($this->optionsIn($arguments) as $option) {
                    if (! $this->hasOption($name, $option)) {
                        $wrong[] = $file . ': php spark ' . $name . ' ' . $option;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($wrong)));
    }

    public function testEveryBackOfficeAndApiUrlInTheDocsIsARoute(): void
    {
        $patterns = $this->routePatterns();
        $wrong    = [];

        foreach ($this->documents(false) as $file => $text) {
            preg_match_all('#`/?((?:backend|api/v1)(?:/[^`\s]*)?)`#', $text, $m);

            foreach (array_unique($m[1]) as $url) {
                if (! in_array($url, self::EXAMPLE_URLS, true) && ! $this->isRoute($url, $patterns)) {
                    $wrong[] = $file . ': ' . $url;
                }
            }
        }

        $this->assertSame([], $wrong);
    }

    public function testTheRouteMatcherOfThisTestWorks(): void
    {
        $patterns = $this->routePatterns();

        $this->assertTrue($this->isRoute('backend/news/posts', $patterns));
        $this->assertTrue($this->isRoute('backend/news/posts/edit/<id>', $patterns));
        $this->assertTrue($this->isRoute('backend/settings/{module}', $patterns));
        $this->assertTrue($this->isRoute('api/v1/<module>/<key>', $patterns));
        $this->assertTrue($this->isRoute('backend/dashboard/groups[/<group>]', $patterns));
        $this->assertTrue($this->isRoute('api/v1/', $patterns));
        $this->assertFalse($this->isRoute('backend/no-such-screen', $patterns));
        $this->assertFalse($this->isRoute('api/v1/no-such-module/books', $patterns));
        $this->assertSame(
            [['modules:sync', ' --demo=News'], ['permissions:check', '']],
            $this->sparkCalls("Run `php spark modules:sync --demo=News` and\n`php spark permissions:check`."),
        );
        $this->assertSame(
            ['tutorial-a-new-view-with-javascript', 'the-boardjs-module', 'steps', 'steps-1', 'catàleg-i-permisos'],
            $this->anchors("# Tutorial: a new view with JavaScript\n## The `board.js` module\n## Steps\n```\n# not a heading\n```\n## Steps\n## Catàleg i permisos"),
        );
        $this->assertSame(['--demo', '--prune'], $this->optionsIn('`modules:sync [--prune] [--demo[=<A>]]`'));
    }

    /**
     * Markdown files of the project: README, CHANGELOG, CLAUDE.md files, docs/ and the Claude
     * Code commands. `$withHistory` adds the historical records.
     *
     * @return array<string, string> relative path => contents
     */
    private function documents(bool $withHistory): array
    {
        $files = array_merge(
            ['README.md', 'CHANGELOG.md', 'CLAUDE.md'],
            $this->glob('docs', '*.md'),
            $this->glob('docs/ca', '*.md'),
            $this->glob('docs/es', '*.md'),
            $this->glob('docs/tutorials', '*.md'),
            $this->glob('.claude/commands', '*.md'),
            array_map(
                static fn (string $path): string => substr($path, strlen(ROOTPATH)),
                array_merge(glob(ROOTPATH . 'modules/*/CLAUDE.md') ?: [], glob(ROOTPATH . 'modules/*/README.md') ?: []),
            ),
        );

        $documents = [];

        foreach ($files as $file) {
            if (! $withHistory && in_array($file, self::HISTORICAL, true)) {
                continue;
            }
            if (is_file(ROOTPATH . $file)) {
                $documents[$file] = (string) file_get_contents(ROOTPATH . $file);
            }
        }

        return $documents;
    }

    /**
     * @return list<string>
     */
    private function glob(string $folder, string $pattern): array
    {
        return array_map(
            static fn (string $path): string => $folder . '/' . basename($path),
            glob(ROOTPATH . $folder . '/' . $pattern) ?: [],
        );
    }

    /**
     * GitHub's heading anchors of a Markdown text (lower case, punctuation removed, spaces as
     * hyphens, `-1`, `-2`… for repeated headings).
     *
     * @return list<string>
     */
    private function anchors(string $text): array
    {
        $anchors = [];
        $seen    = [];

        preg_match_all('/^#{1,6}\s+(.+?)\s*#*\s*$/m', $this->withoutFencedCode($text), $m);

        foreach ($m[1] as $heading) {
            $slug = mb_strtolower(trim($heading));
            $slug = (string) preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $slug);
            $slug = str_replace(' ', '-', $slug);

            $anchors[]   = isset($seen[$slug]) ? $slug . '-' . $seen[$slug] : $slug;
            $seen[$slug] = ($seen[$slug] ?? 0) + 1;
        }

        return $anchors;
    }

    private function withoutFencedCode(string $text): string
    {
        return (string) preg_replace('#^```.*?^```#ms', '', $text);
    }

    /**
     * Rows of the tables in docs/commands.md, by command name (the first code span of the row).
     *
     * @return array<string, string>
     */
    private function commandRows(): array
    {
        $rows = [];
        $text = (string) file_get_contents(ROOTPATH . 'docs/commands.md');

        foreach (explode("\n", $text) as $line) {
            if (preg_match('#^\|\s*`([a-z]+:[a-z:-]+|about)[\s`]#', $line, $m) === 1) {
                $rows[$m[1]] = isset($rows[$m[1]]) ? $rows[$m[1]] . "\n" . $line : $line;
            }
        }

        return $rows;
    }

    /**
     * `php spark <command> <arguments>` calls written in a text, the arguments cut at the end of
     * the code span, the line or a shell separator.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function sparkCalls(string $text): array
    {
        preg_match_all('~php spark ([a-z][a-z0-9]*(?::[a-z0-9-]+)*)([^`\n|;&#)]*)~', $text, $m, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [$match[1], $match[2]], $m);
    }

    /**
     * @return list<string>
     */
    private function optionsIn(string $text): array
    {
        preg_match_all('#(?<![\w-])(--[a-z][a-z0-9-]*)#', $text, $m);
        $options = array_values(array_unique($m[1]));
        sort($options);

        return $options;
    }

    private function hasOption(string $command, string $option): bool
    {
        if (in_array($option, self::GLOBAL_OPTIONS, true)) {
            return true;
        }

        $instance = $this->commands()[$command] ?? null;
        if ($instance === null) {
            return false;
        }

        $declared = array_map(
            static fn (string $key): string => '--' . ltrim(explode('=', $key, 2)[0], '-'),
            array_keys($instance->options),
        );

        return in_array($option, $declared, true)
            || str_contains($instance->usage, $option);
    }

    /**
     * @return array<string, BaseCommand>
     */
    private function commands(): array
    {
        if (self::$commands !== null) {
            return self::$commands;
        }

        $runner = Services::commands(false);
        $list   = [];

        foreach ($runner->getCommands() as $name => $details) {
            $class = $details['class'];
            $list[$name] = new $class(Services::logger(), $runner);
        }

        return self::$commands = $list;
    }

    /**
     * Every route (all verbs) as a regular expression and as a sample path (each group of the
     * route's pattern replaced by `1`).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function routePatterns(): array
    {
        /** @var RouteCollection $routes */
        $routes = Services::routes(false)->loadRoutes();
        $list   = [];

        // Devtools registers its routes only in development (D-41-01); the suite runs in `testing`.
        \Modules\Devtools\Routing\DevtoolsRoutes::define($routes);

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', '*'] as $verb) {
            foreach (array_keys($routes->getRoutes($verb)) as $route) {
                $route        = (string) $route;
                $list[$route] = [
                    '#^' . str_replace('#', '\#', $route) . '$#',
                    $this->sample($route),
                ];
            }
        }

        return array_values($list);
    }

    /**
     * A path matched by a route pattern: each (group) becomes `1`.
     */
    private function sample(string $route): string
    {
        do {
            $route = (string) preg_replace('#\([^()]*\)#', '1', $route, -1, $count);
        } while ($count > 0);

        return $route;
    }

    /**
     * Whether a URL written in the docs is a route, or the beginning of routes (`api/v1`,
     * `backend/files/api`, `api/v1/<module>/<key>`). Placeholders (`<id>`, `{id}`, `(:num)`) stand
     * for one segment, `…` and `*` for the rest, `[…]` for an optional part.
     *
     * @param list<array{0: string, 1: string}> $patterns
     */
    private function isRoute(string $url, array $patterns): bool
    {
        $url = rtrim(explode('#', explode('?', $url, 2)[0], 2)[0], '.,:');
        $url = (string) preg_replace('#\[[^\]]*\]#', '', $url);
        $url = rtrim($url, '/');

        $placeholder = '#<[^>/]*>|\{[^}/]*\}|\(:[a-z]+\)#';
        $concrete    = (string) preg_replace($placeholder, '1', str_replace(['/…', '/*', '…', '*'], '', $url));
        $quoted      = preg_split($placeholder, str_replace(['/…', '/*', '…', '*'], '', $url)) ?: [];
        $prefix      = '#^' . implode('[^/]+', array_map(static fn (string $part): string => preg_quote($part, '#'), $quoted)) . '(/.*)?$#';

        foreach ($patterns as [$pattern, $sample]) {
            if (preg_match($pattern, $concrete) === 1 || preg_match($prefix, $sample) === 1) {
                return true;
            }
        }

        return false;
    }
}
