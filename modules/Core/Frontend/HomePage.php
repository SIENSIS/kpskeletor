<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * The public home page (`GET /`) as an extension point: `App\Controllers\Home` triggers the event
 * `homePage` with an instance of this class; a module's listener (e.g. Pages, when a page is
 * chosen as the home page in its settings) may answer it with `set()`. The first answer wins;
 * without one the theme's default home is shown. Listeners must check that their module is
 * enabled (a disabled module's `Config/Events.php` is still discovered).
 */
final class HomePage
{
    public const EVENT = 'homePage';

    private ResponseInterface|string|null $content = null;

    public function set(ResponseInterface|string $content): void
    {
        $this->content ??= $content;
    }

    public function answered(): bool
    {
        return $this->content !== null;
    }

    public function content(): ResponseInterface|string|null
    {
        return $this->content;
    }
}
