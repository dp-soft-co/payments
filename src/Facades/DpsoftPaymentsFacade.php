<?php

namespace Dpsoft\Payments\Facades;

use Illuminate\Support\Facades\Facade;

class DpsoftPaymentsFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'dpsoft_payments';
    }
}
