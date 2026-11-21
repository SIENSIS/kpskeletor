<?php

declare(strict_types=1);

namespace Modules\News\Sections;

use Modules\Core\Sections\Fields\BooleanField;
use Modules\Core\Sections\Fields\ChoicesField;
use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\NumberField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;
use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionOptions;
use Modules\Core\Sections\SectionQuery;
use Modules\Core\Sections\SectionType;
use Modules\News\Services\NewsRepository;

/**
 * A list of news `news.list` (session 46; docs/news.md, "News in pages"):
 *
 * - mode **latest** (the newest N posts and an optional "more" link) or **full list** (pages of N
 *   posts, the page in the query parameter `page-<section id>` so two lists on one page do not
 *   clash, and an optional search box `q-<section id>`);
 * - one or several categories (none = every category), 2–4 columns, cards or a compact list,
 *   date, summary and image optional.
 *
 * It shows exactly what the public news list shows (`NewsRepository` with `$publicOnly`: public
 * posts, published, not expired, in the reader's language), whoever reads the page.
 */
final class NewsListSection extends SectionType
{
    public const MODES = ['latest', 'full'];

    public function key(): string
    {
        return 'news.list';
    }

    public function label(): string
    {
        return lang('News.sections.list.label');
    }

    public function description(): string
    {
        return lang('News.sections.list.description');
    }

    public function icon(): string
    {
        return 'bi bi-newspaper';
    }

    public function thumbnail(): string
    {
        helper('asset');

        return asset('news::img/sections/list.svg');
    }

    public function styles(): array
    {
        return ['cards' => 'News.sections.list.styles.cards', 'list' => 'News.sections.list.styles.list'];
    }

    public function backgrounds(): array
    {
        return ['none', 'light', 'dark', 'primary'];
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('News.sections.fields.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('News.sections.fields.intro')->translated()->maxLength(400),
            SelectField::make('mode')->label('News.sections.fields.mode')->options([
                'latest' => 'News.sections.list.modes.latest',
                'full'   => 'News.sections.list.modes.full',
            ])->help('News.sections.help.mode'),
            NumberField::make('limit')->label('News.sections.fields.limit')->between(1, 24)->default(4)->help('News.sections.help.limit'),
            ChoicesField::make('categories')->label('News.sections.fields.categories')->help('News.sections.help.categories')
                ->optionsFrom(static function (): array {
                    $options = [];

                    foreach ((new NewsRepository())->categories() as $category) {
                        $options[(string) $category['id']] = $category['name'];
                    }

                    return $options;
                }),
            SelectField::make('columns')->label('News.sections.fields.columns')->options(['2' => '2', '3' => '3', '4' => '4'])->default('4'),
            BooleanField::make('show_date')->label('News.sections.fields.showDate')->default(true),
            BooleanField::make('show_summary')->label('News.sections.fields.showSummary')->default(true),
            BooleanField::make('show_image')->label('News.sections.fields.showImage')->default(true),
            BooleanField::make('search')->label('News.sections.fields.search')->help('News.sections.help.search'),
            LinkField::make('more')->label('News.sections.fields.more')->help('News.sections.help.more'),
        ];
    }

    public function render(SectionData $section, RenderContext $context): string
    {
        $values  = $this->resolve($section, $context->locale, $context->defaultLocale);
        $options = SectionOptions::normalise($section->options, $this);
        $full    = $values['mode'] === 'full';
        $limit   = max(1, min(24, (int) ($values['limit'] ?? 4)));
        $query   = new SectionQuery($section);
        $search  = $full && $values['search'] === true ? mb_substr($query->get('q'), 0, 100) : '';
        $page    = $full ? $query->page() : 1;
        $filters = ['categories' => array_map('intval', $values['categories'])];

        if ($search !== '') {
            $filters['q'] = $search;
        }

        $result = (new NewsRepository())->page(null, $page, $limit, $filters, true);
        $pages  = $full ? max(1, (int) ceil($result['total'] / $limit)) : 1;

        return theme_view($this->view(), [
            'section' => $section,
            'values'  => $values,
            'options' => $options,
            'style'   => $options['style'],
            'context' => $context,
            'type'    => $this,
            'items'   => $result['rows'],
            'full'    => $full,
            'query'   => $query,
            'search'  => $search,
            'page'    => $page,
            'pages'   => $pages,
        ]);
    }
}
