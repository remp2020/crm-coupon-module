<?php

namespace Crm\CouponModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Api\JsonValidationTrait;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\CouponModule\CouponAlreadyAssignedException;
use Crm\CouponModule\CouponExpiredException;
use Crm\CouponModule\Repository\CouponsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use DateTime;
use Nette\Http\Response;

class ActivateCouponApiHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $couponsRepository;

    private $usersRepository;

    private $subscriptionsRepository;

    public function __construct(
        CouponsRepository $couponsRepository,
        UsersRepository $usersRepository,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->couponsRepository = $couponsRepository;
        $this->usersRepository = $usersRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function handle(array $params): ApiResponseInterface
    {
        $result = $this->validateInput(__DIR__ . '/activate-coupon.schema.json', $this->rawPayload());
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token'])) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => 'Cannot authorize user',
                'code' => 'cannot_authorize_user',
            ]);
            $response->setHttpCode(Response::S403_FORBIDDEN);
            return $response;
        }

        $json = $result->getParsedObject();

        $couponRow = $this->couponsRepository->findByCode($json->code)->fetch();
        if (!$couponRow) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "Coupon doesn't exist: {$json->code}",
                'code' => 'coupon_doesnt_exist',
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $token = $data['token'];
        $userRow = $this->usersRepository->find($token->user_id);
        $notifyUser = isset($json->notifyUser) && $json->notifyUser;

        try {
            $this->couponsRepository->activate($userRow, $couponRow, $notifyUser);
        } catch (CouponAlreadyAssignedException $exception) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => 'Coupon is already used',
                'code' => 'coupon_already_used',
            ]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        } catch (CouponExpiredException $exception) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => 'Coupon expired',
                'code' => 'coupon_expired',
            ]);
            $response->setHttpCode(Response::S410_GONE);
            return $response;
        }

        $couponRow = $this->couponsRepository->find($couponRow->id);
        $subscriptionRow = $this->subscriptionsRepository->find($couponRow->subscription_id);

        $response = new JsonResponse([
            'coupon_id' => $couponRow->id,
            'coupon_type' => $couponRow->type,
            'subscription_id' => $couponRow->subscription_id,
            'subscription_type_id' => $couponRow->subscription_type_id,
            'subscription_type_name' => $couponRow->subscription_type->name,
            'subscription_start_time' => $subscriptionRow->start_time->format(DateTime::RFC3339),
            'subscription_end_time' => $subscriptionRow->end_time->format(DateTime::RFC3339),
        ]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }

    public function params(): array
    {
        return [];
    }
}
