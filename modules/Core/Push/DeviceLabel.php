<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use CodeIgniter\I18n\Time;

/**
 * A readable name for a subscribed device from its user agent ("Firefox · Windows"); the user can
 * rename it. Only the browser and system families are kept (no versions).
 */
final class DeviceLabel
{
    public const MAX_LENGTH = 100;

    public static function fromUserAgent(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/')                                     => 'Edge',
            str_contains($userAgent, 'OPR/')                                     => 'Opera',
            str_contains($userAgent, 'SamsungBrowser/')                          => 'Samsung Internet',
            str_contains($userAgent, 'Firefox/') || str_contains($userAgent, 'FxiOS/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/')                                  => 'Safari',
            default                                                              => '',
        };
        $system = match (true) {
            str_contains($userAgent, 'iPhone')                                   => 'iPhone',
            str_contains($userAgent, 'iPad')                                     => 'iPad',
            str_contains($userAgent, 'Android')                                  => 'Android',
            str_contains($userAgent, 'Windows')                                  => 'Windows',
            str_contains($userAgent, 'Mac OS X') || str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'CrOS')                                     => 'ChromeOS',
            str_contains($userAgent, 'Linux')                                    => 'Linux',
            default                                                              => '',
        };
        $label = implode(' · ', array_filter([$browser, $system], static fn (string $part): bool => $part !== ''));

        return $label !== '' ? $label : lang('Push.device.unknown');
    }

    /**
     * A stored date as plain text in the site's time zone and the current language ("20 de
     * nov. 2026, 19:05"), for the device list (scripts show it with textContent).
     */
    public static function date(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return (string) Time::parse($date)->setTimezone(site_timezone())->toLocalizedString('d MMM y, HH:mm');
    }

    /**
     * A label typed by the user: one line, trimmed, at most MAX_LENGTH characters.
     */
    public static function clean(string $label): string
    {
        $label = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $label));

        return mb_substr($label, 0, self::MAX_LENGTH);
    }
}
