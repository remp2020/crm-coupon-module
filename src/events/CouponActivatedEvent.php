<?php

namespace Crm\CouponModule\Events;

use League\Event\AbstractEvent;

class CouponActivatedEvent extends AbstractEvent
{
    private $user;

    private $coupon;

    public function __construct($user, $coupon)
    {
        $this->user = $user;
        $this->coupon = $coupon;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getCoupon()
    {
        return $this->coupon;
    }
}
