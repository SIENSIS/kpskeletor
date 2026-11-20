<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\DefinitionValidator;

/**
 * The definition's validation (the wizard's errors and `make:module --definition`).
 *
 * @internal
 */
final class GeneratorValidatorTest extends CIUnitTestCase
{
    use GeneratorTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
    }

    protected function tearDown(): void
    {
        $this->removeTempRoot();
        parent::tearDown();
    }

    public function testTheFixturesAreValid(): void
    {
        foreach (['library', 'notes'] as $case) {
            $this->assertSame([], $this->errors($this->definition($case)->toArray()), $case);
        }
    }

    public function testModuleNames(): void
    {
        $this->assertArrayHasKey('module.name', $this->errors(['module' => ['name' => 'lower']]));
        $this->assertArrayHasKey('module.name', $this->errors(['module' => ['name' => 'Security']]));
        $this->assertArrayHasKey('module.name', $this->errors(['module' => ['name' => 'Ghost', 'existing' => true], 'tables' => [$this->table()]]));
        $this->assertArrayHasKey('module.requires', $this->errors(['module' => ['name' => 'Shop', 'requires' => ['Nope' => '^1.0']]]));
        $this->assertArrayHasKey('module.requires', $this->errors(['module' => ['name' => 'Shop', 'requires' => ['Auth' => 'latest']]]));
        $this->assertSame([], $this->errors(['module' => ['name' => 'Shop', 'requires' => ['Auth' => '^0.2']]]));
    }

    public function testTableNames(): void
    {
        $this->assertArrayHasKey('tables.0.name', $this->errors($this->with(['name' => 'Shop Items'])));
        $this->assertArrayHasKey('tables.0.name', $this->errors($this->with(['name' => 'items'])), 'The module prefix is required.');
        $this->assertArrayHasKey('tables.0.name', $this->errors($this->with(['name' => 'shop_main'])), 'main is the module page.');
        $this->assertArrayHasKey('tables.1.name', $this->errors(['module' => ['name' => 'Shop'], 'tables' => [$this->table(), $this->table()]]));
        $this->assertArrayHasKey('tables.0.name', $this->errors($this->with(['name' => 'users'])));
        $this->assertArrayHasKey('tables.0.columns', $this->errors($this->with(['columns' => []])));
    }

    public function testColumns(): void
    {
        $column = static fn (array $values): array => ['columns' => [$values + ['name' => 'title', 'type' => 'string']]];

        $this->assertArrayHasKey('tables.0.columns.0.name', $this->errors($this->with($column(['name' => 'Title']))));
        $this->assertArrayHasKey('tables.0.columns.0.name', $this->errors($this->with($column(['name' => 'created_at']))));
        $this->assertArrayHasKey('tables.0.columns.0.type', $this->errors($this->with($column(['type' => 'money']))));
        $this->assertArrayHasKey('tables.0.columns.0.length', $this->errors($this->with($column(['length' => 300]))));
        $this->assertArrayHasKey('tables.0.columns.0.length', $this->errors($this->with($column(['length' => 250, 'unique' => true]))), 'Index limit of MariaDB.');
        $this->assertArrayHasKey('tables.0.columns.0.unique', $this->errors($this->with($column(['type' => 'text', 'index' => true]))));
        $this->assertArrayHasKey('tables.0.columns.0.precision', $this->errors($this->with($column(['type' => 'decimal', 'precision' => 4, 'scale' => 6]))));
        $this->assertArrayHasKey('tables.0.columns.0.options', $this->errors($this->with($column(['type' => 'enum']))));
        $this->assertArrayHasKey('tables.0.columns.0.options', $this->errors($this->with($column(['type' => 'enum', 'options' => ['ok', 'not ok']]))));
        $this->assertArrayHasKey('tables.0.columns.0.default', $this->errors($this->with($column(['type' => 'enum', 'options' => ['a', 'b'], 'default' => 'c']))));
        $this->assertArrayHasKey('tables.0.columns.0.default', $this->errors($this->with($column(['type' => 'date', 'default' => '2026-02-30']))));
        $this->assertArrayHasKey('tables.0.columns.0.default', $this->errors($this->with($column(['type' => 'text', 'default' => 'x']))));
        $this->assertArrayHasKey('tables.0.columns.0.default', $this->errors($this->with($column(['type' => 'boolean', 'default' => '2']))));
        $this->assertArrayHasKey('tables.0.columns.0.translated', $this->errors($this->with($column(['type' => 'integer', 'translated' => true]))));
        $this->assertArrayHasKey('tables.0.columns.0.faker', $this->errors($this->with($column(['faker' => 'lottery']))));
        $this->assertSame([], $this->errors($this->with($column(['type' => 'date', 'default' => '2026-02-28']))));
    }

    public function testForeignKeys(): void
    {
        $fk = static fn (array $values, array $column = []): array => ['columns' => [['name' => 'title', 'type' => 'string'], $column + ['name' => 'owner_id', 'type' => 'integer', 'nullable' => true, 'foreign' => $values + ['table' => 'users', 'onDelete' => 'SET NULL']]]];

        $this->assertSame([], $this->errors($this->with($fk([]))));
        $this->assertArrayHasKey('tables.0.columns.1.foreign.onDelete', $this->errors($this->with($fk([], ['nullable' => false]))), 'SET NULL on a NOT NULL column.');
        $this->assertArrayHasKey('tables.0.columns.1.foreign.table', $this->errors($this->with($fk(['table' => 'nowhere']))));
        $this->assertArrayHasKey('tables.0.columns.1.foreign.table', $this->errors($this->with($fk([], ['type' => 'string']))));
        $this->assertArrayHasKey('tables.0.columns.1.foreign.onDelete', $this->errors($this->with($fk(['onDelete' => 'EXPLODE']))));

        // A table of the definition, defined before.
        $two = ['module' => ['name' => 'Shop'], 'tables' => [
            $this->table(['name' => 'shop_categories']),
            $this->table(['name' => 'shop_items', 'columns' => [['name' => 'title', 'type' => 'string'], ['name' => 'category_id', 'type' => 'integer', 'foreign' => ['table' => 'shop_categories', 'onDelete' => 'CASCADE']]]]),
        ]];
        $this->assertSame([], $this->errors($two));
        $two['tables'] = array_reverse($two['tables']);
        $this->assertArrayHasKey('tables.0.columns.1.foreign.table', $this->errors($two));
    }

    public function testCrudOptions(): void
    {
        $crud = fn (array $values, array $table = []): array => $this->with(['crud' => $values] + $table);

        $this->assertArrayHasKey('tables.0.crud.columns', $this->errors($crud(['columns' => ['nope']])));
        $this->assertArrayHasKey('tables.0.crud.required', $this->errors($crud(['fields' => ['title'], 'required' => ['body']], ['columns' => [['name' => 'title', 'type' => 'string'], ['name' => 'body', 'type' => 'text']]])));
        $this->assertArrayHasKey('tables.0.crud.uploads', $this->errors($crud(['uploads' => ['body']], ['columns' => [['name' => 'body', 'type' => 'text']]])));
        $this->assertArrayHasKey('tables.0.crud.richText', $this->errors($crud(['richText' => ['title']])));
        $this->assertArrayHasKey('tables.0.crud.operations', $this->errors($crud(['operations' => ['list', 'trash']])), 'The trash needs soft deletes.');
        $this->assertArrayHasKey('tables.0.crud.customOperations.0.key', $this->errors($crud(['customOperations' => [['key' => 'delete']]])));
        $this->assertArrayHasKey('tables.0.crud.customOperations.1.key', $this->errors($crud(['customOperations' => [['key' => 'go'], ['key' => 'go']]])));
        $this->assertArrayHasKey('tables.0.crud.hidden', $this->errors($crud(['hidden' => ['title' => 'Edit']])));
        $this->assertArrayHasKey('tables.0.crud.recordRule', $this->errors($crud(['recordRule' => 'friends'])));
        $this->assertArrayHasKey('tables.0.crud.ruleColumn', $this->errors($crud(['recordRule' => 'column', 'ruleColumn' => 'title'])));
        $this->assertSame([], $this->errors($crud(['recordRule' => 'own', 'customOperations' => [['key' => 'approve', 'reauth' => true]], 'hidden' => ['title' => 'secret']])));
    }

    public function testMenuGrantsAndApi(): void
    {
        $this->assertArrayHasKey('tables.0.menu.parent', $this->errors($this->with(['menu' => ['parent' => 'nowhere']])));
        $this->assertArrayHasKey('tables.0.menu.parent', $this->errors($this->with(['menu' => ['zone' => 'settings', 'parent' => 'main']])), 'D-38-01');
        $this->assertArrayHasKey('tables.0.menu.icon', $this->errors($this->with(['menu' => ['icon' => 'fa fa-book']])));
        $this->assertArrayHasKey('tables.0.grants.wizards', $this->errors($this->with(['grants' => ['wizards' => ['*']]])));
        $this->assertArrayHasKey('tables.0.grants.user', $this->errors($this->with(['grants' => ['user' => ['import'], 'admin' => ['*']], 'crud' => ['readOnly' => true]])));
        $this->assertArrayHasKey('tables.0.api.enabled', $this->errors($this->with(['crud' => ['enabled' => false], 'api' => ['enabled' => true]])));
        $this->assertSame([], $this->errors($this->with(['crud' => ['enabled' => false], 'api' => ['enabled' => true, 'public' => true]])));
        $this->assertSame([], $this->errors($this->with(['menu' => ['zone' => 'settings', 'parent' => 'group.system']])));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function table(array $values = []): array
    {
        return $values + ['name' => 'shop_items', 'columns' => [['name' => 'title', 'type' => 'string']]];
    }

    /**
     * A definition of the module Shop with one table.
     *
     * @param array<string, mixed> $table
     *
     * @return array<string, mixed>
     */
    private function with(array $table): array
    {
        return ['module' => ['name' => 'Shop'], 'tables' => [$this->table($table)]];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function errors(array $data): array
    {
        return (new DefinitionValidator($this->context()))->validate(ModuleDefinition::fromArray($data));
    }
}
