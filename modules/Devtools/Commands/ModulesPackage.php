<?php

declare(strict_types=1);

namespace Modules\Devtools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Commands\ModulesSync;
use Modules\Core\Packages\PackageInfo;
use Modules\Devtools\Packages\PackageBuilder;
use Modules\Devtools\Packages\PackageBuildFailed;
use Modules\Devtools\Packages\PackageSigner;
use Modules\Devtools\Packages\PackageSource;
use Modules\Devtools\Packages\SigningFailed;

/**
 * php spark modules:package <Name> [--theme] [--out=<dir>] [--sign=<secret key file>]
 *
 * Builds `<Name>-<version>.zip` of a module (or a theme with `--theme`), validated exactly as
 * `modules:install` validates it, with `<zip>.sha256` and, with `--sign`, `<zip>.minisig`
 * (the key's password is asked without echo; D-43-01). Development only, like the rest of
 * Devtools (docs/devtools.md, "Building and signing packages").
 */
final class ModulesPackage extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:package';
    protected $description = 'Builds, validates and optionally signs the installable ZIP of a module or theme (development only).';
    protected $usage       = 'modules:package <Name> [--theme] [--out=<dir>] [--sign=<secret key file>]';

    /**
     * @var array<string, string>
     */
    protected $arguments = ['Name' => 'The module (modules/<Name>) or, with --theme, the theme slug (themes/<slug>).'];

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--theme' => 'Package a theme instead of a module.',
        '--out'   => 'Output folder (default: writable/devtools/packages).',
        '--sign'  => 'A minisign secret key file: sign the package (password asked without echo; KP_SIGN_PASSWORD for scripts).',
    ];

    /** Tests only: the password instead of the hidden prompt. */
    public static ?string $testPassword = null;

    /** Tests only: the environment (the suite runs in `testing`). */
    public static ?string $testEnvironment = null;

    /** Tests only: the folder that holds modules/ and themes/. */
    public static ?string $testRoot = null;

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        if ((self::$testEnvironment ?? ENVIRONMENT) !== 'development') {
            CLI::error(lang('Devtools.packages.cli.developmentOnly'));

            return EXIT_ERROR;
        }

        $name = (string) ($params[0] ?? '');

        if ($name === '') {
            CLI::error(lang('Devtools.packages.cli.noName'));

            return EXIT_USER_INPUT;
        }

        $type = self::option($params, 'theme') !== null ? PackageInfo::THEME : PackageInfo::MODULE;
        $out  = self::path(self::option($params, 'out'));
        $sign = self::path(self::option($params, 'sign'));

        try {
            $source = PackageSource::find($type, $name, self::$testRoot);
            CLI::write(lang('Devtools.packages.cli.building', [$source->fileName()]));
            $result = (new PackageBuilder())->build($source, $out !== null && $out !== '' ? $out : null);
        } catch (PackageBuildFailed $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        foreach ($result->warnings as $warning) {
            CLI::write(lang('Devtools.packages.cli.warning', [$warning]), 'yellow');
        }

        foreach ($result->skipped as $skipped) {
            CLI::write(lang('Devtools.packages.cli.skipped', [$skipped]), 'yellow');
        }

        CLI::write(lang('Devtools.packages.cli.valid', [$result->info->name, $result->info->version, count($result->info->files)]), 'green');
        CLI::write($result->zip);
        CLI::write($result->zip . '.sha256');
        CLI::write('SHA-256: ' . $result->sha256);

        activity_log('Devtools', 'packaged', ['resource' => 'devtools', 'summary' => $result->file()]);

        if ($sign === null) {
            CLI::write(lang('Devtools.packages.cli.signElsewhere'));
            CLI::write('  minisign -Sm ' . $result->zip . " -t '" . $source->trustedComment() . "'");

            return EXIT_SUCCESS;
        }

        if ($sign === '' || ! is_file($sign) || ! is_readable($sign) || filesize($sign) > 4096) {
            CLI::error(lang('Devtools.packages.cli.keyFile'));

            return EXIT_ERROR;
        }

        $key      = (string) file_get_contents($sign);
        $password = self::$testPassword ?? self::password($key);

        try {
            $signed = (new PackageSigner())->sign($result->zip, $source->trustedComment(), $key, $password);
        } catch (SigningFailed $e) {
            CLI::error($e->userMessage());

            return EXIT_ERROR;
        } finally {
            if ($key !== '') {
                sodium_memzero($key);
            }

            if ($password !== '') {
                sodium_memzero($password);
            }
        }

        activity_log('Devtools', 'signed', ['resource' => 'devtools', 'summary' => lang('Devtools.packages.sign.summary', [$signed->file, $signed->keyId])]);

        CLI::write(lang('Devtools.packages.cli.signed', [$signed->keyId]), 'green');
        CLI::write($result->zip . '.minisig');
        CLI::write(lang('Devtools.packages.cli.publicKey'));
        CLI::write('  ' . $signed->publicKey);

        return EXIT_SUCCESS;
    }

    /**
     * The key's password: none for an unencrypted key, else KP_SIGN_PASSWORD or a hidden prompt.
     */
    private static function password(#[\SensitiveParameter] string $key): string
    {
        $lines = preg_split('/\R/', trim($key)) ?: [];
        $raw   = base64_decode(trim((string) end($lines)), true);

        if (is_string($raw) && substr($raw, 2, 2) === "\0\0") {
            return '';
        }

        $env = getenv('KP_SIGN_PASSWORD');

        if (is_string($env) && $env !== '') {
            return $env;
        }

        CLI::print(lang('Devtools.packages.cli.password') . ' ');
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

    /**
     * A path given on the command line: relative to the shell's folder (spark runs from public/),
     * or to the project root when the shell's folder is unknown.
     */
    private static function path(?string $path): ?string
    {
        if ($path === null || $path === '' || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        $pwd = getenv('PWD');

        return rtrim(is_string($pwd) && $pwd !== '' && is_dir($pwd) ? $pwd : ROOTPATH, '/\\') . '/' . $path;
    }

    /**
     * @param array<int|string, string|null> $params
     */
    private static function option(array $params, string $name): ?string
    {
        // The Core's parser of "--name value" and "--name=value".
        $value = ModulesSync::option($params, $name);

        return $value === null ? null : ($value === true ? '' : $value);
    }
}
