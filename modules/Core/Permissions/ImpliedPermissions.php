<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use Kpcrudy\Crud;

/**
 * Completes a set of grants with the permissions they presuppose (`Kpcrudy\Crud::impliedPermissions()`:
 * edit → list, show; restore/purge → trash; export → list…), keeping only operations that exist
 * in the resource's catalogue. Wildcards and names of unknown resources are kept as they are.
 * Used for `defaultGrants` and for every grant saved later (decision D-00-21).
 */
final class ImpliedPermissions
{
    /**
     * @param list<string>                $grants     Atomic names or wildcards
     * @param array<string, list<string>> $operations Resource prefix => its operations in the catalogue
     *
     * @return list<string> Sorted, unique
     */
    public static function complete(array $grants, array $operations): array
    {
        $expanded = [];
        $atomic   = [];

        foreach ($grants as $grant) {
            $grant                = strtolower(trim($grant));
            [$prefix, $operation] = array_pad(explode('.', $grant, 2), 2, '');

            if (str_contains($grant, '*') || ! isset($operations[$prefix])) {
                $expanded[] = $grant;

                continue;
            }

            $atomic[$prefix][] = $operation;
        }

        foreach ($atomic as $prefix => $ops) {
            foreach (Crud::impliedPermissions($ops) as $operation) {
                if (in_array($operation, $operations[$prefix], true)) {
                    $expanded[] = $prefix . '.' . $operation;
                }
            }
        }

        $expanded = array_values(array_unique($expanded));
        sort($expanded);

        return $expanded;
    }

    /**
     * Operations by resource prefix of a catalogue (as built by CatalogueBuilder or stored).
     *
     * @param array<string, array{module: string, resource: string, operation: string, name: string, description: string}> $catalogue
     *
     * @return array<string, list<string>>
     */
    public static function operationsOf(array $catalogue): array
    {
        $operations = [];

        foreach ($catalogue as $name => $entry) {
            $dot                       = strrpos($name, '.');
            $prefix                    = $dot === false ? $name : substr($name, 0, $dot);
            $operations[$prefix][]     = $entry['operation'];
        }

        return $operations;
    }
}
