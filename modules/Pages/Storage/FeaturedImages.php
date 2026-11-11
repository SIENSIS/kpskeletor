<?php

declare(strict_types=1);

namespace Modules\Pages\Storage;

use Modules\Core\Storage\CrudUploadHandler;

/**
 * kpcrudy upload handler of the featured image of a page: the Storage service's public disk,
 * folder `modules/pages` (Core `CrudUploadHandler`).
 *
 * TODO(session 23): replace this field with the Core file picker (`callbackField()`).
 */
final class FeaturedImages extends CrudUploadHandler
{
    protected function folder(): string
    {
        return 'modules/pages';
    }
}
