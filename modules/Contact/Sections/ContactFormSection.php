<?php

declare(strict_types=1);

namespace Modules\Contact\Sections;

use Modules\Contact\Services\ContactForm;
use Modules\Contact\Services\Recipients;
use Modules\Core\Sections\Fields\ChoicesField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;
use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionOptions;
use Modules\Core\Sections\SectionType;

/**
 * The contact form inside a page, `contact.form` (session 48, D-48-01): a heading, an
 * introduction and the form, which may offer only some recipients (none chosen = every active
 * one). The form posts to `contact` with the same layers as the contact page; the signed token
 * carries the recipients offered and the page's path, so a visitor cannot choose another
 * recipient and goes back to this page after sending.
 */
final class ContactFormSection extends SectionType
{
    public function key(): string
    {
        return 'contact.form';
    }

    public function label(): string
    {
        return lang('Contact.sections.form.label');
    }

    public function description(): string
    {
        return lang('Contact.sections.form.description');
    }

    public function icon(): string
    {
        return 'bi bi-envelope';
    }

    public function thumbnail(): string
    {
        helper('asset');

        return asset('contact::img/sections/form.svg');
    }

    public function backgrounds(): array
    {
        return ['none', 'light'];
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Contact.sections.form.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('Contact.sections.form.intro')->translated()->maxLength(400),
            ChoicesField::make('recipients')->label('Contact.sections.form.recipients')->help('Contact.sections.form.recipientsHelp')
                ->optionsFrom(static function (): array {
                    $options = [];

                    foreach ((new Recipients())->active() as $recipient) {
                        $options[(string) $recipient['id']] = $recipient['name'];
                    }

                    return $options;
                }),
        ];
    }

    public function render(SectionData $section, RenderContext $context): string
    {
        $values  = $this->resolve($section, $context->locale, $context->defaultLocale);
        $options = SectionOptions::normalise($section->options, $this);
        $only    = array_values(array_filter(array_map('intval', (array) $values['recipients']), static fn (int $id): bool => $id > 0));
        $return  = '/' . ltrim(service('localeurls')->path(), '/');

        return theme_view($this->view(), [
            'section' => $section,
            'values'  => $values,
            'options' => $options,
            'style'   => $options['style'],
            'context' => $context,
            'type'    => $this,
            'form'    => (new ContactForm())->data($only, $return, 'contact-' . ($section->id ?? $context->index)),
        ]);
    }
}
