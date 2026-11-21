<?php

declare(strict_types=1);

namespace Modules\Pages\Embeds;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Settings\SecurityRules;
use Throwable;

/**
 * The hosts whose pages may be embedded in a page (D-46-01): the setting `Pages.embedHosts` of
 * Settings › Security (empty by default: nothing can be embedded), one host per line,
 * `www.youtube-nocookie.com` or `*.example.com` for its subdomains (rule `outbound_hosts`).
 *
 * An address is accepted only when it is `https://`, has no user name or password and its host
 * is on the list. Its origin (`https://host[:port]`) is the `frame-src` source the page needs.
 */
final class EmbedHosts
{
    /** The setting and the anchor of its field in Settings › Security. */
    public const SETTING = 'Pages.embedHosts';

    public const MAX_URL = 1000;

    /** @var list<string>|null */
    private ?array $hosts;

    /**
     * @param list<string>|null $hosts the allowed hosts (tests); default: the setting
     */
    public function __construct(?array $hosts = null)
    {
        $this->hosts = $hosts === null ? null : array_map('strtolower', $hosts);
    }

    /**
     * @return list<string>
     */
    public function hosts(): array
    {
        if ($this->hosts === null) {
            try {
                $value = setting(self::SETTING);
            } catch (Throwable) {
                $value = [];
            }

            $this->hosts = array_values(array_filter(array_map(static fn (string $host): string => strtolower(trim($host)), SecurityRules::lines($value)), static fn (string $host): bool => preg_match('/^(\*\.)?[a-z0-9.-]+$/', $host) === 1));
        }

        return $this->hosts;
    }

    /**
     * The host of an address when it is a well-formed `https://` URL (lower case), else null.
     */
    public static function host(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > self::MAX_URL || preg_match('/[\s\x00-\x1F\x7F\\\\]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || ! isset($parts['host']) || preg_match('/^[a-z0-9.-]+$/i', $parts['host']) !== 1) {
            return null;
        }

        return strtolower($parts['host']);
    }

    /** Whether a host is allowed (exactly, or as a subdomain of a `*.` entry). */
    public function allows(string $host): bool
    {
        $host = strtolower($host);

        foreach ($this->hosts() as $allowed) {
            if ($allowed === $host || (str_starts_with($allowed, '*.') && str_ends_with($host, substr($allowed, 1)))) {
                return true;
            }
        }

        return false;
    }

    /** The `frame-src` source of an accepted address (`https://host[:port]`), or null. */
    public function origin(string $url): ?string
    {
        $host = self::host($url);

        if ($host === null || ! $this->allows($host)) {
            return null;
        }

        $port = parse_url(trim($url), PHP_URL_PORT);

        return 'https://' . $host . (is_int($port) && $port !== 443 ? ':' . $port : '');
    }

    /**
     * The link to the setting for people who may change it (`settings.security`), or null.
     *
     * @return array{url: string, label: string}|null
     */
    public static function settingLink(?User $user): ?array
    {
        if (! $user instanceof User || ! Authorization::for($user)->can('settings.security')) {
            return null;
        }

        return ['url' => backend_url('settings/security') . '#setting-Pages-embedHosts', 'label' => lang('Pages.embed.openSetting')];
    }
}
