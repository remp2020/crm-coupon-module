<?php

namespace Crm\CouponModule;

use Nette\Database\Table\IRow;

interface CouponGeneratorInterface
{
    public function generate(): IRow;

    public function setPrefix(string $prefix): void;
}
