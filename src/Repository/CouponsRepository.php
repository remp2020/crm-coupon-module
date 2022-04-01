<?php

namespace Crm\CouponModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\CouponModule\CouponAlreadyAssignedException;
use Crm\CouponModule\CouponExpiredException;
use Crm\CouponModule\Events\CouponActivatedEvent;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use DateTime;
use League\Event\Emitter;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class CouponsRepository extends Repository
{
    protected $tableName = 'coupons';

    private $subscriptionsRepository;

    private $emitter;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        Explorer $database,
        Emitter $emitter,
        Storage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->emitter = $emitter;
    }

    final public function add(string $type, string $batchUuid, int $subscriptionTypeId, int $subscriptionTypeNameId, int $couponCodeId, bool $isPaid, ?DateTime $expiresAt = null)
    {
        return $this->insert([
            'type' => $type,
            'batch_uuid' => $batchUuid,
            'subscription_type_id' => $subscriptionTypeId,
            'subscription_type_name_id' => $subscriptionTypeNameId,
            'coupon_code_id' => $couponCodeId,
            'is_paid' => $isPaid,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
            'expires_at' => $expiresAt
        ]);
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new \DateTime();
        return parent::update($row, $data);
    }

    final public function all()
    {
        return $this->getTable()->order('created_at DESC');
    }

    final public function search($coupon, $type, $email)
    {
        $query = $this->all();
        if ($coupon) {
            $query->where(['coupon_code.code LIKE ?' => '%' . $coupon . '%']);
        }
        if ($email) {
            $query->where(['subscription.user.email LIKE ?' => '%' . $email . '%']);
        }
        if ($type) {
            $query->where(['coupons.type' => $type]);
        }
        return $query;
    }

    final public function findByCode($code)
    {
        return $this->getTable()
            ->where(['coupon_code.code' => $code]);
    }

    final public function allTypes()
    {
        return $this->getTable()
            ->select('type, count(*) AS count')
            ->group('type');
    }

    /**
     * @param ActiveRow $user
     * @param ActiveRow $coupon
     * @param bool $sendEmail
     * @throws CouponAlreadyAssignedException
     * @throws CouponExpiredException
     */
    final public function activate(ActiveRow $user, ActiveRow $coupon, bool $sendEmail = false)
    {
        if ($coupon->assigned_at !== null) {
            throw new CouponAlreadyAssignedException('Coupon already assigned: {$coupon}');
        }
        if (isset($coupon->expires_at) && $coupon->expires_at < new DateTime()) {
            throw new CouponExpiredException('Coupon expired: {$coupon}');
        }

        $subscription = $this->subscriptionsRepository->add(
            $coupon->subscription_type,
            false,
            $coupon->is_paid,
            $user,
            $coupon->subscription_type_name->type,
            null,
            null,
            null,
            null,
            $sendEmail
        );

        $this->update($coupon, [
            'assigned_at' => new \DateTime(),
            'subscription_id' => $subscription->id,
        ]);

        $this->emitter->emit(new CouponActivatedEvent($user, $coupon));
    }
}
