<?php

namespace Dpsoft\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Dpsoft\Payments\Interfaces\PaymentInterface;
use Dpsoft\Payments\Classes\BaseController;

class MyFatoorahPayment extends BaseController implements PaymentInterface
{
    public $myfatoorah_mode;
    public $myfatoorah_country;
    public $app_name;

    private $myfatoorah_api_key;
    private $verify_route_name;

    public function __construct()
    {
        $this->myfatoorah_api_key = config('dpsoft-payments.MYFATOORAH_API_KEY');
        $this->myfatoorah_mode = config('dpsoft-payments.MYFATOORAH_MODE', 'test');
        $this->myfatoorah_country = config('dpsoft-payments.MYFATOORAH_COUNTRY', '');
        $this->currency = config('dpsoft-payments.MYFATOORAH_CURRENCY', 'KWD');
        $this->app_name = config('dpsoft-payments.APP_NAME');
        $this->verify_route_name = config('dpsoft-payments.VERIFY_ROUTE_NAME');
    }

    private function getApiBaseUrl(): string
    {
        if ($this->myfatoorah_mode === 'live') {
            $country = strtolower($this->myfatoorah_country);
            $prefixes = ['eg' => 'api-eg', 'sa' => 'api-sa', 'ae' => 'api-ae', 'qa' => 'api-qa'];
            $sub = $prefixes[$country] ?? 'api';
            return "https://{$sub}.myfatoorah.com";
        }
        return "https://apitest.myfatoorah.com";
    }

    private function getSessionJsUrl(): string
    {
        if ($this->myfatoorah_mode === 'live') {
            $country = strtolower($this->myfatoorah_country);
            $prefixes = ['eg' => 'eg', 'sa' => 'sa', 'ae' => 'ae', 'qa' => 'qa'];
            $sub = $prefixes[$country] ?? 'portal';
            return "https://{$sub}.myfatoorah.com/sessions/v1/session.js";
        }
        return "https://demo.myfatoorah.com/sessions/v1/session.js";
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
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'MYFATOORAH');

        $payment_id = uniqid() . rand(100000, 999999);

        $verify_url = route($this->verify_route_name, ['gateway' => 'myfatoorah']);

        $session_data = [
            'PaymentMode' => 'COMPLETE_PAYMENT',
            'Order' => [
                'Amount' => (float) $this->amount,
                'Currency' => $this->currency,
                'ExternalIdentifier' => $payment_id,
            ],
            'Customer' => [
                'Reference' => (string) ($this->user_id ?? 0),
                'Email' => $this->user_email ?? '',
                'Name' => trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? '')),
            ],
            'IntegrationUrls' => [
                'Redirection' => $verify_url . '?payment_id=' . $payment_id,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->myfatoorah_api_key,
                'Content-Type' => 'application/json',
            ])->post($this->getApiBaseUrl() . '/v3/sessions', $session_data);

            $response_data = $response->json();

            if (!$response->successful() || !isset($response_data['Data']['SessionId'])) {
                return [
                    'payment_id' => $payment_id,
                    'html' => '<p>Payment session creation failed: ' . ($response_data['Message'] ?? 'Unknown error') . '</p>',
                    'redirect_url' => ""
                ];
            }

            $session_id = $response_data['Data']['SessionId'];
            $encryption_key = $response_data['Data']['EncryptionKey'];

            Cache::put('myfatoorah_key_' . $payment_id, $encryption_key, now()->addMinutes(30));

            $session_js_url = $this->getSessionJsUrl();

            $html = <<<HTML
