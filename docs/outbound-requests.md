# Outbound requests

Regular page requests never call external services. Scheduled tasks, console commands and
explicit admin actions that must call one go through **`Modules\Core\Http\OutboundClient`**
(SSRF protection; docs/specification.md, "Security baseline › Outbound requests"; the security
baseline is in [`security.md`](security.md)).

## Why outbound calls are restricted

A server that fetches URLs is a door into the network it sits in (**server-side request forgery,
SSRF**): a URL chosen or redirected by someone else can reach the cloud metadata service
(`169.254.169.254`, with the server's credentials), the database, an admin panel on
`localhost` or another host of the internal network, none of which is reachable from the
Internet. Calls to the outside also leak data, hang requests when the remote host is slow and
make the site depend on third parties. So:

- nothing calls out during a regular page request (a slow or hostile host cannot block a page);
- the code that must call out names its hosts in advance (the allow-list) and every request is
  checked again after DNS and on every redirect;
- every request and every refusal is logged.

## Rules

- **HTTPS only**, ports of `Config\Outbound::$ports` (443), no `user:pass@` in the URL.
- The host must be in the **allow-list** (below).
- The host is resolved and **every** address must be public: private (10/8, 172.16/12,
  192.168/16, fc00::/7), loopback, link-local (169.254/16 — cloud metadata), CGNAT, documentation,
  multicast and reserved ranges are refused, also in IPv4-mapped/NAT64 form. The connection is
  **pinned** to the checked address (`CURLOPT_RESOLVE`), so DNS cannot change between the check
  and the connection (DNS rebinding).
- Redirects are followed by hand (at most `maxRedirects`, 3) and **each hop is checked again**
  (scheme, host, addresses). 303 (and 301/302 after a POST) continue with GET.
- Timeouts (`connectTimeout` 5 s, `timeout` 15 s) and a maximum response size (`maxBytes`,
  10 MB; the transfer is cut beyond it).
- Direct connections: environment proxy variables are ignored unless `outbound.proxy` is set.
- Every request is written to the activity log (method, host, path — never the query string — and
  the status): `Core`/`outbound` entries, and `Core`/`outbound_refused` **security** entries with
  the refusal reason ([`activity.md`](activity.md)).
- Refusals and failures throw `OutboundException` with a `reason`: `url`, `scheme`, `port`,
  `host`, `dns`, `private`, `redirects`, `tooLarge`, `transport`.

## The allow-list

The union of:

1. `Config\Outbound::$allowedHosts` (`app/Config/Outbound.php`) and `outbound.hosts` in `.env`
   (comma separated);
2. the `$outboundHosts` of the **enabled** modules (plain host names, validated by
   `modules:sync`; a disabled module's hosts are not allowed);
3. the `Core.outboundHosts` setting (a `list`, one host per line), edited in **Settings › Security**
   (`backend/settings/security`, `settings.security` + re-authentication; IP addresses, local
   names such as `localhost`, schemes and ports are refused by the `outbound_hosts` rule,
   [`settings.md`](settings.md)).

An entry is a host name (`api.github.com`) or, in the configuration and the setting,
`*.example.com` (its subdomains, not `example.com` itself). IP literals are never needed; when
listed, they must still be public. `Modules\Core\Http\OutboundAllowList` builds the union; the
Security status page and `php spark security:check --only=outbound` list it (`outbound.allowList`)
and the hosts each module adds (`outbound.modules`).

```php
// app/Config/Outbound.php
public array $allowedHosts = ['api.example.org', '*.s3.eu-west-1.amazonaws.com'];
```

```ini
# .env
outbound.hosts = smtp.example.com, api.example.org
```

```php
// modules/<Name>/Config/Module.php
public array $outboundHosts = ['api.open-meteo.com'];
```

The Updates module declares `api.github.com`, `github.com`, `objects.githubusercontent.com`,
`release-assets.githubusercontent.com` and `gitlab.com`; the other modules of the skeleton call
nothing. Other options of `Config\Outbound` (the scalars also from `.env`, `outbound.<name>`): `ports`,
`connectTimeout`, `timeout`, `maxBytes`, `maxRedirects`, `userAgent`, `proxy`.

## SMTP

SMTP is not HTTP (decision D-00-29). The `email` service is `Modules\Core\Mail\GuardedEmail`
(`app/Config/Services.php`): with `protocol = smtp` the `SMTPHost` must be in the same allow-list,
otherwise `send()` returns false and the refusal is an `outbound_refused` security entry (and a
line in the application log). `mail`/`sendmail` (the default `protocol` is `mail`) deliver
locally and are not checked. Settings › Actions › "Send a test email" shows whether delivery works.

## Exceptions (console only)

These run only from the console (or the scheduler, which is the console) and never from a web
request:

| Client | Used by | Why it is not the OutboundClient |
|---|---|---|
| `git` (`git fetch --tags`, `git verify-tag`) | `php spark app:update` on a git checkout (`updates.method`, [`updates.md`](updates.md)) | git speaks its own protocols; the tag is verified before anything changes |
| `composer install --no-dev` | `php spark app:update` | Composer downloads from Packagist and its mirrors |
| `composer audit` | `php spark security:audit` and the daily task "Security checks and composer audit" (`productioncheck.composer`) | Composer has no offline mode; in CI run it there and store the result with `php spark security:audit --from=<file>` |
| the probe client (`Modules\Core\Security\Check\Probe\ProbeClient`) | `php spark security:check --probe=<url>` and "Run external probe" on the Security status page (re-authentication) | it must reach the site itself, which may resolve to a private or loopback address the OutboundClient refuses on purpose |

The probe client sends requests **only** to the host of the probed URL, which must be the
`app.baseURL` host or one of `app.allowedHostnames`; HTTPS only, except one plain-HTTP request
for the redirect check; redirects are never followed; `productioncheck.probeTimeout` seconds per
request and a 1 MB response limit. It never calls a third party.

Everything else goes through `OutboundClient`: the release checks and the archive download of the
Updates module (the download with a copy of `Config\Outbound` whose `maxBytes` and `timeout` are
`updates.maxArchiveBytes` and `updates.downloadTimeout`), and the package download of
`php spark modules:install <https URL>` / `themes:install` (`Modules\Core\Packages\PackageDownloader`,
with `<url>.minisig`).

## Credentials and redirects

Headers that carry credentials (`Authorization`, `Proxy-Authorization`, `Cookie`,
`PRIVATE-TOKEN`, `JOB-TOKEN`) are dropped when a redirect leads to another origin (scheme, host
or port): a token sent to an API is never forwarded to the storage host the API redirects a
download to.

## Examples

**GitHub releases (Updates module):**

```php
// Module.php
public array $outboundHosts = ['api.github.com'];

// In a scheduled task
$response = (new OutboundClient())->get('https://api.github.com/repos/acme/news-module/releases/latest', [
    'Accept' => 'application/vnd.github+json',
]);
$latest = $response->ok() ? $response->json()['tag_name'] ?? null : null;
```

**An SMTP relay:** `.env`

```ini
email.protocol = smtp
email.SMTPHost = smtp.example.com
outbound.hosts = smtp.example.com
```

**A module calling a weather API** from a scheduled task (never while rendering a page; the page
shows what the task stored):

```php
// Module.php
public array $outboundHosts = ['api.open-meteo.com'];

// Tasks/RefreshForecast.php
use Modules\Core\Http\OutboundClient;
use Modules\Core\Http\OutboundException;

try {
    $response = (new OutboundClient())->get('https://api.open-meteo.com/v1/forecast?latitude=41.4&longitude=2.2&daily=temperature_2m_max');
    $forecast = $response->ok() ? $response->json() : null;
} catch (OutboundException $e) {
    // Already logged by the client ($e->reason: 'host', 'private', 'dns', 'tooLarge'…)
    $forecast = null;
}
```

**A larger download** (a copy of the configuration, as the Updates module does):

```php
$config           = clone config(\Config\Outbound::class);
$config->maxBytes = 200 * 1024 * 1024;
$config->timeout  = 120;

$archive = (new OutboundClient($config))->get('https://github.com/acme/app/releases/download/v1.2.0/app.zip')->body;
```

**What a refusal looks like:** `https://169.254.169.254/latest/meta-data/` throws
`OutboundException` with reason `host` (not in the allow-list) and, if someone listed a host that
resolves there, `private`; both are `outbound_refused` security entries.

## Testing

Pass a fake `OutboundTransport` and resolver to the constructor
(`modules/Core/Tests/OutboundClientTest.php`): no test needs the network.
