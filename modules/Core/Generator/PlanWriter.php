<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\NamespaceFile;

/**
 * Writes a plan: refused when the plan's fingerprint is not the one previewed (a file changed on
 * disk, or the definition changed), then every file is written to a temporary name and renamed
 * (atomic per file); if one fails, the files already written are put back as they were. A new
 * module's namespace is registered in `app/Config/ModuleNamespaces.php`.
 */
final class PlanWriter
{
    public function __construct(private readonly GeneratorContext $context = new GeneratorContext())
    {
    }

    /**
     * @return list<string> paths written (inside the modules folder)
     *
     * @throws GeneratorException
     */
    public function write(Plan $plan, ?string $expectedFingerprint = null): array
    {
        if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, $plan->fingerprint())) {
            throw new GeneratorException(lang('Core.generator.changedSincePreview'));
        }

        $root    = rtrim($this->context->root, '/\\');
        $written = [];

        // The files must still be as the plan saw them (also without a preview).
        foreach ($plan->changes() as $path => $file) {
            $disk    = $root . '/' . $path;
            $current = is_file($disk) ? hash_file('sha256', $disk) : null;

            if ($current !== $file->originalHash()) {
                throw new GeneratorException(lang('Core.generator.changedSincePreview'));
            }
        }

        try {
            foreach ($plan->changes() as $path => $file) {
                $this->writeFile($root . '/' . $path, $file->content);
                $written[] = $path;
            }
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                $file = $plan->files[$path];

                if ($file->original === null) {
                    @unlink($root . '/' . $path);
                } else {
                    @file_put_contents($root . '/' . $path, $file->original);
                }
            }

            throw new GeneratorException(lang('Core.generator.writeFailed', [$e->getMessage()]), 0, $e);
        }

        if (! $plan->definition->existing && $root === rtrim(ROOTPATH . 'modules', '/\\')) {
            (new NamespaceFile())->write((new ModuleDiscovery())->folders());
        }

        return $written;
    }

    private function writeFile(string $file, string $content): void
    {
        $dir = dirname($file);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException($dir);
        }

        $temporary = $file . '.tmp-' . bin2hex(random_bytes(4));

        if (file_put_contents($temporary, $content) === false || ! rename($temporary, $file)) {
            @unlink($temporary);

            throw new \RuntimeException($file);
        }
    }
}
