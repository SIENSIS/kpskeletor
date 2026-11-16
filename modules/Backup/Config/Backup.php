<?php

declare(strict_types=1);

namespace Modules\Backup\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Backup module configuration (docs/backup-restore.md). Values that administrators change in the
 * back office are settings of the manifest (`Backup.*`); these are deployment values.
 */
class Backup extends BaseConfig
{
    /** Folder of the backup files (outside `public/`, never served directly). */
    public string $path = WRITEPATH . 'backups';

    /** `writable/uploads` (area `writable-uploads`, backed up only when it is not empty). */
    public string $uploadsPath = WRITEPATH . 'uploads';

    /** The `.env` read into backups and, from the console only, written by a restore. */
    public string $envPath = ROOTPATH . '.env';

    /** Permissions of the backup files and of the folders the module creates. */
    public int $filePermission = 0640;

    public int $dirPermission = 0750;

    /**
     * Argon2id cost (`sodium_crypto_pwhash`) for new backups. The values used are stored in each
     * file's header; restores accept at most `$maxOpsLimit` / `$maxMemLimit` (a crafted header
     * cannot make the server derive a key for minutes or with gigabytes of memory).
     */
    public int $opsLimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;

    public int $memLimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;

    public int $maxOpsLimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE;

    public int $maxMemLimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE;

    /** Minimum length of a backup password (characters). */
    public int $minPasswordLength = 12;

    /** Rows read per query when the database is dumped. */
    public int $dumpChunk = 500;

    /** Largest backup file accepted by "upload a backup" (bytes; also limited by PHP's upload_max_filesize). */
    public int $maxUploadBytes = 2 * 1024 * 1024 * 1024;

    /** Largest total size of an archive once extracted, and largest compression ratio of an entry (zip bomb). */
    public int $maxUncompressedBytes = 50 * 1024 * 1024 * 1024;

    public int $maxRatio = 1_000;

    /**
     * Extensions never written into the public disk by a restore (server code and server
     * configuration, whatever the archive contains).
     *
     * @var list<string>
     */
    public array $neverRestorePublic = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'cgi', 'pl', 'py', 'sh', 'htaccess', '.htaccess', '.htpasswd', '.user.ini', 'web.config'];

    /**
     * Optional copy of every new backup to another Storage disk (a name of `Config\Storage::$disks`,
     * never the public one); null: no copy.
     */
    public ?string $copyDisk = null;

    /**
     * Run restores started from the back office after the response is sent (FastCGI), so the
     * browser shows the progress. False runs them inside the request (tests).
     */
    public bool $jobsAfterResponse = true;

    /** Hours a finished job's progress file is kept. */
    public int $jobHours = 24;
}
