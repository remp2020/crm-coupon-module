<?php

namespace Crm\CouponModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class SubscriptionHasCouponCodeCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'subscription_has_coupon_code';

    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [
            new BooleanParam(
                self::KEY,
                $this->translator->translate('coupon.admin.scenarios.subscription_has_coupon_code.label')
            ),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $hasCoupon = $paramValues[self::KEY]->selection;

        if ($hasCoupon) {
            $selection->where(":coupons.id IS NOT NULL");
        } else {
            $selection->where(":coupons.id IS NULL");
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('coupon.admin.scenarios.subscription_has_coupon_code.label');
    }
}