<div id="myfatoorah-embedded-{$payment_id}" style="width:100%;min-height:600px;"></div>
<script src="{$session_js_url}"></script>
<script>
(function() {
    var config = {
        sessionId: "{$session_id}",
        callback: function(response) {
            if (response.paymentCompleted) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = "{$verify_url}";
                var paymentIdField = document.createElement('input');
                paymentIdField.type = 'hidden';
                paymentIdField.name = 'payment_id';
                paymentIdField.value = "{$payment_id}";
                form.appendChild(paymentIdField);
                if (response.paymentData) {
                    var dataField = document.createElement('input');
                    dataField.type = 'hidden';
                    dataField.name = 'paymentData';
                    dataField.value = response.paymentData;
                    form.appendChild(dataField);
                }
                if (response.redirectionUrl) {
                    var urlField = document.createElement('input');
                    urlField.type = 'hidden';
                    urlField.name = 'redirectionUrl';
                    urlField.value = response.redirectionUrl;
                    form.appendChild(urlField);
                }
                var tokenField = document.createElement('input');
                tokenField.type = 'hidden';
                tokenField.name = '_token';
                tokenField.value = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
                form.appendChild(tokenField);
                document.body.appendChild(form);
                form.submit();
            } else {
                window.location.href = "{$verify_url}?payment_id={$payment_id}&paymentData=FAILED";
            }
        },
        containerId: "myfatoorah-embedded-{$payment_id}",
        shouldHandlePaymentUrl: true
    };
    myfatoorah.init(config);
})();
</script>
HTML;

            return [
                'payment_id' => $payment_id,
                'html' => $html,
                'redirect_url' => ""
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
        $payment_id = $request['payment_id'] ?? null;
        $payment_data = $request['paymentData'] ?? null;

        if ($payment_data && $payment_data !== 'FAILED') {
            $cacheKey = 'myfatoorah_key_' . $payment_id;
            $encryption_key = Cache::get($cacheKey);

            if ($encryption_key) {
                $decrypted = $this->decryptPaymentData($payment_data, $encryption_key);

                if ($decrypted) {
                    $result = json_decode($decrypted, true);

                    if (isset($result['Invoice']['Status']) && $result['Invoice']['Status'] === 'PAID') {
                        Cache::forget($cacheKey);
                        return [
                            'success' => true,
                            'payment_id' => $payment_id,
                            'message' => __('dpsoft::messages.PAYMENT_DONE'),
                            'process_data' => $result
                        ];
                    } else {
                        Cache::forget($cacheKey);
                        return [
                            'success' => false,
                            'payment_id' => $payment_id,
                            'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                            'process_data' => $result ?? $request->all()
                        ];
                    }
                }
            }
        }

        $myfatoorah_payment_id = $request['paymentId'] ?? $request['Id'] ?? null;

        if (! $myfatoorah_payment_id && $request['redirectionUrl']) {
            parse_str(parse_url($request['redirectionUrl'], PHP_URL_QUERY), $query_params);
            $myfatoorah_payment_id = $query_params['paymentId'] ?? $query_params['Id'] ?? null;
        }

        if ($myfatoorah_payment_id) {
            $status_response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->myfatoorah_api_key,
                'Content-Type' => 'application/json',
            ])->get($this->getApiBaseUrl() . '/v3/payments/' . $myfatoorah_payment_id)->json();

            $external_id = $status_response['Data']['Invoice']['ExternalIdentifier'] ?? $payment_id;
            $status = $status_response['Data']['Invoice']['Status'] ?? null;

            if ($status === 'PAID') {
                return [
                    'success' => true,
                    'payment_id' => $external_id,
                    'message' => __('dpsoft::messages.PAYMENT_DONE'),
                    'process_data' => $status_response
                ];
            } else {
                return [
                    'success' => false,
                    'payment_id' => $external_id,
                    'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                    'process_data' => $status_response ?? $request->all()
                ];
            }
        }

        $session_id = $request['sessionId'] ?? null;
        if ($session_id) {
            $session_response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->myfatoorah_api_key,
                'Content-Type' => 'application/json',
            ])->get($this->getApiBaseUrl() . '/v3/sessions/' . $session_id)->json();

            $myfatoorah_payment_id = $session_response['Data']['TransactionDetails']['PaymentId']
                ?? $session_response['Data']['TransactionResult']['PaymentId']
                ?? $session_response['Data']['PaymentId']
                ?? null;

            if ($myfatoorah_payment_id) {
                $status_response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->myfatoorah_api_key,
                    'Content-Type' => 'application/json',
                ])->get($this->getApiBaseUrl() . '/v3/payments/' . $myfatoorah_payment_id)->json();

                $external_id = $status_response['Data']['Invoice']['ExternalIdentifier'] ?? $payment_id;
                $status = $status_response['Data']['Invoice']['Status'] ?? null;

                if ($status === 'PAID') {
                    return [
                        'success' => true,
                        'payment_id' => $external_id,
                        'message' => __('dpsoft::messages.PAYMENT_DONE'),
                        'process_data' => $status_response
                    ];
                } else {
                    return [
                        'success' => false,
                        'payment_id' => $external_id,
                        'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                        'process_data' => $status_response ?? $request->all()
                    ];
                }
            }

            return [
                'success' => false,
                'payment_id' => $payment_id,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => $session_response ?? $request->all()
            ];
        }

        return [
            'success' => false,
            'payment_id' => $payment_id,
            'message' => __('dpsoft::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }

    /**
     * Decrypt MyFatoorah paymentData using AES-128-CBC.
     *
     * @param string $encrypted_text
     * @param string $encryption_key
     * @return string|false
     */
    private function decryptPaymentData(string $encrypted_text, string $encryption_key): string|false
    {
        try {
            $encrypted_bytes = base64_decode($encrypted_text);
            $pass_bytes = mb_substr($encryption_key, 0, 16, '8bit');
            $key_bytes = str_pad($pass_bytes, 16, "\0");

            $decrypted = openssl_decrypt($encrypted_bytes, 'AES-128-CBC', $key_bytes, OPENSSL_RAW_DATA, $key_bytes);

            return $decrypted !== false ? $decrypted : false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
