<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddInvestmentsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('investments');
        $table->addColumn('investment_name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('amount_invested', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
              ->addColumn('current_value', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
              ->addColumn('investment_date', 'date', ['null' => false])
              ->addColumn('expected_return_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
              ->addColumn('status', 'string', ['limit' => 50, 'default' => 'active', 'null' => false])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addIndex(['status'])
              ->create();
    }

    public function down(): void
    {
        $this->table('investments')->drop()->save();
    }
}
