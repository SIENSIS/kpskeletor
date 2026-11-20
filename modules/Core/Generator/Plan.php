<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

use Modules\Core\Generator\Definition\ModuleDefinition;

/**
 * What the generator would write for a definition: every file to create or change with its full
 * new content, and notes (next steps). Nothing is written until `PlanWriter::write()`; the
 * `fingerprint()` of the files as they were on disk lets the writer refuse a plan when a file
 * changed after the preview.
 */
final class Plan
{
    /**
     * @param array<string, PlannedFile> $files path => file, sorted by path
     * @param list<string>               $notes
     */
    public function __construct(
        public readonly ModuleDefinition $definition,
        public readonly array $files,
        public readonly array $notes = [],
    ) {
    }

    /**
     * Files that are new or change.
     *
     * @return array<string, PlannedFile>
     */
    public function changes(): array
    {
        return array_filter($this->files, static fn (PlannedFile $f): bool => $f->status() !== 'unchanged');
    }

    /**
     * Hash of the definition and of every existing file the plan touches, as it was on disk. New
     * files are left out (a migration's name carries the time of the plan); the writer refuses
     * any of them that exists by then.
     */
    public function fingerprint(): string
    {
        $state = ['definition' => $this->definition->toArray(), 'files' => []];

        foreach ($this->files as $path => $file) {
            if ($file->original !== null) {
                $state['files'][$path] = $file->originalHash();
            }
        }

        return hash('sha256', (string) json_encode($state));
    }

    /**
     * A tree of the paths for the preview: folder => [subfolders and files].
     *
     * @return array<string, mixed>
     */
    public function tree(): array
    {
        $tree = [];

        foreach (array_keys($this->files) as $path) {
            $node  = &$tree;
            $parts = explode('/', $path);
            $file  = array_pop($parts);

            foreach ($parts as $part) {
                $node[$part . '/'] ??= [];
                $node = &$node[$part . '/'];
            }

            $node[$file] = $path;
            unset($node);
        }

        return $tree;
    }
}
