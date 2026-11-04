<?php

declare(strict_types=1);

namespace Modules\Core\Demo;

/**
 * What "remove demo data" did.
 */
final class DemoRemoval
{
    public int $rows = 0;

    public int $files = 0;

    /** @var list<string> "<table>#<id>" rows kept because other data depends on them */
    public array $kept = [];
}
