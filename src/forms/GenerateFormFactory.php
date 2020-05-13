<?php

namespace Crm\CouponModule\Forms;

use Crm\CouponModule\CouponGeneratorInterface;
use Crm\CouponModule\Repository\CouponsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Subscription\SubscriptionType;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Ramsey\Uuid\Uuid;
use Tomaj\Form\Renderer\BootstrapRenderer;

class GenerateFormFactory
{
    private $subscriptionTypesRepository;

    private $subscriptionsRepository;

    private $subscriptionTypeNamesRepository;

    private $couponsRepository;

    private $couponGenerator;

    private $translator;

    public $onSuccess;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        SubscriptionTypeNamesRepository $subscriptionTypeNamesRepository,
        CouponsRepository $couponsRepository,
        CouponGeneratorInterface $couponGenerator,
        ITranslator $translator
    ) {
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->subscriptionTypeNamesRepository = $subscriptionTypeNamesRepository;
        $this->couponsRepository = $couponsRepository;
        $this->couponGenerator = $couponGenerator;
        $this->translator = $translator;
    }

    public function create(): Form
    {
        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $form->addText('type', 'coupon.admin.component.generate_form.type.label')
            ->setAttribute('placeholder', 'coupon.admin.component.generate_form.type.placeholder')
            ->setOption('description', 'coupon.admin.component.generate_form.type.description')
            ->setRequired('coupon.admin.component.generate_form.type.required');

        $subscriptionTypePairs = SubscriptionType::getPairs($this->subscriptionTypesRepository->getAllActive());

        $subscriptionTypesElem = $form->addSelect('subscription_type_id', 'coupon.admin.component.generate_form.subscription_type_id.label', $subscriptionTypePairs)
            ->setAttribute('placeholder', 'coupon.admin.component.generate_form.subscription_type_id.placeholder')
            ->setOption('description', 'coupon.admin.component.generate_form.subscription_type_id.description')
            ->setRequired('coupon.admin.component.generate_form.subscription_type_id.required');
        $subscriptionTypesElem->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSelect('subscription_type_name_id', 'coupon.admin.component.generate_form.subscription_type_name_id.label', $this->subscriptionTypeNamesRepository->allActive()->fetchPairs('id', 'type'))
            ->setAttribute('placeholder', 'coupon.admin.component.generate_form.subscription_type_name_id.placeholder')
            ->setOption('description', 'coupon.admin.component.generate_form.subscription_type_name_id.description')
            ->setRequired('coupon.admin.component.generate_form.subscription_type_name_id.required');

        $countElem = $form->addText('count', 'coupon.admin.component.generate_form.count.label')
            ->setRequired('coupon.admin.component.generate_form.count.required');
        $countElem->getControlPrototype()->addAttributes(['pattern' => '[0-9]*']);

        $form->addCheckbox('is_paid', 'coupon.admin.component.generate_form.is_paid.label')
            ->setOption('description', 'coupon.admin.component.generate_form.is_paid.description');

        $form->addSubmit('send', 'coupon.admin.component.generate_form.submit')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-cogs"></i> ' . $this->translator->translate('coupon.admin.component.generate_form.submit'));

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $batchUuid = Uuid::uuid4();
        foreach (range(1, $values->count) as $_) {
            $couponCode = $this->couponGenerator->generate();
            $this->couponsRepository->add(
                $values->type,
                $batchUuid,
                $values->subscription_type_id,
                $values->subscription_type_name_id,
                $couponCode->id,
                $values->is_paid
            );
        }

        ($this->onSuccess)($form, $values);
    }
}
