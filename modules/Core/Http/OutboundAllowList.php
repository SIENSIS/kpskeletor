<?php

declare(strict_types=1);

namespace Modules\Core\Http;

use Closure;
use Config\Outbound;
use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * The outbound allow-list: `Config\Outbound` (`allowedHosts` and `.env` `outbound.hosts`), the
 * `$outboundHosts` of enabled modules and the `Core.outboundHosts` setting. Entries are host
 * names (exact match) or `*.example.com` (subdomains of example.com, not example.com itself).
 * Used by OutboundClient for HTTPS and by the mail service for SMTP hosts.
 */
final class OutboundAllowList
{
    /** @var list<string>|null */
    private ?array $hosts = null;

    /**
     * @param (Closure(): list<string>)|null $moduleHosts
     * @param (Closure(): mixed)|null        $settingHosts
     */
    public function __construct(
        private readonly ?Outbound $config = null,
        private readonly ?Closure $moduleHosts = null,
        private readonly ?Closure $settingHosts = null,
    ) {
    }

    public function allows(string $host): bool
    {
        $host = rtrim(strtolower(trim($host)), '.');

        if ($host === '') {
            return false;
        }

        foreach ($this->hosts() as $entry) {
            if ($entry === $host) {
                return true;
            }

            if (str_starts_with($entry, '*.') && str_ends_with($host, substr($entry, 1)) && strlen($host) > strlen($entry) - 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function hosts(): array
    {
        if ($this->hosts !== null) {
            return $this->hosts;
        }

        $config = $this->config ?? config(Outbound::class);
        $hosts  = [...$config->allowedHosts, ...explode(',', $config->hosts)];

        try {
            $hosts = [...$hosts, ...($this->moduleHosts !== null ? ($this->moduleHosts)() : (new DeclarationRegistry())->outboundHosts())];
        } catch (Throwable) {
            // not installed yet
        }

        try {
            $setting = $this->settingHosts !== null ? ($this->settingHosts)() : setting('Core.outboundHosts');
        } catch (Throwable) {
            $setting = null;
        }

        if (is_string($setting)) {
            $setting = json_decode($setting, true) ?? explode(',', $setting);
        }

        foreach (is_array($setting) ? $setting : [] as $host) {
            if (is_string($host)) {
                $hosts[] = $host;
            }
        }

        $hosts = array_map(static fn (string $h): string => rtrim(strtolower(trim($h)), '.'), $hosts);

        return $this->hosts = array_values(array_unique(array_filter($hosts, static fn (string $h): bool => $h !== '' && $h !== '*' && $h !== '*.')));
    }
}
