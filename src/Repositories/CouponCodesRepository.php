<?php

namespace Crm\CouponModule\Repositories;

use Crm\ApplicationModule\Repository;
use Crm\CouponModule\CouponAlreadyExistsException;
use Nette\Database\UniqueConstraintViolationException;

class CouponCodesRepository extends Repository
{
    protected $tableName = 'coupon_codes';

    final public function add($code)
    {
        try {
            return $this->insert([
                'code' => $code,
                'created_at' => new \DateTime(),
            ]);
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw new CouponAlreadyExistsException('Coupon already exists: '. $code);
        }
    }
}
