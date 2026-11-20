<?php

declare(strict_types=1);

namespace Modules\Core\Generator\Definition;

/**
 * The kpapy resource of a generated table (docs/api.md): `api/v1/<module>/<key>` with the CRUD's
 * resource name (`setResource()`), so its scopes are the CRUD's permissions; `public` makes a
 * read-only resource without a token instead (`api/v1/public/<module>/<key>`, `kpapy:public`, GET
 * only, list and show).
 */
final class ApiDefinition
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly bool $public = false,
    ) {
    }

    /**
     * @param array<string, mixed>|bool|null $data
     */
    public static function fromArray(array|bool|null $data): self
    {
        if (! is_array($data)) {
            return new self($data === true);
        }

        return new self(
            ! array_key_exists('enabled', $data) || ColumnDefinition::bool($data['enabled']),
            ColumnDefinition::bool($data['public'] ?? false),
        );
    }

    /**
     * @return array{enabled: bool, public: bool}
     */
    public function toArray(): array
    {
        return ['enabled' => $this->enabled, 'public' => $this->public];
    }
}
