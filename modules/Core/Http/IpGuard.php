<?php

declare(strict_types=1);

namespace Modules\Core\Http;

/**
 * Whether an IP address may be the target of an outbound request: only public unicast
 * addresses (no private, loopback, link-local, CGNAT, documentation, multicast, reserved or
 * IPv4-mapped/NAT64 forms of them).
 */
final class IpGuard
{
    /** @var list<string> */
    private const BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/23', '2001:db8::/32',
        '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    public static function isPublic(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        // IPv4-mapped (::ffff:a.b.c.d): the IPv4 decides. IPv4-compatible (::a.b.c.d), "::" and
        // "::1": refused.
        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            $v4 = inet_ntop(substr($packed, 12));

            return is_string($v4) && self::isPublic($v4);
        }

        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 12))) {
            return false;
        }

        foreach (self::BLOCKED as $cidr) {
            if (self::inRange($packed, $cidr)) {
                return false;
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function inRange(string $packed, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $net              = inet_pton($network);

        if ($net === false || strlen($net) !== strlen($packed)) {
            return false;
        }

        $bits  = (int) $bits;
        $bytes = intdiv($bits, 8);

        if (substr($packed, 0, $bytes) !== substr($net, 0, $bytes)) {
            return false;
        }

        $rest = $bits % 8;

        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($net[$bytes]) & $mask);
    }
}
