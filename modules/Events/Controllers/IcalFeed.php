<?php

declare(strict_types=1);

namespace Modules\Events\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Filters\RateLimit;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Security\RateLimiter;
use Modules\Events\Config\Calendar;
use Modules\Events\Services\EventRepository;
use Modules\Events\Services\IcalFeeds;

/**
 * The subscribable iCal feeds (docs/events.md, "iCal"). Calendar clients cannot sign in, so:
 *
 * - `GET ical/user/<secret>.ics`: the personal feed. The secret is looked up by its hash; a
 *   wrong, revoked or regenerated secret, a banned or inactive user, or a user without
 *   `events-calendar.view` gets a 404. The feed is built with the user's **current** permissions
 *   and audiences. Rate limit `feeds` per secret (and the route's per IP);
 * - `GET ical/public/all.ics`, `ical/public/<category slug>.ics`: public events only, while the
 *   public calendar and `Config\Calendar::$publicFeeds` are on.
 *
 * Responses carry `ETag` and `Last-Modified` and answer `304 Not Modified` to a matching
 * `If-None-Match` / `If-Modified-Since`.
 */
final class IcalFeed extends Controller
{
    public function personal(string $file): ResponseInterface
    {
        $secret = self::name($file);
        $feeds  = new IcalFeeds();
        $limit  = (new RateLimiter())->attempt('feeds', IcalFeeds::hash($secret));

        if ($limit > 0) {
            return RateLimit::tooManyRequests($this->request, $limit);
        }

        $row  = $feeds->findBySecret($secret);
        $user = $row === null ? null : auth()->getProvider()->findById((int) $row['user_id']);

        if ($row === null || ! $user instanceof User || ! $user->isActivated() || $user->isBanned()
            || ! Authorization::for($user)->can('events-calendar.view')) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($feeds->touch($row, (string) $this->request->getIPAddress())) {
            activity_log('Events', 'ical_accessed', [
                'user_id'   => (int) $user->id,
                'resource'  => 'events-calendar',
                'record_id' => (int) $row['id'],
                'summary'   => mb_substr((string) $this->request->getUserAgent(), 0, 190),
            ]);
        }

        return $this->respond($feeds->personal($user, $row), 'private');
    }

    public function public(string $file): ResponseInterface
    {
        $config = config(Calendar::class);

        if (! $config->publicCalendar || ! $config->publicFeeds) {
            throw PageNotFoundException::forPageNotFound();
        }

        $slug     = self::name($file);
        $category = $slug === 'all' ? null : (new EventRepository())->categoryBySlug($slug);

        if ($slug !== 'all' && $category === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->respond((new IcalFeeds())->public($category), 'public');
    }

    /**
     * Sends a document as `text/calendar` with its validators, or a 304.
     *
     * @param array{body: string, modified: int} $feed
     */
    private function respond(array $feed, string $cache): ResponseInterface
    {
        $etag     = '"' . sha1($feed['body']) . '"';
        $modified = gmdate('D, d M Y H:i:s', $feed['modified']) . ' GMT';
        $response = $this->response
            ->setHeader('ETag', $etag)
            ->setHeader('Last-Modified', $modified)
            ->setHeader('Cache-Control', $cache . ', max-age=0, must-revalidate')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');

        $match = $this->request->getHeaderLine('If-None-Match');
        $since = $this->request->getHeaderLine('If-Modified-Since');

        if (($match !== '' && in_array($etag, array_map('trim', explode(',', $match)), true))
            || ($match === '' && $since !== '' && ($time = strtotime($since)) !== false && $time >= $feed['modified'])) {
            return $response->setStatusCode(304)->setBody('');
        }

        return $response
            ->setContentType('text/calendar', 'utf-8')
            ->setHeader('Content-Disposition', 'inline; filename="calendar.ics"')
            ->setBody($feed['body']);
    }

    /**
     * The name of `<name>.ics` (anything else is a 404).
     */
    private static function name(string $file): string
    {
        if (preg_match('/^([A-Za-z0-9_-]{1,100})\.ics$/', $file, $m) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $m[1];
    }
}
