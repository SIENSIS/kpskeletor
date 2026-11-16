<?php

declare(strict_types=1);

namespace Modules\Backup\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserModel;
use Kpcrudy\Crud;
use Modules\Backup\Config\Backup as BackupConfig;
use Modules\Backup\Services\ArchiveCrypto;
use Modules\Backup\Services\BackupException;
use Modules\Backup\Services\BackupPassword;
use Modules\Backup\Services\BackupRepository;
use Modules\Backup\Services\BackupService;
use Modules\Backup\Services\JobStore;
use Modules\Backup\Services\RestoreJob;
use Modules\Core\Controllers\BackendController;

/**
 * The backups screen (resource `backup`; docs/backup-restore.md):
 *
 * - the list (kpcrudy): date, type, source, size, result, demo data, who, last verification;
 *   delete (`backup.delete`, the file too);
 * - create now (`backup.create`): type and password (12+ characters, typed twice);
 * - verify (`backup.list`): decrypt and check the checksums with the password;
 * - download (`backup.download` + re-authentication), logged as a security entry;
 * - upload a backup file (`backup.restore` + re-authentication): only files with a backup
 *   header, stored with a generated name, never served;
 * - restore (`backup.restore` + re-authentication): password, parts and the file name typed as
 *   confirmation; a quick password check, then a background job with progress (RestoreJob). The
 *   project root is never written: when the secrets differ the merged `.env` is shown once.
 */
final class Backups extends BackendController
{
    private const MASK = 'backups';

    public function index(): ResponseInterface|string
    {
        $repository = new BackupRepository();
        $repository->reconcile();
        $names = [];

        $crud = $this->crud('backup')
            ->setTable('backups')
            ->setSubject(lang('Backup.backup'), lang('Backup.title'))
            ->columns(['created_at', 'type', 'source', 'size', 'result', 'demo', 'created_by', 'verify_result'])
            ->showFields(['file', 'created_at', 'type', 'source', 'size', 'result', 'message', 'demo', 'core_version', 'created_by', 'verified_at', 'verify_result'])
            ->searchFields(['file', 'message'])
            ->filters(['type', 'source', 'result'])
            ->displayAs([
                'file'          => lang('Backup.field.file'),
                'created_at'    => lang('Backup.field.date'),
                'type'          => lang('Backup.field.type'),
                'source'        => lang('Backup.field.source'),
                'size'          => lang('Backup.field.size'),
                'result'        => lang('Backup.field.result'),
                'message'       => lang('Backup.field.message'),
                'demo'          => lang('Backup.field.demo'),
                'core_version'  => lang('Backup.field.version'),
                'created_by'    => lang('Backup.field.who'),
                'verified_at'   => lang('Backup.field.verifiedAt'),
                'verify_result' => lang('Backup.field.verified'),
            ])
            ->callbackColumn('created_at', static fn ($value): string => ui_date((string) $value, 'datetime'))
            ->callbackColumn('verified_at', static fn ($value): string => $value === null ? '—' : ui_date((string) $value, 'datetime'))
            ->callbackColumn('type', static fn ($value): string => esc(lang('Backup.type.' . $value)))
            ->callbackColumn('source', static fn ($value): string => esc(lang('Backup.source.' . $value)))
            ->callbackColumn('size', static fn ($value, array $row): string => $row['file'] === null ? '—' : esc(ui_bytes((int) $value)))
            ->callbackColumn('result', static fn ($value): string => $value === 'success'
                ? ui_badge(lang('Backup.result.success'), 'success')
                : ui_badge(lang('Backup.result.failed'), 'danger'))
            ->callbackColumn('verify_result', static fn ($value): string => match ($value) {
                'success' => ui_badge(lang('Backup.result.verified'), 'success', 'bi bi-shield-check'),
                'failed'  => ui_badge(lang('Backup.result.failed'), 'danger'),
                default   => '—',
            })
            ->callbackColumn('demo', static fn ($value): string => (int) $value === 1 ? ui_badge(lang('Backup.demo'), 'warning') : '—')
            ->callbackColumn('created_by', static function ($value) use (&$names): string {
                if ($value === null) {
                    return '—';
                }

                if (! isset($names[(int) $value])) {
                    $user                 = model(UserModel::class)->findById((int) $value);
                    $names[(int) $value] = $user !== null ? (string) $user->username : '#' . $value;
                }

                return esc($names[(int) $value]);
            })
            ->defaultOrder('created_at', 'desc')
            ->unsetAdd()->unsetEdit()->unsetExport()->unsetBulk()
            ->afterDelete(static function (string $id, array $row) use ($repository): void {
                if (is_string($row['file'] ?? null)) {
                    $repository->delete($row['file']);
                }
            })
            ->addHeaderAction(lang('Backup.action.create'), 'backend/backups/create', ['icon' => 'bi bi-plus-lg', 'class' => 'btn-primary', 'permission' => 'create'])
            ->addAction(lang('Backup.action.download'), 'backend/backups/download/{kp_id}', ['icon' => 'bi bi-download', 'permission' => 'download', 'visible' => static fn (array $row): bool => $row['file'] !== null])
            ->addAction(lang('Backup.action.verify'), 'backend/backups/verify/{kp_id}', ['icon' => 'bi bi-shield-check', 'permission' => 'list', 'visible' => static fn (array $row): bool => $row['file'] !== null]);

        // `restore` is also kpcrudy's own trash operation (this CRUD has no trash), so the
        // buttons of `backup.restore` are decided here; the routes check it again (`can:` + `reauth`).
        if ($this->can('backup.restore')) {
            $crud->addHeaderAction(lang('Backup.action.upload'), 'backend/backups/upload', ['icon' => 'bi bi-upload'])
                ->addAction(lang('Backup.action.restore'), 'backend/backups/recover/{kp_id}', ['icon' => 'bi bi-arrow-counterclockwise', 'visible' => static fn (array $row): bool => $row['file'] !== null]);
        }

        return $this->renderCrud($crud);
    }

