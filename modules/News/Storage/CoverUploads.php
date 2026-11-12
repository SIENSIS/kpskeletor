<?php

declare(strict_types=1);

namespace Modules\News\Storage;

use Modules\Core\Storage\CrudUploadHandler;

/**
 * The post cover: a file picker (upload or choose; JPG, PNG, WebP up to 5 MB) on the Storage
 * service's public disk, folder `modules/news`, also choosing from the public shared folders the
 * author may view (Core `CrudUploadHandler::pickerField()`; session 23).
 */
final class CoverUploads extends CrudUploadHandler
{
    protected function folder(): string
    {
        return 'modules/news';
    }
}
