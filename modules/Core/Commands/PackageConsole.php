<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\CLI;
use Modules\Core\Packages\PackageDownloader;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Packages\PackageRefused;

/**
 * Shared console steps of the package commands (`modules:install`, `modules:uninstall`,
 * `themes:install`, `themes:uninstall`): options, the package source, the backup password and
 * what a package contains.
 */
final class PackageConsole
{
    /**
     * Options shared by the commands that change code.
     *
     * @param array<int|string, string|null> $params
     *
     * @return array{backup: bool, password: string|null, yes: bool}
     */
    public static function safety(array $params): array
    {
        $password = null;

        if (ModulesSync::option($params, 'password') !== null) {
            $password = self::hiddenPrompt(lang('Core.packages.cli.password'));
        }

        return [
            'backup'   => ModulesSync::option($params, 'no-backup') === null,
            'password' => $password,
            'yes'      => ModulesSync::option($params, 'yes') !== null,
        ];
    }

    /**
     * A local file (with `<file>.minisig` or `--signature=<file>`) or an https URL (downloaded).
     *
     * @param array<int|string, string|null> $params
     *
     * @return array{file: string, signature: string|null, temporary: bool}
     *
     * @throws PackageRefused
     */
    public static function source(string $source, array $params): array
    {
        $signatureFile = ModulesSync::option($params, 'signature');

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $source) === 1) {
            $download = (new PackageDownloader())->download($source);

            return ['file' => $download['file'], 'signature' => is_string($signatureFile) ? self::read($signatureFile) : $download['signature'], 'temporary' => true];
        }

        if (! is_file($source)) {
            throw new PackageRefused(lang('Core.packages.cli.noFile', [$source]));
        }

        $signature = is_string($signatureFile) ? self::read($signatureFile) : (is_file($source . '.minisig') ? self::read($source . '.minisig') : null);

        return ['file' => $source, 'signature' => $signature, 'temporary' => false];
    }

    public static function describe(PackageInfo $info): void
    {
        CLI::write(lang('Core.packages.cli.package', [lang('Core.packages.types.' . $info->type), $info->name, $info->version]), 'yellow');

        $rows = [
            lang('Core.packages.author')      => $info->author !== '' ? $info->author : '—',
            lang('Core.packages.description') => $info->description !== '' ? lang($info->description) : '—',
            lang('Core.packages.signed')      => lang($info->signed ? 'Core.list.yes' : 'Core.list.no'),
            lang('Core.packages.files')       => count($info->files) . ' (' . number_format($info->totalSize()) . ' B)',
            'SHA-256'                         => $info->sha256,
        ];

        if ($info->isModule()) {
            $rows[lang('Core.packages.requires')]    = $info->requires === [] ? '—' : implode(', ', array_map(static fn (string $m, string $c): string => $m . ' ' . $c, array_keys($info->requires), $info->requires));
            $rows[lang('Core.packages.permissions')] = $info->permissions === [] ? '—' : implode(', ', $info->permissions);
            $rows[lang('Core.packages.menus')]       = $info->menus === [] ? '—' : implode(', ', array_map(static fn (string $label): string => lang($label), $info->menus));
            $rows[lang('Core.packages.widgets')]     = $info->widgets === [] ? '—' : implode(', ', $info->widgets);
            $rows[lang('Core.packages.tasks')]       = $info->tasks === [] ? '—' : implode(', ', $info->tasks);
        } else {
            $rows[lang('Core.packages.parent')] = $info->parent ?? '—';
        }

        foreach ($rows as $label => $value) {
            CLI::write('  ' . str_pad($label . ':', 16) . ' ' . $value);
        }
    }

    public static function say(): \Closure
    {
        return static function (string $line, string $color = ''): void {
            CLI::write($line, $color !== '' ? $color : null);
        };
    }

    private static function read(string $file): string
    {
        $content = @file_get_contents($file);

        if ($content === false) {
            throw new PackageRefused(lang('Core.packages.cli.noFile', [$file]));
        }

        return $content;
    }

    private static function hiddenPrompt(string $prompt): string
    {
        CLI::print($prompt . ' ');
        $tty = defined('STDIN') && stream_isatty(STDIN);

        if ($tty) {
            shell_exec('stty -echo 2>/dev/null');
        }

        $line = defined('STDIN') ? fgets(STDIN) : false;

        if ($tty) {
            shell_exec('stty echo 2>/dev/null');
        }

        CLI::newLine();

        return rtrim((string) $line, "\r\n");
    }
}