    public function createForm(): string
    {
        return $this->form('create', ['errors' => [], 'type' => 'full']);
    }

    public function create(): ResponseInterface|string
    {
        $type     = (string) $this->request->getPost('type');
        $password = (string) $this->request->getPost('password');
        $errors   = [];

        if (! in_array($type, BackupRepository::TYPES, true)) {
            $errors['type'] = lang('Backup.error.type');
        }

        $problem = (new BackupPassword())->problem($password);

        if ($problem !== null) {
            $errors['password'] = $problem;
        } elseif (! hash_equals($password, (string) $this->request->getPost('password_confirm'))) {
            $errors['password_confirm'] = lang('Backup.error.passwordMismatch');
        }

        if ($errors !== []) {
            return $this->form('create', ['errors' => $errors, 'type' => $type]);
        }

        @set_time_limit(0);

        try {
            $row = (new BackupService())->create($password, $type, 'ui', (int) auth()->id());
        } catch (BackupException $e) {
            return $this->form('create', ['errors' => ['password' => $e->getMessage()], 'type' => $type]);
        }

        return redirect()->to(site_url('backend/backups'))->with('success', lang('Backup.msg.created', [$row['file'], ui_bytes((int) $row['size'])]));
    }

    public function verifyForm(string $code): string
    {
        return $this->form('verify', ['errors' => [], 'row' => $this->row($code), 'code' => $code]);
    }

    public function verify(string $code): string
    {
        $row = $this->row($code);
        @set_time_limit(0);

        try {
            $manifest = (new BackupService())->verify((string) $row['file'], (string) $this->request->getPost('password'));
        } catch (BackupException $e) {
            return $this->form('verify', ['errors' => ['password' => $e->getMessage()], 'row' => $row, 'code' => $code]);
        }

        return $this->form('verify', ['errors' => [], 'row' => $row, 'code' => $code, 'manifest' => $manifest]);
    }

    public function download(string $code): ResponseInterface
    {
        $row = $this->row($code);

        try {
            $path = (new BackupRepository())->path((string) $row['file']);
        } catch (BackupException) {
            throw PageNotFoundException::forPageNotFound();
        }

        security_log('Backup', 'download', ['resource' => 'backup', 'summary' => (string) $row['file']]);

        return $this->response->download($path, null)->setFileName((string) $row['file'])
            ->setContentType('application/octet-stream')
            ->setHeader('Cache-Control', 'no-store');
    }

    public function restoreForm(string $code): string
    {
        return $this->form('restore', ['errors' => [], 'row' => $this->row($code), 'code' => $code, 'only' => 'all']);
    }

