<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddIndexToCouponsCreatedAt extends AbstractMigration
{
    public function change(): void
    {
        $this->table('coupons')
            ->addIndex('created_at')
            ->save();
    }
}
