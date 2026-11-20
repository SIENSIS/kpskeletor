<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

/**
 * The folder of built packages, `writable/devtools/packages/` (never under `public/`): the ZIP,
 * `<zip>.sha256`, `<zip>.minisig` and `<zip>.json` (public facts of the signature). Files are
 * served only by `Devtools\Controllers\Packages::download()` through path(), which accepts
 * plain names that match NAME_PATTERN and exist in this folder.
 */
final class PackageStore
{
    /** `<Name>-<version>.zip` and its companions; no folders, no `..`. */
    public const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,80}-\d+\.\d+\.\d+(?:-[0-9A-Za-z.]{1,40})?\.zip(?:\.sha256|\.minisig)?$/';

    /** Tests only: another folder. */
    public static ?string $testFolder = null;

    public static function folder(): string
    {
        return self::$testFolder ?? WRITEPATH . 'devtools/packages';
    }

    public static function ensureFolder(): string
    {
        $folder = self::folder();

        if (! is_dir($folder)) {
            mkdir($folder, 0750, true);
        }

        return $folder;
    }

    /**
     * The full path of a stored file, or null when the name is not acceptable or missing.
     */
    public static function path(string $name): ?string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || str_contains($name, '..')) {
            return null;
        }

        $path = self::folder() . '/' . $name;

        return is_file($path) && ! is_link($path) ? $path : null;
    }

    /**
     * The built packages, newest first.
     *
     * @return list<array{file: string, size: int, time: int, sha256: bool, minisig: bool, info: array<string, string>|null}>
     */
    public static function packages(): array
    {
        $list = [];

        foreach (glob(self::folder() . '/*.zip') ?: [] as $zip) {
            $name = basename($zip);

            if (self::path($name) === null) {
                continue;
            }

            $list[] = [
                'file'    => $name,
                'size'    => (int) filesize($zip),
                'time'    => (int) filemtime($zip),
                'sha256'  => is_file($zip . '.sha256'),
                'minisig' => is_file($zip . '.minisig'),
                'info'    => is_file($zip . '.minisig') ? self::readInfo($zip) : null,
            ];
        }

        usort($list, static fn (array $a, array $b): int => [$b['time'], $a['file']] <=> [$a['time'], $b['file']]);

        return $list;
    }

    /**
     * @param array<string, string> $info
     */
    public static function writeInfo(string $zip, array $info): void
    {
        file_put_contents($zip . '.json', json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }

    /**
     * @return array<string, string>|null
     */
    public static function readInfo(string $zip): ?array
    {
        if (! is_file($zip . '.json')) {
            return null;
        }

        $data = json_decode((string) file_get_contents($zip . '.json'), true);

        if (! is_array($data)) {
            return null;
        }

        $info = [];

        foreach (['file', 'keyId', 'publicKey', 'sha256', 'trustedComment'] as $key) {
            $info[$key] = is_string($data[$key] ?? null) ? $data[$key] : '';
        }

        return $info;
    }

    /** Removes the signature files of a package (rebuilt: the old signature no longer matches). */
    public static function forgetSignature(string $zip): void
    {
        @unlink($zip . '.minisig');
        @unlink($zip . '.json');
    }
}
