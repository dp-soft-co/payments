<?php

namespace Dpsoft\Payments\Factories;

use Dpsoft\Payments\Interfaces\PaymentInterface;
use Dpsoft\Payments\Classes;


class PaymentFactory
{


    /**
     *
     * get the payment class that the user want
     * if not exist return ex
     * @param string $name
     * @return PaymentInterface|Exception
     * @throws Exception
     */

    public function get(string $name): PaymentInterface|Exception
    {

        $className = 'Dpsoft\Payments\Classes\\' . $name . 'Payment';

        if (class_exists($className))
            return new $className();

        throw new \Exception("Invalid gateway");
    }


}
