<?php

declare(strict_types=1);

namespace Modules\Pages\Storage;

use Modules\Core\Storage\CrudUploadHandler;

/**
 * The featured image of a page: a file picker (upload or choose) on the Storage service's public
 * disk, folder `modules/pages`, also choosing from the public shared folders the editor may view
 * (Core `CrudUploadHandler::pickerField()`; session 23).
 */
final class FeaturedImages extends CrudUploadHandler
{
    protected function folder(): string
    {
        return 'modules/pages';
    }
}
