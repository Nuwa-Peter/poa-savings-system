<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWelfareTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('welfare_contributions');
        $table->addColumn('user_id', 'integer', ['null' => false])
              ->addColumn('amount', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
              ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addIndex(['user_id'])
              ->create();
    }

    public function down(): void
    {
        $this->table('welfare_contributions')->drop()->save();
    }
}
