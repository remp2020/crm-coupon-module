<?php

use Phinx\Migration\AbstractMigration;

class CouponsInitialMigration extends AbstractMigration
{
    public function change()
    {
        $this->table('coupon_codes')
            ->addColumn('code', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex('code', ['unique' => true])
            ->create();

        if ($this->hasTable('coupons')) {
            $this->table('coupons')
                ->addColumn('batch_uuid', 'string', ['null' => true])
                ->addColumn('coupon_code_id', 'integer', ['null' => true, 'after' => 'type'])
                ->addColumn('subscription_type_id', 'integer', ['null' => true, 'after' => 'coupon_code_id'])
                ->addColumn('subscription_type_name_id', 'integer', ['null' => true, 'after' => 'subscription_type_id'])
                ->dropForeignKey('user_id')
                ->removeColumn('user_id')
                ->removeIndex(['code', 'type'])
                ->addIndex('type')
                ->update();

            $migrateSql = <<<SQL
-- get rid of never used coupons before migration
DELETE FROM coupons WHERE subscription_id IS NULL;

-- insert coupon codes
INSERT INTO coupon_codes (code, created_at)
SELECT code, created_at FROM coupons;

-- migrate references to coupon_codes, set newly created subscription-based columns
UPDATE coupons
JOIN coupon_codes
    ON coupons.code = coupon_codes.code
JOIN subscriptions
    ON subscription_id = subscriptions.id
JOIN subscription_type_names
    ON subscriptions.type = subscription_type_names.type
SET
    batch_uuid = UUID(),
    coupon_code_id = coupon_codes.id,
    coupons.subscription_type_id = subscriptions.subscription_type_id,
    coupons.subscription_type_name_id = subscription_type_names.id;
SQL;
            $this->execute($migrateSql);

            $this->table('coupons')
                ->changeColumn('batch_uuid', 'string', ['null' => false])
                ->changeColumn('coupon_code_id', 'integer', ['null' => false, 'after' => 'type'])
                ->changeColumn('subscription_type_id', 'integer', ['null' => false, 'after' => 'coupon_code_id'])
                ->changeColumn('subscription_type_name_id', 'integer', ['null' => false, 'after' => 'subscription_type_id'])
                ->addForeignKey('coupon_code_id', 'coupon_codes')
                ->addForeignKey('subscription_type_id', 'subscription_types')
                ->addForeignKey('subscription_type_name_id', 'subscription_type_names')
                ->update();
        } else {
            $this->table('coupons')
                ->addColumn('type', 'string', ['null' => false])
                ->addColumn('batch_uuid', 'string', ['null' => false])
                ->addColumn('coupon_code_id', 'integer', ['null' => false])
                ->addColumn('subscription_type_id', 'integer', ['null' => false])
                ->addColumn('subscription_type_name_id', 'integer', ['null' => false])
                ->addColumn('subscription_id', 'integer', ['null' => true])
                ->addColumn('assigned_at', 'datetime', ['null' => false])
                ->addTimestamps()
                ->addForeignKey('coupon_code_id', 'coupon_codes')
                ->addForeignKey('subscription_type_id', 'subscription_types')
                ->addForeignKey('subscription_type_name_id', 'subscription_type_names')
                ->addForeignKey('subscription_id', 'subscriptions')
                ->addIndex('type')
                ->create();
        }
    }
}