<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionValidationException;
use Modules\Core\Security\HtmlSanitizer;

/**
 * Rich text edited with the Core's editor (`RichTextEditor`), sanitised with HTMLPurifier when it
 * is saved and again when it is shown (`HtmlSanitizer`).
 */
class RichTextField extends Field
{
    protected int $maxLength = 100000;

    public function type(): string
    {
        return 'richtext';
    }

    public function maxLength(int $length): static
    {
        $this->maxLength = max(1, $length);

        return $this;
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        return $this->cleanScalarOrTexts($posted, $context, $path, function (mixed $value, string $path, ?string $locale): string {
            $html = $this->string($value, $path, true);

            if (strlen($html) > $this->maxLength) {
                throw SectionValidationException::at($path . ($locale === null ? '' : '.' . $locale), lang('Sections.errors.tooLong', [$this->getLabel(), $this->maxLength]));
            }

            $html = HtmlSanitizer::clean($html);

            // An editor left empty posts "<p><br></p>".
            return trim(strip_tags($html, '<img>')) === '' ? '' : $html;
        });
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        $html = parent::resolve($value, $texts, $locale, $default);

        return is_string($html) && $html !== '' ? HtmlSanitizer::clean($html) : '';
    }

    public function summary(mixed $resolved): string
    {
        return is_string($resolved) ? trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>'], ' ', $resolved)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) : '';
    }
}
