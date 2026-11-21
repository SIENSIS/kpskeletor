<?php

declare(strict_types=1);

namespace Modules\Events\Sections;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Sections\Fields\ChoicesField;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionType;
use Modules\Events\Services\EventRepository;

/**
 * Base of the Events module's section types (`events.<name>`, session 46): texts in
 * `Events.sections.<name>`, thumbnails in `Assets/img/sections/`. They show **public events only**,
 * like the public calendar, and nothing while the public calendar is off
 * (`Config\Calendar::$publicCalendar`; the editor sees a warning).
 */
abstract class EventsSectionType extends SectionType
{
    abstract protected function typeName(): string;

    public function key(): string
    {
        return 'events.' . $this->typeName();
    }

    public function label(): string
    {
        return lang('Events.sections.' . $this->typeName() . '.label');
    }

    public function description(): string
    {
        return lang('Events.sections.' . $this->typeName() . '.description');
    }

    public function thumbnail(): ?string
    {
        helper('asset');

        return asset('events::img/sections/' . $this->typeName() . '.svg');
    }

    public function backgrounds(): array
    {
        return ['none', 'light', 'dark', 'primary'];
    }

    public function warnings(SectionData $section, ?User $user = null): array
    {
        return self::publicCalendar() ? [] : [['message' => lang('Events.sections.calendarOff')]];
    }

    public static function publicCalendar(): bool
    {
        return (bool) config(\Modules\Events\Config\Calendar::class)->publicCalendar;
    }

    /** The categories (active ones) to choose from. */
    protected static function categoriesField(): ChoicesField
    {
        return ChoicesField::make('categories')->label('Events.sections.fields.categories')->help('Events.sections.help.categories')
            ->optionsFrom(static function (): array {
                $options = [];

                foreach ((new EventRepository())->categories() as $category) {
                    $options[(string) $category['id']] = $category['name'];
                }

                return $options;
            });
    }
}
