<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Storage;

use Modules\Core\Storage\CrudUploadHandler;

/**
 * The file fields of `{{table}}` ({{fields}}): the Core's file picker on the {{disk}} disk,
 * folder `{{folder}}` (Storage service: allow-lists, random names, docs/storage.md). Generated
 * by the code generator (docs/devtools.md); change the types or size here.
 */
final class {{class}} extends CrudUploadHandler
{
    public const TYPES = 'jpg,jpeg,png,webp,pdf';

    protected function folder(): string
    {
        return '{{folder}}';
    }
}
