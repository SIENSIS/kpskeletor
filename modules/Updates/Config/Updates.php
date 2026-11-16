<?php

declare(strict_types=1);

namespace Modules\Updates\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Updates configuration (docs/updates.md). Deployment values only, overridable from `.env`
 * (`updates.repository`, `updates.token`, …); the token and nothing secret belong in this file.
 *
 * Releases are verified before anything is applied: the release archive's SHA-256 (asset
 * `<archive>.sha256`) **and** its Ed25519 signature (asset `<archive>.minisig`, minisign
 * format) made with the owner's private key, which never leaves the owner's computer. Without a
 * public key every update is refused. Installs that are git checkouts verify the release tag
 * with `git verify-tag` instead (the signer's key must be trusted by git on the server).
 */
class Updates extends BaseConfig
{
    /**
     * Where the application's releases are published: `github:owner/repo`, `gitlab:group/project`
     * (gitlab.com) or `https://gitlab.example.com/group/project` (a self-hosted GitLab). Empty =
     * update checks are off for the application (modules may still declare `$repository`).
     */
    public string $repository = '';

    /** Token for a private repository (`updates.token` in `.env`; never in this file). */
    public string $token = '';

    /**
     * Minisign public keys (the base64 line of `minisign.pub`, starting with `RW`) trusted for
     * release archives, comma separated: two keys during a key rotation; empty = every update is
     * refused. `.env`: `updates.publicKeys = RW…,RW…`.
     */
    public string $publicKeys = '';

    /** Offer pre-releases (`1.2.0-beta.1`) too. */
    public bool $prereleases = false;

    /**
     * How `app:update` installs the code: `auto` (a git checkout updates with git, anything else
     * with the release archive), `archive` or `git`.
     */
    public string $method = 'auto';

    /** Git remote used by the `git` method. */
    public string $gitRemote = 'origin';

    /** Largest release archive downloaded (bytes) and the download timeout (seconds). */
    public int $maxArchiveBytes = 104857600;

    public int $downloadTimeout = 300;

    /**
     * Paths of the installation that an archive update never writes nor deletes (relative to
     * the root; a trailing `/` = a folder).
     *
     * @var list<string>
     */
    public array $protectedPaths = ['.env', '.git/', 'writable/', 'vendor/', 'public/uploads/', 'public/assets/modules/', 'node_modules/'];

    /** Folder of the update state, downloads and the copies of replaced files. */
    public string $path = WRITEPATH . 'updates';

    /** The installation updated by `app:update` (tests point it elsewhere). */
    public string $rootPath = ROOTPATH;

    /** PHP binary used to run `spark` after the code changed (a new process loads the new code). */
    public string $phpBinary = PHP_BINARY;

    /** Composer binary (`composer install --no-dev`). */
    public string $composerBinary = 'composer';

    /** Git binary. */
    public string $gitBinary = 'git';

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->publicKeys)), static fn (string $key): bool => $key !== ''));
    }
}