    public function restore(string $code): string
    {
        $row      = $this->row($code);
        $file     = (string) $row['file'];
        $password = (string) $this->request->getPost('password');
        $only     = (string) $this->request->getPost('only');
        $errors   = [];

        if (! in_array($only, ['all', 'db', 'files'], true)) {
            $errors['only'] = lang('Backup.error.type');
        }

        if (! hash_equals($file, trim((string) $this->request->getPost('confirm')))) {
            $errors['confirm'] = lang('Backup.error.confirm');
        }

        try {
            $path = (new BackupRepository())->path($file);
        } catch (BackupException) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($errors === [] && ! (new ArchiveCrypto())->checkPassword($path, $password)) {
            $errors['password'] = lang('Backup.error.password');
            security_log('Backup', 'restore_refused', ['resource' => 'backup', 'summary' => $file . ': ' . lang('Backup.error.password')]);
        }

        if ($errors !== []) {
            return $this->form('restore', ['errors' => $errors, 'row' => $row, 'code' => $code, 'only' => $only]);
        }

        $userId = (int) auth()->id();
        $jobs   = new JobStore();
        $id     = $jobs->create('restore', $userId, ['file' => $file, 'only' => $only]);

        (new RestoreJob(config(BackupConfig::class), $jobs))->launch($id, $file, $password, $only === 'all' ? null : $only, $userId);

        // The page itself (not a redirect): while the job runs, every other page answers 503.
        return $this->jobPage($id);
    }

    public function job(string $id): string
    {
        return $this->jobPage($id);
    }

    public function uploadForm(): string
    {
        return $this->form('upload', ['errors' => []]);
    }

    public function upload(): ResponseInterface|string
    {
        $config = config(BackupConfig::class);
        $file   = $this->request->getFile('backup');
        $crypto = new ArchiveCrypto($config);

        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            return $this->form('upload', ['errors' => ['backup' => lang('Backup.error.upload')]]);
        }

        if ($file->getSize() > $config->maxUploadBytes || ! $crypto->isBackup($file->getTempName())) {
            security_log('Backup', 'upload_refused', ['resource' => 'backup', 'summary' => mb_substr($file->getClientName(), 0, 120)]);

            return $this->form('upload', ['errors' => ['backup' => lang('Backup.error.notBackup')]]);
        }

        $repository = new BackupRepository($config);
        $name       = $repository->newName('upload');
        $file->move($repository->folder(), $name);
        @chmod($repository->folder() . '/' . $name, $config->filePermission);

        $id = $repository->record([
            'file' => $name, 'size' => (int) filesize($repository->folder() . '/' . $name), 'type' => 'full',
            'source' => 'upload', 'result' => 'success', 'created_by' => (int) auth()->id(),
        ]);
        activity_log('Backup', 'upload', ['resource' => 'backup', 'summary' => $name, 'changes' => ['original' => mb_substr($file->getClientName(), 0, 120)]]);

        return redirect()->to(site_url('backend/backups/recover/' . Crud::maskId($id, self::MASK)))->with('success', lang('Backup.msg.uploaded', [$name]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function form(string $view, array $data): string
    {
        $this->breadcrumb(lang('Backup.title'), site_url('backend/backups'))->breadcrumb(lang('Backup.page.' . $view));

        return $this->render('Modules\Backup\Views\\' . $view, $data + [
            'title'     => lang('Backup.page.' . $view),
            'icon'      => 'bi bi-archive',
            'minLength' => (new BackupPassword())->minLength(),
        ]);
    }

    private function jobPage(string $id): string
    {
        $jobs = new JobStore();
        $job  = $jobs->get($id);

        if ($job === null || (int) ($job['user'] ?? 0) !== (int) auth()->id()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $env = ($job['status'] ?? '') === 'done' ? $jobs->takeEnv($id) : null;

        $title = match ($job['status'] ?? '') {
            'done'   => lang('Backup.page.jobDone'),
            'failed' => lang('Backup.page.jobFailed'),
            default  => lang('Backup.page.job'),
        };
        $this->breadcrumb(lang('Backup.title'), site_url('backend/backups'))->breadcrumb($title);

        return $this->render('Modules\Backup\Views\job', [
            'title'       => $title,
            'icon'        => 'bi bi-arrow-counterclockwise',
            'job'         => $job,
            'env'         => $env,
            'progressUrl' => site_url('progress/backup/' . $id),
            'pageUrl'     => site_url('backend/backups/jobs/' . $id),
        ]);
    }

    /**
     * The backup of a masked id, with a file.
     *
     * @return array<string, mixed>
     */
    private function row(string $code): array
    {
        $id  = Crud::unmaskId($code, self::MASK);
        $row = $id === null ? null : (new BackupRepository())->find((int) $id);

        if ($row === null || ! is_string($row['file'] ?? null)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $row;
    }
}
