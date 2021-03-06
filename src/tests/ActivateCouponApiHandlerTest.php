<?php

namespace Crm\CouponModule\Tests;

use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\CouponModule\Api\ActivateCouponApiHandler;
use Crm\CouponModule\CouponGeneratorInterface;
use Crm\CouponModule\DefaultCouponGenerator;
use Crm\CouponModule\Repository\CouponsRepository;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Http\Response;
use Nette\Utils\Json;

class ActivateCouponApiHandlerTest extends DatabaseTestCase
{
    /** @var CouponsRepository */
    private $couponsRepository;

    /** @var ActivateCouponApiHandler */
    private $activateCouponApiHandler;

    /** @var UserManager */
    private $userManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->couponsRepository = $this->getRepository(CouponsRepository::class);
        $this->activateCouponApiHandler = $this->inject(ActivateCouponApiHandler::class);
        $this->userManager = $this->inject(UserManager::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            CouponsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeNamesRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
        ];
    }

    /**
     * @group coupon
     */
    public function testNonExistingCode()
    {
        $userRow = $this->userManager->addNewUser('test@test.sk');

        /** @var ApiAuthorizationInterface $userTokenAuthorization */
        $userTokenAuthorization = \Mockery::mock(ApiAuthorizationInterface::class)
            ->shouldReceive('getAuthorizedData')
            ->andReturn(['token' => (object)['user_id' => $userRow->id]])
            ->getMock();

        $this->activateCouponApiHandler->setRawPayload(Json::encode(['code' => 'test']));
        $response = $this->activateCouponApiHandler->handle($userTokenAuthorization);

        $payload = $response->getPayload();

        $this->assertEquals(Response::S404_NOT_FOUND, $response->getHttpCode());
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('coupon_doesnt_exist', $payload['code']);
    }

    /**
     * @group coupon
     */
    public function testAlreadyUsedCode(): void
    {
        [$userRow, $validCouponRow, $validCouponCodeRow, $expiredCouponRow, $expiredCouponCodeRow, $userTokenAuthorization] = $this->prepareDataForTest();

        $this->couponsRepository->activate($userRow, $validCouponRow);

        $this->activateCouponApiHandler->setRawPayload(Json::encode(['code' => $validCouponCodeRow->code]));
        $response = $this->activateCouponApiHandler->handle($userTokenAuthorization);

        $payload = $response->getPayload();

        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getHttpCode());
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('coupon_already_used', $payload['code']);
    }

    /**
     * @group coupon
     */
    public function testSuccessActivation(): void
    {
        [$userRow, $validCouponRow, $validCouponCodeRow, $expiredCouponRow, $expiredCouponCodeRow, $userTokenAuthorization] = $this->prepareDataForTest();

        $this->activateCouponApiHandler->setRawPayload(Json::encode(['code' => $validCouponCodeRow->code]));
        $response = $this->activateCouponApiHandler->handle($userTokenAuthorization);
        $payload = $response->getPayload();

        $this->assertEquals(Response::S200_OK, $response->getHttpCode());
        $this->assertEquals($validCouponRow->id, $payload['coupon_id']);
    }

    /**
     * @group coupon
     */
    public function testExpiredCoupon(): void
    {
        [$userRow, $validCouponRow, $validCouponCodeRow, $expiredCouponRow, $expiredCouponCodeRow, $userTokenAuthorization] = $this->prepareDataForTest();

        $this->activateCouponApiHandler->setRawPayload(Json::encode(['code' => $expiredCouponCodeRow->code]));
        $response = $this->activateCouponApiHandler->handle($userTokenAuthorization);

        $payload = $response->getPayload();

        $this->assertEquals(Response::S410_GONE, $response->getHttpCode());
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('coupon_expired', $payload['code']);
    }

    private function prepareDataForTest(): array
    {
        $userRow = $this->userManager->addNewUser('test@test.sk');

        /** @var ApiAuthorizationInterface $userTokenAuthorization */
        $userTokenAuthorization = \Mockery::mock(ApiAuthorizationInterface::class)
            ->shouldReceive('getAuthorizedData')
            ->andReturn(['token' => (object)['user_id' => $userRow->id]])
            ->getMock();

        /** @var CouponGeneratorInterface $couponGenerator */
        $couponGenerator = $this->inject(DefaultCouponGenerator::class);
        $validCouponCodeRow = $couponGenerator->generate();
        $expiredCouponCodeRow = $couponGenerator->generate();

        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('test')
            ->setCode('test')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(31)
            ->save();

        /** @var SubscriptionTypeNamesRepository $subscriptionTypeNamesRepository */
        $subscriptionTypeNamesRepository = $this->getRepository(SubscriptionTypeNamesRepository::class);
        $subscriptionTypeNamesRow = $subscriptionTypeNamesRepository->add('test', 31);

        $validCouponRow = $this->couponsRepository->add(
            'test',
            'batuuid',
            $subscriptionTypeRow->id,
            $subscriptionTypeNamesRow->id,
            $validCouponCodeRow->id,
            0,
            null
        );

        $expiredCouponRow = $this->couponsRepository->add(
            'test',
            'batuuid',
            $subscriptionTypeRow->id,
            $subscriptionTypeNamesRow->id,
            $expiredCouponCodeRow->id,
            0,
            new \DateTime('-1 hour')
        );

        return [$userRow, $validCouponRow, $validCouponCodeRow, $expiredCouponRow, $expiredCouponCodeRow, $userTokenAuthorization];
    }
}
