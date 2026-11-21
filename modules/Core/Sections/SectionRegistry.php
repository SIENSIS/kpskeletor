<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * The section types of the enabled modules (manifest `$sectionTypes`, stored by `modules:sync`),
 * by key. A class that is missing, does not extend `SectionType`, cannot be built, or has an
 * invalid or repeated key (or a key whose module part is not the declaring module) is skipped and
 * logged, so one broken module never breaks a page.
 */
final class SectionRegistry
{
    /** @var array<string, array{type: SectionType, module: string}>|null */
    private ?array $types = null;

    public function __construct(private readonly DeclarationRegistry $registry = new DeclarationRegistry())
    {
    }

    /**
     * Every type of the enabled modules, in manifest order.
     *
     * @return array<string, SectionType> key => type
     */
    public function all(): array
    {
        return array_map(static fn (array $entry): SectionType => $entry['type'], $this->load());
    }

    public function get(string $key): ?SectionType
    {
        return $this->load()[$key]['type'] ?? null;
    }

    /** The module that declares a type (null when unknown). */
    public function module(string $key): ?string
    {
        return $this->load()[$key]['module'] ?? null;
    }

    /**
     * @return array<string, array{type: SectionType, module: string}>
     */
    private function load(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $this->types = [];

        foreach ($this->registry->sectionTypes() as $class => $module) {
            try {
                $type = class_exists($class) && is_subclass_of($class, SectionType::class) ? new $class() : null;
                $key  = $type?->key();
            } catch (Throwable $e) {
                $type = $key = null;
                log_message('error', 'Section type {class} could not be built: {message}', ['class' => $class, 'message' => $e->getMessage()]);
            }

            if (! $type instanceof SectionType || ! is_string($key) || preg_match(SectionType::KEY_PATTERN, $key) !== 1
                || $type->module() !== strtolower($module) || isset($this->types[$key])) {
                log_message('error', 'Section type {class} skipped: not a SectionType, or an invalid, foreign or repeated key.', ['class' => $class]);

                continue;
            }

            $this->types[$key] = ['type' => $type, 'module' => $module];
        }

        return $this->types;
    }
}
