<?php

namespace Crm\CouponModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\UsersModule\DataProvider\FilterUsersFormDataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;
use Nette\Localization\Translator;

class FilterUsersFormDataProvider implements FilterUsersFormDataProviderInterface
{
    private $translator;

    public function __construct(
        Translator $translator
    ) {
        $this->translator = $translator;
    }

    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }
        if (!($params['form'] instanceof Form)) {
            throw new DataProviderException('invalid type of provided form: ' . get_class($params['form']));
        }

        if (!isset($params['formData'])) {
            throw new DataProviderException('missing [formData] within data provider params');
        }
        if (!is_array($params['formData'])) {
            throw new DataProviderException('invalid type of provided formData: ' . get_class($params['formData']));
        }

        $form = $params['form'];
        $formData = $params['formData'];

        $form->addText('coupon', $this->translator->translate('coupon.admin.filter_users.coupon.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('coupon.admin.filter_users.coupon.placeholder'));

        $form->setDefaults([
            'coupon' => $this->getCoupon($formData)
        ]);

        return $form;
    }

    public function filter(Selection $selection, array $formData): Selection
    {
        if ($this->getCoupon($formData)) {
            $selection->where(':subscriptions:coupons.coupon_code.code', $this->getCoupon($formData));
        }

        return $selection;
    }

    private function getCoupon($formData)
    {
        return $formData['coupon'] ?? null;
    }
}
