<?php

namespace Crm\CouponModule\Tests;

use Crm\CouponModule\Repository\CouponCodesRepository;
use Crm\CouponModule\Repository\CouponsRepository;
use Crm\CouponModule\Repository\SubscriptionHasCouponCodeCriteria;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;

class SubscriptionHasCouponCodeCriteriaTest extends PaymentsTestCase
{
    public function requiredRepositories(): array
    {
        return array_merge(parent::requiredRepositories(), [
            CouponCodesRepository::class,
            CouponsRepository::class,
            SubscriptionTypeNamesRepository::class,
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
        ]);
    }

    public function requiredSeeders(): array
    {
        return array_merge(parent::requiredSeeders(), [
            SubscriptionTypeNamesSeeder::class,
        ]);
    }

    public function dataProviderForTestSubscriptionHasCouponCodeCriteria(): array
    {
        return [
            [
                "hasCode" => true,
                "shouldHaveCode" => true,
                "expectedResult" => true,
            ],
            [
                "hasCode" => true,
                "shouldHaveCode" => false,
                "expectedResult" => false,
            ],
            [
                "hasCode" => false,
                "shouldHaveCode" => true,
                "expectedResult" => false,
            ],
            [
                "hasCode" => false,
                "shouldHaveCode" => false,
                "expectedResult" => true,
            ],
        ];
    }

    /**
     * @dataProvider dataProviderForTestSubscriptionHasCouponCodeCriteria
     */
    public function testSubscriptionHasCouponCodeCriteria($hasCode, $shouldHaveCode, $expectedResult)
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData($hasCode);

        /** @var SubscriptionHasCouponCodeCriteria $criteria */
        $criteria = $this->inject(SubscriptionHasCouponCodeCriteria::class);
        $values = (object)['selection' => $shouldHaveCode];
        $criteria->addConditions($subscriptionSelection, [SubscriptionHasCouponCodeCriteria::KEY => $values], $subscriptionRow);

        if ($expectedResult) {
            $this->assertNotFalse($subscriptionSelection->fetch());
        } else {
            $this->assertFalse($subscriptionSelection->fetch());
        }
    }

    private function prepareData(bool $withCoupon)
    {
        $user = $this->getUser();

        $subscriptionTypeRow = null;
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $subscriptionTypeRow = $subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->save();

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->inject(SubscriptionsRepository::class);

        /** @var SubscriptionTypeNamesRepository $subscriptionTypeNamesRepository */
        $subscriptionTypeNamesRepository = $this->getRepository(SubscriptionTypeNamesRepository::class);
        $subscriptionTypeNameRow = $subscriptionTypeNamesRepository->allActive()->fetch();

        if ($withCoupon) {
            /** @var CouponCodesRepository $couponCodesRepository */
            $couponCodesRepository = $this->getRepository(CouponCodesRepository::class);
            $counponCodeRow = $couponCodesRepository->add('TEST-CODE03232939');

            /** @var CouponsRepository $couponsRepository */
            $couponsRepository = $this->getRepository(CouponsRepository::class);
            $couponRow = $couponsRepository->add(
                'testtype',
                'd9a80gf7',
                $subscriptionTypeRow->id,
                $subscriptionTypeNameRow->id,
                $counponCodeRow->id,
                true
            );

            $subscriptionRow = $subscriptionsRepository->add(
                $couponRow->subscription_type,
                false,
                $couponRow->is_paid,
                $user,
                $couponRow->subscription_type_name->type,
                null,
                null,
                null,
                null,
                false
            );

            $couponsRepository->update($couponRow, [
                'assigned_at' => new \DateTime(),
                'subscription_id' => $subscriptionRow->id,
            ]);

            $subscriptionSelection = $subscriptionsRepository->getTable()->where('subscriptions.id', $subscriptionRow->id);
            return [$subscriptionSelection, $subscriptionRow];
        }

        // without coupon
        $subscriptionRow = $subscriptionsRepository->add(
            $subscriptionTypeRow,
            false,
            true,
            $user,
            $subscriptionTypeNameRow->type,
            null,
            null,
            null,
            null,
            false
        );
        $subscriptionSelection = $subscriptionsRepository->getTable()->where('subscriptions.id', $subscriptionRow->id);
        return [$subscriptionSelection, $subscriptionRow];
    }
}
