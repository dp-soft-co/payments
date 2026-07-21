<?php

namespace Dpsoft\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Dpsoft\Payments\Interfaces\PaymentInterface;
use Dpsoft\Payments\Classes\BaseController;

class KashierPayment extends BaseController implements PaymentInterface
{
    public  $kashier_url;
    public  $kashier_webhook_url;
    public  $kashier_mode;
    private $kashier_account_key;
    private $kashier_iframe_key;
    private $kashier_token;
    public  $app_name;

    private $verify_route_name;

    public function __construct()
    {
        $this->kashier_url = config("dpsoft-payments.KASHIER_URL");
        $this->kashier_webhook_url = config("dpsoft-payments.KASHIER_WEBHOOK_URL");
        $this->kashier_mode = config("dpsoft-payments.KASHIER_MODE");
        $this->kashier_account_key = config("dpsoft-payments.KASHIER_ACCOUNT_KEY");
        $this->kashier_iframe_key = config("dpsoft-payments.KASHIER_IFRAME_KEY");
        $this->kashier_token = config("dpsoft-payments.KASHIER_TOKEN");
        $this->currency = config('dpsoft-payments.KASHIER_CURRENCY');
        $this->app_name = config('dpsoft-payments.APP_NAME');
        $this->verify_route_name = config('dpsoft-payments.VERIFY_ROUTE_NAME');
    }


    /**
     * @param $amount
     * @param null $user_id
     * @param null $user_first_name
     * @param null $user_last_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $source
     * @return string[]
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'KASHIER');

        $payment_id = uniqid() . rand(100000, 999999);

        $mid = $this->kashier_account_key;
        $api_key = $this->kashier_iframe_key;
        $secret_key = $this->kashier_token;
        $order_id = $payment_id;

        $api_url = $this->kashier_mode === 'live'
            ? 'https://api.kashier.io/v3/payment/sessions'
            : 'https://test-api.kashier.io/v3/payment/sessions';

        $session_data = [
            'merchantId' => $mid,
            'paymentType' => 'credit',
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'order' => $order_id,
            'merchantRedirect' => route($this->verify_route_name, ['gateway' => "kashier"]),
            'display' => 'ar',
            'type' => 'one-time',
            'allowedMethods' => $this->source ?? 'card,wallet',
            'redirectMethod' => 'get',
            'failureRedirect' => false,
            'defaultMethod' => 'card',
            'description' => 'Payment - ' . $this->app_name,
            'manualCapture' => false,
            'interactionSource' => 'ECOMMERCE',
            'enable3DS' => true,
            'serverWebhook' => $this->kashier_webhook_url,
            'brandColor' => (function () {
                try {
                    $cached = app('cached_data');
                    return $cached['settings']['primary_color'] ?? '#E50606';
                } catch (\Exception $e) {
                    return \DB::table('settings')->where('option', 'primary_color')->value('value') ?: '#E50606';
                }
            })(),
            'iframeBackgroundColor' => '#FFFFFF',
            'customer' => [
                'email' => $this->user_email ?? 'guest@example.com',
                'reference' => (string) ($this->user_id ?? 0),
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $secret_key,
                'api-key' => $api_key,
                'Content-Type' => 'application/json',
            ])->post($api_url, $session_data);

            $response_data = $response->json();

            if (!$response->successful() || !isset($response_data['sessionUrl'])) {
                return [
                    'payment_id' => $payment_id,
                    'html' => '<p>Payment session creation failed: ' . ($response_data['message'] ?? 'Unknown error') . '</p>',
                    'redirect_url' => ""
                ];
            }

            $session_url = $response_data['sessionUrl'];

            $html = '<iframe src="' . $session_url . '" style="width:100%;height:600px;border:none;" allow="payment"></iframe>';

            return [
                'payment_id' => $payment_id,
                'html' => $html,
                'redirect_url' => $session_url
            ];

        } catch (\Exception $e) {
            return [
                'payment_id' => $payment_id,
                'html' => '<p>Payment session error: ' . $e->getMessage() . '</p>',
                'redirect_url' => ""
            ];
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $merchantOrderId = $request['merchantOrderId'] ?? $request['order'] ?? null;

        if ($merchantOrderId != null) {
            $url_mode = $this->kashier_mode == "live" ? '' : 'test-';
            $response = Http::withHeaders([
                'Authorization' => $this->kashier_token
            ])->get('https://' . $url_mode . 'api.kashier.io/payments/orders/' . $merchantOrderId)->json();

            if (isset($response['response']['status']) && $response['response']['status'] == "CAPTURED") {
                return [
                    'success' => true,
                    'payment_id' => $merchantOrderId,
                    'message' => __('dpsoft::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            } else {
                return [
                    'success' => false,
                    'payment_id' => $merchantOrderId,
                    'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                    'process_data' => $request->all()
                ];
            }
        }

        return [
            'success' => false,
            'payment_id' => $merchantOrderId,
            'message' => __('dpsoft::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }


}
