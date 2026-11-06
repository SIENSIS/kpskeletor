<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use Config\Logger as LoggerConfig;
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
