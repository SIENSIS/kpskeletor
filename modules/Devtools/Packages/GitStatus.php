<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

/**
 * Local changes not committed in a folder (`git status --porcelain -- .`), so packaging can warn
 * about them. Returns null when the folder is not in a git work tree or git is not installed.
 */
final class GitStatus
{
    /**
     * @return list<string>|null changed paths (relative to the repository), or null without git
     */
    public static function changes(string $folder): ?array
    {
        if (! function_exists('proc_open') || ! is_dir($folder)) {
            return null;
        }

        $process = @proc_open(['git', '-C', $folder, 'status', '--porcelain', '--untracked-files=all', '--', '.'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            return null;
        }

        $output = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            return null;
        }

        $changes = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (trim($line) !== '') {
                $changes[] = trim(substr($line, 3));
            }
        }

        return $changes;
    }
}
