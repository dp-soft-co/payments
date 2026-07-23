<?php

namespace Dpsoft\Payments\Factories;

use Dpsoft\Payments\Interfaces\PaymentInterface;
use Dpsoft\Payments\Classes;
use Illuminate\Http\Request;


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

        $className = 'Dpsoft\Payments\Classes\\' . $this->normalizeGatewayName($name) . 'Payment';

        if (class_exists($className))
            return new $className();

        throw new \Exception("Invalid gateway");
    }

    private function normalizeGatewayName(string $name): string
    {
        $normalizedName = strtolower(str_replace(['-', '_', ' '], '', trim($name)));

        return match ($normalizedName) {
            'clickpay' => 'ClickPay',
            'hyperpay' => 'HyperPay',
            'myfatoorah' => 'MyFatoorah',
            'paypal' => 'PayPal',
            'paypalbutton' => 'PayPalButton',
            'paypalbuttons' => 'PayPalButton',
            'paymobwallet' => 'PaymobWallet',
            'perfectmoney' => 'PerfectMoney',
            'fawaterk' => 'Fawaterk',
            'fawaterak' => 'Fawaterk',
            default => str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', trim($name)))),
        };
    }

    /**
     * Alias for get() to provide a cleaner unified API.
     *
     * @param string $name
     * @return PaymentInterface
     * @throws \Exception
     */
    public function gateway(string $name): PaymentInterface
    {
        return $this->get($name);
    }

    /**
     * Unified one-call payment: pick gateway, set data, and pay.
     *
     * @param string $gateway
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function pay(string $gateway, array $data): array
    {
        return $this->gateway($gateway)->setPaymentData($data)->pay();
    }

    /**
     * Unified one-call verification for any gateway.
     *
     * @param string $gateway
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function verify(string $gateway, Request $request): array
    {
        return $this->gateway($gateway)->verify($request);
    }

}
