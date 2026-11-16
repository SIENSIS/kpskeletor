<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

/**
 * What a validated package contains (PackageValidator), read without running its code: shown by
 * `modules:install` and the Modules screen before anything is installed, and stored with a
 * staged package (`module_packages.manifest`, JSON).
 */
final class PackageInfo
{
    public const MODULE = 'module';

    public const THEME = 'theme';

    /**
     * @param 'module'|'theme'      $type
     * @param array<string, string> $requires    module => version constraint (modules)
     * @param list<string>          $permissions permission names the module generates
     * @param list<string>          $menus       menu entry labels (language keys)
     * @param list<string>          $widgets     widget classes
     * @param list<string>          $tasks       scheduled task classes
     * @param array<string, int>    $files       entry name (without the top folder) => size
     */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $version,
        public readonly string $author = '',
        public readonly string $description = '',
        public readonly array $requires = [],
        public readonly ?string $parent = null,
        public readonly array $permissions = [],
        public readonly array $menus = [],
        public readonly array $widgets = [],
        public readonly array $tasks = [],
        public readonly array $files = [],
        public readonly bool $signed = false,
        public readonly string $sha256 = '',
    ) {
    }

    public function isModule(): bool
    {
        return $this->type === self::MODULE;
    }

    public function totalSize(): int
    {
        return array_sum($this->files);
    }

    /**
     * Where the package is installed (`modules/<Name>` or `themes/<slug>`), relative to ROOTPATH.
     */
    public function folder(): string
    {
        return ($this->isModule() ? 'modules/' : 'themes/') . $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type'        => $this->type,
            'name'        => $this->name,
            'version'     => $this->version,
            'author'      => $this->author,
            'description' => $this->description,
            'requires'    => $this->requires,
            'parent'      => $this->parent,
            'permissions' => $this->permissions,
            'menus'       => $this->menus,
            'widgets'     => $this->widgets,
            'tasks'       => $this->tasks,
            'files'       => $this->files,
            'signed'      => $this->signed,
            'sha256'      => $this->sha256,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $strings = static fn (mixed $list): array => is_array($list) ? array_values(array_map('strval', array_filter($list, 'is_scalar'))) : [];
        $files   = [];

        foreach (is_array($data['files'] ?? null) ? $data['files'] : [] as $name => $size) {
            $files[(string) $name] = (int) $size;
        }

        $requires = [];

        foreach (is_array($data['requires'] ?? null) ? $data['requires'] : [] as $module => $constraint) {
            $requires[(string) $module] = (string) $constraint;
        }

        return new self(
            ($data['type'] ?? '') === self::THEME ? self::THEME : self::MODULE,
            (string) ($data['name'] ?? ''),
            (string) ($data['version'] ?? ''),
            (string) ($data['author'] ?? ''),
            (string) ($data['description'] ?? ''),
            $requires,
            is_string($data['parent'] ?? null) ? $data['parent'] : null,
            $strings($data['permissions'] ?? []),
            $strings($data['menus'] ?? []),
            $strings($data['widgets'] ?? []),
            $strings($data['tasks'] ?? []),
            $files,
            ($data['signed'] ?? false) === true,
            (string) ($data['sha256'] ?? ''),
        );
    }
}
