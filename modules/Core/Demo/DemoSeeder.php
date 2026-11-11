<?php

declare(strict_types=1);

namespace Modules\Core\Demo;

use CodeIgniter\Database\Seeder;

/**
 * Base class of `Modules\<Name>\Database\Seeds\<Name>DemoSeeder`: demo data in Catalan, written
 * only through the DemoData helper. Loaded and removed by DemoDataManager (`modules:demo`).
 */
abstract class DemoSeeder extends Seeder
{
    /**
     * Modules whose demo data this one uses (e.g. the Menus demo links to the Pages demo): when
     * several modules are loaded together (`app:install --demo`, `modules:demo` without a name),
     * those load first.
     *
     * @var list<string>
     */
    public const AFTER = [];

    /**
     * Creates the demo data of the module through `$demo`.
     */
    abstract public function load(DemoData $demo): void;

    /**
     * Running the seeder directly (`php spark db:seed`) is refused: it would not be tracked by
     * the Core. Use `php spark modules:demo <Name>`.
     */
    public function run(): void
    {
        throw new \LogicException(lang('Core.demo.useCommand'));
    }
}
