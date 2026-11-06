<?php

declare(strict_types=1);

/**
 * Small view components (badge, status, avatar, date, links, pagination info). Each one renders a
 * standard view `components/<name>`, so a theme can override its HTML.
 * Loaded by app/Config/Autoload.php ($helpers).
 */

use CodeIgniter\I18n\Time;

if (! function_exists('ui_badge')) {
    /**
     * @param string $variant Bootstrap colour: primary, secondary, success, danger, warning, info, light, dark
     */
    function ui_badge(string $text, string $variant = 'secondary', ?string $icon = null): string
    {
        return theme_component('badge', ['text' => $text, 'variant' => ui_variant($variant), 'icon' => $icon]);
    }
}

if (! function_exists('ui_status')) {
    /**
     * A record status as a coloured badge with a dot: well-known statuses get a colour
     * (published/active → success, draft → secondary, pending/scheduled → warning,
     * error/failed/blocked → danger, archived → dark), others are secondary.
     */
    function ui_status(string $status, ?string $label = null): string
    {
        $variant = match (strtolower($status)) {
            'published', 'active', 'enabled', 'done', 'ok', 'success', 'approved' => 'success',
            'pending', 'scheduled', 'waiting', 'warning', 'review' => 'warning',
            'error', 'failed', 'blocked', 'rejected', 'disabled', 'expired' => 'danger',
            'info', 'running', 'new' => 'info',
            'archived' => 'dark',
            default => 'secondary',
        };

        return theme_component('status', ['status' => $status, 'label' => $label ?? $status, 'variant' => $variant]);
    }
}

if (! function_exists('ui_avatar')) {
    /**
     * A user's picture, or their initials on a colour derived from the name.
     *
     * @param string $size sm, md or lg (anything else: md)
     */
    function ui_avatar(string $name, ?string $imageUrl = null, string $size = 'md'): string
    {
        $words    = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return theme_component('avatar', [
            'name'     => $name,
            'imageUrl' => $imageUrl,
            'initials' => $initials === '' ? '?' : $initials,
            'size'     => in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md',
            'colour'   => (abs(crc32($name)) % 8) + 1,
        ]);
    }
}

if (! function_exists('ui_date')) {
    /**
     * A `<time>` element with the date formatted for the current locale.
     *
     * @param 'date'|'datetime'|'long'|'relative' $format
     */
    function ui_date(DateTimeInterface|string|null $date, string $format = 'date'): string
    {
        if ($date === null || $date === '') {
            return theme_component('date', ['iso' => null, 'text' => '—', 'title' => null]);
        }

        $time   = ($date instanceof DateTimeInterface ? Time::createFromInstance($date) : Time::parse($date))->setTimezone(site_timezone());
        $locale = service('request')->getLocale();

        $text = match ($format) {
            'relative' => $time->humanize(),
            'datetime' => (string) $time->toLocalizedString('d MMM y, HH:mm'),
            'long'     => (string) $time->toLocalizedString('EEEE, d MMMM y'),
            default    => (string) $time->toLocalizedString('d MMM y'),
        };

        return theme_component('date', [
            'iso'    => $time->format(DATE_ATOM),
            'text'   => $text,
            'title'  => $format === 'relative' ? (string) $time->toLocalizedString('d MMM y, HH:mm') : null,
            'locale' => $locale,
        ]);
    }
}

if (! function_exists('ui_link')) {
    /**
     * A link to an internal path or URL (external links get rel="noopener noreferrer").
     */
    function ui_link(string $label, string $url, ?string $icon = null): string
    {
        return theme_component('link', [
            'label'    => $label,
            'url'      => $url,
            'icon'     => $icon,
            'external' => ui_is_external($url),
        ]);
    }
}

