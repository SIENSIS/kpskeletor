<?php

declare(strict_types=1);

namespace Modules\Backup\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Backup\Services\JobStore;

/**
 * `GET progress/backup/<job>`: the progress of a restore as JSON (status, step, its label and
 * the percentage). Read from a file, without session or database (both may be replaced while
 * the job runs); the job id (128 random bits) is the capability and nothing secret is returned.
 */
final class Jobs extends Controller
{
    public function progress(string $id): ResponseInterface
    {
        $job = (new JobStore())->get($id);

        $this->response->setHeader('Cache-Control', 'no-store');

        if ($job === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'unknown']);
        }

        $step = (string) ($job['step'] ?? 'queued');

        return $this->response->setJSON([
            'status'  => (string) ($job['status'] ?? 'queued'),
            'step'    => $step,
            'label'   => lang('Backup.step.' . $step),
            'percent' => (int) ($job['percent'] ?? 0),
        ]);
    }
}
