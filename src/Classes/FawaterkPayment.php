<?php

namespace Dpsoft\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Dpsoft\Payments\Interfaces\PaymentInterface;
use Dpsoft\Payments\Classes\BaseController;

class FawaterkPayment extends BaseController implements PaymentInterface
{
    public $fawaterk_api_key;
    public $fawaterk_base_url;
    public $fawaterk_mode;
    public $fawaterk_currency;
    public $fawaterk_payment_method_id;
    public $fawaterk_webhook_secret;
    public $verify_route_name;
    public $app_name;

    public function __construct()
    {
        $this->fawaterk_api_key = config('dpsoft-payments.FAWATERK_API_KEY');
        $this->fawaterk_mode = config('dpsoft-payments.FAWATERK_MODE', 'test');
        $this->fawaterk_base_url = config('dpsoft-payments.FAWATERK_BASE_URL') ?: $this->getDefaultBaseUrl();
        $this->fawaterk_currency = config('dpsoft-payments.FAWATERK_CURRENCY', 'EGP');
        $this->fawaterk_payment_method_id = config('dpsoft-payments.FAWATERK_PAYMENT_METHOD_ID', 2);
        $this->fawaterk_webhook_secret = config('dpsoft-payments.FAWATERK_WEBHOOK_SECRET');
        $this->verify_route_name = config('dpsoft-payments.VERIFY_ROUTE_NAME');
        $this->app_name = config('dpsoft-payments.APP_NAME');
    }

    private function getDefaultBaseUrl(): string
    {
        return $this->fawaterk_mode === 'live' ? 'https://app.fawaterk.com' : 'https://staging.fawaterk.com';
    }

    /**
     * @param mixed $amount
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

        $required_fields = ['amount', 'user_first_name', 'user_last_name'];
        $this->checkRequiredFields($required_fields, 'FAWATERK');

        $this->currency = $this->currency ?? $this->fawaterk_currency;

        $localReference = $this->user_id ? (string) $this->user_id : uniqid();

        $verifyUrl = route($this->verify_route_name, ['gateway' => 'fawaterk']);

        $payload = [
            'cartTotal' => $this->formatAmount($this->amount, $this->currency),
            'currency' => $this->currency,
            'customer' => [
                'first_name' => $this->user_first_name,
                'last_name' => $this->user_last_name,
                'email' => $this->user_email ?? '',
                'phone' => $this->user_phone ?? '',
                'address' => '',
            ],
            'cartItems' => [
                [
                    'name' => 'Payment - ' . ($this->app_name ?? 'Service'),
                    'price' => $this->formatAmount($this->amount, $this->currency),
                    'quantity' => 1,
                ],
            ],
            'redirectionUrls' => [
                'successUrl' => $verifyUrl . '?status=success',
                'failUrl' => $verifyUrl . '?status=fail',
                'pendingUrl' => $verifyUrl . '?status=pending',
            ],
            'payLoad' => [
                'reference' => $localReference,
                'amount' => $this->amount,
                'currency' => $this->currency,
            ],
            'invoice_number' => $localReference,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->fawaterk_api_key,
                'Content-Type' => 'application/json',
            ])->post($this->fawaterk_base_url . '/api/v2/createInvoiceLink', $payload);

            $responseData = $response->json();

            if (!$response->successful() || ($responseData['status'] ?? '') !== 'success' || empty($responseData['data']['url'])) {
                return [
                    'payment_id' => $localReference,
                    'html' => '<p>Fawaterk payment creation failed: ' . e($responseData['message'] ?? $response->body()) . '</p>',
                    'redirect_url' => '',
                    'success' => false,
                    'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                    'process_data' => $responseData,
                ];
            }

            return [
                'payment_id' => $responseData['data']['invoiceId'] ?? $responseData['data']['invoiceKey'] ?? $localReference,
                'redirect_url' => $responseData['data']['url'],
                'html' => '',
            ];
        } catch (\Exception $e) {
            return [
                'payment_id' => $localReference,
                'html' => '<p>Fawaterk payment error: ' . e($e->getMessage()) . '</p>',
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
        $invoiceId = $request->input('invoice_id') ?? $request->input('invoiceId') ?? $request->input('id');
        $status = $request->input('status') ?? 'success';

        if (!$invoiceId) {
            return [
                'success' => false,
                'payment_id' => null,
                'message' => __('dpsoft::messages.PAYMENT_FAILED') . ': Missing invoice ID',
                'process_data' => $request->all(),
            ];
        }

        if (in_array(strtolower($status), ['fail', 'failed', 'pending', 'cancel', 'cancelled'])) {
            return [
                'success' => false,
                'payment_id' => $invoiceId,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->fawaterk_api_key,
                'Content-Type' => 'application/json',
            ])->get($this->fawaterk_base_url . '/api/v2/getInvoiceData/' . $invoiceId);

            $responseData = $response->json();

            if (!$response->successful() || ($responseData['status'] ?? '') !== 'success') {
                return [
                    'success' => false,
                    'payment_id' => $invoiceId,
                    'message' => __('dpsoft::messages.PAYMENT_FAILED') . ': Could not verify invoice',
                    'process_data' => $responseData,
                ];
            }

            $invoiceData = $responseData['data'] ?? [];

            if (!empty($this->fawaterk_webhook_secret) && $request->has('hashKey')) {
                $expectedHash = $this->generateHashKey($request->all(), $invoiceData);
                if (!hash_equals($expectedHash, $request->input('hashKey'))) {
                    return [
                        'success' => false,
                        'payment_id' => $invoiceId,
                        'message' => __('dpsoft::messages.PAYMENT_FAILED') . ': Invalid webhook hash',
                        'process_data' => $request->all(),
                    ];
                }
            }

            $isPaid = (int) ($invoiceData['paid'] ?? 0) === 1;
            $expectedTotal = (float) ($request->input('total') ?? ($invoiceData['pay_load']['amount'] ?? $this->amount));
            $apiTotal = (float) ($invoiceData['total'] ?? 0);
            $apiCurrency = $invoiceData['currency'] ?? '';

            if ($isPaid && $apiTotal === $expectedTotal && strtoupper($apiCurrency) === strtoupper($this->currency ?? '')) {
                return [
                    'success' => true,
                    'payment_id' => $invoiceId,
                    'message' => __('dpsoft::messages.PAYMENT_DONE'),
                    'process_data' => $invoiceData,
                ];
            }

            return [
                'success' => false,
                'payment_id' => $invoiceId,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => $invoiceData,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'payment_id' => $invoiceId,
                'message' => __('dpsoft::messages.PAYMENT_FAILED') . ': ' . $e->getMessage(),
                'process_data' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function generateHashKey(array $data, array $invoiceData): string
    {
        $invoiceId = $data['invoice_id'] ?? $data['invoiceId'] ?? $invoiceData['invoice_id'] ?? '';
        $invoiceKey = $data['invoice_key'] ?? $data['invoiceKey'] ?? $invoiceData['invoice_key'] ?? '';
        $paymentMethod = $data['payment_method'] ?? $data['paymentMethod'] ?? $invoiceData['payment_method'] ?? '';
        $queryParam = "InvoiceId={$invoiceId}&InvoiceKey={$invoiceKey}&PaymentMethod={$paymentMethod}";
        return hash_hmac('sha256', $queryParam, $this->fawaterk_webhook_secret, false);
    }

    private function formatAmount($amount, string $currency): string
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
