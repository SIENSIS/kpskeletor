<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

/**
 * One release of a repository: its tag and version, release notes and downloadable assets.
 */
final class Release
{
    /**
     * @param array<string, string> $assets file name => download URL (an API URL when the
     *                                      repository needs a token)
     */
    public function __construct(
        public readonly string $version,
        public readonly string $tag,
        public readonly string $name = '',
        public readonly string $notes = '',
        public readonly string $url = '',
        public readonly string $publishedAt = '',
        public readonly bool $prerelease = false,
        public readonly array $assets = [],
    ) {
    }

    /**
     * The release archive: the first `.zip` asset published with its `.minisig` and `.sha256`
     * files, or null.
     *
     * @return array{name: string, url: string, signature: string, checksum: string}|null
     */
    public function archive(): ?array
    {
        foreach ($this->assets as $name => $url) {
            if (str_ends_with(strtolower($name), '.zip') && isset($this->assets[$name . '.minisig'], $this->assets[$name . '.sha256'])) {
                return ['name' => $name, 'url' => $url, 'signature' => $this->assets[$name . '.minisig'], 'checksum' => $this->assets[$name . '.sha256']];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version'     => $this->version,
            'tag'         => $this->tag,
            'name'        => $this->name,
            'notes'       => $this->notes,
            'url'         => $this->url,
            'publishedAt' => $this->publishedAt,
            'prerelease'  => $this->prerelease,
            'assets'      => $this->assets,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $version = VersionComparator::normalize((string) ($data['version'] ?? ''));

        if ($version === null) {
            return null;
        }

        $assets = [];

        foreach (is_array($data['assets'] ?? null) ? $data['assets'] : [] as $name => $url) {
            if (is_string($name) && is_string($url)) {
                $assets[$name] = $url;
            }
        }

        return new self(
            $version,
            (string) ($data['tag'] ?? $version),
            (string) ($data['name'] ?? ''),
            (string) ($data['notes'] ?? ''),
            (string) ($data['url'] ?? ''),
            (string) ($data['publishedAt'] ?? ''),
            (bool) ($data['prerelease'] ?? false),
            $assets,
        );
    }
}
