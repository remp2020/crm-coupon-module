<?php

namespace Crm\CouponModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\CouponModule\Forms\AdminFilterFormFactory;
use Crm\CouponModule\Forms\GenerateFormFactory;
use Crm\CouponModule\Repository\CouponsRepository;

class CouponsAdminPresenter extends AdminPresenter
{
    /** @persistent */
    public $text;

    /** @persistent */
    public $type;

    /** @var AdminFilterFormFactory @inject */
    public $adminFilterFormFactory;

    /** @var GenerateFormFactory @inject */
    public $generateFormFactory;

    /** @var CouponsRepository @inject */
    public $couponsRepository;

    public function renderDefault($type = null)
    {
        $coupons = $this->couponsRepository->search($this->text, $this->type);
        $filteredCount = (clone $coupons)->count('*');
        $availableCount = (clone $coupons)->where('assigned_at IS NULL')->count('*');

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($filteredCount);
        $paginator->setItemsPerPage($this->onPage);

        $coupons->limit($paginator->getLength(), $paginator->getOffset());

        $this->template->vp = $vp;
        $this->template->filteredCount = $filteredCount;
        $this->template->availableCount = $availableCount;
        $this->template->coupons = $coupons;
    }

    public function renderGenerate()
    {
    }

    public function createComponentFilterForm()
    {
        $form = $this->adminFilterFormFactory->create();
        $form->setDefaults([
            'text' => $this->text,
            'type' => $this->type,
        ]);

        $this->adminFilterFormFactory->onCancel = function () use ($form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $this->redirect($this->action, $emptyDefaults);
        };
        $form->onSuccess[] = [$this, 'filterFormSucceeded'];

        return $form;
    }

    public function filterFormSucceeded($form, $values)
    {
        $this->redirect($this->action, array_filter((array)$values));
    }

    public function createComponentGenerateForm()
    {
        $form = $this->generateFormFactory->create();
        $this->generateFormFactory->onSuccess = function ($form, $values) {
            $this->flashMessage($this->translator->translate('coupon.admin.component.generate_form.success'));
            $this->redirect('default');
        };
        return $form;
    }
}
