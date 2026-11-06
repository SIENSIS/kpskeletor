<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

use Config\Storage;
use Modules\Core\Modules\ManifestValidator;

/**
 * Validation rules that refuse setting values which would weaken the security baseline
 * (Settings › Security, docs/settings.md). Registered in `Config\Validation::$ruleSets` by the
 * Core's registrar, so manifests use them in a setting's `rules`:
 *
 * - `csp_sources`: extra CSP origins, one per line: `https://host[:port]` or
 *   `https://*.host`, `wss://` too; never a keyword (`'unsafe-inline'`, `'self'`…), `*`,
 *   a scheme alone (`https:`, `data:`) or a path.
 * - `csp_script_sources`: the same for `script-src`/`style-src`, without wildcards.
 * - `outbound_hosts`: host names or `*.example.com`; no IP addresses, `localhost`, scheme or port.
 * - `cors_origins`: full origins `https://host[:port]` (`http://` only for localhost).
 * - `upload_types`: comma-separated extensions; never a forbidden one (`Config\Storage`);
 *   `svg` may be listed but is only accepted with `Core.uploadAllowSvg` (UploadValidator).
 * - `min_config[<Class>.<property>]`: a number not below the file configuration's value
 *   (`Config\<Class>` read fresh, `.env` included), e.g. Shield's minimum password length.
 *
 * Lists are given as text (one entry per line or separated by commas), as the form posts them.
 */
final class SecurityRules
{
    private const HOST = '(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,62}';

    private const PORT = '(?::[0-9]{1,5})?';

    public function csp_sources(mixed $str = null, ?string &$error = null): bool
    {
        return $this->eachLine($str, '#^(?:https|wss)://(?:\*\.)?' . self::HOST . self::PORT . '/?$#i', 'Core.validation.cspSources', $error);
    }

    public function csp_script_sources(mixed $str = null, ?string &$error = null): bool
    {
        return $this->eachLine($str, '#^https://' . self::HOST . self::PORT . '/?$#i', 'Core.validation.cspScriptSources', $error);
    }

    public function outbound_hosts(mixed $str = null, ?string &$error = null): bool
    {
        if (! $this->eachLine($str, '#^(?:\*\.)?' . self::HOST . '$#i', 'Core.validation.outboundHosts', $error)) {
            return false;
        }

        foreach (self::lines($str) as $host) {
            if (in_array(strtolower(ltrim($host, '*.')), ['localhost', 'localdomain', 'local', 'internal'], true)) {
                $error = self::message('Core.validation.outboundHosts', [$host]);

                return false;
            }
        }

        return true;
    }

    public function cors_origins(mixed $str = null, ?string &$error = null): bool
    {
        return $this->eachLine($str, '#^(?:https://' . self::HOST . self::PORT . '|http://(?:localhost|127\.0\.0\.1)' . self::PORT . ')$#i', 'Core.validation.corsOrigins', $error);
    }

    public function upload_types(mixed $str = null, ?string &$error = null): bool
    {
        $forbidden = [...config(Storage::class)->forbiddenExtensions, 'svgz'];

        foreach (self::lines($str) as $extension) {
            $extension = strtolower(ltrim($extension, '.'));

            if (preg_match('/^[a-z0-9]{1,10}$/', $extension) !== 1 || in_array($extension, $forbidden, true)) {
                $error = self::message('Core.validation.uploadTypes', [$extension]);

                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function min_config(mixed $str, string $reference, array $data = [], ?string &$error = null): bool
    {
        $floor = self::configValue($reference);

        if ($floor === null || ! is_numeric($str)) {
            return $floor === null; // unknown reference: nothing to compare (a typo is caught by the tests)
        }

        if ((float) $str < $floor) {
            $error = self::message('Core.validation.minConfig', [self::number($floor)]);

            return false;
        }

        return true;
    }

    /**
     * The numeric value of a file configuration property (`Auth.minimumPasswordLength` reads a
     * new `Config\Auth`, so values copied into the shared instance at run time do not count).
     */
    public static function configValue(string $reference): ?float
    {
        [$class, $property] = array_pad(explode('.', $reference, 2), 2, '');
        $class              = str_contains($class, '\\') ? $class : 'Config\\' . $class;

        if ($property === '' || ! class_exists($class) || ! property_exists($class, $property)) {
            return null;
        }

        $value = (new $class())->{$property};

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Whether a source would weaken the policy whatever the directive (also used by the filter
     * that adds the stored origins, in case a value reached the database another way).
     */
    public static function isAllowedCspSource(string $source, bool $strict = false): bool
    {
        $source = trim($source);

        if ($source === '' || in_array(strtolower($source), [...ManifestValidator::FORBIDDEN_CSP_SOURCES, "'none'"], true)) {
            return false;
        }

        $pattern = $strict
            ? '#^https://' . self::HOST . self::PORT . '/?$#i'
            : '#^(?:https|wss)://(?:\*\.)?' . self::HOST . self::PORT . '/?$#i';

        return preg_match($pattern, $source) === 1;
    }

    /**
     * @return list<string>
     */
    public static function lines(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn ($v): string => is_scalar($v) ? trim((string) $v) : '', $value), static fn (string $v): bool => $v !== ''));
        }

        if (! is_scalar($value)) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', (string) $value) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $v): bool => $v !== ''));
    }

    private function eachLine(mixed $str, string $pattern, string $message, ?string &$error): bool
    {
        foreach (self::lines($str) as $line) {
            if (preg_match($pattern, $line) !== 1 || preg_match('/[\s\'";]/', $line) === 1) {
                $error = self::message($message, [$line]);

                return false;
            }
        }

        return true;
    }

    private static function number(float $value): string
    {
        return floor($value) === $value ? (string) (int) $value : (string) $value;
    }

    /**
     * @param list<mixed> $args
     */
    private static function message(string $key, array $args = []): string
    {
        $message = lang($key, $args);

        return is_string($message) ? $message : $key;
    }
}
