<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * The response of a one-off `.ics` download.
 */
final class IcalExport
{
    public static function download(string $body, string $title, ResponseInterface $response): ResponseInterface
    {
        $name = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: ''), '-');
        $name = strtolower($name === '' ? 'event' : mb_substr($name, 0, 60)) . '.ics';

        return $response
            ->setContentType('text/calendar', 'utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow')
            ->setBody($body);
    }
}
