<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSavingsGoalsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('savings_goals');
        $table->addColumn('user_id', 'integer', ['null' => false])
              ->addColumn('goal_name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('target_amount', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
              ->addColumn('deadline', 'date', ['null' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addIndex(['user_id'])
              ->create();
    }

    public function down(): void
    {
        $this->table('savings_goals')->drop()->save();
    }
}
