<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

use CodeIgniter\HTTP\IncomingRequest;
use Throwable;

/**
 * Renders the 403/404/429/500 pages through the active theme (standard views `errors/<code>`), with a
 * plain fallback when the theme itself cannot render (a broken theme never hides the error).
 */
final class ErrorPage
{
    public const CODES = [403, 404, 429, 500];

    /**
     * @param string|null $message text safe to show (never an exception message in production)
     */
    public static function render(int $code, ?string $message = null): string
    {
        $code = in_array($code, self::CODES, true) ? $code : 500;

        try {
            $theme = service('theme');

            if (self::isBackendRequest()) {
                $theme->setArea('backend');
            }

            return $theme->render('errors/' . $code, [
                'message' => $message,
                'homeUrl' => base_url($theme->area() === 'backend' ? 'backend' : '/'),
                'title'   => lang('Core.errors.' . self::key($code) . '.title'),
            ], ['saveData' => false]);
        } catch (Throwable $e) {
            log_message('error', 'The error page {code} could not be rendered by the theme: {message}', [
                'code'    => $code,
                'message' => $e->getMessage(),
            ]);

            return self::plain($code, $message);
        }
    }

    public static function key(int $code): string
    {
        return match ($code) {
            403     => 'forbidden',
            404     => 'notFound',
            429     => 'tooManyRequests',
            default => 'serverError',
        };
    }

    /**
     * Minimal page without theme, styles or scripts.
     */
    public static function plain(int $code, ?string $message = null): string
    {
        $key   = self::key($code);
        $title = esc(lang('Core.errors.' . $key . '.title'));
        $text  = esc($message ?? lang('Core.errors.' . $key . '.message'));

        return '<!doctype html><html lang="' . esc(service('request')->getLocale()) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>' . $code . ' · ' . $title . '</title></head><body><main><h1>' . $code . ' · ' . $title . '</h1><p>'
            . $text . '</p></main></body></html>';
    }

    private static function isBackendRequest(): bool
    {
        $request = service('request');

        if (! $request instanceof IncomingRequest) {
            return false;
        }

        $path = trim($request->getPath(), '/');

        return $path === 'backend' || str_starts_with($path, 'backend/');
    }
}
