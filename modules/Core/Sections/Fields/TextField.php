<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionValidationException;

/**
 * One line of plain text (a title, a label). Markup is kept as typed and escaped when shown.
 */
class TextField extends Field
{
    protected int $maxLength = 190;

    public function type(): string
    {
        return 'text';
    }

    public function maxLength(int $length): static
    {
        $this->maxLength = max(1, $length);

        return $this;
    }

    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        return $this->cleanScalarOrTexts($posted, $context, $path, function (mixed $value, string $path, ?string $locale): string {
            $text = $this->string($value, $path, $this->multiline());

            if (mb_strlen($text) > $this->maxLength) {
                throw SectionValidationException::at($path . ($locale === null ? '' : '.' . $locale), lang('Sections.errors.tooLong', [$this->getLabel(), $this->maxLength]));
            }

            return $text;
        });
    }

    protected function multiline(): bool
    {
        return false;
    }
}
