<?php

declare(strict_types=1);

namespace Modules\Files\Controllers;

use Modules\Core\Controllers\BackendController;
use Modules\Core\Storage\UploadRules;
use Modules\Files\Services\Browser;
use Modules\Files\Services\FileRepository;

/**
 * The file manager (`backend/files`, `files.list`): the page shell; the folder tree, the
 * listing and every action come from the JSON endpoints of FileApi through
 * `Assets/js/file-manager.js` (`files/file-manager`). Without JavaScript the page says so.
 */
final class FileManager extends BackendController
{
    public function index(): string
    {
        $user = $this->currentUser();
        (new Browser())->ensureSpaces($user);
        $validator = service('storage')->validator();

        $config = [
            'api'        => site_url('backend/files/api'),
            'css'        => asset('files::css/file-manager.css'),
            'types'      => array_keys(FileRepository::TYPES),
            'maxSize'    => $validator->maxSize(new UploadRules()),
            'extensions' => $validator->allowedTypes(new UploadRules()),
            'can'        => [
                'upload'  => $this->can('files.upload'),
                'edit'    => $this->can('files.edit'),
                'delete'  => $this->can('files.delete'),
                'folders' => $this->can('files.folders'),
                'acl'     => $this->can('files.acl'),
            ],
            'texts'      => lang('Files.js'),
        ];

        return $this->render('Modules\Files\Views\manager', [
            'title'    => lang('Files.manager.title'),
            'subtitle' => lang('Files.manager.subtitle'),
            'icon'     => 'bi bi-folder2-open',
            'config'   => $config,
        ]);
    }
}
