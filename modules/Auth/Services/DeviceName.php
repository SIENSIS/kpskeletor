<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\HTTP\UserAgent;

/**
 * A readable device name ("Firefox 133 · Linux") from a stored user-agent string, parsed
 * locally (no lookup service).
 */
final class DeviceName
{
    public static function fromUserAgent(?string $agent): string
    {
        if ($agent === null || trim($agent) === '') {
            return lang('Auth.sessions.unknownDevice');
        }

        $parsed = new UserAgent();
        $parsed->parse($agent);

        $browser = $parsed->isBrowser() ? trim($parsed->getBrowser() . ' ' . strtok($parsed->getVersion(), '.')) : ($parsed->isRobot() ? $parsed->getRobot() : ($parsed->isMobile() ? $parsed->getMobile() : ''));
        $system  = $parsed->getPlatform();
        $parts   = array_filter([$browser, $system !== 'Unknown Platform' ? $system : ''], static fn (string $part): bool => $part !== '');

        return $parts === [] ? lang('Auth.sessions.unknownDevice') : implode(' · ', $parts);
    }
}
