<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

use Modules\Core\Http\OutboundClient;
use Modules\Core\Http\OutboundException;

/**
 * Reads the releases of a repository through the Core's OutboundClient (HTTPS, allow-list):
 *
 * - `github:owner/repo`: `GET https://api.github.com/repos/owner/repo/releases`;
 * - `gitlab:group/project` (gitlab.com) or `https://gitlab.example.com/group/project`:
 *   `GET https://<host>/api/v4/projects/<id>/releases`.
 *
 * Drafts, upcoming releases and tags that are not semantic versions are ignored. With a token
 * (private repositories) GitHub assets are downloaded through the API (`Accept:
 * application/octet-stream`); OutboundClient drops the token when a download redirects to
 * another host.
 */
final class ReleaseSource
{
    /** @var array{type: string, host: string, path: string} */
    private readonly array $repository;

    public function __construct(string $repository, private readonly string $token = '', private readonly ?OutboundClient $client = null)
    {
        $parsed = self::parse($repository);

        if ($parsed === null) {
            throw new UpdateException(lang('Updates.error.repository', [$repository]));
        }

        $this->repository = $parsed;
    }

    /**
     * @return array{type: string, host: string, path: string}|null
     */
    public static function parse(string $repository): ?array
    {
        $repository = trim($repository);
        $segment    = '[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9_])?';

        if (preg_match('#\Agithub:(' . $segment . '/' . $segment . ')\z#', $repository, $m) === 1) {
            return ['type' => 'github', 'host' => 'api.github.com', 'path' => $m[1]];
        }

        if (preg_match('#\Agitlab:(' . $segment . '(?:/' . $segment . ')+)\z#', $repository, $m) === 1) {
            return ['type' => 'gitlab', 'host' => 'gitlab.com', 'path' => $m[1]];
        }

        $url = (string) preg_replace('#(?:\.git)?/?\z#', '', $repository);

        if (preg_match('#\Ahttps://([a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)/(' . $segment . '(?:/' . $segment . ')+)\z#i', $url, $m) === 1) {
            $host = strtolower($m[1]);

            if (in_array($host, ['github.com', 'www.github.com'], true)) {
                return substr_count($m[2], '/') === 1 ? ['type' => 'github', 'host' => 'api.github.com', 'path' => $m[2]] : null;
            }

            return ['type' => 'gitlab', 'host' => $host, 'path' => $m[2]];
        }

        return null;
    }

    /**
     * Host names the source calls (for the outbound allow-list).
     *
     * @return list<string>
     */
    public function hosts(): array
    {
        return $this->repository['type'] === 'github'
            ? ['api.github.com', 'github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com']
            : [$this->repository['host']];
    }

    /**
     * The newest release (highest semantic version), or null when there is none.
     *
     * @throws UpdateException
     */
    public function latest(bool $prereleases = false): ?Release
    {
        $latest = null;

        foreach ($this->releases() as $release) {
            if ($release->prerelease && ! $prereleases) {
                continue;
            }

            if ($latest === null || VersionComparator::isNewer($release->version, $latest->version)) {
                $latest = $release;
            }
        }

        return $latest;
    }

    /**
     * @return list<Release>
     *
     * @throws UpdateException
     */
    public function releases(): array
    {
        $data = $this->json($this->releasesUrl());

        if (! array_is_list($data)) {
            throw new UpdateException(lang('Updates.error.response'));
        }

        $releases = [];

        foreach ($data as $item) {
            $release = is_array($item) ? ($this->repository['type'] === 'github' ? $this->github($item) : $this->gitlab($item)) : null;

            if ($release !== null) {
                $releases[] = $release;
            }
        }

        return $releases;
    }

    /**
     * Downloads one asset (archive, signature or checksum).
     *
     * @throws UpdateException
     */
    public function download(string $url, ?OutboundClient $client = null): string
    {
        $headers = $this->authHeaders();

        if ($this->repository['type'] === 'github' && str_starts_with($url, 'https://api.github.com/')) {
            $headers['Accept'] = 'application/octet-stream';
        }

        try {
            $response = ($client ?? $this->client())->get($url, $headers);
        } catch (OutboundException $e) {
            throw new UpdateException(lang('Updates.error.network', [$e->getMessage()]), 0, $e);
        }

        if (! $response->ok()) {
            throw new UpdateException(lang('Updates.error.status', [$response->status, self::basename($url)]));
        }

        return $response->body;
    }

