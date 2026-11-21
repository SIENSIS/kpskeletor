<?php

declare(strict_types=1);

namespace Modules\Events\Sections;

use Modules\Core\Sections\Fields\BooleanField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;
use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionOptions;
use Modules\Events\Services\CalendarComponent;
use Modules\Events\Services\EventRepository;

/**
 * Calendar `events.calendar`: the public calendar component (`events/calendar`, the vendored
 * FullCalendar) inside a page, with its first view, the categories it shows (none = all) and
 * an optional category filter among them. Its events come from the public feed
 * (`calendar/feed`, public events only).
 */
final class CalendarSection extends EventsSectionType
{
    public const VIEWS = ['dayGridMonth', 'timeGridWeek', 'listMonth'];

    protected function typeName(): string
    {
        return 'calendar';
    }

    public function icon(): string
    {
        return 'bi bi-calendar3';
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Events.sections.fields.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('Events.sections.fields.intro')->translated()->maxLength(400),
            self::categoriesField(),
            SelectField::make('view')->label('Events.sections.fields.view')->options([
                'dayGridMonth' => 'Events.sections.views.dayGridMonth',
                'timeGridWeek' => 'Events.sections.views.timeGridWeek',
                'listMonth'    => 'Events.sections.views.listMonth',
            ])->help('Events.sections.help.view'),
            BooleanField::make('filter')->label('Events.sections.fields.filter')->default(true),
        ];
    }

    public function render(SectionData $section, RenderContext $context): string
    {
        if (! self::publicCalendar()) {
            return '';
        }

        $values     = $this->resolve($section, $context->locale, $context->defaultLocale);
        $chosen     = array_map('intval', $values['categories']);
        $categories = array_values(array_filter((new EventRepository())->categories(), static fn (array $category): bool => $chosen === [] || in_array($category['id'], $chosen, true)));
        $filterId   = 'events-category-' . ($section->id ?? 0);
        $options    = CalendarComponent::options(site_url('calendar/feed'), $values['filter'] === true && count($categories) > 1 ? $filterId : null, (string) $values['view']);

        if ($chosen !== []) {
            $options['categories'] = $chosen;
        }

        return theme_view($this->view(), [
            'section'    => $section,
            'values'     => $values,
            'options'    => SectionOptions::normalise($section->options, $this),
            'context'    => $context,
            'calendar'   => $options,
            'categories' => $values['filter'] === true && count($categories) > 1 ? $categories : [],
            'filterId'   => $filterId,
        ]);
    }
}
