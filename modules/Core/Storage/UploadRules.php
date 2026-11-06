<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

/**
 * The rules of one upload field: allowed extensions, maximum size and image re-encoding. A field
 * can only narrow the global rules (`Core.uploadTypes`, `Core.uploadMaxSize`,
 * `Core.uploadAllowSvg`): an extension must be in both lists and the smaller size wins.
 *
 * ```php
 * $rules = UploadRules::make(['jpg', 'png', 'webp'], 2 * 1024 * 1024);
 * $file  = service('storage')->store($this->request->getFile('cover'), 'public', 'modules/news', $rules);
 * ```
 */
final class UploadRules
{
    /**
     * @param list<string> $types   allowed extensions (empty = the global list)
     * @param int|null     $maxSize bytes (null = the global limit)
     * @param bool         $reencode re-encode images (default; turn off only for a field that
     *                               must keep the original bytes)
     */
    public function __construct(
        public readonly array $types = [],
        public readonly ?int $maxSize = null,
        public readonly bool $reencode = true,
    ) {
    }

    /**
     * @param list<string>|string $types extensions, as a list or comma separated
     */
    public static function make(array|string $types = [], ?int $maxSize = null, bool $reencode = true): self
    {
        return new self(self::parseTypes($types), $maxSize, $reencode);
    }

    /**
     * @param list<string>|string $types
     *
     * @return list<string>
     */
    public static function parseTypes(array|string $types): array
    {
        if (is_string($types)) {
            $types = explode(',', $types);
        }

        $list = [];

        foreach ($types as $type) {
            $type = strtolower(trim(ltrim(trim((string) $type), '.')));

            if ($type !== '' && preg_match('/^[a-z0-9]{1,16}$/', $type) === 1) {
                $list[] = $type;
            }
        }

        return array_values(array_unique($list));
    }
}
