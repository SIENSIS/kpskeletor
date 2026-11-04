<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Tasks;

/**
 * Scheduled task of the {{Name}} module (stub). It implements the Core's TaskInterface once the
 * scheduler exists.
 */
final class {{Name}}Task
{
    public function description(): string
    {
        return lang('{{Name}}.task.description');
    }

    public function run(): void
    {
    }
}
