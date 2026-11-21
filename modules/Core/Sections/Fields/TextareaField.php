<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

/**
 * Several lines of plain text (an introduction, a short paragraph): line breaks are kept, markup is
 * escaped when shown.
 */
class TextareaField extends TextField
{
    protected int $maxLength = 2000;

    public function type(): string
    {
        return 'textarea';
    }

    protected function multiline(): bool
    {
        return true;
    }
}
