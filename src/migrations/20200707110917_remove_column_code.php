<?php

use Phinx\Migration\AbstractMigration;

class RemoveColumnCode extends AbstractMigration
{
    public function change()
    {
        if ($this->table('coupons')->hasColumn('code') === true) {
            $this->table('coupons')
                ->removeColumn('code')
                ->update();
        }
    }
}
