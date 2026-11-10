<?php

declare(strict_types=1);

namespace Modules\Core\Install;

/**
 * The installer's conversation with the person installing (the console; none on the web), handed
 * to the listeners of `Installer::EVENT_SUPERADMIN` (e.g. Auth offers 2FA) so they can ask
 * questions without knowing where they run.
 */
interface InstallPrompt
{
    /**
     * Whether questions can be asked (false: use the options or the defaults).
     */
    public function interactive(): bool;

    /**
     * A command-line option (e.g. `2fa`), or null when not given.
     */
    public function option(string $name): ?string;

    /**
     * Asks a question; `$options` limits the answers (first = default when not interactive).
     *
     * @param list<string> $options
     */
    public function ask(string $question, array $options = [], ?string $default = null): string;

    public function write(string $line, ?string $color = null): void;
}