    public function releasesUrl(): string
    {
        if ($this->repository['type'] === 'github') {
            return 'https://api.github.com/repos/' . $this->repository['path'] . '/releases?per_page=50';
        }

        return 'https://' . $this->repository['host'] . '/api/v4/projects/' . rawurlencode($this->repository['path']) . '/releases?per_page=50';
    }

    /**
     * @param array<mixed> $item
     */
    private function github(array $item): ?Release
    {
        if (($item['draft'] ?? false) === true) {
            return null;
        }

        $tag     = is_string($item['tag_name'] ?? null) ? $item['tag_name'] : '';
        $version = VersionComparator::normalize($tag);

        if ($version === null) {
            return null;
        }

        $assets = [];

        foreach (is_array($item['assets'] ?? null) ? $item['assets'] : [] as $asset) {
            if (! is_array($asset) || ! is_string($asset['name'] ?? null)) {
                continue;
            }

            $url = $this->token !== '' ? ($asset['url'] ?? null) : ($asset['browser_download_url'] ?? null);

            if (is_string($url) && str_starts_with($url, 'https://')) {
                $assets[$asset['name']] = $url;
            }
        }

        return new Release(
            $version,
            $tag,
            is_string($item['name'] ?? null) ? $item['name'] : '',
            is_string($item['body'] ?? null) ? $item['body'] : '',
            is_string($item['html_url'] ?? null) ? $item['html_url'] : '',
            is_string($item['published_at'] ?? null) ? $item['published_at'] : '',
            ($item['prerelease'] ?? false) === true || VersionComparator::isPrerelease($version),
            $assets,
        );
    }

    /**
     * @param array<mixed> $item
     */
    private function gitlab(array $item): ?Release
    {
        if (($item['upcoming_release'] ?? false) === true) {
            return null;
        }

        $tag     = is_string($item['tag_name'] ?? null) ? $item['tag_name'] : '';
        $version = VersionComparator::normalize($tag);

        if ($version === null) {
            return null;
        }

        $assets = [];
        $links  = is_array($item['assets'] ?? null) && is_array($item['assets']['links'] ?? null) ? $item['assets']['links'] : [];

        foreach ($links as $link) {
            if (! is_array($link) || ! is_string($link['name'] ?? null)) {
                continue;
            }

            $url = $link['direct_asset_url'] ?? $link['url'] ?? null;

            if (is_string($url) && str_starts_with($url, 'https://')) {
                $assets[$link['name']] = $url;
            }
        }

        $self = is_array($item['_links'] ?? null) && is_string($item['_links']['self'] ?? null) ? $item['_links']['self'] : '';

        return new Release(
            $version,
            $tag,
            is_string($item['name'] ?? null) ? $item['name'] : '',
            is_string($item['description'] ?? null) ? $item['description'] : '',
            $self,
            is_string($item['released_at'] ?? null) ? $item['released_at'] : '',
            VersionComparator::isPrerelease($version),
            $assets,
        );
    }

    /**
     * @return array<mixed>
     *
     * @throws UpdateException
     */
    private function json(string $url): array
    {
        $headers = $this->authHeaders() + ['Accept' => $this->repository['type'] === 'github' ? 'application/vnd.github+json' : 'application/json'];

        try {
            $response = $this->client()->get($url, $headers);
        } catch (OutboundException $e) {
            throw new UpdateException(lang('Updates.error.network', [$e->getMessage()]), 0, $e);
        }

        if (in_array($response->status, [401, 403, 404], true)) {
            throw new UpdateException(lang('Updates.error.access', [$response->status]));
        }

        if (! $response->ok()) {
            throw new UpdateException(lang('Updates.error.status', [$response->status, $this->repository['host']]));
        }

        try {
            $data = $response->json();
        } catch (\JsonException) {
            $data = null;
        }

        if (! is_array($data)) {
            throw new UpdateException(lang('Updates.error.response'));
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        if ($this->token === '') {
            return [];
        }

        return $this->repository['type'] === 'github'
            ? ['Authorization' => 'Bearer ' . $this->token, 'X-GitHub-Api-Version' => '2022-11-28']
            : ['PRIVATE-TOKEN' => $this->token];
    }

    private function client(): OutboundClient
    {
        return $this->client ?? new OutboundClient();
    }

    private static function basename(string $url): string
    {
        return basename((string) parse_url($url, PHP_URL_PATH));
    }
}
