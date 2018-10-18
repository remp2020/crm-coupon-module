<?php

namespace Crm\CouponModule\Events;

use Crm\CouponModule\Repository\CouponsRepository;
use Crm\PaymentsModule\CannotAssignCoupon;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;

class NewSubscriptionHandler extends AbstractListener
{
    private $couponsRepository;

    private $subscriptionTypesCodes = [];

    private $salesFunnelsMetaRepository;

    private $paymentsRepository;

    private $subscriptionsRepository;

    private $emitter;

    /**
     * NewSubscriptionHandler constructor.
     * @param CouponsRepository $couponsRepository
     * @param SalesFunnelsMetaRepository $salesFunnelsMetaRepository
     * @param PaymentsRepository $paymentsRepository
     * @param SubscriptionsRepository $subscriptionsRepository
     * @param Emitter $emitter
     */
    public function __construct(
        CouponsRepository $couponsRepository,
        SalesFunnelsMetaRepository $salesFunnelsMetaRepository,
        SubscriptionsRepository $subscriptionsRepository,
        PaymentsRepository $paymentsRepository,
        Emitter $emitter
    ) {
        $this->couponsRepository = $couponsRepository;
        $this->salesFunnelsMetaRepository = $salesFunnelsMetaRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->emitter = $emitter;

        $this->subscriptionTypesCodes = [
            'hbo' => [
                'types' => ['hbo_web', 'hbo_app', 'hbo_print'],
                'email' => 'hbo_voucher',
            ],
            'respekt_2016' => [
                'types' => ['respect_web_year', 'respect_app_year', 'respect_friday_year'],
                'email' => 'respekt_activation_code',
            ],
            'dokina_2016' => [
                'types' => ['dokina_web_month', 'dokina_web_app_club_month', 'dokina_web_print_month'],
                'email' => 'dokina_activation_code',
            ],
            'martinus_web_2017' => [
                'types' => ['martinus_web_2017'],
                'email' => 'martinus_web_activation_code',
            ],
            'martinus_web_app_club_2017' => [
                'types' => ['martinus_web_app_club_2017'],
                'email' => 'martinus_web_app_club_year_activation_code',
            ],
        ];
    }

    public function handle(EventInterface $event)
    {
        $subscription = $event->getSubscription();

        $type = false;
        $email = false;
        foreach ($this->subscriptionTypesCodes as $key => $data) {
            if (in_array($subscription->subscription_type->code, $data['types'])) {
                $type = $key;
                $email = $data['email'];
                break;
            }
        }

        if (!$type) {
            return;
        }

        // pozrieme sa ci neide o predlzenie predplatneho
        $previousSubscription = $this->subscriptionsRepository->getPreviousSubscription($subscription->id);
        if ($previousSubscription && $previousSubscription->subscription_type_id == $subscription->subscription_type_id) {
            return;
        }

        $coupon = $this->couponsRepository->assignCoupon($type, $subscription->user_id, $subscription->id);

        if (!$coupon) {
            throw new CannotAssignCoupon("Unable to assign coupon type '{$type} to user_id '{$subscription->user_id}'");
        }

        $this->subscriptionsRepository->update($subscription, [
            'note' => ($subscription->note ? $subscription->note . '<br>' : '') . 'Coupon: ' . $coupon->code . ' (' . $type . ')',
        ]);

        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        $this->salesFunnelsMetaRepository->updateValue($payment->sales_funnel, 'available_coupons', $this->couponsRepository->availableCoupons($type));

        $this->emitter->emit(new NotificationEvent($subscription->user, $email, [
            'subscription' => $subscription->toArray(),
            'coupon' => $coupon->code,
        ], "subscription.{$subscription->id}", []));

        // vypneme dalsiu propagaciu eventu
        // tzn. neposle sa email o novej subscription
        // POZOR - toto ale musi byt zavolane tesne potom ako s ide posielat email
        $event->stopPropagation();
    }
}
