<?php

namespace Dpsoft\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Dpsoft\Payments\Exceptions\MissingPaymentInfoException;
use Dpsoft\Payments\Interfaces\PaymentInterface;
use Dpsoft\Payments\Classes\BaseController;

class PaymobWalletPayment extends BaseController implements PaymentInterface
{
    private $secretKey;
    private $publicKey;
    private $baseUrl;
    private $walletIntegrationId;
    private $hmacSecret;
    private $notificationUrl;
    private $redirectionUrl;

    public function __construct()
    {
        $this->secretKey = config('dpsoft-payments.PAYMOB_SECRET_KEY');
        $this->publicKey = config('dpsoft-payments.PAYMOB_PUBLIC_KEY');
        $this->baseUrl = rtrim(config('dpsoft-payments.PAYMOB_BASE_URL', 'https://accept.paymob.com'), '/');
        $this->currency = config('dpsoft-payments.PAYMOB_CURRENCY');
        $this->walletIntegrationId = config('dpsoft-payments.PAYMOB_WALLET_INTEGRATION_ID');
        $this->hmacSecret = config('dpsoft-payments.PAYMOB_HMAC');
        $this->notificationUrl = config('dpsoft-payments.PAYMOB_NOTIFICATION_URL');
        $this->redirectionUrl = config('dpsoft-payments.PAYMOB_REDIRECTION_URL');
    }

    /**
     * @param $amount
     * @param null $user_id
     * @param null $user_first_name
     * @param null $user_last_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $source
     * @return void
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email', 'user_phone'];
        $this->checkRequiredFields($required_fields, 'PayMob');

        $payload = [
            'amount' => (int) round($this->amount * 100),
            'currency' => $this->currency,
            'payment_methods' => [$this->walletIntegrationId],
            'items' => [],
            'billing_data' => [
                'apartment' => 'NA',
                'email' => $this->user_email,
                'floor' => 'NA',
                'first_name' => $this->user_first_name,
                'street' => 'NA',
                'building' => 'NA',
                'phone_number' => $this->user_phone,
                'shipping_method' => 'NA',
                'postal_code' => 'NA',
                'city' => 'NA',
                'country' => 'NA',
                'last_name' => $this->user_last_name,
                'state' => 'NA',
            ],
            'customer' => [
                'first_name' => $this->user_first_name,
                'last_name' => $this->user_last_name,
                'email' => $this->user_email,
            ],
            'delivery_needed' => false,
        ];

        if ($this->notificationUrl) {
            $payload['notification_url'] = $this->notificationUrl;
        }

        if ($this->redirectionUrl) {
            $payload['redirection_url'] = $this->redirectionUrl;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Token ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/v1/intention/', $payload);

        $result = $response->json();

        if (!$response->successful() || empty($result['client_secret'])) {
            return [
                'payment_id' => $result['id'] ?? null,
                'html' => '<p>Wallet payment intention creation failed: ' . ($result['message'] ?? 'Unknown error') . '</p>',
                'redirect_url' => ''
            ];
        }

        return [
            'payment_id' => $result['id'],
            'html' => '',
            'redirect_url' => $this->baseUrl . '/unifiedcheckout/?publicKey=' . $this->publicKey . '&clientSecret=' . $result['client_secret']
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $orderId = $request['order_id'] ?? $request['order'] ?? null;

        $fields = [
            $request['amount_cents'] ?? '',
            $request['created_at'] ?? '',
            $request['currency'] ?? '',
            $request['error_occured'] ?? '',
            $request['has_parent_transaction'] ?? '',
            $request['id'] ?? '',
            $request['integration_id'] ?? '',
            $request['is_3d_secure'] ?? '',
            $request['is_auth'] ?? '',
            $request['is_capture'] ?? '',
            $request['is_refunded'] ?? '',
            $request['is_standalone_payment'] ?? '',
            $request['is_voided'] ?? '',
            $orderId ?? '',
            $request['owner'] ?? '',
            $request['pending'] ?? '',
            $request['source_data_pan'] ?? '',
            $request['source_data_sub_type'] ?? '',
            $request['source_data_type'] ?? '',
            $request['success'] ?? '',
        ];

        $string = implode('', array_map('strval', $fields));

        if (hash_equals(hash_hmac('sha512', $string, $this->hmacSecret), $request['hmac'] ?? '')) {
            if ($request['success'] == "true") {
                return [
                    'success' => true,
                    'payment_id' => $orderId,
                    'message' => __('dpsoft::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            } else {
                return [
                    'success' => false,
                    'payment_id' => $orderId,
                    'message' => __('dpsoft::messages.PAYMENT_FAILED_WITH_CODE',['CODE'=>$this->getErrorMessage($request['txn_response_code'] ?? '')]),
                    'process_data' => $request->all()
                ];
            }
        }

        return [
            'success' => false,
            'payment_id' => $orderId,
            'message' => __('dpsoft::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }
    public function getErrorMessage($code){
        $errors=[
            'BLOCKED'=>__('dpsoft::messages.Process_Has_Been_Blocked_From_System'),
            'B'=>__('dpsoft::messages.Process_Has_Been_Blocked_From_System'),
            '5'=>__('dpsoft::messages.Balance_is_not_enough'),
            'F'=>__('dpsoft::messages.Your_card_is_not_authorized_with_3D_secure'),
            '7'=>__('dpsoft::messages.Incorrect_card_expiration_date'),
            '2'=>__('dpsoft::messages.Declined'),
            '6051'=>__('dpsoft::messages.Balance_is_not_enough'),
            '637'=>__('dpsoft::messages.The_OTP_number_was_entered_incorrectly'),
            '11'=>__('dpsoft::messages.Security_checks_are_not_passed_by_the_system'),
        ];
        if(isset($errors[$code]))
            return $errors[$code];
        else
            return __('dpsoft::messages.An_error_occurred_while_executing_the_operation');
    }
}
