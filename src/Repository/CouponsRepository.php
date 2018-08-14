<?php

namespace Crm\CouponModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class CouponsRepository extends Repository
{
    protected $tableName = 'coupons';

    public function all($type = false)
    {
        $where = [];
        if ($type) {
            $where = ['type' => $type];
        }

        return $this->getTable()->where($where)->order('created_at');
    }

    public function allTypes()
    {
        return $this->getTable()->group('type')->select('type, count(*) AS count')->fetchAll();
    }

    public function assignCoupon($type, $userId, $subscriptionId)
    {
        // nepekne fuj!, robi sa to na 2 query, v nejakej situacii ak naraz zbehnu 2 platby tak to moze robit problem ;-(
        $coupon = $this->available($type)->order('RAND()')->limit(1)->fetch();
        if (!$coupon) {
            return false;
        }
        $this->update($coupon, [
            'user_id' => $userId,
            'subscription_id' => $subscriptionId,
            'updated_at' => new DateTime(),
            'assigned_at' => new DateTime()
        ]);

        return $coupon;
    }

    public function availableCoupons($type = false)
    {
        return $this->available($type)->count('*');
    }

    private function available($type = false)
    {
        $where = ['assigned_at' => null];
        if ($type) {
            $where['type'] = $type;
        }

        return $this->getTable()->where($where);
    }
}
