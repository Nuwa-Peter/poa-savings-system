<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLoanCollateralTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('loan_collateral');
        $table->addColumn('loan_id', 'integer', ['null' => false])
              ->addColumn('description', 'text', ['null' => false])
              ->addColumn('estimated_value', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
              ->addColumn('document_path', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('is_verified', 'boolean', ['default' => false, 'null' => false])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addIndex(['loan_id'])
              ->create();
    }

    public function down(): void
    {
        $this->table('loan_collateral')->drop()->save();
    }
}
