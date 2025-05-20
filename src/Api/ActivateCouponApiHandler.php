<?php

namespace Crm\CouponModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\JsonValidationTrait;
use Crm\CouponModule\Generator\CouponAlreadyAssignedException;
use Crm\CouponModule\Generator\CouponExpiredException;
use Crm\CouponModule\Repositories\CouponsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use DateTime;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ActivateCouponApiHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $couponsRepository;

    private $usersRepository;

    private $subscriptionsRepository;

    public function __construct(
        CouponsRepository $couponsRepository,
        UsersRepository $usersRepository,
        SubscriptionsRepository $subscriptionsRepository,
    ) {
        $this->couponsRepository = $couponsRepository;
        $this->usersRepository = $usersRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function handle(array $params): ResponseInterface
    {
        $result = $this->validateInput(__DIR__ . '/activate-coupon.schema.json', $this->rawPayload());
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token'])) {
            $response = new JsonApiResponse(Response::S403_FORBIDDEN, [
                'status' => 'error',
                'message' => 'Cannot authorize user',
                'code' => 'cannot_authorize_user',
            ]);
            return $response;
        }

        $json = $result->getParsedObject();

        $couponRow = $this->couponsRepository->findByCode($json->code)->fetch();
        if (!$couponRow) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'message' => "Coupon doesn't exist: {$json->code}",
                'code' => 'coupon_doesnt_exist',
            ]);
            return $response;
        }

        $token = $data['token'];
        $userRow = $this->usersRepository->find($token->user_id);
        $notifyUser = isset($json->notifyUser) && $json->notifyUser;

        try {
            $this->couponsRepository->activate($userRow, $couponRow, $notifyUser);
        } catch (CouponAlreadyAssignedException $exception) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, [
                'status' => 'error',
                'message' => 'Coupon is already used',
                'code' => 'coupon_already_used',
            ]);
            return $response;
        } catch (CouponExpiredException $exception) {
            $response = new JsonApiResponse(Response::S410_GONE, [
                'status' => 'error',
                'message' => 'Coupon expired',
                'code' => 'coupon_expired',
            ]);
            return $response;
        }

        $couponRow = $this->couponsRepository->find($couponRow->id);
        $subscriptionRow = $this->subscriptionsRepository->find($couponRow->subscription_id);

        $response = new JsonApiResponse(Response::S200_OK, [
            'coupon_id' => $couponRow->id,
            'coupon_type' => $couponRow->type,
            'subscription_id' => $couponRow->subscription_id,
            'subscription_type_id' => $couponRow->subscription_type_id,
            'subscription_type_name' => $couponRow->subscription_type->name,
            'subscription_start_time' => $subscriptionRow->start_time->format(DateTime::RFC3339),
            'subscription_end_time' => $subscriptionRow->end_time->format(DateTime::RFC3339),
        ]);

        return $response;
    }

    public function params(): array
    {
        return [];
    }
}
