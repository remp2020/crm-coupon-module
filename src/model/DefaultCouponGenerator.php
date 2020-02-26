<?php

namespace Crm\CouponModule;

use Crm\CouponModule\Repository\CouponCodesRepository;
use Nette\Database\Table\IRow;

class DefaultCouponGenerator implements CouponGeneratorInterface
{
    private $couponCodesRepository;

    private $length = 8;

    // alphabet without colliding characters (0 vs O, I vs l)
    private $charset = 'ABCDEFGHKLMNPQRSTUVWXYZ123456789';

    public function __construct(CouponCodesRepository $couponCodesRepository)
    {
        $this->couponCodesRepository = $couponCodesRepository;
    }

    public function setLength($length)
    {
        $this->length = $length;
    }

    public function setCharset($charset)
    {
        $this->charset = $charset;
    }

    public function generate(): IRow
    {
        $couponCode = null;
        while ($couponCode === null) {
            $code = '';
            foreach (range(1, $this->length) as $i) {
                $code .= $this->charset[random_int(0, strlen($this->charset)-1)];
            }

            try {
                $couponCode = $this->couponCodesRepository->add($code);
            } catch (CouponAlreadyExistsException $e) {
                // already used, do nothing, iterate again
            }
        }

        return $couponCode;
    }
}
