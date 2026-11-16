<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

use Closure;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Modules\Core\Config\Packages;
use Modules\Core\Notifications\Types\PackageProcessed;
use Throwable;

/**
 * Staged package operations (table `module_packages`, files in `writable/packages/`; D-29-04):
 *
 * - **upload** (Modules screen, `modules.upload` + re-authentication): the ZIP is validated (and
 *   syntax-checked when the web server may run `php -l`), stored with a random name, never in a
 *   code folder; status *uploaded*;
 * - **approve**: *pending*; **request an uninstall** (`modules.uninstall` + re-authentication):
 *   *pending* at once; **discard**: *discarded* and the file deleted;
 * - **process** (`php spark modules:install --pending`, by an operator or a cron line of a user
 *   that may write the code folders): every pending operation runs in order of approval, its
 *   output is stored in `log`, the approver is notified (PackageProcessed);
 * - **expire**: uploaded and pending entries older than `Packages.pendingDays` expire and their
 *   files are deleted.
 */
final class StagedPackages
{
    public const STATUSES = ['uploaded', 'pending', 'installing', 'installed', 'failed', 'discarded', 'expired'];

    private readonly Packages $config;

    private readonly string $folder;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(
        private readonly PackageValidator $validator = new PackageValidator(),
        ?string $folder = null,
        private readonly ?BaseConnection $db = null,
    ) {
        $this->config = $validator->config();
        $this->folder = rtrim($folder ?? WRITEPATH . 'packages', '/');
    }

    public function folder(): string
    {
        return $this->folder;
    }

