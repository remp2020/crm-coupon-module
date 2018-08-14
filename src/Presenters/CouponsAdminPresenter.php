<?php

namespace Crm\CouponModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\CouponModule\Repository\CouponsRepository;

class CouponsAdminPresenter extends AdminPresenter
{
    private $couponsRepository;

    public function __construct(CouponsRepository $couponsRepository)
    {
        parent::__construct();
        $this->couponsRepository = $couponsRepository;
    }

    public function renderDefault($type = null)
    {
        $this->template->availableCount = $this->couponsRepository->availableCoupons($type);
        $this->template->coupons = $this->couponsRepository->all($type);
        $this->template->types = $this->couponsRepository->allTypes();
        $this->template->actualType = $type;
    }
}
