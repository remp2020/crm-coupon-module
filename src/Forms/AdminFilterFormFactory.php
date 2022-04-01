<?php

namespace Crm\CouponModule\Forms;

use Crm\CouponModule\Repository\CouponsRepository;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class AdminFilterFormFactory
{
    private CouponsRepository $couponsRepository;

    private Translator $translator;

    private LinkGenerator $linkGenerator;

    public $onCancel;

    public function __construct(
        CouponsRepository $couponsRepository,
        Translator $translator,
        LinkGenerator $linkGenerator
    ) {
        $this->couponsRepository = $couponsRepository;
        $this->translator = $translator;
        $this->linkGenerator = $linkGenerator;
    }

    public function create(): Form
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);

        $form->addText('coupon', $this->translator->translate('coupon.admin.component.filter_form.coupon.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('coupon.admin.component.filter_form.coupon.placeholder'))
            ->setHtmlAttribute('autofocus');

        $form->addText('email', $this->translator->translate('coupon.admin.component.filter_form.email.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('coupon.admin.component.filter_form.email.placeholder'));

        $types = [];
        foreach ($this->getAvailableCouponTypes() as $couponType) {
            $types[$couponType['key']] = $couponType['value'];
        }
        $typeElem = $form->addSelect('type', '', $types)
            ->setPrompt($this->translator->translate('coupon.admin.component.filter_form.type.placeholder'));
        $typeElem->getControlPrototype()->addAttributes([
            'class' => 'select2',
        ]);
        if (count($types) >= 500) { // too much for select2 initialization, use AJAX
            $typeElem->getControlPrototype()->addAttributes([
                'data-ajax-url' => $this->linkGenerator->link('Coupon:CouponsAdmin:typesJson')
            ]);
        }

        $form->addSubmit('send', $this->translator->translate('coupon.admin.component.filter_form.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('coupon.admin.component.filter_form.submit'));

        $form->addSubmit('cancel', 'coupon.admin.component.filter_form.cancel')->onClick[] = function () {
            $this->onCancel->__invoke();
        };

        return $form;
    }

    public function getAvailableCouponTypes(?string $searchType = null)
    {
        $matchedTypes = $this->couponsRepository->allTypes();
        if ($searchType) {
            $matchedTypes
                ->where('type LIKE ?', "{$searchType}%")
                ->limit(50);
        }

        $types = [];
        foreach ($matchedTypes as $couponType) {
            $types[] = [
                'key' => $couponType->type,
                'value' => "{$couponType->type} <small class='text-muted'>({$couponType->count}x)</small>",
            ];
        }
        return $types;
    }
}
