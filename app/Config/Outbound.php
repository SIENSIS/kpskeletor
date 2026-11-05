<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Outbound requests (SSRF protection; docs/specification.md, "Security baseline › Outbound
 * requests", docs/outbound-requests.md). Every external HTTP call goes through
 * Modules\Core\Http\OutboundClient: HTTPS only, hosts in the allow-list, never private or
 * loopback addresses (checked after DNS and on every redirect, with the resolved address
 * pinned), timeouts and a maximum response size. SMTP hosts are checked against the same
 * allow-list by the mail service.
 *
 * The allow-list is this file + `outbound.hosts` in `.env` (comma separated) + the
 * `$outboundHosts` of enabled modules + the `Core.outboundHosts` setting (Settings › Security).
 * An entry is a host name (`api.github.com`) or `*.example.com` (its subdomains only).
 */
class Outbound extends BaseConfig
{
    /**
     * @var list<string>
     */
    public array $allowedHosts = [];

    /** Extra hosts from `.env`, comma separated (`outbound.hosts = smtp.example.com, api.example.org`). */
    public string $hosts = '';

    /**
     * Ports allowed in URLs.
     *
     * @var list<int>
     */
    public array $ports = [443];

    /** Seconds to connect / for the whole request. */
    public int $connectTimeout = 5;

    public int $timeout = 15;

    /** Maximum response body in bytes (default 10 MB). */
    public int $maxBytes = 10485760;

    public int $maxRedirects = 3;

    public string $userAgent = 'kpskeletor';

    /**
     * Proxy for outbound requests (`http://proxy:3128`), or '' for a direct connection (the
     * default; environment proxy variables are ignored so the pinned address is the one used).
     */
    public string $proxy = '';
}
