<?php

use Phinx\Migration\AbstractMigration;

class CreateCreditScoresTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('member_credit_scores', ['id' => false, 'primary_key' => ['user_id']]);
        $table->addColumn('user_id', 'integer')
              ->addColumn('score', 'integer', ['default' => 500])
              ->addColumn('last_updated', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addForeignKey('user_id', 'users', 'id', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])
              ->create();
    }
}
