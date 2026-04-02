<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNewFinancialModules extends AbstractMigration
{
    public function change(): void
    {
        // 1. Share Capital Table
        $table = $this->table('share_capital');
        $table->addColumn('user_id', 'integer')
              ->addColumn('amount', 'decimal', ['precision' => 15, 'scale' => 2])
              ->addColumn('description', 'string', ['null' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['user_id'])
              ->create();

        // 2. Fixed Deposits Table
        $table = $this->table('fixed_deposits');
        $table->addColumn('user_id', 'integer')
              ->addColumn('amount', 'decimal', ['precision' => 15, 'scale' => 2])
              ->addColumn('interest_rate', 'decimal', ['precision' => 5, 'scale' => 2])
              ->addColumn('start_date', 'date')
              ->addColumn('end_date', 'date')
              ->addColumn('status', 'enum', ['values' => ['active', 'matured', 'withdrawn'], 'default' => 'active'])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['user_id'])
              ->create();

        // 3. Subscriptions (Config)
        $table = $this->table('subscriptions');
        $table->addColumn('name', 'string')
              ->addColumn('amount', 'decimal', ['precision' => 15, 'scale' => 2])
              ->addColumn('is_mandatory', 'boolean', ['default' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->create();

        // 4. Subscription Payments
        $table = $this->table('subscription_payments');
        $table->addColumn('user_id', 'integer')
              ->addColumn('subscription_id', 'integer')
              ->addColumn('amount_paid', 'decimal', ['precision' => 15, 'scale' => 2])
              ->addColumn('month', 'integer')
              ->addColumn('year', 'integer')
              ->addColumn('paid_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['user_id', 'subscription_id', 'month', 'year'], ['unique' => true])
              ->create();

        // 5. Member Credit Scores
        $table = $this->table('member_credit_scores');
        $table->addColumn('user_id', 'integer')
              ->addColumn('score', 'integer', ['default' => 500])
              ->addColumn('last_updated', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['user_id'], ['unique' => true])
              ->create();
    }
}
