<?php

use Phinx\Migration\AbstractMigration;

class AddDescriptionToSavings extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('savings');
        $table->addColumn('description', 'string', ['limit' => 255, 'null' => true, 'after' => 'amount'])
              ->update();
    }
}
