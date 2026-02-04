<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddMultiTierApprovalToLoans extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('loans');
        $table->addColumn('secretary_approval', 'boolean', ['default' => false, 'null' => false])
              ->addColumn('secretary_id', 'integer', ['null' => true])
              ->addColumn('chairman_approval', 'boolean', ['default' => false, 'null' => false])
              ->addColumn('chairman_id', 'integer', ['null' => true])
              ->update();
    }

    public function down(): void
    {
        $table = $this->table('loans');
        $table->removeColumn('secretary_approval')
              ->removeColumn('secretary_id')
              ->removeColumn('chairman_approval')
              ->removeColumn('chairman_id')
              ->update();
    }
}
