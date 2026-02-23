<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddRolesTable extends AbstractMigration
{
    public function change(): void
    {
        // Create roles table
        $table = $this->table('roles');
        $table->addColumn('role_name', 'string', ['limit' => 50])
              ->create();

        // Seed initial roles
        $rows = [
            ['id' => 1, 'role_name' => 'Root'],
            ['id' => 2, 'role_name' => 'Chairman'],
            ['id' => 3, 'role_name' => 'Secretary'],
            ['id' => 4, 'role_name' => 'Treasurer'],
            ['id' => 5, 'role_name' => 'Member']
        ];

        $this->table('roles')->insert($rows)->saveData();
    }
}
