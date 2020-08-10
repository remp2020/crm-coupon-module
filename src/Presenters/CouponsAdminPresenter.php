<?php

namespace Crm\CouponModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\ExcelFactory;
use Crm\CouponModule\Forms\AdminFilterFormFactory;
use Crm\CouponModule\Forms\GenerateFormFactory;
use Crm\CouponModule\Repository\CouponsRepository;
use Nette\Application\Responses\CallbackResponse;
use Nette\Localization\ITranslator;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

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

    /** @var ITranslator @inject */
    public $translator;

    /** @var ExcelFactory @inject */
    public $excelFactory;

    public function renderDefault()
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
            $this->redirect('default', [
                'type' => $values['type'],
                'text' => null
            ]);
        };
        return $form;
    }

    public function renderDownload()
    {
        $coupons = $this->couponsRepository->search($this->text, $this->type)->fetchAll();

        $excel = $this->excelFactory->createExcel('Coupons Export');
        $excel->getActiveSheet()->setTitle('Export');
        $rows[] = $this->addExportHeader();

        foreach ($coupons as $coupon) {
            $rows[] = [
                $coupon->coupon_code->code,
                $coupon->type,
                ($coupon->subscription) ? $coupon->subscription->user->public_name : null,
                $coupon->subscription_type->name,
                $coupon->subscription_type_name->type,
                $coupon->created_at,
                $coupon->expires_at,
                $coupon->assigned_at
            ];
        }

        $fileName = 'coupons-export-' . date('y-m-d-h-i-s') . '.csv';
        $this->getHttpResponse()->addHeader('Content-Encoding', 'windows-1250');
        $this->getHttpResponse()->addHeader('Content-Type', 'application/octet-stream; charset=windows-1250');
        $this->getHttpResponse()->addHeader('Content-Disposition', "attachment; filename=" . $fileName);

        $excel->getActiveSheet()
            ->fromArray($rows);

        $csv = new Csv($excel);
        $csv->setDelimiter(';');
        $csv->setUseBOM(true);
        $csv->setEnclosure('"');

        $response = new CallbackResponse(function () use ($csv) {
            $csv->save("php://output");
        });

        $this->sendResponse($response);
    }

    private function addExportHeader(): array
    {
        return [
            $this->translator->translate('coupon.admin.default.fields.coupon'),
            $this->translator->translate('coupon.admin.default.fields.type'),
            $this->translator->translate('coupon.admin.default.fields.user'),
            $this->translator->translate('coupon.admin.default.fields.subscription_type'),
            $this->translator->translate('coupon.admin.default.fields.subscription_type_name'),
            $this->translator->translate('coupon.admin.default.fields.created_at'),
            $this->translator->translate('coupon.admin.default.fields.expires_at'),
            $this->translator->translate('coupon.admin.default.fields.assigned_at')
        ];
    }
}
