<?php

declare(strict_types=1);

namespace Tests\Unit\Docs;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The tutorials (`docs/tutorials/`, session 34) stay in step with the `Tasks` module: the index
 * lists the 14 tutorials of the specification in order, every tutorial ends with a "Tests"
 * section, every test method it names exists, and every file of the repository it names exists.
 * Links, anchors, `php spark` commands and back-office/API URLs are checked by
 * `DocumentationTest`.
 *
 * @internal
 */
final class TutorialsTest extends CIUnitTestCase
{
    private const FOLDER = 'docs/tutorials';

    /** The 14 tutorials of the specification ("Tutorials"), in order. */
    private const TUTORIALS = [
        '01-module-from-scratch.md', '02-extending-a-crud.md', '03-view-with-javascript.md',
        '04-widget.md', '05-public-page.md', '06-talking-to-other-modules.md',
        '07-scheduled-task.md', '08-setting.md', '09-child-theme.md', '10-file-uploads.md',
        '11-json-api.md', '12-public-form.md', '13-releasing-a-version.md', '14-writing-tests.md',
    ];

    /**
     * Names in code spans that are not files of this repository: the scratch and example modules,
     * placeholders, and the Storage folder of the module's attachments (`modules/tasks`).
     */
    private const EXAMPLES = ['modules/Tasks2', 'modules/Books', 'modules/<', 'themes/<', 'themes/corporate', 'modules/tasks'];

    public function testTheIndexListsEveryTutorialInOrder(): void
    {
        $files = array_map('basename', glob(ROOTPATH . self::FOLDER . '/[0-9][0-9]-*.md') ?: []);
        sort($files);
        $this->assertSame(self::TUTORIALS, $files);

        $index = $this->read('README.md');
        preg_match_all('/\]\((\d\d-[a-z0-9-]+\.md)\)/', $index, $links);
        $this->assertSame(self::TUTORIALS, array_values(array_unique($links[1])), 'README.md links every tutorial once, in order.');
    }

    public function testEveryTutorialEndsWithItsTests(): void
    {
        foreach (self::TUTORIALS as $file) {
            preg_match_all('/^## (.+)$/m', $this->withoutFencedCode($this->read($file)), $headings);
            $this->assertNotSame([], $headings[1], $file);
            $this->assertSame('Tests', end($headings[1]), $file . ': the last section is "Tests".');
        }
    }

    public function testEveryTestMethodNamedExists(): void
    {
        $methods = $this->testMethods();
        $named   = 0;

        foreach (self::TUTORIALS as $file) {
            preg_match_all('/`(test[A-Z]\w+)`/', $this->read($file), $matches);

            foreach (array_unique($matches[1]) as $method) {
                $named++;
                $this->assertContains($method, $methods, $file . ' names a test that does not exist: ' . $method);
            }
        }

        $this->assertGreaterThan(40, $named, 'The tutorials name the tests that cover them.');
    }

    public function testEveryRepositoryFileNamedExists(): void
    {
        foreach (array_merge(['README.md'], self::TUTORIALS) as $file) {
            preg_match_all('/`((?:modules|themes|tests|app)\/[^`\s]+)`/', $this->read($file), $matches);

            foreach (array_unique($matches[1]) as $path) {
                if ($this->isExample($path)) {
                    continue;
                }

                foreach ($this->expand($path) as $candidate) {
                    $this->assertNotSame([], glob(ROOTPATH . $candidate) ?: [], $file . ' names a missing file: ' . $candidate);
                }
            }
        }
    }

    public function testTheModuleNotesPointAtTheTutorials(): void
    {
        $notes = (string) file_get_contents(ROOTPATH . 'modules/Tasks/CLAUDE.md');

        $this->assertStringContainsString('docs/tutorials/', $notes);
        $this->assertStringContainsString('AddStartedAtToTasks', $notes, 'Tutorial 13 is mapped to its files.');
    }

    private function read(string $file): string
    {
        $path = ROOTPATH . self::FOLDER . '/' . $file;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function withoutFencedCode(string $text): string
    {
        return (string) preg_replace('/^```.*?^```/ms', '', $text);
    }

    private function isExample(string $path): bool
    {
        foreach (self::EXAMPLES as $prefix) {
            if ($path === $prefix || str_starts_with($path, str_ends_with($prefix, '<') ? $prefix : $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * A path as written in the docs, as glob patterns: `{ca,es,en}` expanded, `…` and a trailing
     * `*` kept as wildcards, a trailing punctuation or `(…)` call dropped.
     *
     * @return list<string>
     */
    private function expand(string $path): array
    {
        $path = (string) preg_replace('/[(:].*$/', '', $path);
        $path = rtrim(str_replace('…', '*', $path), '.,;');

        if (preg_match('/\{([^}]+)\}/', $path, $match) !== 1) {
            return [$path];
        }

        return array_map(static fn (string $part): string => str_replace($match[0], $part, $path), explode(',', $match[1]));
    }

    /**
     * Every `test…` method of the module and suite test classes.
     *
     * @return list<string>
     */
    private function testMethods(): array
    {
        $files = array_merge(
            glob(ROOTPATH . 'modules/*/Tests/*.php') ?: [],
            glob(ROOTPATH . 'tests/*/*/*.php') ?: [],
            glob(ROOTPATH . 'tests/*/*.php') ?: [],
        );
        $methods = [];

        foreach ($files as $file) {
            preg_match_all('/function (test\w+)\s*\(/', (string) file_get_contents($file), $matches);
            array_push($methods, ...$matches[1]);
        }

        return array_values(array_unique($methods));
    }
}
