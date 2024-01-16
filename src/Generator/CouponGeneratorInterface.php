<?php

namespace Crm\CouponModule\Generator;

use Nette\Database\Table\ActiveRow;

interface CouponGeneratorInterface
{
    public function generate(): ActiveRow;

    public function setPrefix(string $prefix): void;

    public function setLength(int $length): void;
}
