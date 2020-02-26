<?php

namespace Crm\CouponModule;

use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;

class CouponModule extends CrmModule
{
    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem(
            '',
            '#coupons',
            'fa fa-tag',
            791,
            true
        );

        $menuItem = new MenuItem(
            $this->translator->translate('coupon.menu.coupons'),
            ':Coupon:CouponsAdmin:default',
            'fa fa-tag',
            100
        );
        $mainMenu->addChild($menuItem);
        $menuItem = new MenuItem(
            $this->translator->translate('coupon.menu.generator'),
            ':Coupon:CouponsAdmin:generate',
            'fa fa-cogs',
            200
        );
        $mainMenu->addChild($menuItem);

        $menuContainer->attachMenuItem($mainMenu);
    }
}
