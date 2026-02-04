<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddExpensesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('expenses');
        $table->addColumn('amount', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
              ->addColumn('category', 'string', ['limit' => 100, 'null' => false])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('date_incurred', 'date', ['null' => false])
              ->addColumn('admin_id', 'integer', ['null' => false])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addIndex(['date_incurred'])
              ->create();
    }

    public function down(): void
    {
        $this->table('expenses')->drop()->save();
    }
}
