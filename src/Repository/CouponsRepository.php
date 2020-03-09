<?php

namespace Crm\CouponModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\CouponModule\CouponAlreadyAssignedException;
use Crm\CouponModule\Events\CouponActivatedEvent;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use League\Event\Emitter;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\Table\IRow;

class CouponsRepository extends Repository
{
    protected $tableName = 'coupons';

    private $subscriptionsRepository;

    private $emitter;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        Context $database,
        Emitter $emitter,
        IStorage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->emitter = $emitter;
    }

    final public function add($type, $batchUuid, $subscriptionTypeId, $subscriptionTypeNameId, $couponCodeId)
    {
        return $this->insert([
            'type' => $type,
            'batch_uuid' => $batchUuid,
            'subscription_type_id' => $subscriptionTypeId,
            'subscription_type_name_id' => $subscriptionTypeNameId,
            'coupon_code_id' => $couponCodeId,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }

    final public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new \DateTime();
        return parent::update($row, $data);
    }

    final public function all()
    {
        return $this->getTable()->order('created_at DESC');
    }

    final public function search($text, $type)
    {
        $query = $this->all();
        if ($text) {
            $query->where(['coupon_code.code' => $text]);
        }
        if ($type) {
            $query->where(['type' => $type]);
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
        return $this->getTable()->group('type')->select('type, count(*) AS count')->fetchAll();
    }

    /**
     * @param IRow $user
     * @param IRow $coupon
     * @throws CouponAlreadyAssignedException
     */
    final public function activate(IRow $user, IRow $coupon)
    {
        if ($coupon->assigned_at !== null) {
            throw new CouponAlreadyAssignedException('Coupon already assigned: {$coupon}');
        }

        $subscription = $this->subscriptionsRepository->add(
            $coupon->subscription_type,
            false,
            $user,
            $coupon->subscription_type_name->type,
            null,
            null,
            null,
            null,
            false
        );

        $this->update($coupon, [
            'assigned_at' => new \DateTime(),
            'subscription_id' => $subscription->id,
        ]);

        $this->emitter->emit(new CouponActivatedEvent($user, $coupon));
    }
}
