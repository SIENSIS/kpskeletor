<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\View\Exceptions\ViewException;
use Modules\Core\Security\HtmlSanitizer;
use Modules\Pages\Storage\FeaturedImages;

/**
 * Renders a page through the public theme with its template (`Modules\Pages\Views\templates\<key>`,
 * overridable by themes), for the public routes, the home page and the back office's preview.
 *
 * Meta: title and description (SEO fields, else title and summary), the canonical URL (the SEO
 * field, else the page's own path, `/` for the home page) and `noindex` for pages marked so, for
 * every page that is not public and for previews.
 */
final class PageRenderer
{
    public function __construct(private readonly PageTree $tree = new PageTree())
    {
    }

    /**
     * @param array<string, mixed> $page a row of PageRepository::find()
     */
    public function render(array $page, ?User $user, bool $home = false, bool $preview = false): string
    {
        service('theme')->setArea('public');

        $id     = (int) $page['id'];
        $locale = service('request')->getLocale();
        $path   = $this->tree->path($id, $locale);
        $image  = FeaturedImages::urlOf(is_string($page['featured_image'] ?? null) ? $page['featured_image'] : null);

        $canonical = trim((string) $page['canonical']);
        $canonical = $canonical !== '' ? $canonical : ($home ? site_url('/') : ($path !== null ? site_url($path) : null));
        $noindex   = $preview || (int) $page['noindex'] === 1 || $page['visibility'] !== 'public';

        $meta = [
            'description' => (string) ($page['meta_description'] !== '' ? $page['meta_description'] : $page['summary']),
            'canonical'   => $preview ? null : $canonical,
            'robots'      => $noindex ? 'noindex, nofollow' : null,
            'og'          => ! $noindex,
            'type'        => 'website',
            'image'       => $image,
        ];

        return theme_view($this->view((string) $page['template']), [
            'layout'      => theme_layout('public'),
            'meta'        => $meta,
            'title'       => (string) ($page['meta_title'] !== '' ? $page['meta_title'] : $page['title']),
            'page'        => $page,
            'body'        => HtmlSanitizer::clean((string) $page['body']),
            'image'       => $image,
            'children'    => (new PageRepository())->children($id, $user),
            'childUrl'    => fn (int $child): ?string => ($childPath = $this->tree->path($child, $locale)) !== null ? site_url($childPath) : null,
            'trail'       => $home ? [] : $this->breadcrumbs($id, $locale),
            'preview'     => $preview,
            'home'        => $home,
        ]);
    }

    /**
     * The view of a template: the page's when the public theme offers it and a view exists for
     * it, otherwise `default`.
     */
    public function view(string $template): string
    {
        $templates = service('theme')->pageTemplates('public');

        if ($template !== 'default' && array_key_exists($template, $templates) && preg_match('/^[a-z][a-z0-9_-]*$/', $template) === 1) {
            try {
                service('theme')->resolve('Modules\Pages\Views\templates\\' . $template, 'public');

                return 'Modules\Pages\Views\templates\\' . $template;
            } catch (ViewException) {
                // Declared by the theme without a view: fall back to the default template.
            }
        }

        return 'Modules\Pages\Views\templates\default';
    }

    /**
     * Ancestors (links) and the page itself.
     *
     * @return list<array{label: string, url?: string}>
     */
    private function breadcrumbs(int $id, string $locale): array
    {
        $crumbs  = [];
        $lineage = $this->tree->lineage($id);

        foreach ($lineage as $page) {
            $label = $this->tree->title($page, $locale);

            if ($page === $id) {
                $crumbs[] = ['label' => $label];

                continue;
            }

            $path     = $this->tree->path($page, $locale);
            $crumbs[] = $path !== null && PageVisibility::isPublished($page) ? ['label' => $label, 'url' => site_url($path)] : ['label' => $label];
        }

        return count($crumbs) > 1 ? $crumbs : [];
    }
}
