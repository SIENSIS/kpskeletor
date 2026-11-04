<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use Modules\Core\Config\ModuleManifest;

/**
 * Checks `$requires` (presence and version constraints) and orders modules so that every module
 * comes after the modules it depends on.
 */
final class DependencyResolver
{
    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<string, ModuleManifest> $manifests name => manifest
     *
     * @return list<string> Module names in dependency order (Core first, then alphabetical)
     */
    public function order(array $manifests): array
    {
        $this->errors = [];
        $pending      = [];

        foreach ($manifests as $name => $manifest) {
            $pending[$name] = [];

            foreach ($manifest->requires as $required => $constraint) {
                if (! isset($manifests[$required])) {
                    $this->errors[] = lang('Core.manifest.error', [$name, "\$requires['{$required}']", lang('Core.manifest.dependencyMissing', [$required])]);

                    continue;
                }

                $version = $manifests[$required]->version;

                if (SemVer::isConstraint($constraint) && SemVer::isVersion($version) && ! SemVer::satisfies($version, $constraint)) {
                    $this->errors[] = lang('Core.manifest.error', [$name, "\$requires['{$required}']", lang('Core.manifest.dependencyVersion', [$required, $constraint, $version])]);
                }

                $pending[$name][] = $required;
            }
        }

        // Core always first, then a stable topological order (Kahn, alphabetical ties).
        $ordered = [];

        while ($pending !== []) {
            $ready = array_keys(array_filter($pending, static fn (array $deps): bool => $deps === []));

            if ($ready === []) {
                $this->errors[] = lang('Core.manifest.error', [
                    implode(', ', array_keys($pending)), '$requires', lang('Core.manifest.dependencyCycle', [implode(' → ', array_keys($pending))]),
                ]);
                break;
            }

            usort($ready, static fn (string $a, string $b): int => [$a !== 'Core', $a] <=> [$b !== 'Core', $b]);
            $next      = $ready[0];
            $ordered[] = $next;
            unset($pending[$next]);

            foreach ($pending as $name => $deps) {
                $pending[$name] = array_values(array_filter($deps, static fn (string $dep): bool => $dep !== $next));
            }
        }

        return $ordered;
    }

    /**
     * @return list<string> Errors of the last call to order()
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
