<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\HTTP\Request;
use CodeIgniter\Router\RouteCollectionInterface;
use Config\Logger as LoggerConfig;
use Modules\Core\I18n\Language;
use Modules\Core\I18n\LocaleRouter;
use Modules\Core\I18n\LocaleUrls;
use Modules\Core\Log\SafeLogger;
use Modules\Core\Mail\GuardedEmail;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * The application (file) log with line breaks and control characters escaped in the values
     * interpolated into messages, so user input cannot forge log lines
     * (Modules\Core\Log\SafeLogger; docs/activity.md).
     */
    public static function logger(bool $getShared = true): SafeLogger
    {
        if ($getShared) {
            $logger = static::getSharedInstance('logger');

            if ($logger instanceof SafeLogger) {
                return $logger;
            }
        }

        return new SafeLogger(config(LoggerConfig::class));
    }

    /**
     * The mail service with the SMTP host checked against the outbound allow-list
     * (Modules\Core\Mail\GuardedEmail, decision D-00-29).
     *
     * @param array<string, mixed>|Email|null $config
     */
    public static function email($config = null, bool $getShared = true): GuardedEmail
    {
        if ($getShared) {
            $email = static::getSharedInstance('email', $config);

            if ($email instanceof GuardedEmail) {
                return $email;
            }
        }

        $config ??= config(Email::class);

        return new GuardedEmail($config);
    }

    /**
     * The language service with the application's fallback chain (requested → base locale →
     * `Config\App::$localeFallbacks` → `en`; Modules\Core\I18n\Language, docs/i18n.md).
     */
    public static function language(?string $locale = null, bool $getShared = true): Language
    {
        if ($getShared) {
            $language = static::getSharedInstance('language', $locale);

            if ($language instanceof Language) {
                return $language->setLocale($locale);
            }
        }

        $request = static::get('request');
        $current = $request instanceof \CodeIgniter\HTTP\IncomingRequest ? $request->getLocale() : \Locale::getDefault();
        $locale  = in_array($locale, [null, '', '0'], true) ? $current : $locale;

        return new Language($locale);
    }

    /**
     * The language of public URLs (Modules\Core\I18n\LocaleUrls, docs/i18n.md "Language in the URL").
     */
    public static function localeurls(bool $getShared = true): LocaleUrls
    {
        if ($getShared) {
            return static::getSharedInstance('localeurls');
        }

        return new LocaleUrls(config(\Config\LocaleUrls::class));
    }

    /**
     * The router that reads the language prefix of public URLs before routing the rest
     * (Modules\Core\I18n\LocaleRouter, D-37-02).
     */
    public static function router(?RouteCollectionInterface $routes = null, ?Request $request = null, bool $getShared = true): \CodeIgniter\Router\Router
    {
        if ($getShared) {
            return static::getSharedInstance('router', $routes, $request);
        }

        $routes ??= static::get('routes');
        $request ??= static::get('request');

        return new LocaleRouter($routes, $request);
    }

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
