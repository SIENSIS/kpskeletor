<?php

declare(strict_types=1);

namespace Modules\Events\Sections;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\NumberField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;
use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionOptions;
use Modules\Events\Services\EventDetails;
use Modules\Events\Services\EventRepository;

/**
 * Upcoming events `events.list`: the next N public events (occurrences of recurring events
 * included, cancelled ones left out) of some categories (none = all) within a number of days, as
 * a list or as cards, each linking to its public page; an optional "more" link.
 */
final class EventsListSection extends EventsSectionType
{
    protected function typeName(): string
    {
        return 'list';
    }

    public function icon(): string
    {
        return 'bi bi-calendar-event';
    }

    public function styles(): array
    {
        return ['list' => 'Events.sections.list.styles.list', 'cards' => 'Events.sections.list.styles.cards'];
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Events.sections.fields.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('Events.sections.fields.intro')->translated()->maxLength(400),
            self::categoriesField(),
            NumberField::make('limit')->label('Events.sections.fields.limit')->between(1, 12)->default(4),
            NumberField::make('days')->label('Events.sections.fields.days')->between(1, 365)->default(60)->help('Events.sections.help.days'),
            SelectField::make('columns')->label('Events.sections.fields.columns')->options(['2' => '2', '3' => '3', '4' => '4'])->default('4')->help('Events.sections.help.columns'),
            LinkField::make('more')->label('Events.sections.fields.more')->help('Events.sections.help.more'),
        ];
    }

    public function render(SectionData $section, RenderContext $context): string
    {
        if (! self::publicCalendar()) {
            return '';
        }

        $values = $this->resolve($section, $context->locale, $context->defaultLocale);
        $now    = new DateTimeImmutable('now', new DateTimeZone(site_timezone()));
        $days   = max(1, min(365, (int) ($values['days'] ?? 60)));
        $rows   = (new EventRepository())->occurrences(null, $now, $now->modify('+' . $days . ' days'), true, ['categories' => array_map('intval', $values['categories'])]);
        $rows   = array_values(array_filter($rows, static fn (array $row): bool => $row['occurrence_end'] > $now && $row['status'] !== 'cancelled'));
        $rows   = array_slice($rows, 0, max(1, min(12, (int) ($values['limit'] ?? 4))));
        $items  = array_map(static fn (array $row): array => [
            'title'    => (string) $row['title'],
            'when'     => EventDetails::when($row['occurrence_start'], $row['occurrence_end'], (bool) $row['all_day']),
            'day'      => $row['occurrence_start']->format('d'),
            'month'    => (string) \IntlDateFormatter::formatObject($row['occurrence_start'], 'MMM', $context->locale),
            'date'     => $row['occurrence_start']->format('Y-m-d'),
            'location' => (string) ($row['location'] ?? ''),
            'url'      => site_url('calendar/event/' . EventRepository::code((int) $row['id'])) . '?date=' . $row['occurrence_start']->format('Y-m-d'),
        ], $rows);
        $options = SectionOptions::normalise($section->options, $this);

        return theme_view($this->view(), [
            'section' => $section,
            'values'  => $values,
            'options' => $options,
            'style'   => $options['style'],
            'context' => $context,
            'items'   => $items,
        ]);
    }
}
