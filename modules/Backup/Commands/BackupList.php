<?php

declare(strict_types=1);

namespace Modules\Backup\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Backup\Services\BackupRepository;

/**
 * php spark backup:list
 *
 * The backups of `writable/backups`, newest first (the index is reconciled with the folder).
 */
final class BackupList extends BaseCommand
{
    protected $group       = 'Backup';
    protected $name        = 'backup:list';
    protected $description = 'Lists the backups (date, file, type, source, size, result).';
    protected $usage       = 'backup:list';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $repository = new BackupRepository();
        $repository->reconcile();
        $rows = [];

        foreach (db_connect()->table('backups')->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray() as $row) {
            $rows[] = [
                (string) $row['created_at'],
                (string) ($row['file'] ?? '—'),
                lang('Backup.type.' . $row['type']),
                lang('Backup.source.' . $row['source']),
                $row['file'] === null ? '—' : ui_bytes((int) $row['size']),
                lang('Backup.result.' . $row['result']) . ($row['result'] === 'failed' && $row['message'] !== null ? ': ' . $row['message'] : ''),
            ];
        }

        if ($rows === []) {
            CLI::write(lang('Backup.cli.none'));

            return EXIT_SUCCESS;
        }

        CLI::table($rows, [
            lang('Backup.field.date'), lang('Backup.field.file'), lang('Backup.field.type'),
            lang('Backup.field.source'), lang('Backup.field.size'), lang('Backup.field.result'),
        ]);

        return EXIT_SUCCESS;
    }
}
