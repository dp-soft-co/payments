<?php

namespace Dpsoft\Payments\Classes;

use Illuminate\Http\Request;
use Dpsoft\Payments\Interfaces\PaymentInterface;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Models\Builders\OrderRequestBuilder;
use PaypalServerSdkLib\Models\Builders\PurchaseUnitRequestBuilder;
use PaypalServerSdkLib\Models\Builders\AmountWithBreakdownBuilder;

class PayPalButtonPayment extends BaseController implements PaymentInterface
{
    private $paypal_client_id;
    private $paypal_secret;
    private $paypal_mode;
    public $currency;
    private $app_name;
    private $verify_route_name;

    public function __construct()
    {
        $this->paypal_client_id = config('dpsoft-payments.PAYPAL_CLIENT_ID');
        $this->paypal_secret = config('dpsoft-payments.PAYPAL_SECRET');
        $this->paypal_mode = config('dpsoft-payments.PAYPAL_MODE', 'sandbox');
        $this->currency = config('dpsoft-payments.PAYPAL_CURRENCY', 'USD');
        $this->app_name = config('dpsoft-payments.APP_NAME');
        $this->verify_route_name = config('dpsoft-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * @param $amount
     * @param mixed $user_id
     * @param mixed $user_first_name
     * @param mixed $user_last_name
     * @param mixed $user_email
     * @param mixed $user_phone
     * @param mixed $source
     * @return array
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $this->checkRequiredFields(['amount'], 'PayPal Button');

        if (empty($this->paypal_client_id) || empty($this->paypal_secret)) {
            throw new \Exception('PayPal client ID and secret are required.');
        }

        $client = $this->getClient();

        $referenceId = uniqid();
        $amountValue = $this->formatPayPalAmount($this->amount, $this->currency);

        $orderAmount = AmountWithBreakdownBuilder::init($this->currency, $amountValue)->build();
        $purchaseUnit = PurchaseUnitRequestBuilder::init($orderAmount)
            ->referenceId($referenceId)
            ->description('Payment - ' . $this->app_name)
            ->build();

        $orderRequest = OrderRequestBuilder::init('CAPTURE', [$purchaseUnit])->build();

        try {
            $apiResponse = $client->getOrdersController()->createOrder([
                'body' => $orderRequest,
                'prefer' => 'return=representation',
            ]);

            if (!$apiResponse->isSuccess()) {
                return [
                    'payment_id' => $referenceId,
                    'html' => '<p>PayPal order creation failed.</p>',
                    'redirect_url' => '',
                    'success' => false,
                    'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                    'process_data' => $apiResponse->getBody(),
                ];
            }

            $order = $apiResponse->getResult();
            $orderId = $order->getId();

            $verifyUrl = route($this->verify_route_name, ['gateway' => 'paypalbutton']);
            $scriptBaseUrl = $this->paypal_mode === 'live'
                ? 'https://www.paypal.com/sdk/js'
                : 'https://www.sandbox.paypal.com/sdk/js';

            $scriptUrl = $scriptBaseUrl
                . '?client-id=' . urlencode($this->paypal_client_id)
                . '&currency=' . urlencode($this->currency)
                . '&intent=capture';

            $orderIdJson = json_encode($orderId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
            $verifyUrlJson = json_encode($verifyUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

            $html = <<<HTML
<div id="paypal-button-container-{$referenceId}" style="text-align:center;"></div>
<script src="{$scriptUrl}"></script>
<script>
    (function () {
        var orderId = {$orderIdJson};
        var verifyUrl = {$verifyUrlJson};

        paypal.Buttons({
            createOrder: function (data, actions) {
                return orderId;
            },
            onApprove: function (data, actions) {
                var separator = verifyUrl.indexOf('?') === -1 ? '?' : '&';
                window.location.href = verifyUrl + separator + 'token=' + encodeURIComponent(data.orderID);
            },
            onError: function (err) {
                console.error('PayPal button error', err);
            }
        }).render('#paypal-button-container-{$referenceId}');
    })();
</script>
HTML;

            return [
                'payment_id' => $orderId,
                'html' => $html,
                'redirect_url' => '',
            ];
        } catch (\Exception $e) {
            return [
                'payment_id' => $referenceId,
                'html' => '<p>PayPal button error: ' . e($e->getMessage()) . '</p>',
                'redirect_url' => '',
                'success' => false,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $orderId = $request->input('token');

        if (empty($orderId) || empty($this->paypal_client_id) || empty($this->paypal_secret)) {
            return [
                'success' => false,
                'payment_id' => $orderId,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        try {
            $client = $this->getClient();
            $apiResponse = $client->getOrdersController()->captureOrder([
                'id' => $orderId,
                'prefer' => 'return=representation',
            ]);

            if (!$apiResponse->isSuccess()) {
                return [
                    'success' => false,
                    'payment_id' => $orderId,
                    'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                    'process_data' => $apiResponse->getBody() ?? $request->all(),
                ];
            }

            $order = $apiResponse->getResult();

            if ($order->getStatus() === 'COMPLETED') {
                return [
                    'success' => true,
                    'payment_id' => $orderId,
                    'message' => __('dpsoft::messages.PAYMENT_DONE'),
                    'process_data' => $order,
                ];
            }

            return [
                'success' => false,
                'payment_id' => $orderId,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => $order,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'payment_id' => $orderId,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => ['error' => $e->getMessage()] + $request->all(),
            ];
        }
    }

    private function getClient()
    {
        return PaypalServerSdkClientBuilder::init()
            ->clientCredentialsAuthCredentials(
                ClientCredentialsAuthCredentialsBuilder::init(
                    $this->paypal_client_id,
                    $this->paypal_secret
                )
            )
            ->environment($this->paypal_mode === 'live' ? Environment::PRODUCTION : Environment::SANDBOX)
            ->build();
    }

    private function formatPayPalAmount(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        $zeroDecimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF','HUF','TWD'];
        $threeDecimal = ['BHD','JOD','KWD','OMR','TND'];

        if (in_array($currency, $zeroDecimal, true)) {
            return (string) (int) round($amount);
        }

        if (in_array($currency, $threeDecimal, true)) {
            return number_format($amount, 3, '.', '');
        }

        return number_format($amount, 2, '.', '');
    }
}
