<?php

use Phinx\Migration\AbstractMigration;

class FixLoanGuarantorsStatusColumn extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('loan_guarantors');
        $table->changeColumn('status', 'string', ['limit' => 50, 'default' => 'pending', 'null' => false])
              ->update();
    }

    public function down()
    {
        $table = $this->table('loan_guarantors');
        $table->changeColumn('status', 'enum', ['values' => ['pending', 'approved', 'rejected'], 'default' => 'pending', 'null' => false])
              ->update();
    }
}
