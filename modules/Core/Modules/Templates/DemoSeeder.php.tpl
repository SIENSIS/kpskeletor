<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Database\Seeds;

use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;

/**
 * Demo data of the {{Name}} module, in Catalan, loaded with `php spark modules:demo {{Name}}`.
 * Write every row through $demo so that `--remove` deletes exactly these rows.
 */
final class {{Name}}DemoSeeder extends DemoSeeder
{
    public function load(DemoData $demo): void
    {
        $demo->insert('{{name}}_items', ['name' => 'Element de demostració']);
    }
}
