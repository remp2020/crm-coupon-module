<?php

use Phinx\Migration\AbstractMigration;

class AddCouponsExpirationTime extends AbstractMigration
{
    public function change()
    {
        $this->table('coupons')
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->update();
    }
}
