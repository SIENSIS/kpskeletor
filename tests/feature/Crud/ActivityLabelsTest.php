<?php

declare(strict_types=1);

namespace Tests\Feature\Crud;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Activity\Controllers\Activity;

/**
 * Every custom operation and bulk action of a module's CRUDs that `AuditedCrud` writes to the
 * activity log (POST, not skipped with `skipActivity()`) has a label: `<Module>.activity.<key>`
 * or `Activity.actions.<key>` in ca, es and en (docs/activity.md). Without one the Activity
 * screen shows the raw code.
 *
 * @internal
 */
final class ActivityLabelsTest extends CIUnitTestCase
{
    public function testLoggedOperationsHaveActivityLabels(): void
    {
        $missing = [];
        $checked = 0;

        foreach ($this->loggedOperations() as [$module, $key, $file]) {
            $checked++;

            foreach (['ca', 'es', 'en'] as $locale) {
                service('language')->setLocale($locale);

                if (Activity::actionLabel($module, $key) === $key) {
                    $missing[] = "{$module}.activity.{$key} ({$locale}, {$file})";
                }
            }
        }

        service('language')->setLocale('en');

        $this->assertGreaterThan(0, $checked, 'The scan finds the operations.');
        $this->assertSame([], $missing);
    }

    /**
     * (module, key, file) of the POST operations and bulk actions declared in module controllers.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function loggedOperations(): array
    {
        $operations = [];

        foreach (glob(ROOTPATH . 'modules/*/Controllers/{,*/}*.php', GLOB_BRACE) ?: [] as $file) {
            $source = (string) file_get_contents($file);
            $module = basename(dirname($file, str_contains($file, '/Controllers/Api/') ? 3 : 2));
            $skipped = [];

            if (preg_match_all('/skipActivity\(([^)]*)\)/', $source, $calls) > 0) {
                foreach ($calls[1] as $arguments) {
                    preg_match_all("/'([a-z0-9_]+)'/", $arguments, $keys);
                    $skipped = [...$skipped, ...$keys[1]];
                }
            }

            preg_match_all("/(addOperation|addBulkAction)\\(\\s*'([a-z0-9_]+)'/", $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $key = $match[2][0];
                // The options of this call: up to the next declaration.
                $next = strpos($source, '->add', $match[0][1] + 1);
                $call = substr($source, $match[0][1], $next === false ? null : $next - $match[0][1]);

                if (in_array($key, $skipped, true) || ($match[1][0] === 'addOperation' && preg_match("/'method'\\s*=>\\s*'get'/i", $call) === 1)) {
                    continue;
                }

                $operations[] = [$module, $key, substr($file, strlen(ROOTPATH))];
            }
        }

        return $operations;
    }
}
