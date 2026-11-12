<?php

declare(strict_types=1);

namespace Modules\Files\Database\Seeds;

use CodeIgniter\I18n\Time;
use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;
use Modules\Core\Storage\Storage;
use Modules\Files\Services\ThumbnailService;

/**
 * Demo data of the Files module (`php spark modules:demo Files`):
 *
 * - shared folders on the private disk: "Secretaria" (group `editor`: view, upload, edit;
 *   `admin`: every right) with a subfolder "Actes" that defines its own permissions (only
 *   `admin`), and "Documents comuns" (every signed-in user: view) — so inheritance and its
 *   override can be tried at once;
 * - a public shared folder "Imatges web" (group `editor`: view, upload, edit, delete);
 * - a few generated files (PNG images with thumbnails, a text file and a CSV) on the private
 *   disk, with tags.
 *
 * Files are written by the Storage service on the private disk (inside `writable/`), so removing
 * the demo deletes them too.
 */
final class FilesDemoSeeder extends DemoSeeder
{
    public function load(DemoData $demo): void
    {
        $now = Time::now()->toDateTimeString();

        foreach (['public', 'private'] as $disk) {
            if (db_connect()->table('folders')->where('disk', $disk)->where('path', 'shared')->countAllResults() === 0) {
                $demo->insert('folders', ['disk' => $disk, 'path' => 'shared', 'own_permissions' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $this->folder($demo, 'private', 'shared/Secretaria', [['group', 'editor', 'view,upload,edit'], ['group', 'admin', 'view,upload,edit,delete,manage']], 50 * 1024 * 1024);
        $this->folder($demo, 'private', 'shared/Secretaria/Circulars', null);
        $this->folder($demo, 'private', 'shared/Secretaria/Actes', [['group', 'admin', 'view,upload,edit,delete,manage']]);
        $this->folder($demo, 'private', 'shared/Documents comuns', [['authenticated', '', 'view'], ['group', 'editor', 'view,upload,edit,delete']]);
        $this->folder($demo, 'public', 'shared/Imatges web', [['group', 'editor', 'view,upload,edit,delete'], ['authenticated', '', 'view']]);

        $this->file($demo, 'shared/Secretaria', 'Calendari escolar 2026-2027.csv', "Data,Esdeveniment\n2026-09-07,Inici de curs\n2026-12-22,Vacances de Nadal\n2027-06-22,Final de curs\n", 'calendari,curs');
        $this->file($demo, 'shared/Secretaria/Circulars', 'Circular inici de curs.txt', "Benvolgudes famílies,\n\nEl curs comença el 7 de setembre.\n", 'circulars');
        $this->file($demo, 'shared/Secretaria/Actes', 'Acta claustre setembre.txt', "Acta del claustre de setembre (demo).\n", 'actes');
        $this->file($demo, 'shared/Documents comuns', 'Logotip.png', self::png(640, 360, [32, 96, 168]), 'logotip,imatge', 'Logotip del centre');
        $this->file($demo, 'shared/Documents comuns', 'Pati.png', self::png(800, 600, [60, 150, 90]), 'imatge,pati', 'Fotografia del pati');
        $this->file($demo, 'shared/Documents comuns', 'Normes de convivència.txt', "1. Respecte.\n2. Puntualitat.\n3. Cura dels espais.\n", 'normes');

    }

    /**
     * @param list<array{0: string, 1: string, 2: string}>|null $entries null: inherits
     */
    private function folder(DemoData $demo, string $disk, string $path, ?array $entries, ?int $quota = null): int
    {
        $now = Time::now()->toDateTimeString();
        $id  = (int) $demo->insert('folders', [
            'disk' => $disk, 'path' => $path, 'quota' => $quota, 'own_permissions' => $entries === null ? 0 : 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach ($entries ?? [] as [$type, $subject, $rights]) {
            $demo->insert('folder_permissions', ['folder_id' => $id, 'subject_type' => $type, 'subject_id' => $subject, 'rights' => $rights]);
        }

        return $id;
    }

    private function file(DemoData $demo, string $folder, string $name, string $contents, string $tags, string $alt = ''): void
    {
        /** @var Storage $storage */
        $storage = service('storage');
        $stored  = $storage->storeContents($contents, $name, 'private', $folder);
        $demo->record('stored_files', $stored->id);
        $this->recordFile($demo, $storage, $stored->path);
        $thumbnail = (new ThumbnailService($storage))->make($stored);

        if ($thumbnail !== null) {
            $this->recordFile($demo, $storage, $thumbnail);
        }

        $now = Time::now()->toDateTimeString();
        $demo->insert('files', [
            'stored_file_id' => $stored->id, 'disk' => 'private', 'folder' => $folder, 'name' => $stored->originalName,
            'visibility' => 'private', 'alt' => $alt, 'tags' => $tags, 'thumbnail' => $thumbnail, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /**
     * Records a file of the private disk for removal (paths relative to WRITEPATH).
     */
    private function recordFile(DemoData $demo, Storage $storage, string $path): void
    {
        $root     = rtrim(str_replace('\\', '/', $storage->config()->disks['private']['root']), '/') . '/';
        $writable = rtrim(str_replace('\\', '/', WRITEPATH), '/') . '/';

        if (str_starts_with($root, $writable)) {
            $demo->recordFile(substr($root, strlen($writable)) . $path);
        }
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private static function png(int $width, int $height, array $rgb): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]));
        $light = (int) imagecolorallocate($image, 255, 255, 255);

        for ($i = 0; $i < 6; $i++) {
            imagefilledellipse($image, (int) ($width * (0.15 + $i * 0.14)), (int) ($height / 2), (int) ($width / 10), (int) ($width / 10), $light);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
