<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Base controller of every public page.
 *
 * Renders through the public theme (`Theme.public`) with the `public` layout, page metadata
 * (SEO: description, canonical URL, robots, Open Graph) and the theme regions.
 */
abstract class FrontendController extends BaseController
{
    /** @var array<string, mixed> */
    protected array $meta = [];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        service('theme')->setArea('public');
    }

    /**
     * Page metadata printed by the layout: description, canonical, robots, og (bool), image, type.
     *
     * @param array<string, mixed> $meta
     */
    protected function meta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Adds already-escaped HTML to a region of the layout (sidebar, footer, home…).
     */
    protected function region(string $region, string $html, int $order = 100): static
    {
        service('theme')->regions()->add($region, $html, $order);

        return $this;
    }

    protected function layout(): string
    {
        return theme_layout('public');
    }

    /**
     * Renders a view through the theme; adds `meta` and `layout` (unless given).
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        $data['meta'] ??= $this->meta;
        $data['layout'] ??= $this->layout();

        return theme_view($view, $data);
    }
}
