<?php

declare(strict_types=1);

namespace Modules\Events\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Kpapy as KpapyConfig;
use Kpapy\Http\ApiException;
use Kpapy\Http\Responder;
use Modules\Events\Config\Calendar;
use Modules\Events\Services\EventsApi;

/**
 * Public API resource `public/events` (`GET api/v1/public/events[/{id}|/occurrences]`, kpapy,
 * `kpapy:public`, D-26-01): the **public** events only, like the public calendar, without a token
 * (no permission resolver; only `list`, `show` and `occurrences`). Rate-limited per IP
 * (`Core.rateLimitApiIp`). 404 while `Config\Calendar::$publicCalendar` is off.
 */
final class PublicEvents extends Controller
{
    public const PATH = 'api/v1/public/events';

    public function index(): ResponseInterface
    {
        if (! (bool) config(Calendar::class)->publicCalendar) {
            return (new Responder(config(KpapyConfig::class), $this->response))->problem(ApiException::notFound());
        }

        return EventsApi::resource(null, true, self::PATH)->render();
    }
}
