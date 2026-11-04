<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Core\Models\BaseModel;

/**
 * BaseModel: timestamps, soft deletes and author columns that cannot be forged.
 *
 * @internal
 */
final class BaseModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        $forge = \Config\Database::forge();
        $forge->dropTable('base_model_items', true);
        $forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('base_model_items');
    }

    protected function tearDown(): void
    {
        \Config\Database::forge()->dropTable('base_model_items', true);
        parent::tearDown();
    }

    private function model(): BaseModel
    {
        return new class () extends BaseModel {
            protected $table         = 'base_model_items';
            protected $allowedFields = ['name', 'created_by', 'updated_by'];
        };
    }

    public function testAuthorColumnsCannotBeForgedAndTimestampsAreSet(): void
    {
        $model = $this->model();
        $id    = $model->insert(['name' => 'one', 'created_by' => 99, 'updated_by' => 99]);
        $row   = $model->find($id);

        $this->assertIsArray($row);
        $this->assertNull($row['created_by'], 'no logged-in user: the forged value is replaced');
        $this->assertNull($row['updated_by']);
        $this->assertNotNull($row['created_at']);

        $this->db->table('base_model_items')->where('id', $id)->update(['created_by' => 5]);
        $model->update($id, ['name' => 'two', 'created_by' => 99, 'updated_by' => 99]);
        $row = $model->find($id);

        $this->assertIsArray($row);
        $this->assertSame(5, (int) $row['created_by'], 'created_by never changes on update');
        $this->assertNull($row['updated_by']);
    }

    public function testDeleteIsSoft(): void
    {
        $model = $this->model();
        $id    = $model->insert(['name' => 'one']);
        $model->delete($id);

        $this->assertNull($model->find($id));
        $this->assertNotNull($model->withDeleted()->find($id));
    }
}
