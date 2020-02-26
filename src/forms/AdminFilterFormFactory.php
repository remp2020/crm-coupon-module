<?php

namespace Crm\CouponModule\Forms;

use Crm\CouponModule\Repository\CouponsRepository;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class AdminFilterFormFactory
{
    private $couponsRepository;

    private $translator;

    public $onCancel;

    public function __construct(
        CouponsRepository $couponsRepository,
        ITranslator $translator
    ) {
        $this->couponsRepository = $couponsRepository;
        $this->translator = $translator;
    }

    public function create(): Form
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);

        $form->addText('text', $this->translator->translate('coupon.admin.component.filter_form.text.label'))
            ->setAttribute('placeholder', $this->translator->translate('coupon.admin.component.filter_form.text.placeholder'))
            ->setAttribute('autofocus');

        $types = [];
        foreach ($this->couponsRepository->allTypes() as $couponType) {
            $types[$couponType->type] = sprintf("%s <small class='text-muted'>(%dx)</small>", $couponType->type, $couponType->count);
        }
        $typeElem = $form->addSelect('type', '', $types)
            ->setPrompt($this->translator->translate('coupon.admin.component.filter_form.type.placeholder'));
        $typeElem->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSubmit('send', $this->translator->translate('coupon.admin.admin_filter_form.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('coupon.admin.component.filter_form.submit'));

        $form->addSubmit('cancel', 'coupon.admin.component.filter_form.cancel')->onClick[] = function () {
            $this->onCancel->__invoke();
        };

        return $form;
    }
}
