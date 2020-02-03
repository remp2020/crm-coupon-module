<?php

namespace Crm\CouponModule;

use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use League\Event\Emitter;

class CouponModule extends CrmModule
{
    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem(
            '',
            ':Coupon:CouponsAdmin:default',
            'fa fa-tag',
            791,
            true
        );
        $menuContainer->attachMenuItem($mainMenu);
    }

    public function registerEventHandlers(Emitter $emitter)
    {
        $emitter->addListener(
            \Crm\SubscriptionsModule\Events\NewSubscriptionEvent::class,
            $this->getInstance(\Crm\CouponModule\Events\NewSubscriptionHandler::class),
            -199
        );
    }
}
