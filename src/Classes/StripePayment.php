<?php

namespace Dpsoft\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Dpsoft\Payments\Interfaces\PaymentInterface;

class StripePayment extends BaseController implements PaymentInterface
{
    private $stripe_secret_key;
    private $stripe_publishable_key;
    private $stripe_currency;
    public $app_name;
    private $verify_route_name;

    public function __construct()
    {
        $this->stripe_secret_key = config('dpsoft-payments.STRIPE_SECRET_KEY');
        $this->stripe_publishable_key = config('dpsoft-payments.STRIPE_PUBLISHABLE_KEY');
        $this->stripe_currency = config('dpsoft-payments.STRIPE_CURRENCY', 'USD');
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
     * @return array
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $this->checkRequiredFields(['amount'], 'STRIPE');

        if (empty($this->stripe_secret_key) || empty($this->stripe_publishable_key)) {
            throw new \Exception('Stripe secret key and publishable key are required.');
        }

        $payment_id = uniqid() . rand(100000, 999999);
        $this->currency = $this->currency ?: $this->stripe_currency;

        $unit_amount = $this->toSmallestCurrencyUnit($this->amount, $this->currency);

        $verify_url = route($this->verify_route_name, ['gateway' => 'stripe']);
        $return_url = $verify_url . '?session_id={CHECKOUT_SESSION_ID}';

        $session_data = [
            'ui_mode' => 'embedded_page',
            'mode' => 'payment',
            'redirect_on_completion' => 'if_required',
            'return_url' => $return_url,
            'client_reference_id' => $payment_id,
            'customer_email' => $this->user_email ?? null,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $this->currency,
                        'unit_amount' => $unit_amount,
                        'product_data' => [
                            'name' => $this->app_name ?: 'Payment',
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'payment_id' => $payment_id,
                'user_id' => $this->user_id ?? null,
            ],
        ];

        $session_data = $this->removeNullValues($session_data);

        try {
            $response = Http::asForm()
                ->withToken($this->stripe_secret_key, 'Bearer')
                ->post('https://api.stripe.com/v1/checkout/sessions', $session_data);

            if (!$response->successful()) {
                $error = $response->json()['error']['message'] ?? 'Unknown Stripe error';
                return [
                    'payment_id' => $payment_id,
                    'html' => '<p>Stripe session creation failed: ' . e($error) . '</p>',
                    'redirect_url' => '',
                ];
            }

            $session = $response->json();
            $client_secret = $session['client_secret'] ?? null;
            $session_id = $session['id'] ?? null;

            if (!$client_secret || !$session_id) {
                return [
                    'payment_id' => $payment_id,
                    'html' => '<p>Stripe session creation failed: invalid response.</p>',
                    'redirect_url' => '',
                ];
            }

            $html = $this->buildEmbeddedCheckoutHtml($client_secret, $session_id, $verify_url);

            return [
                'payment_id' => $payment_id,
                'html' => $html,
                'redirect_url' => '',
            ];
        } catch (\Exception $e) {
            return [
                'payment_id' => $payment_id,
                'html' => '<p>Stripe session creation failed: ' . e($e->getMessage()) . '</p>',
                'redirect_url' => '',
            ];
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $session_id = $request->input('session_id');

        if (empty($session_id) || empty($this->stripe_secret_key)) {
            return [
                'success' => false,
                'payment_id' => null,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        try {
            $response = Http::withToken($this->stripe_secret_key, 'Bearer')
                ->get('https://api.stripe.com/v1/checkout/sessions/' . $session_id);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'payment_id' => $session_id,
                    'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                    'process_data' => $response->json() ?? $request->all(),
                ];
            }

            $session = $response->json();

            $payment_id = $session['client_reference_id'] ?? $session['metadata']['payment_id'] ?? $session_id;
            $payment_status = $session['payment_status'] ?? null;
            $status = $session['status'] ?? null;

            if ($status === 'complete' && $payment_status === 'paid') {
                return [
                    'success' => true,
                    'payment_id' => $payment_id,
                    'message' => __('dpsoft::messages.PAYMENT_DONE'),
                    'process_data' => $session,
                ];
            }

            return [
                'success' => false,
                'payment_id' => $payment_id,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => $session ?? $request->all(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'payment_id' => $session_id,
                'message' => __('dpsoft::messages.PAYMENT_FAILED'),
                'process_data' => ['error' => $e->getMessage()] + $request->all(),
            ];
        }
    }

    /**
     * Convert an amount to the smallest unit for the given currency.
     */
    private function toSmallestCurrencyUnit(float $amount, string $currency): int
    {
        $currency = strtoupper($currency);

        $zeroDecimal = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $threeDecimal = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

        if (in_array($currency, $zeroDecimal, true)) {
            return (int) round($amount);
        }

        if (in_array($currency, $threeDecimal, true)) {
            return (int) round($amount * 1000);
        }

        return (int) round($amount * 100);
    }

    /**
     * Remove null values from a nested array recursively.
     */
    private function removeNullValues(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeNullValues($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif ($value === null) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Build the HTML/JS needed to mount Stripe Embedded Checkout.
     */
    private function buildEmbeddedCheckoutHtml(string $client_secret, string $session_id, string $verify_url): string
    {
        $publishable_key_json = json_encode($this->stripe_publishable_key, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $client_secret_json = json_encode($client_secret, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $session_id_json = json_encode($session_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $verify_url_json = json_encode($verify_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
<div id="stripe-checkout" style="width:100%;min-height:500px;"></div>
<script src="https://js.stripe.com/v3/"></script>
<script>
    (async () => {
        const stripe = Stripe({$publishable_key_json});
        const clientSecret = {$client_secret_json};
        const sessionId = {$session_id_json};
        const verifyUrl = {$verify_url_json};

        const fetchClientSecret = async () => clientSecret;

        const checkout = await stripe.createEmbeddedCheckoutPage({
            fetchClientSecret: fetchClientSecret,
            onComplete: () => {
                window.location.href = verifyUrl + '?session_id=' + encodeURIComponent(sessionId);
            },
        });

        checkout.mount('#stripe-checkout');
    })();
</script>
HTML;
    }
}
