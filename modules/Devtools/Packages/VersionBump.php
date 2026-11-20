<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

/**
 * Raises the version of a module or theme before packaging (Devtools › Packages): the `$version`
 * literal of its manifest (`Config/Module.php` or `Theme.php`) becomes the next patch, minor or
 * major version, and its `CHANGELOG.md` gets a section for it (created when missing; an
 * `## [Unreleased]` section becomes the new version's). Only those two files change.
 */
final class VersionBump
{
    public const LEVELS = ['patch', 'minor', 'major'];

    private const VERSION = '/(public\s+string\s+\$version\s*=\s*)([\'"])(\d+)\.(\d+)\.(\d+)([^\'"]*)\2/';

    /**
     * @return string the new version
     *
     * @throws PackageBuildFailed
     */
    public function raise(PackageSource $source, string $level): string
    {
        if (! in_array($level, self::LEVELS, true)) {
            throw new PackageBuildFailed(lang('Devtools.packages.errors.level'));
        }

        $manifest = $source->manifest();
        $code     = (string) @file_get_contents($manifest);

        if (preg_match_all(self::VERSION, $code, $matches, PREG_SET_ORDER) !== 1) {
            throw new PackageBuildFailed(lang('Devtools.packages.errors.versionLiteral', [basename($manifest)]));
        }

        $version = self::next((int) $matches[0][3], (int) $matches[0][4], (int) $matches[0][5], $level);
        $code    = (string) preg_replace(self::VERSION, '${1}${2}' . $version . '${2}', $code, 1);

        $changelog = $source->folder . '/CHANGELOG.md';
        $text      = is_file($changelog) ? (string) file_get_contents($changelog) : '';

        self::write($manifest, $code);
        self::write($changelog, self::changelog($text, $source->name, $version));

        return $version;
    }

    public static function next(int $major, int $minor, int $patch, string $level): string
    {
        return match ($level) {
            'major' => ($major + 1) . '.0.0',
            'minor' => $major . '.' . ($minor + 1) . '.0',
            default => $major . '.' . $minor . '.' . ($patch + 1),
        };
    }

    public static function changelog(string $text, string $name, string $version): string
    {
        $section = '## ' . $version;

        if (trim($text) === '') {
            $text = '# Changelog of ' . $name . "\n\nNewest first. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).\n";
        }

        if (preg_match('/^## \[?Unreleased\]?[^\n]*$/mi', $text, $match, PREG_OFFSET_CAPTURE) === 1) {
            $at = $match[0][1] + strlen($match[0][0]);

            return substr($text, 0, $at) . "\n\n" . $section . substr($text, $at);
        }

        $new = $section . "\n\n### Changed\n\n- (describe the changes of this version)\n\n";

        if (preg_match('/^## /m', $text, $match, PREG_OFFSET_CAPTURE) === 1) {
            return substr($text, 0, $match[0][1]) . $new . substr($text, $match[0][1]);
        }

        return rtrim($text) . "\n\n" . $new;
    }

    private static function write(string $file, string $contents): void
    {
        $temp = $file . '.tmp-' . bin2hex(random_bytes(4));

        if (file_put_contents($temp, $contents, LOCK_EX) === false || ! @rename($temp, $file)) {
            @unlink($temp);

            throw new PackageBuildFailed(lang('Devtools.packages.errors.write', [basename($file)]));
        }
    }
}
