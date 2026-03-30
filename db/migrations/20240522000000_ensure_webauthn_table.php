<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EnsureWebauthnTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up(): void
    {
        if (!$this->hasTable('webauthn_credentials')) {
            $table = $this->table('webauthn_credentials');
            $table->addColumn('user_id', 'integer', ['null' => false])
                  ->addColumn('credential_id', 'string', ['limit' => 255, 'null' => false])
                  ->addColumn('public_key', 'text', ['null' => false])
                  ->addColumn('attestation_object', 'text', ['null' => true])
                  ->addColumn('user_agent', 'string', ['limit' => 255, 'null' => true])
                  ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
                  ->addIndex(['credential_id'], ['unique' => true])
                  ->addIndex(['user_id'])
                  ->create();
        }
    }

    public function down(): void
    {
        if ($this->hasTable('webauthn_credentials')) {
            $this->table('webauthn_credentials')->drop()->save();
        }
    }
}
