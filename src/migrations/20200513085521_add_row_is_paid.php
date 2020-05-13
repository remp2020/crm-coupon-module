<?php

use Phinx\Migration\AbstractMigration;

class AddRowIsPaid extends AbstractMigration
{
    public function change()
    {
        $this->table('coupons')
            ->addColumn('is_paid', 'boolean', ['null' => false, 'default' => null, 'after' => 'subscription_id'])
            ->update();
    }
}
