<?php

namespace Crm\CouponModule\Forms;

use Crm\CouponModule\Generator\CouponGeneratorInterface;
use Crm\CouponModule\Repositories\CouponsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Subscription\SubscriptionTypeHelper;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class EditCouponFormFactory
{
    private SubscriptionTypesRepository $subscriptionTypesRepository;

    private SubscriptionsRepository $subscriptionsRepository;

    private SubscriptionTypeNamesRepository $subscriptionTypeNamesRepository;

    private CouponsRepository $couponsRepository;

    private CouponGeneratorInterface $couponGenerator;

    private Translator $translator;

    private SubscriptionTypeHelper $subscriptionTypeHelper;

    public $onSuccess;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        SubscriptionTypeNamesRepository $subscriptionTypeNamesRepository,
        CouponsRepository $couponsRepository,
        CouponGeneratorInterface $couponGenerator,
        Translator $translator,
        SubscriptionTypeHelper $subscriptionTypeHelper
    ) {
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->subscriptionTypeNamesRepository = $subscriptionTypeNamesRepository;
        $this->couponsRepository = $couponsRepository;
        $this->couponGenerator = $couponGenerator;
        $this->translator = $translator;
        $this->subscriptionTypeHelper = $subscriptionTypeHelper;
    }

    public function create(int $couponId): Form
    {
        $coupon = $this->couponsRepository->find($couponId);

        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $form->addHidden('coupon_id');

        $form->addSelect('subscription_type_name_id', 'coupon.admin.edit_form.subscription_type_name_id.label', $this->subscriptionTypeNamesRepository->allActive()->fetchPairs('id', 'type'))
            ->setHtmlAttribute('placeholder', 'coupon.admin.component.generate_form.subscription_type_name_id.placeholder')
            ->setOption('description', 'coupon.admin.component.generate_form.subscription_type_name_id.description')
            ->setRequired('coupon.admin.component.generate_form.subscription_type_name_id.required');

        $form->addText('expires_at', 'coupon.admin.edit_form.expires_at.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'coupon.admin.component.generate_form.expires_at.placeholder')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime', "1");

        $form->addSubmit('send', $this->translator->translate('coupon.admin.default.edit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('coupon.admin.default.edit'));

        $form->setDefaults([
            'coupon_id' => $coupon->id,
            'subscription_type_name_id' => $coupon->subscription_type_name_id,
            'expires_at' => $coupon->expires_at,
        ]);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $coupon = $this->couponsRepository->find($values['coupon_id']);

        $expiresAt = null;
        if ($values['expires_at']) {
            $expiresAt = DateTime::from($values['expires_at']);
        }

        $this->couponsRepository->update($coupon, [
            'subscription_type_name_id' => $values['subscription_type_name_id'],
            'expires_at' => $expiresAt,
        ]);

        ($this->onSuccess)($form, $values);
    }
}
