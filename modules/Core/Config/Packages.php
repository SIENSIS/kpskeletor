<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Module and theme package options (decision D-00-05; docs/packages.md): demo data in production,
 * new modules found by `modules:sync`, the limits and signatures of ZIP packages
 * (`modules:install`, `themes:install`, staged uploads in the Modules screen).
 *
 * Overridden by `app/Config/Packages.php` and `.env` (`packages.requireSignature = true`);
 * read with `config('Packages')`. CodeIgniter's own `Config\Modules` is never extended.
 */
class Packages extends BaseConfig
{
    /**
     * Allows loading or removing demo data when CI_ENVIRONMENT is production. Off by default.
     */
    public bool $allowDemoInProduction = false;

    /**
     * Enables the modules `modules:sync` finds for the first time (a folder copied by hand). Off:
     * they are listed as *installed, disabled* until an administrator enables them. The first
     * sync of an installation and every environment but production enable them anyway
     * (`make:module` → `modules:sync` in development; D-29-02).
     */
    public bool $autoEnable = false;

    /**
     * Trusted publisher keys (minisign public keys: the base64 line or the whole `.pub` file).
     * A package with a `.minisig` signature must be signed by one of them.
     *
     * @var list<string>
     */
    public array $publicKeys = [];

    /** Refuses unsigned packages (signature by one of $publicKeys). */
    public bool $requireSignature = false;

    /** Largest ZIP accepted (bytes, compressed): uploads, files and downloads. */
    public int $maxPackageBytes = 20 * 1024 * 1024;

    /** Largest total uncompressed size of a package (bytes; zip bombs). */
    public int $maxUncompressedBytes = 100 * 1024 * 1024;

    /** Most entries in a package. */
    public int $maxFiles = 5000;

    /** Highest compression ratio of an entry larger than 1 MB (zip bombs). */
    public int $maxRatio = 100;

    /**
     * File extensions a package may contain (lower case; files without an extension are refused
     * except `LICENSE`, `README`, `CHANGELOG`, `NOTICE` and `AUTHORS`).
     *
     * @var list<string>
     */
    public array $extensions = [
        'php', 'json', 'md', 'txt', 'css', 'js', 'mjs', 'map', 'png', 'jpg', 'jpeg', 'gif', 'webp',
        'avif', 'svg', 'ico', 'woff', 'woff2', 'pot', 'po', 'mo', 'xml', 'csv', 'yml', 'yaml',
    ];

    /** Days a staged package (uploaded or approved, not installed) or a pending uninstall lives. */
    public int $pendingDays = 7;

    /** Seconds `php -l` may take per file before the package is refused. */
    public int $lintTimeout = 20;

    /** PHP binary for `php -l` (empty: the running PHP_BINARY, or `php`). */
    public string $phpBinary = '';
}
