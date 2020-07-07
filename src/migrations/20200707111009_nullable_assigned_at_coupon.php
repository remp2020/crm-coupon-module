<?php

use Phinx\Migration\AbstractMigration;

class NullableAssignedAtCoupon extends AbstractMigration
{
    public function change()
    {
        $this->table('coupons')
            ->changeColumn('assigned_at', 'datetime', ['null' => true, 'after' => 'is_paid'])
            ->update();
    }
}
