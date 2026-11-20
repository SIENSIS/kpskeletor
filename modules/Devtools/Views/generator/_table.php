<?php
/**
 * One table of the wizard (steps 2 to 6): table and columns with their labels, demo data, CRUD,
 * API, menu and permissions. Names: `tables[<t>][…]` following the definition's JSON.
 *
 * @var int                     $t
 * @var array<string, mixed>    $table
 * @var array<string, mixed>    $module
 * @var array<string, string>   $errors
 * @var list<string>            $groups
 * @var list<string>            $tables      database tables
 * @var list<string>            $permissions permission names of this table
 * @var list<string>            $locales
 * @var list<array<string, mixed>> $others   every table of the definition
 */

use Modules\Core\Generator\Definition\ColumnDefinition;
use Modules\Core\Generator\Definition\CrudDefinition;
use Modules\Core\Generator\Definition\MenuDefinition;
use Modules\Core\Generator\DemoValues;

$p        = 'tables[' . $t . ']';
$path     = 'tables.' . $t;
$crud     = $table['crud'];
$columns  = $table['columns'];
$invalid  = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';
$feedback = static fn (string $key): string => isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . esc($errors[$key]) . '</div>' : '';
$recipe   = static fn (string $id): string => '<a class="small text-nowrap position-relative" href="' . esc(backend_url('devtools/recipes') . '#recipe-' . $id, 'attr') . '"><i class="bi bi-journal-code" aria-hidden="true"></i> ' . esc(lang('Devtools.generator.recipe')) . '<span class="visually-hidden">: ' . esc(lang('Devtools.recipes.' . $id . '.title')) . '</span></a>';
$checked  = static fn (bool $on): string => $on ? ' checked' : '';
$in       = static fn (string $list, string $name): bool => in_array($name, (array) ($crud[$list] ?? []), true);
$names    = array_values(array_filter(array_map(static fn (array $c): string => (string) ($c['name'] ?? ''), $columns), static fn (string $n): bool => $n !== ''));
$fields   = ($crud['recordRule'] ?? 'everyone') === 'own' && ! in_array(CrudDefinition::OWNER_COLUMN, $names, true) ? [...$names, CrudDefinition::OWNER_COLUMN] : $names;
$lower    = strtolower((string) ($module['name'] ?? ''));
$key      = (string) preg_replace('/[^a-z0-9]/', '', (string) preg_replace('/^' . preg_quote($lower, '/') . '_/', '', (string) $table['name']));
$fkTables = array_values(array_unique([...array_map(static fn (array $o): string => (string) $o['name'], array_slice($others, 0, $t)), ...$tables]));
$parents  = ['main', ...array_map(static fn (array $o): string => (string) preg_replace('/[^a-z0-9]/', '', (string) preg_replace('/^' . preg_quote($lower, '/') . '_/', '', (string) $o['name'])), $others)];
$ops      = ['list', 'show', 'add', 'edit', 'delete', 'trash', 'export', 'import'];
$boolean  = static fn (string $name, bool $on, string $id, string $text, string $extra = ''): string => '<div class="form-check"><input type="hidden" name="' . esc($name, 'attr') . '" value="0"><input class="form-check-input" type="checkbox" id="' . esc($id, 'attr') . '" name="' . esc($name, 'attr') . '" value="1"' . ($on ? ' checked' : '') . $extra . '><label class="form-check-label" for="' . esc($id, 'attr') . '">' . esc($text) . '</label></div>';
?>
<section class="card mb-4 border-primary-subtle" id="table-<?= $t ?>" aria-labelledby="table-<?= $t ?>-title" data-generator-table="<?= $t ?>">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <h2 class="h5 mb-0 flex-grow-1" id="table-<?= $t ?>-title"><?= esc(lang('Devtools.generator.tableN', [$t + 1])) ?>: <code><?= esc($table['name'] !== '' ? $table['name'] : '…') ?></code></h2>
        <button type="submit" class="btn btn-sm btn-outline-danger" name="action" value="removeTable:<?= $t ?>" formnovalidate><i class="bi bi-trash3" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.removeTable')) ?></button>
    </div>
    <div class="card-body">

        <h3 class="h6 border-bottom pb-1" id="table-<?= $t ?>-step2">2. <?= esc(lang('Devtools.generator.step.table')) ?></h3>
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="t<?= $t ?>-name"><?= esc(lang('Devtools.generator.table.name')) ?></label>
                <input class="form-control font-monospace<?= $invalid($path . '.name') ?>" id="t<?= $t ?>-name" name="<?= $p ?>[name]" value="<?= esc($table['name'], 'attr') ?>" autocomplete="off" data-generator-table-name>
                <div class="form-text"><?= esc(lang('Devtools.generator.table.nameHelp', [$lower !== '' ? $lower . '_' : '<module>_'])) ?></div>
                <?= $feedback($path . '.name') ?>
            </div>
            <div class="col-12 col-md-8 d-flex flex-wrap gap-4 align-items-center pt-md-4">
                <?= $boolean($p . '[timestamps]', (bool) $table['timestamps'], 't' . $t . '-timestamps', lang('Devtools.generator.table.timestamps')) ?>
                <?= $boolean($p . '[softDeletes]', (bool) $table['softDeletes'], 't' . $t . '-soft', lang('Devtools.generator.table.softDeletes')) ?>
                <?= $recipe('trash') ?>
            </div>
        </div>

        <div class="table-responsive position-relative mb-2">
            <table class="table table-sm align-middle small" data-generator-columns>
                <caption class="visually-hidden"><?= esc(lang('Devtools.generator.columns.title')) ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?= esc(lang('Devtools.generator.columns.name')) ?></th>
                        <th scope="col"><?= esc(lang('Devtools.generator.columns.type')) ?></th>
                        <th scope="col"><?= esc(lang('Devtools.generator.columns.size')) ?></th>
                        <th scope="col"><?= esc(lang('Devtools.generator.columns.default')) ?></th>
                        <th scope="col"><?= esc(lang('Devtools.generator.columns.flags')) ?></th>
                        <th scope="col"><?= esc(lang('Devtools.generator.columns.foreign')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= esc(lang('Devtools.generator.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($columns as $c => $column): ?>
                        <?php $cp = $p . '[columns][' . $c . ']';
                        $cpath = $path . '.columns.' . $c;
                        $id = 't' . $t . 'c' . $c; ?>
                        <tr data-generator-column>
                            <td class="text-nowrap">
                                <input class="form-control form-control-sm font-monospace<?= $invalid($cpath . '.name') ?>" name="<?= $cp ?>[name]" value="<?= esc((string) ($column['name'] ?? ''), 'attr') ?>" aria-label="<?= esc(lang('Devtools.generator.columns.name'), 'attr') ?>" autocomplete="off">
                                <?= $feedback($cpath . '.name') ?>
                            </td>
                            <td>
                                <select class="form-select form-select-sm<?= $invalid($cpath . '.type') ?>" name="<?= $cp ?>[type]" aria-label="<?= esc(lang('Devtools.generator.columns.type'), 'attr') ?>" data-generator-type>
                                    <?php foreach (ColumnDefinition::TYPES as $type): ?>
                                        <option value="<?= $type ?>"<?= ($column['type'] ?? 'string') === $type ? ' selected' : '' ?>><?= esc(lang('Devtools.generator.types.' . $type)) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <input class="form-control form-control-sm mt-1<?= $invalid($cpath . '.options') ?>" name="<?= $cp ?>[options]" value="<?= esc(implode(',', (array) ($column['options'] ?? [])), 'attr') ?>" placeholder="<?= esc(lang('Devtools.generator.columns.options'), 'attr') ?>" aria-label="<?= esc(lang('Devtools.generator.columns.options'), 'attr') ?>" data-generator-for="enum">
                                <?= $feedback($cpath . '.type') ?><?= $feedback($cpath . '.options') ?>
                            </td>
                            <td class="text-nowrap">
                                <input class="form-control form-control-sm d-inline-block w-auto<?= $invalid($cpath . '.length') ?>" type="number" min="1" max="255" size="4" name="<?= $cp ?>[length]" value="<?= esc((string) ($column['length'] ?? ''), 'attr') ?>" placeholder="190" aria-label="<?= esc(lang('Devtools.generator.columns.length'), 'attr') ?>" data-generator-for="string enum">
                                <span data-generator-for="decimal">
                                    <input class="form-control form-control-sm d-inline-block w-auto<?= $invalid($cpath . '.precision') ?>" type="number" min="1" max="30" name="<?= $cp ?>[precision]" value="<?= esc((string) ($column['precision'] ?? 10), 'attr') ?>" aria-label="<?= esc(lang('Devtools.generator.columns.precision'), 'attr') ?>">
                                    <input class="form-control form-control-sm d-inline-block w-auto" type="number" min="0" max="30" name="<?= $cp ?>[scale]" value="<?= esc((string) ($column['scale'] ?? 2), 'attr') ?>" aria-label="<?= esc(lang('Devtools.generator.columns.scale'), 'attr') ?>">
                                </span>
                                <?= $feedback($cpath . '.length') ?><?= $feedback($cpath . '.precision') ?>
                            </td>
                            <td>
                                <input class="form-control form-control-sm<?= $invalid($cpath . '.default') ?>" name="<?= $cp ?>[default]" value="<?= esc((string) ($column['default'] ?? ''), 'attr') ?>" aria-label="<?= esc(lang('Devtools.generator.columns.default'), 'attr') ?>">
                                <?= $feedback($cpath . '.default') ?>
                            </td>
                            <td class="text-nowrap">
                                <?php foreach (['nullable', 'unique', 'index', 'translated'] as $flag): ?>
                                    <?= $boolean($cp . '[' . $flag . ']', (bool) ($column[$flag] ?? false), $id . '-' . $flag, lang('Devtools.generator.columns.' . $flag), $flag === 'nullable' ? ' data-generator-nullable' : '') ?>
                                <?php endforeach ?>
                                <?= $feedback($cpath . '.unique') ?><?= $feedback($cpath . '.translated') ?>
                            </td>
                            <td>
                                <input class="form-control form-control-sm font-monospace<?= $invalid($cpath . '.foreign.table') ?>" name="<?= $cp ?>[foreign][table]" value="<?= esc((string) ($column['foreign']['table'] ?? ''), 'attr') ?>" list="fk-tables-<?= $t ?>" placeholder="<?= esc(lang('Devtools.generator.columns.foreignTable'), 'attr') ?>" aria-label="<?= esc(lang('Devtools.generator.columns.foreignTable'), 'attr') ?>" autocomplete="off">
                                <select class="form-select form-select-sm mt-1<?= $invalid($cpath . '.foreign.onDelete') ?>" name="<?= $cp ?>[foreign][onDelete]" aria-label="<?= esc(lang('Devtools.generator.columns.onDelete'), 'attr') ?>" data-generator-ondelete>
                                    <?php foreach (ColumnDefinition::ON_DELETE as $rule): ?>
                                        <option value="<?= $rule ?>"<?= ($column['foreign']['onDelete'] ?? 'RESTRICT') === $rule ? ' selected' : '' ?>>ON DELETE <?= $rule ?></option>
                                    <?php endforeach ?>
                                </select>
                                <div class="invalid-feedback" data-generator-setnull-warning></div>
                                <?= $feedback($cpath . '.foreign.table') ?><?= $feedback($cpath . '.foreign.onDelete') ?>
                            </td>
                            <td>
                                <button type="submit" class="btn btn-sm btn-outline-danger" name="action" value="removeColumn:<?= $t ?>:<?= $c ?>" formnovalidate aria-label="<?= esc(lang('Devtools.generator.removeColumn', [(string) ($column['name'] ?? '')]), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <datalist id="fk-tables-<?= $t ?>">
            <?php foreach ($fkTables as $name): ?>
                <option value="<?= esc($name, 'attr') ?>"></option>
            <?php endforeach ?>
        </datalist>
        <?= $feedback($path . '.columns') ?>
        <p><button type="submit" class="btn btn-sm btn-outline-primary" name="action" value="addColumn:<?= $t ?>" formnovalidate><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.addColumn')) ?></button>
            <span class="small text-body-secondary ms-2"><?= esc(lang('Devtools.generator.columns.help')) ?></span> <?= $recipe('translations') ?></p>

        <details class="mb-4" <?= $table['name'] === '' ? '' : 'open' ?>>
            <summary class="fw-semibold small"><?= esc(lang('Devtools.generator.labels.title')) ?></summary>
            <div class="table-responsive position-relative">
                <table class="table table-sm small align-middle mt-2">
                    <thead><tr><th scope="col"><?= esc(lang('Devtools.generator.labels.text')) ?></th><?php foreach ($locales as $locale): ?><th scope="col" class="text-uppercase"><?= $locale ?></th><?php endforeach ?></tr></thead>
                    <tbody>
                        <?php foreach (['singular', 'plural'] as $which): ?>
                            <tr>
                                <th scope="row" class="fw-normal"><?= esc(lang('Devtools.generator.labels.' . $which)) ?></th>
                                <?php foreach ($locales as $locale): ?>
                                    <td><input class="form-control form-control-sm" name="<?= $p ?>[labels][<?= $which ?>][<?= $locale ?>]" value="<?= esc((string) ($table['labels'][$which][$locale] ?? ''), 'attr') ?>" aria-label="<?= esc(lang('Devtools.generator.labels.' . $which) . ' ' . $locale, 'attr') ?>"></td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                        <?php foreach ($columns as $c => $column): ?>
                            <tr>
                                <th scope="row" class="fw-normal font-monospace"><?= esc((string) ($column['name'] ?? '')) ?></th>
                                <?php foreach ($locales as $locale): ?>
                                    <td><input class="form-control form-control-sm" name="<?= $p ?>[columns][<?= $c ?>][label][<?= $locale ?>]" value="<?= esc((string) ($column['label'][$locale] ?? ''), 'attr') ?>" aria-label="<?= esc((string) ($column['name'] ?? '') . ' ' . $locale, 'attr') ?>"></td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </details>

        <h3 class="h6 border-bottom pb-1" id="table-<?= $t ?>-step3">3. <?= esc(lang('Devtools.generator.step.demo')) ?></h3>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-3">
                <label class="form-label" for="t<?= $t ?>-rows"><?= esc(lang('Devtools.generator.demo.rows')) ?></label>
                <input class="form-control" type="number" min="0" max="200" id="t<?= $t ?>-rows" name="<?= $p ?>[demoRows]" value="<?= esc((string) $table['demoRows'], 'attr') ?>">
                <div class="form-text"><?= esc(lang('Devtools.generator.demo.help')) ?></div>
            </div>
            <div class="col-12 col-md-9">
                <div class="row g-2">
                    <?php foreach ($columns as $c => $column): ?>
                        <?php $default = DemoValues::defaultFor(ColumnDefinition::fromArray(['faker' => null] + $column)); ?>
                        <div class="col-6 col-lg-4">
                            <label class="form-label small font-monospace" for="t<?= $t ?>c<?= $c ?>-faker"><?= esc((string) ($column['name'] ?? '')) ?></label>
                            <select class="form-select form-select-sm<?= $invalid($path . '.columns.' . $c . '.faker') ?>" id="t<?= $t ?>c<?= $c ?>-faker" name="<?= $p ?>[columns][<?= $c ?>][faker]">
                                <option value=""><?= esc(lang('Devtools.generator.demo.auto', [$default])) ?></option>
                                <?php foreach (DemoValues::GENERATORS as $generator): ?>
                                    <option value="<?= $generator ?>"<?= ($column['faker'] ?? '') === $generator ? ' selected' : '' ?>><?= $generator ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <h3 class="h6 border-bottom pb-1 d-flex flex-wrap gap-2" id="table-<?= $t ?>-step4"><span class="flex-grow-1">4. <?= esc(lang('Devtools.generator.step.crud')) ?></span> <?= $recipe('plain') ?></h3>
        <div class="d-flex flex-wrap gap-4 mb-2">
            <?= $boolean($p . '[crud][enabled]', (bool) ($crud['enabled'] ?? true), 't' . $t . '-crud', lang('Devtools.generator.crud.enabled')) ?>
            <?= $boolean($p . '[crud][readOnly]', (bool) ($crud['readOnly'] ?? false), 't' . $t . '-readonly', lang('Devtools.generator.crud.readOnly')) ?>
            <?= $recipe('readonly') ?>
            <?= $boolean($p . '[crud][maskIds]', (bool) ($crud['maskIds'] ?? true), 't' . $t . '-mask', lang('Devtools.generator.crud.maskIds')) ?>
            <?= $recipe('maskedIds') ?>
        </div>
        <fieldset class="mb-3">
            <legend class="form-label fs-6 small"><?= esc(lang('Devtools.generator.crud.operations')) ?> <?= $recipe('export') ?></legend>
            <div class="d-flex flex-wrap gap-3">
                <input type="hidden" name="<?= $p ?>[crud][operations][]" value="list">
                <?php foreach ($ops as $op): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="t<?= $t ?>-op-<?= $op ?>" name="<?= $p ?>[crud][operations][]" value="<?= $op ?>"<?= $checked($in('operations', $op)) ?><?= $op === 'list' ? ' disabled' : '' ?>>
                        <label class="form-check-label" for="t<?= $t ?>-op-<?= $op ?>"><?= esc(lang('Devtools.generator.ops.' . $op)) ?></label>
                    </div>
                <?php endforeach ?>
            </div>
            <?= $feedback($path . '.crud.operations') ?>
        </fieldset>

        <div class="table-responsive position-relative">
            <table class="table table-sm small align-middle text-center">
                <caption class="text-start"><?= esc(lang('Devtools.generator.crud.matrixHelp')) ?> <?= $recipe('hiddenFields') ?> <?= $recipe('uploads') ?> <?= $recipe('richText') ?></caption>
                <thead>
                    <tr>
                        <th scope="col" class="text-start"><?= esc(lang('Devtools.generator.columns.name')) ?></th>
                        <?php foreach (['columns', 'fields', 'required', 'search', 'filters', 'uploads', 'richText'] as $list): ?>
                            <th scope="col"><?= esc(lang('Devtools.generator.crud.' . $list)) ?></th>
                        <?php endforeach ?>
                        <th scope="col"><?= esc(lang('Devtools.generator.crud.hidden')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $field): ?>
                        <tr>
                            <th scope="row" class="text-start fw-normal font-monospace"><?= esc($field) ?></th>
                            <?php foreach (['columns', 'fields', 'required', 'search', 'filters', 'uploads', 'richText'] as $list): ?>
                                <td><input class="form-check-input" type="checkbox" name="<?= $p ?>[crud][<?= $list ?>][]" value="<?= esc($field, 'attr') ?>"<?= $checked($in($list, $field)) ?> aria-label="<?= esc($field . ': ' . lang('Devtools.generator.crud.' . $list), 'attr') ?>"></td>
                            <?php endforeach ?>
                            <td><input class="form-control form-control-sm font-monospace" name="<?= $p ?>[crud][hidden][<?= esc($field, 'attr') ?>]" value="<?= esc((string) ($crud['hidden'][$field] ?? ''), 'attr') ?>" placeholder="<?= esc(lang('Devtools.generator.crud.hiddenPlaceholder'), 'attr') ?>" aria-label="<?= esc($field . ': ' . lang('Devtools.generator.crud.hidden'), 'attr') ?>" size="10"></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php foreach (['columns', 'fields', 'required', 'search', 'filters', 'uploads', 'richText', 'hidden'] as $list): ?>
            <?= $feedback($path . '.crud.' . $list) ?>
        <?php endforeach ?>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="t<?= $t ?>-rule"><?= esc(lang('Devtools.generator.crud.recordRule')) ?></label>
                <select class="form-select<?= $invalid($path . '.crud.recordRule') ?>" id="t<?= $t ?>-rule" name="<?= $p ?>[crud][recordRule]">
                    <?php foreach (CrudDefinition::RECORD_RULES as $rule): ?>
                        <option value="<?= $rule ?>"<?= ($crud['recordRule'] ?? 'everyone') === $rule ? ' selected' : '' ?>><?= esc(lang('Devtools.generator.crud.rules.' . $rule)) ?></option>
                    <?php endforeach ?>
                </select>
                <div class="form-text"><?= esc(lang('Devtools.generator.crud.recordRuleHelp')) ?> <?= $recipe('ownRows') ?></div>
                <?= $feedback($path . '.crud.recordRule') ?>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="t<?= $t ?>-rule-column"><?= esc(lang('Devtools.generator.crud.ruleColumn')) ?></label>
                <select class="form-select<?= $invalid($path . '.crud.ruleColumn') ?>" id="t<?= $t ?>-rule-column" name="<?= $p ?>[crud][ruleColumn]">
                    <option value=""></option>
                    <?php foreach ($names as $name): ?>
                        <option value="<?= esc($name, 'attr') ?>"<?= ($crud['ruleColumn'] ?? '') === $name ? ' selected' : '' ?>><?= esc($name) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $feedback($path . '.crud.ruleColumn') ?>
            </div>
        </div>

        <fieldset class="mb-4">
            <legend class="form-label fs-6"><?= esc(lang('Devtools.generator.crud.customOperations')) ?> <?= $recipe('operation') ?> <?= $recipe('reauth') ?></legend>
            <?php foreach ((array) ($crud['customOperations'] ?? []) as $o => $operation): ?>
                <?php $op = $p . '[crud][customOperations][' . $o . ']';
                $opath = $path . '.crud.customOperations.' . $o; ?>
                <div class="border rounded p-2 mb-2" data-generator-operation>
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="t<?= $t ?>o<?= $o ?>-key"><?= esc(lang('Devtools.generator.crud.operationKey')) ?></label>
                            <input class="form-control form-control-sm font-monospace<?= $invalid($opath . '.key') ?>" id="t<?= $t ?>o<?= $o ?>-key" name="<?= $op ?>[key]" value="<?= esc((string) $operation['key'], 'attr') ?>" autocomplete="off">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="t<?= $t ?>o<?= $o ?>-icon"><?= esc(lang('Devtools.generator.icon')) ?></label>
                            <input class="form-control form-control-sm font-monospace" id="t<?= $t ?>o<?= $o ?>-icon" name="<?= $op ?>[icon]" value="<?= esc((string) $operation['icon'], 'attr') ?>">
                        </div>
                        <div class="col-8 col-md-4">
                            <?= $boolean($op . '[reauth]', (bool) $operation['reauth'], 't' . $t . 'o' . $o . '-reauth', lang('Devtools.generator.crud.reauth')) ?>
                        </div>
                        <div class="col-4 col-md-2 text-end">
                            <button type="submit" class="btn btn-sm btn-outline-danger" name="action" value="removeOperation:<?= $t ?>:<?= $o ?>" formnovalidate aria-label="<?= esc(lang('Devtools.generator.removeOperation'), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                        </div>
                        <?php foreach (['label', 'confirm'] as $text): ?>
                            <?php foreach ($locales as $locale): ?>
                                <div class="col-12 col-md-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><?= esc(lang('Devtools.generator.crud.operation' . ucfirst($text))) ?> <span class="text-uppercase ms-1"><?= $locale ?></span></span>
                                        <input class="form-control" name="<?= $op ?>[<?= $text ?>][<?= $locale ?>]" value="<?= esc((string) ($operation[$text][$locale] ?? ''), 'attr') ?>" aria-label="<?= esc(lang('Devtools.generator.crud.operation' . ucfirst($text)) . ' ' . $locale, 'attr') ?>">
                                    </div>
                                </div>
                            <?php endforeach ?>
                        <?php endforeach ?>
                    </div>
                    <?= $feedback($opath . '.key') ?>
                </div>
            <?php endforeach ?>
            <button type="submit" class="btn btn-sm btn-outline-primary" name="action" value="addOperation:<?= $t ?>" formnovalidate><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.addOperation')) ?></button>
        </fieldset>

        <h3 class="h6 border-bottom pb-1 d-flex flex-wrap gap-2" id="table-<?= $t ?>-step5"><span class="flex-grow-1">5. <?= esc(lang('Devtools.generator.step.api')) ?></span> <?= $recipe('api') ?></h3>
        <div class="d-flex flex-wrap gap-4 mb-1">
            <?= $boolean($p . '[api][enabled]', (bool) ($table['api']['enabled'] ?? false), 't' . $t . '-api', lang('Devtools.generator.api.enabled', [$lower . '/' . $key])) ?>
            <?= $boolean($p . '[api][public]', (bool) ($table['api']['public'] ?? false), 't' . $t . '-public', lang('Devtools.generator.api.public')) ?>
        </div>
        <p class="small text-body-secondary mb-4"><?= esc(lang('Devtools.generator.api.help')) ?></p>
        <?= $feedback($path . '.api.enabled') ?>

        <h3 class="h6 border-bottom pb-1 d-flex flex-wrap gap-2" id="table-<?= $t ?>-step6"><span class="flex-grow-1">6. <?= esc(lang('Devtools.generator.step.menu')) ?></span> <?= $recipe('menu') ?></h3>
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-2 pt-md-4">
                <?= $boolean($p . '[menu][enabled]', (bool) ($table['menu']['enabled'] ?? true), 't' . $t . '-menu', lang('Devtools.generator.menu.enabled')) ?>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="t<?= $t ?>-zone"><?= esc(lang('Devtools.generator.menu.zone')) ?></label>
                <select class="form-select<?= $invalid($path . '.menu.zone') ?>" id="t<?= $t ?>-zone" name="<?= $p ?>[menu][zone]">
                    <?php foreach (MenuDefinition::ZONES as $zone): ?>
                        <option value="<?= $zone ?>"<?= ($table['menu']['zone'] ?? 'sidebar') === $zone ? ' selected' : '' ?>><?= esc(lang('Devtools.generator.menu.zones.' . $zone)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="t<?= $t ?>-parent"><?= esc(lang('Devtools.generator.menu.parent')) ?></label>
                <select class="form-select<?= $invalid($path . '.menu.parent') ?>" id="t<?= $t ?>-parent" name="<?= $p ?>[menu][parent]">
                    <option value=""><?= esc(lang('Devtools.generator.menu.root')) ?></option>
                    <?php foreach (array_values(array_unique([...$parents, ...MenuDefinition::SETTINGS_GROUPS])) as $parent): ?>
                        <?php if ($parent !== '' && $parent !== $key): ?>
                            <option value="<?= esc($parent, 'attr') ?>"<?= ($table['menu']['parent'] ?? '') === $parent ? ' selected' : '' ?>><?= esc($parent) ?></option>
                        <?php endif ?>
                    <?php endforeach ?>
                </select>
                <?= $feedback($path . '.menu.parent') ?>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="t<?= $t ?>-icon"><?= esc(lang('Devtools.generator.icon')) ?> <i class="<?= esc((string) ($table['menu']['icon'] ?? ''), 'attr') ?>" aria-hidden="true"></i></label>
                <input class="form-control font-monospace<?= $invalid($path . '.menu.icon') ?>" id="t<?= $t ?>-icon" name="<?= $p ?>[menu][icon]" value="<?= esc((string) ($table['menu']['icon'] ?? 'bi bi-table'), 'attr') ?>">
                <?= $feedback($path . '.menu.icon') ?>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="t<?= $t ?>-order"><?= esc(lang('Devtools.generator.menu.order')) ?></label>
                <input class="form-control" type="number" id="t<?= $t ?>-order" name="<?= $p ?>[menu][order]" value="<?= esc((string) ($table['menu']['order'] ?? 10), 'attr') ?>">
            </div>
        </div>

        <div class="table-responsive position-relative">
            <input type="hidden" name="<?= $p ?>[grants][]" value="">
            <table class="table table-sm small align-middle text-center" data-generator-grants>
                <caption class="text-start"><?= esc(lang('Devtools.generator.grants.help')) ?></caption>
                <thead>
                    <tr>
                        <th scope="col" class="text-start"><?= esc(lang('Devtools.generator.grants.group')) ?></th>
                        <th scope="col"><?= esc(lang('Devtools.generator.grants.all')) ?></th>
                        <?php foreach ($permissions as $permission): ?>
                            <th scope="col" class="font-monospace"><?= esc(substr($permission, (int) strrpos($permission, '.') + 1)) ?></th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                        <?php if ($group === 'superadmin') {
                            continue;
                        } ?>
                        <?php $granted = (array) ($table['grants'][$group] ?? []); ?>
                        <tr>
                            <th scope="row" class="text-start fw-normal"><?= esc($group) ?></th>
                            <td><input class="form-check-input" type="checkbox" name="<?= $p ?>[grants][<?= esc($group, 'attr') ?>][]" value="*"<?= $checked(in_array('*', $granted, true)) ?> aria-label="<?= esc($group . ': *', 'attr') ?>"></td>
                            <?php foreach ($permissions as $permission): ?>
                                <?php $operation = substr($permission, (int) strrpos($permission, '.') + 1); ?>
                                <td><input class="form-check-input" type="checkbox" name="<?= $p ?>[grants][<?= esc($group, 'attr') ?>][]" value="<?= esc($operation, 'attr') ?>"<?= $checked(in_array($operation, $granted, true)) ?> aria-label="<?= esc($group . ': ' . $permission, 'attr') ?>"></td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php foreach ($groups as $group): ?><?= $feedback($path . '.grants.' . $group) ?><?php endforeach ?>
        <p class="small mb-0"><?= esc(lang('Devtools.generator.grants.names')) ?> <?php foreach ($permissions as $permission): ?><code class="me-2" data-generator-permission><?= esc($permission) ?></code><?php endforeach ?></p>
    </div>
</section>
