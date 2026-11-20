<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

use Modules\Core\Config\Packages;
use Modules\Core\Packages\PackageRefused;
use Modules\Core\Packages\PackageValidator;
use ZipArchive;

/**
 * Builds the installable ZIP of a module or theme (`modules:package`, Devtools › Packages;
 * docs/devtools.md): `<Name>-<version>.zip` with one top folder, leaving out dotfiles and dot
 * folders, `node_modules`, `writable`, editor files and every file whose extension the
 * installer would refuse (`Config\Packages::$extensions`; listed as *skipped*). A symbolic link
 * stops the build. The ZIP is then validated with the Core's `PackageValidator` exactly as
 * `modules:install` / `themes:install` validate it (manifest read without running it, sizes,
 * zip-slip, links, extensions, `php -l`); any error removes it. Finally `<zip>.sha256`
 * (`sha256sum` format) is written and a previous signature of the same file is removed.
 */
final class PackageBuilder
{
    /** Folder names never packaged (besides every name starting with a dot). */
    public const EXCLUDED_FOLDERS = ['node_modules', 'writable'];

    /** Editor and system files never packaged. */
    public const EDITOR_FILES = '/(~|\.swp|\.swo|\.swx|\.bak|\.orig|\.rej|\.tmp|\.kate-swp)$|^(Thumbs\.db|desktop\.ini|Desktop\.ini)$/';

    private readonly Packages $config;

    public function __construct(?Packages $config = null, private readonly bool $checkGit = true)
    {
        // Built packages are validated without a signature: they are signed afterwards.
        $this->config                   = clone ($config ?? config('Packages'));
        $this->config->requireSignature = false;
    }

    /**
     * @throws PackageBuildFailed
     */
    public function build(PackageSource $source, ?string $outDir = null): BuildResult
    {
        $outDir = rtrim($outDir ?? PackageStore::ensureFolder(), '/');

        if (! is_dir($outDir) && ! @mkdir($outDir, 0750, true)) {
            throw new PackageBuildFailed(lang('Devtools.packages.errors.outDir', [$outDir]));
        }

        $warnings = [];

        if ($this->checkGit) {
            $changes = GitStatus::changes($source->folder);

            if ($changes !== null && $changes !== []) {
                $warnings[] = lang('Devtools.packages.warnings.uncommitted', [count($changes), implode(', ', array_slice($changes, 0, 5))]);
            }
        }

        [$files, $skipped] = $this->collect($source->folder);

        $final = $outDir . '/' . $source->fileName();
        $temp  = $outDir . '/.' . $source->name . '-' . bin2hex(random_bytes(6)) . '.zip.tmp';

        try {
            $this->zip($temp, $source->name, $files);

            try {
                $info = (new PackageValidator($this->config))->validateAndLint($temp, null, $source->type, $outDir, $source->fileName());
            } catch (PackageRefused $e) {
                throw new PackageBuildFailed(lang('Devtools.packages.errors.invalid', [$e->getMessage()]), 0, $e);
            }

            if (! @rename($temp, $final)) {
                throw new PackageBuildFailed(lang('Devtools.packages.errors.outDir', [$outDir]));
            }
        } finally {
            @unlink($temp);
        }

        PackageStore::forgetSignature($final);
        $sha256 = (string) hash_file('sha256', $final);
        file_put_contents($final . '.sha256', $sha256 . '  ' . basename($final) . "\n", LOCK_EX);

        return new BuildResult($final, $info, $sha256, $skipped, $warnings);
    }

    /**
     * The files to package (relative path => absolute path) and the ones left out.
     *
     * @return array{0: array<string, string>, 1: list<string>}
     */
    public function collect(string $folder): array
    {
        $folder = rtrim($folder, '/');

        if (is_link($folder) || ! is_dir($folder)) {
            throw new PackageBuildFailed(lang('Devtools.packages.errors.noSource', [basename($folder)]));
        }

        $files   = [];
        $skipped = [];
        $links   = [];

        $filter = static function (\SplFileInfo $item) use ($folder, &$links): bool {
            $name = $item->getFilename();

            if ($item->isLink()) {
                $links[] = substr($item->getPathname(), strlen($folder) + 1);

                return false;
            }

            if (str_starts_with($name, '.')) {
                return false;
            }

            return ! ($item->isDir() && in_array($name, self::EXCLUDED_FOLDERS, true));
        };

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS), $filter),
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($folder) + 1));

            if (! $item->isFile()) {
                continue;
            }

            if (preg_match(self::EDITOR_FILES, $item->getFilename()) === 1 || ! $this->allowedExtension($item->getFilename())) {
                $skipped[] = $relative;

                continue;
            }

            $files[$relative] = $item->getPathname();
        }

        if ($links !== []) {
            sort($links);

            throw new PackageBuildFailed(lang('Devtools.packages.errors.link', [implode(', ', array_slice($links, 0, 5))]));
        }

        ksort($files, SORT_STRING);
        sort($skipped);

        return [$files, $skipped];
    }

    /**
     * The installer's extension rule (PackageValidator::checkExtension()).
     */
    private function allowedExtension(string $base): bool
    {
        if (str_contains($base, '.') && ! str_starts_with($base, '.')) {
            return in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), array_map('strtolower', $this->config->extensions), true);
        }

        return in_array($base, PackageValidator::BARE_NAMES, true);
    }

    /**
     * @param array<string, string> $files
     */
    private function zip(string $target, string $top, array $files): void
    {
        $zip = new ZipArchive();

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new PackageBuildFailed(lang('Devtools.packages.errors.outDir', [dirname($target)]));
        }

        foreach ($files as $relative => $path) {
            $entry = $top . '/' . $relative;
            $zip->addFile($path, $entry);
            $zip->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, 0100644 << 16);
        }

        if (! $zip->close()) {
            throw new PackageBuildFailed(lang('Devtools.packages.errors.outDir', [dirname($target)]));
        }
    }
}