if (! function_exists('ui_file_link')) {
    /**
     * A link to a file with an icon for its type and its size.
     */
    function ui_file_link(string $name, string $url, ?int $bytes = null, ?string $mime = null): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $icon      = match (true) {
            str_starts_with((string) $mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'bi-file-earmark-image',
            $extension === 'pdf'                                                                                              => 'bi-file-earmark-pdf',
            in_array($extension, ['doc', 'docx', 'odt', 'rtf'], true)                                                         => 'bi-file-earmark-word',
            in_array($extension, ['xls', 'xlsx', 'ods', 'csv'], true)                                                         => 'bi-file-earmark-spreadsheet',
            in_array($extension, ['zip', 'gz', 'tar', '7z'], true)                                                            => 'bi-file-earmark-zip',
            default                                                                                                           => 'bi-file-earmark',
        };

        return theme_component('file_link', [
            'name' => $name,
            'url'  => $url,
            'icon' => $icon,
            'size' => $bytes === null ? null : ui_bytes($bytes),
        ]);
    }
}

if (! function_exists('ui_user_link')) {
    /**
     * A user's avatar and name, linked to their profile when a URL is given.
     */
    function ui_user_link(string $name, ?string $url = null, ?string $avatarUrl = null): string
    {
        return theme_component('user_link', [
            'name'   => $name,
            'url'    => $url,
            'avatar' => ui_avatar($name, $avatarUrl, 'sm'),
        ]);
    }
}

if (! function_exists('ui_pagination_info')) {
    /**
     * "Showing 11–20 of 45" (or the empty text when there are no records).
     */
    function ui_pagination_info(int $page, int $perPage, int $total): string
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $from    = $total === 0 ? 0 : min($total, ($page - 1) * $perPage + 1);
        $to      = min($total, $page * $perPage);

        return theme_component('pagination_info', ['from' => $from, 'to' => $to, 'total' => $total]);
    }
}

if (! function_exists('ui_bytes')) {
    function ui_bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size  = (float) max(0, $bytes);
        $unit  = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$unit];
    }
}

if (! function_exists('ui_variant')) {
    /**
     * A Bootstrap colour name, or "secondary" for anything else (never printed unchecked in a class).
     */
    function ui_variant(string $variant): string
    {
        return in_array($variant, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'], true) ? $variant : 'secondary';
    }
}

if (! function_exists('ui_icon')) {
    /**
     * A Bootstrap Icons class ("bi-star" or "bi bi-star"), or '' when it is not a valid icon name.
     */
    function ui_icon(?string $icon): string
    {
        if ($icon === null || preg_match('/^(?:bi )?(bi-[a-z0-9-]+)$/', $icon, $match) !== 1) {
            return '';
        }

        return 'bi ' . $match[1];
    }
}

if (! function_exists('ui_is_external')) {
    function ui_is_external(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        return strcasecmp($host, (string) parse_url(base_url(), PHP_URL_HOST)) !== 0;
    }
}

if (! function_exists('ui_safe_url')) {
    /**
     * The URL when it is http(s), relative or a fragment; '#' for anything else (javascript:, data:…).
     */
    function ui_safe_url(string $url): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('#^(?:https?://|/(?!/)|\#|\?)#i', $url) === 1 || preg_match('#^[a-z0-9._~-][^:]*$#i', $url) === 1) {
            return $url === '' ? '#' : $url;
        }

        return '#';
    }
}

if (! function_exists('site_name')) {
    /**
     * The site name: the `Core.siteName` setting, or the language line `Core.app.name` when it
     * is empty (or before installation).
     */
    function site_name(): string
    {
        try {
            $name = setting('Core.siteName');
        } catch (Throwable) {
            $name = null;
        }

        return is_string($name) && trim($name) !== '' ? trim($name) : lang('Core.app.name');
    }
}

if (! function_exists('site_timezone')) {
    /**
     * The time zone dates are shown in (`Core.timezone`; dates are stored in the application's
     * time zone, UTC). An unknown value falls back to UTC.
     */
    function site_timezone(): string
    {
        try {
            $zone = setting('Core.timezone');
        } catch (Throwable) {
            $zone = null;
        }

        return is_string($zone) && in_array($zone, timezone_identifiers_list(), true) ? $zone : 'UTC';
    }
}
