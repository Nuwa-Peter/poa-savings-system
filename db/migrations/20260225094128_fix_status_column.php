<?php

use Phinx\Migration\AbstractMigration;

class FixStatusColumn extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('users');
        $table->changeColumn('status', 'string', ['limit' => 50, 'default' => 'active', 'null' => false])
              ->update();
    }

    public function down()
    {
        $table = $this->table('users');
        $table->changeColumn('status', 'enum', ['values' => ['active', 'deleted'], 'default' => 'active', 'null' => false])
              ->update();
    }
}
