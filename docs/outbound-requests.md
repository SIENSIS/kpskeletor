# Outbound requests

Regular page requests never call external services. Scheduled tasks, console commands and
explicit admin actions that must call one go through **`Modules\Core\Http\OutboundClient`**
(SSRF protection; docs/specification.md, "Security baseline › Outbound requests").

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
- Every request is logged (method, host, path — never the query string — and the status or the
  refusal reason). Until the activity log (session 12) this is the application log.
- Refusals and failures throw `OutboundException` with a `reason`: `url`, `scheme`, `port`,
  `host`, `dns`, `private`, `redirects`, `tooLarge`, `transport`.

## The allow-list

The union of:

1. `Config\Outbound::$allowedHosts` (`app/Config/Outbound.php`) and `outbound.hosts` in `.env`
   (comma separated);
2. the `$outboundHosts` of the **enabled** modules (shown before enabling a module, session 29);
3. the `Core.outboundHosts` setting (a `list`, one host per line), edited in **Settings › Security**
   (`settings.security` + re-authentication; IP addresses, local names, schemes and ports are
   refused, docs/settings.md).

An entry is a host name (`api.github.com`) or `*.example.com` (its subdomains, not
`example.com` itself). IP literals are never needed; when listed, they must still be public.

## SMTP

SMTP is not HTTP (decision D-00-29). The `email` service is `Modules\Core\Mail\GuardedEmail`
(`app/Config/Services.php`): with `protocol = smtp` the `SMTPHost` must be in the same allow-list,
otherwise `send()` returns false and logs the refusal. `mail`/`sendmail` deliver locally and are
not checked.

## Exceptions

`git` and `composer` (console-only `app:update`, session 28) use their own clients; they are
documented exceptions and never run from a web request.

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

**A module calling a weather API:**

```php
public array $outboundHosts = ['api.open-meteo.com'];

try {
    $forecast = (new OutboundClient())->get('https://api.open-meteo.com/v1/forecast?latitude=41.4&longitude=2.2&daily=temperature_2m_max')->json();
} catch (OutboundException $e) {
    log_message('warning', 'Weather unavailable: {reason}', ['reason' => $e->reason]);
}
```

## Testing

Pass a fake `OutboundTransport` and resolver to the constructor
(`modules/Core/Tests/OutboundClientTest.php`): no test needs the network.
