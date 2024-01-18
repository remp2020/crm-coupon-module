<?php

namespace Crm\CouponModule;

use Crm\ApiModule\Models\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Models\Router\ApiIdentifier;
use Crm\ApiModule\Models\Router\ApiRoute;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\CouponModule\Api\ActivateCouponApiHandler;
use Crm\CouponModule\DataProviders\FilterUsersFormDataProvider;
use Crm\CouponModule\Scenarios\SubscriptionHasCouponCodeCriteria;
use Crm\UsersModule\Models\Auth\UserTokenAuthorization;

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

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'coupon', 'activate'),
                ActivateCouponApiHandler::class,
                UserTokenAuthorization::class
            )
        );
    }

    public function registerDataProviders(DataProviderManager $dataProviderManager)
    {
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.users_filter_form',
            $this->getInstance(FilterUsersFormDataProvider::class)
        );
    }

    public function registerScenariosCriteria(ScenariosCriteriaStorage $scenariosCriteriaStorage)
    {
        $scenariosCriteriaStorage->register('subscription', SubscriptionHasCouponCodeCriteria::KEY, $this->getInstance(SubscriptionHasCouponCodeCriteria::class));
    }
}
