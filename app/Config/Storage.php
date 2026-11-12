<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;
use Modules\Core\Storage\OwnerAccess;
use Modules\Core\Storage\Picker\StorageBackend;

/**
 * File storage (docs/specification.md, "Storage"; docs/storage.md). Modules never use these
 * paths: they call the `storage` service (`service('storage')->disk('private')`,
 * `->store($upload, …)`), so another Flysystem adapter can be added later.
 *
 * The global upload allow-list and size limit are the settings `Core.uploadTypes`,
 * `Core.uploadMaxSize` and `Core.uploadAllowSvg` (Settings › Security, session 14).
 */
class Storage extends BaseConfig
{
    /**
     * Disks: `root` (absolute folder), `url` (public URL prefix relative to the base URL, or null
     * for a disk served only through the permission-checking controller) and the permissions of
     * the folders and files it creates. Private files: `0750`/`0640` (never executable, only the
     * web server user); public files must be readable by the web server.
     *
     * @var array<string, array{root: string, url: string|null, dirPermission: int, filePermission: int}>
     */
    public array $disks = [
        'public' => [
            'root'           => FCPATH . 'uploads',
            'url'            => 'uploads',
            'dirPermission'  => 0755,
            'filePermission' => 0644,
        ],
        'private' => [
            'root'           => WRITEPATH . 'storage',
            'url'            => null,
            'dirPermission'  => 0750,
            'filePermission' => 0640,
        ],
    ];

    /**
     * Extensions that are never accepted, whatever the settings or a field say: server-side code,
     * HTML and other documents a browser runs, executables and server configuration files. SVG is
     * refused unless `Core.uploadAllowSvg` is on (then it is sanitised).
     *
     * @var list<string>
     */
    public array $forbiddenExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'inc',
        'html', 'htm', 'xhtml', 'shtml', 'xht', 'mht', 'mhtml', 'xml', 'xsl', 'xslt', 'hta',
        'js', 'mjs', 'cjs', 'jsp', 'jspx', 'asp', 'aspx', 'cer', 'cfm', 'cgi', 'pl', 'py', 'rb',
        'sh', 'bash', 'bat', 'cmd', 'com', 'exe', 'dll', 'msi', 'scr', 'vbs', 'ps1', 'jar', 'swf',
        'htaccess', 'htpasswd', 'ini', 'env', 'config', 'user',
    ];

    /**
     * Image extensions that are decoded and re-encoded with GD (strips metadata and anything
     * hidden in the file) unless a field turns it off.
     *
     * @var list<string>
     */
    public array $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * MIME types accepted for an extension besides those of Config\Mimes (the first one is the
     * type the file is stored and served with when Config\Mimes has none).
     *
     * @var array<string, list<string>>
     */
    public array $extraMimes = [
        'csv' => ['text/plain'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'svg' => ['text/plain'],
    ];

    /** Images larger than this many pixels are refused (decompression bombs). */
    public int $maxImagePixels = 40_000_000;

    /** Quality of re-encoded JPEG and WebP images (0–100). */
    public int $imageQuality = 85;

    /**
     * Who may read a private file through `files/private/{key}`
     * (`Modules\Core\Storage\FileAccessRule`). Until the Files module's folder permissions
     * (session 22): the user who uploaded it and superadmins.
     *
     * A class implementing Modules\Core\Storage\FileAccessRule; anything else denies every file.
     */
    public string $accessRule = OwnerAccess::class;

    /**
     * What the file picker's endpoints (`files/picker/upload`, `files/picker/browse`) do with a
     * valid upload token (`Modules\Core\Storage\Picker\PickerBackend`). The Core's backend
     * stores into the field's folder; the Files module replaces it through its registrar (folders
     * on demand, quotas, folder ACL). Anything else falls back to the Core's backend.
     */
    public string $pickerBackend = StorageBackend::class;

    /** Lifetime of a file-picker upload token, in seconds (a form may stay open a while). */
    public int $pickerTokenTtl = 4 * HOUR;
}
