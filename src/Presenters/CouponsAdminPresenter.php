<?php

namespace Crm\CouponModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\PreviousNextPaginator;
use Crm\ApplicationModule\ExcelFactory;
use Crm\CouponModule\Forms\AdminFilterFormFactory;
use Crm\CouponModule\Forms\EditCouponFormFactory;
use Crm\CouponModule\Forms\GenerateFormFactory;
use Crm\CouponModule\Repositories\CouponsRepository;
use DateTime;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\CallbackResponse;
use Nette\DI\Attributes\Inject;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class CouponsAdminPresenter extends AdminPresenter
{
    #[Persistent]
    public $coupon;

    #[Persistent]
    public $email;

    #[Persistent]
    public $type;

    #[Persistent]
    public $created_at_from;

    #[Persistent]
    public $created_at_to;

    #[Inject]
    public AdminFilterFormFactory $adminFilterFormFactory;

    #[Inject]
    public GenerateFormFactory $generateFormFactory;

    #[Inject]
    public EditCouponFormFactory $editCouponFormFactory;

    #[Inject]
    public CouponsRepository $couponsRepository;

    #[Inject]
    public ExcelFactory $excelFactory;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $coupons = $this->couponsRepository->search(
            $this->coupon,
            $this->type,
            $this->email,
            $this->created_at_from ? new DateTime($this->created_at_from) : null,
            $this->created_at_to ? new DateTime($this->created_at_to) : null
        );

        $pnp = new PreviousNextPaginator();
        $this->addComponent($pnp, 'paginator');
        $paginator = $pnp->getPaginator();
        $paginator->setItemsPerPage($this->onPage);

        $coupons = $coupons->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
        $pnp->setActualItemCount(count($coupons));

        $this->template->coupons = $coupons;
    }

    /**
     * @admin-access-level write
     */
    public function renderGenerate()
    {
    }

    public function renderEdit(int $couponId)
    {
        $coupon = $this->couponsRepository->find($couponId);
        if ($coupon->subscription_id) {
            $this->flashMessage($this->translator->translate(
                'coupon.admin.edit_form.cant_edit',
                [
                    'coupon' => $coupon->coupon_code->code
                ]
            ), 'info');
            $this->redirect('default');
        }
        $this->template->coupon = $coupon;
    }

    public function createComponentCouponEditForm()
    {
        $this->editCouponFormFactory->onSuccess = function ($form, $values) {
            $coupon = $this->couponsRepository->find($values['coupon_id']);
            $this->flashMessage($this->translator->translate(
                'coupon.admin.edit_form.success',
                [
                    'coupon' => $coupon->coupon_code->code
                ]
            ));
            $this->redirect('default');
        };

        return $this->editCouponFormFactory->create($this->getParameter('couponId'));
    }

    public function createComponentFilterForm()
    {
        $form = $this->adminFilterFormFactory->create();
        $form->setDefaults([
            'coupon' => $this->coupon,
            'email' => $this->email,
            'type' => $this->type ? mb_strtolower($this->type) : null,
            'created_at_from' => $this->created_at_from,
            'created_at_to' => $this->created_at_to,
        ]);

        $this->adminFilterFormFactory->onCancel = function () use ($form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $this->redirect($this->action, $emptyDefaults);
        };
        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];

        return $form;
    }

    /**
     * @admin-access-level read
     */
    public function actionTypesJson()
    {
        $term = $this->request->getParameter('term');
        if (!$term) {
            throw new BadRequestException('missing parameter term');
        }

        $types = $this->adminFilterFormFactory->getAvailableCouponTypes($term);
        $this->sendJson($types);
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

    /**
     * @admin-access-level read
     */
    public function renderDownload()
    {
        $coupons = $this->couponsRepository->search(
            $this->coupon,
            $this->type,
            $this->email,
            $this->created_at_from ? new DateTime($this->created_at_from) : null,
            $this->created_at_to ? new DateTime($this->created_at_to) : null
        )->fetchAll();

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
