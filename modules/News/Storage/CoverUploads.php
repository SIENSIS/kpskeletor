<?php

declare(strict_types=1);

namespace Modules\News\Storage;

use Modules\Core\Storage\CrudUploadHandler;

/**
 * kpcrudy upload handler of the post cover: the Storage service's public disk, folder
 * `modules/news` (Core `CrudUploadHandler`).
 *
 * TODO(session 23): replace this field with the Core file picker (`callbackField()`), which
 * also lets authors reuse files of the Files module.
 */
final class CoverUploads extends CrudUploadHandler
{
    protected function folder(): string
    {
        return 'modules/news';
    }
}