    /**
     * Validates and stores an uploaded package.
     *
     * @param string      $path      The uploaded temporary file
     * @param string|null $signature Contents of the `.minisig` file, if one was uploaded
     *
     * @return array<string, mixed> The new row
     *
     * @throws PackageRefused
     */
    public function upload(string $path, string $label, ?string $signature, ?int $userId): array
    {
        $this->ensureFolder();
        $info = PhpLint::available()
            ? $this->validator->validateAndLint($path, $signature, null, $this->folder, $label)
            : $this->validator->validate($path, $signature, null, $label);

        $name = bin2hex(random_bytes(16)) . '.zip';
        $file = $this->folder . '/' . $name;

        if (! @copy($path, $file)) {
            throw new PackageRefused(lang('Core.packages.notWritable', [$this->folder]));
        }

        @chmod($file, 0640);

        if ($signature !== null && trim($signature) !== '') {
            file_put_contents($file . '.minisig', $signature);
            @chmod($file . '.minisig', 0640);
        }

        $now = Time::now('UTC');
        $row = [
            'action'      => 'install',
            'type'        => $info->type,
            'name'        => $info->name,
            'version'     => $info->version,
            'file'        => $name,
            'sha256'      => $info->sha256,
            'manifest'    => json_encode($info->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status'      => 'uploaded',
            'uploaded_by' => $userId,
            'expires_at'  => $now->addDays($this->config->pendingDays)->format('Y-m-d H:i:s'),
            'created_at'  => $now->format('Y-m-d H:i:s'),
            'updated_at'  => $now->format('Y-m-d H:i:s'),
        ];

        $this->db()->table('module_packages')->insert($row);
        $row['id'] = (int) $this->db()->insertID();

        activity_log('Core', 'package_uploaded', ['resource' => 'modules', 'summary' => $info->name . ' ' . $info->version, 'changes' => ['type' => $info->type, 'sha256' => $info->sha256, 'signed' => $info->signed]]);

        return $row;
    }

    /**
     * @throws PackageRefused when it is not waiting for approval
     */
    public function approve(int $id, ?int $userId): void
    {
        $row = $this->find($id);

        if ($row === null || $row['status'] !== 'uploaded' || $row['action'] !== 'install') {
            throw new PackageRefused(lang('Core.packages.notApprovable'));
        }

        if (! is_file($this->path($row)) || ! hash_equals((string) $row['sha256'], (string) hash_file('sha256', $this->path($row)))) {
            $this->update($id, ['status' => 'failed', 'log' => lang('Core.packages.fileChanged')]);

            throw new PackageRefused(lang('Core.packages.fileChanged'));
        }

        $now = Time::now('UTC');
        $this->update($id, [
            'status'      => 'pending',
            'approved_by' => $userId,
            'approved_at' => $now->format('Y-m-d H:i:s'),
            'expires_at'  => $now->addDays($this->config->pendingDays)->format('Y-m-d H:i:s'),
        ]);

        activity_log('Core', 'package_approved', ['resource' => 'modules', 'summary' => $row['name'] . ' ' . $row['version']]);
    }

    /**
     * A pending uninstall (the "Uninstall" button). Refused when the module or theme cannot be
     * uninstalled now, or when one is already pending.
     *
     * @return int The new row id
     *
     * @throws PackageRefused
     */
    public function requestUninstall(string $type, string $name, bool $keepData, ?int $userId, Uninstaller $uninstaller = new Uninstaller()): int
    {
        // The web server may not write the code folders: that is checked by the console.
        $problems = $type === PackageInfo::THEME ? $uninstaller->themeProblems($name, false) : $uninstaller->moduleProblems($name, false);

        if ($problems !== []) {
            throw new PackageRefused(implode(' ', $problems));
        }

        $open = $this->db()->table('module_packages')->where('action', 'uninstall')->where('name', $name)->whereIn('status', ['pending', 'installing'])->countAllResults();

        if ($open > 0) {
            throw new PackageRefused(lang('Core.packages.alreadyPending', [$name]));
        }

        $now = Time::now('UTC');
        $this->db()->table('module_packages')->insert([
            'action'      => 'uninstall',
            'type'        => $type,
            'name'        => $name,
            'keep_data'   => $keepData ? 1 : 0,
            'status'      => 'pending',
            'uploaded_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => $now->format('Y-m-d H:i:s'),
            'expires_at'  => $now->addDays($this->config->pendingDays)->format('Y-m-d H:i:s'),
            'created_at'  => $now->format('Y-m-d H:i:s'),
            'updated_at'  => $now->format('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db()->insertID();

        activity_log('Core', 'uninstall_requested', ['resource' => 'modules', 'summary' => $name, 'changes' => ['type' => $type, 'keepData' => $keepData]]);

        return $id;
    }

    /**
     * @throws PackageRefused when it already ran
     */
    public function discard(int $id): void
    {
        $row = $this->find($id);

        if ($row === null || ! in_array($row['status'], ['uploaded', 'pending'], true)) {
            throw new PackageRefused(lang('Core.packages.notDiscardable'));
        }

        $this->removeFile($row);
        $this->update($id, ['status' => 'discarded']);
        activity_log('Core', 'package_discarded', ['resource' => 'modules', 'summary' => trim($row['name'] . ' ' . $row['version']), 'changes' => ['action' => $row['action']]]);
    }

    /**
     * Expires old uploaded and pending entries and deletes their files.
     *
     * @return int How many expired
     */
    public function expire(): int
    {
        $now  = Time::now('UTC')->format('Y-m-d H:i:s');
        $rows = $this->db()->table('module_packages')->whereIn('status', ['uploaded', 'pending'])->where('expires_at <', $now)->get()->getResultArray();

        foreach ($rows as $row) {
            $this->removeFile($row);
            $this->update((int) $row['id'], ['status' => 'expired']);
        }

        return count($rows);
    }

    /**
     * Runs every pending operation (console only).
     *
     * @param Closure(string, string): void $say
     * @param array{backup?: bool, password?: string|null} $options
     *
     * @return array{done: int, failed: int}
     */
    public function process(Closure $say, array $options = [], ?PackageInstaller $installer = null, ?Uninstaller $uninstaller = null): array
    {
        $this->expire();
        $result = ['done' => 0, 'failed' => 0];

        foreach ($this->pending() as $row) {
            $id = (int) $row['id'];
            $this->update($id, ['status' => 'installing']);
            $log = [];
            $tee = static function (string $line, string $color = '') use ($say, &$log): void {
                $log[] = $line;
                $say($line, $color);
            };

            $say(lang('Core.packages.processing', [lang('Core.packages.actions.' . $row['action']), trim($row['name'] . ' ' . $row['version'])]), 'yellow');

            try {
                if ($row['action'] === 'uninstall') {
                    $uninstaller ??= new Uninstaller();
                    $uninstaller->onProgress($tee);
                    $row['type'] === PackageInfo::THEME
                        ? $uninstaller->uninstallTheme((string) $row['name'], $options + ['userId' => $row['approved_by'] === null ? null : (int) $row['approved_by']])
                        : $uninstaller->uninstallModule((string) $row['name'], $options + ['keepData' => (bool) $row['keep_data'], 'userId' => $row['approved_by'] === null ? null : (int) $row['approved_by']]);
                } else {
                    $file = $this->path($row);

                    if (! is_file($file) || ! hash_equals((string) $row['sha256'], (string) hash_file('sha256', $file))) {
                        throw new PackageRefused(lang('Core.packages.fileChanged'));
                    }

                    $signature = is_file($file . '.minisig') ? (string) file_get_contents($file . '.minisig') : null;
                    $info      = $this->validator->validate($file, $signature, $row['type'] === PackageInfo::THEME ? PackageInfo::THEME : PackageInfo::MODULE, $row['name'] . ' ' . $row['version']);
                    $installer ??= new PackageInstaller($this->validator);
                    $installer->onProgress($tee);
                    $installer->install($file, $info, $options + ['userId' => $row['approved_by'] === null ? null : (int) $row['approved_by']]);
                }

                $status = 'installed';
                $result['done']++;
            } catch (Throwable $e) {
                $tee($e->getMessage(), 'red');
                $status = 'failed';
                $result['failed']++;
            }

            $this->removeFile($row);
            $this->update($id, ['status' => $status, 'processed_at' => Time::now('UTC')->format('Y-m-d H:i:s'), 'log' => mb_substr(implode("\n", $log), 0, 60000)]);
            $this->notify($row, $status, end($log) ?: '');
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        return $this->db()->table('module_packages')->where('status', 'pending')->orderBy('approved_at')->orderBy('id')->get()->getResultArray();
    }

    /**
     * The latest entries, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 50): array
    {
        return $this->db()->table('module_packages')->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    /**
     * Names with an operation waiting (status pending or installing), by action.
     *
     * @return array<string, string> name => action
     */
    public function waiting(): array
    {
        $names = [];

        foreach ($this->db()->table('module_packages')->select('name, action')->whereIn('status', ['pending', 'installing'])->get()->getResultArray() as $row) {
            $names[(string) $row['name']] = (string) $row['action'];
        }

        return $names;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->db()->table('module_packages')->where('id', $id)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function info(array $row): ?PackageInfo
    {
        $data = json_decode((string) ($row['manifest'] ?? ''), true);

        return is_array($data) ? PackageInfo::fromArray($data) : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function path(array $row): string
    {
        $file = (string) ($row['file'] ?? '');

        // Stored names are generated (hex + .zip); anything else never leaves the folder.
        if (preg_match('/^[a-f0-9]{32}\.zip$/', $file) !== 1) {
            return $this->folder . '/missing.zip';
        }

        return $this->folder . '/' . $file;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function removeFile(array $row): void
    {
        if (($row['file'] ?? null) === null) {
            return;
        }

        $file = $this->path($row);
        @unlink($file);
        @unlink($file . '.minisig');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function notify(array $row, string $status, string $message): void
    {
        $user = $row['approved_by'] ?? $row['uploaded_by'] ?? null;

        if ($user === null) {
            return;
        }

        try {
            notify((int) $user, new PackageProcessed([
                'name'    => trim($row['name'] . ' ' . $row['version']),
                'action'  => (string) $row['action'],
                'status'  => $status,
                'message' => mb_substr($message, 0, 500),
            ]));
        } catch (Throwable $e) {
            log_message('error', 'Package notification failed: {message}', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update(int $id, array $data): void
    {
        $this->db()->table('module_packages')->where('id', $id)->update($data + ['updated_at' => Time::now('UTC')->format('Y-m-d H:i:s')]);
    }

    private function ensureFolder(): void
    {
        if (! is_dir($this->folder)) {
            @mkdir($this->folder, 0750, true);
        }

        if (! is_file($this->folder . '/index.html')) {
            @file_put_contents($this->folder . '/index.html', '');
        }
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
