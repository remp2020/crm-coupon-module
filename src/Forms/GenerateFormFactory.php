<?php

namespace Crm\CouponModule\Forms;

use Crm\CouponModule\Generator\CouponGeneratorInterface;
use Crm\CouponModule\Repositories\CouponsRepository;
use Crm\PaymentsModule\Forms\Controls\SubscriptionTypesSelectItemsBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Ramsey\Uuid\Uuid;
use Tomaj\Form\Renderer\BootstrapRenderer;

class GenerateFormFactory
{
    private const MIN_COUPON_LENGTH = 2;
    private const MAX_COUPON_LENGTH = 100;

    public $onSuccess;

    public function __construct(
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly SubscriptionTypeNamesRepository $subscriptionTypeNamesRepository,
        private readonly CouponsRepository $couponsRepository,
        private readonly CouponGeneratorInterface $couponGenerator,
        private readonly Translator $translator,
        private readonly SubscriptionTypesSelectItemsBuilder $subscriptionTypesSelectItemsBuilder,
    ) {
    }

    public function create(): Form
    {
        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $form->addText('type', 'coupon.admin.component.generate_form.type.label')
            ->setHtmlAttribute('placeholder', 'coupon.admin.component.generate_form.type.placeholder')
            ->setOption('description', 'coupon.admin.component.generate_form.type.description')
            ->setRequired('coupon.admin.component.generate_form.type.required');

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchAll();

        $subscriptionTypesElem = $form->addSelect(
            'subscription_type_id',
            'coupon.admin.component.generate_form.subscription_type_id.label',
            $this->subscriptionTypesSelectItemsBuilder->buildWithDescription($subscriptionTypes)
        )
            ->setHtmlAttribute('placeholder', 'coupon.admin.component.generate_form.subscription_type_id.placeholder')
            ->setOption('description', 'coupon.admin.component.generate_form.subscription_type_id.description')
            ->setRequired('coupon.admin.component.generate_form.subscription_type_id.required');
        $subscriptionTypesElem->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSelect('subscription_type_name_id', 'coupon.admin.component.generate_form.subscription_type_name_id.label', $this->subscriptionTypeNamesRepository->allActive()->fetchPairs('id', 'type'))
            ->setHtmlAttribute('placeholder', 'coupon.admin.component.generate_form.subscription_type_name_id.placeholder')
            ->setOption('description', 'coupon.admin.component.generate_form.subscription_type_name_id.description')
            ->setRequired('coupon.admin.component.generate_form.subscription_type_name_id.required');

        $countElem = $form->addText('count', 'coupon.admin.component.generate_form.count.label')
            ->setRequired('coupon.admin.component.generate_form.count.required');
        $countElem->getControlPrototype()->addAttributes(['pattern' => '[0-9]*']);

        $form->addCheckbox('is_paid', 'coupon.admin.component.generate_form.is_paid.label')
            ->setOption('description', 'coupon.admin.component.generate_form.is_paid.description');

        $form->addText('prefix', 'coupon.admin.component.generate_form.prefix.label')
            ->setHtmlAttribute('placeholder', 'coupon.admin.component.generate_form.prefix.placeholder');

        $form->addInteger('length', 'coupon.admin.component.generate_form.length.label')
            ->addRule(Form::MIN, 'coupon.admin.component.generate_form.length.validation', self::MIN_COUPON_LENGTH)
            ->addRule(Form::MAX, 'coupon.admin.component.generate_form.length.validation', self::MAX_COUPON_LENGTH);

        $form->addText('expires_at', 'coupon.admin.component.generate_form.expires_at.label')
            ->setHtmlAttribute('placeholder', 'coupon.admin.component.generate_form.expires_at.placeholder')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime', "1");

        $form->addSubmit('send', 'coupon.admin.component.generate_form.submit')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-cogs"></i> ' . $this->translator->translate('coupon.admin.component.generate_form.submit'));

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if ($values['prefix'] && $values['length'] && (strlen($values['prefix']) + $values['length'] > self::MAX_COUPON_LENGTH)) {
            $form['length']->addError($this->translator->translate('coupon.admin.component.generate_form.length.validation'));
            return;
        }

        $batchUuid = Uuid::uuid4();
        foreach (range(1, $values->count) as $_) {
            $expiresAt = null;
            if (isset($values['expires_at']) && $values['expires_at'] !== '') {
                $expiresAt = DateTime::from($values['expires_at'])->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }

            if ($values['prefix']) {
                $this->couponGenerator->setPrefix($values['prefix']);
            }

            if ($values['length']) {
                $this->couponGenerator->setLength($values['length']);
            }

            $couponCode = $this->couponGenerator->generate();
            $this->couponsRepository->add(
                $values->type,
                $batchUuid,
                $values->subscription_type_id,
                $values->subscription_type_name_id,
                $couponCode->id,
                $values->is_paid,
                $expiresAt
            );
        }

        ($this->onSuccess)($form, $values);
    }
}
