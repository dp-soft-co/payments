<?php
return [
    #PAYMOB
    'PAYMOB_BASE_URL' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
    'PAYMOB_PUBLIC_KEY' => env('PAYMOB_PUBLIC_KEY'),
    'PAYMOB_SECRET_KEY' => env('PAYMOB_SECRET_KEY'),
    'PAYMOB_HMAC' => env('PAYMOB_HMAC'),
    'PAYMOB_INTEGRATION_ID' => env('PAYMOB_INTEGRATION_ID'),
    'PAYMOB_CURRENCY'=> env('PAYMOB_CURRENCY',"EGP"),
    'PAYMOB_NOTIFICATION_URL' => env('PAYMOB_NOTIFICATION_URL'), // webhook url
    'PAYMOB_CHECKOUT_MODE' => env('PAYMOB_CHECKOUT_MODE', 'pixel'), // redirect, pixel
    'PAYMOB_PIXEL_PAYMENT_METHODS' => env('PAYMOB_PIXEL_PAYMENT_METHODS', 'card'), // card, google-pay, apple-pay

    #HYPERPAY
    'HYPERPAY_BASE_URL' => env('HYPERPAY_BASE_URL', "https://eu-test.oppwa.com"),
    'HYPERPAY_URL' => env('HYPERPAY_URL', env('HYPERPAY_BASE_URL') . "/v1/checkouts"),
    'HYPERPAY_TOKEN' => env('HYPERPAY_TOKEN'),
    'HYPERPAY_CREDIT_ID' => env('HYPERPAY_CREDIT_ID'),
    'HYPERPAY_MADA_ID' => env('HYPERPAY_MADA_ID'),
    'HYPERPAY_APPLE_ID' => env('HYPERPAY_APPLE_ID'),
    'HYPERPAY_CURRENCY' => env('HYPERPAY_CURRENCY', "SAR"),


    #KASHIER
    'KASHIER_ACCOUNT_KEY' => env('KASHIER_ACCOUNT_KEY'),
    'KASHIER_IFRAME_KEY' => env('KASHIER_IFRAME_KEY'),
    'KASHIER_TOKEN' => env('KASHIER_TOKEN'),
    'KASHIER_URL' => env('KASHIER_URL', "https://checkout.kashier.io"),
    'KASHIER_MODE' => env('KASHIER_MODE', "test"), //live or test
    'KASHIER_CURRENCY'=>env('KASHIER_CURRENCY',"EGP"),
    'KASHIER_WEBHOOK_URL'=>env('KASHIER_WEBHOOK_URL'),


    #FAWRY
    'FAWRY_URL' => env('FAWRY_URL', "https://atfawry.fawrystaging.com/"),//https://www.atfawry.com/ for production
    'FAWRY_SECRET' => env('FAWRY_SECRET'),
    'FAWRY_MERCHANT' => env('FAWRY_MERCHANT'),
    'FAWRY_DISPLAY_MODE' => env('FAWRY_DISPLAY_MODE',"POPUP"),//required allowed values [POPUP, INSIDE_PAGE, SIDE_PAGE , SEPARATED]
    'FAWRY_PAY_MODE'=>env('FAWRY_PAY_MODE',"CARD"),//allowed values ['CashOnDelivery', 'PayAtFawry', 'MWALLET', 'CARD' , 'VALU']

    #FAWATERK
    'FAWATERK_CLIENT_ID' => env('FAWATERK_CLIENT_ID'),              // OAuth Client ID from Integration → OAuth clients
    'FAWATERK_CLIENT_SECRET' => env('FAWATERK_CLIENT_SECRET'),        // OAuth Client secret
    'FAWATERK_API_KEY' => env('FAWATERK_API_KEY'),                    // Legacy API key (fallback if OAuth not used)
    'FAWATERK_BASE_URL' => env('FAWATERK_BASE_URL', 'https://staging.fawaterk.com'),
    'FAWATERK_MODE' => env('FAWATERK_MODE', 'test'), // test or live
    'FAWATERK_CURRENCY' => env('FAWATERK_CURRENCY', 'EGP'),
    'FAWATERK_PAYMENT_METHOD_ID' => env('FAWATERK_PAYMENT_METHOD_ID', 2), // 2 = Visa-Mastercard
    'FAWATERK_WEBHOOK_SECRET' => env('FAWATERK_WEBHOOK_SECRET'),      // HASH API key used to verify webhook hashKey and generate iframe hashKey
    'FAWATERK_PROVIDER_KEY' => env('FAWATERK_PROVIDER_KEY'),          // Provider Key from Integration → Fawaterk (used for iframe)
    'FAWATERK_CHECKOUT_MODE' => env('FAWATERK_CHECKOUT_MODE', 'redirect'), // redirect or iframe
    'FAWATERK_IFRAME_JS_URL' => env('FAWATERK_IFRAME_JS_URL', 'https://app.fawaterk.com/fawaterkPlugin/fawaterkPlugin.min.js'),
    'FAWATERK_IFRAME_LISTING' => env('FAWATERK_IFRAME_LISTING', 'horizontal'), // horizontal or vertical
    'FAWATERK_IFRAME_REDIRECT_OUT' => env('FAWATERK_IFRAME_REDIRECT_OUT', true), // redirect out of iframe after payment
    'FAWATERK_OAUTH_SCOPE' => env('FAWATERK_OAUTH_SCOPE', ''), // OAuth scope if required by the client

    #PayPal
    'PAYPAL_CLIENT_ID' => env('PAYPAL_CLIENT_ID'),
    'PAYPAL_SECRET' => env('PAYPAL_SECRET'),
    'PAYPAL_CURRENCY' => env('PAYPAL_CURRENCY', "USD"),
    'PAYPAL_MODE' => env('PAYPAL_MODE',"sandbox"),//sandbox or live


    #THAWANI
    'THAWANI_API_KEY' => env('THAWANI_API_KEY', ''),
    'THAWANI_URL' => env('THAWANI_URL', "https://uatcheckout.thawani.om/"),
    'THAWANI_PUBLISHABLE_KEY' => env('THAWANI_PUBLISHABLE_KEY', ''),

    #TAP
    'TAP_CURRENCY' => env('TAP_CURRENCY',"USD"),
    'TAP_SECRET_KEY'=>env('TAP_SECRET_KEY'),
    'TAP_PUBLIC_KEY'=>env('TAP_PUBLIC_KEY'),
    'TAP_LANG_KEY'=>env('TAP_LANG_KEY','ar'),


    #OPAY
    'OPAY_CURRENCY'=>env('OPAY_CURRENCY',"EGP"),
    'OPAY_SECRET_KEY'=>env('OPAY_SECRET_KEY'),
    'OPAY_PUBLIC_KEY'=>env('OPAY_PUBLIC_KEY'),
    'OPAY_MERCHANT_ID'=>env('OPAY_MERCHANT_ID'),
    'OPAY_COUNTRY_CODE'=>env('OPAY_COUNTRY_CODE',"EG"),
    'OPAY_BASE_URL'=>env('OPAY_BASE_URL',"https://sandboxapi.opaycheckout.com"),//https://api.opaycheckout.com for production


    #PAYMOB_WALLET (vodaphone-cash,orange-money,etisalat-cash,we-cash,meza-wallet) - test phone 01010101010 ,PIN & OTP IS 123456
    'PAYMOB_WALLET_INTEGRATION_ID'=>env('PAYMOB_WALLET_INTEGRATION_ID'),

    #Paytabs
    'PAYTABS_PROFILE_ID'  => env('PAYTABS_PROFILE_ID'),
    'PAYTABS_SERVER_KEY' =>  env('PAYTABS_SERVER_KEY'),
    'PAYTABS_BASE_URL' =>   env('PAYTABS_BASE_URL',"https://secure-egypt.paytabs.com"),
    'PAYTABS_CHECKOUT_LANG' => env('PAYTABS_CHECKOUT_LANG',"AR"),
    'PAYTABS_CURRENCY'=>env('PAYTABS_CURRENCY',"EGP"),


    #Binance
    'BINANCE_API'=>env('BINANCE_API'),
    'BINANCE_SECRET'=>env('BINANCE_SECRET'),



    #NowPayments
    'NOWPAYMENTS_API_KEY'=>env('NOWPAYMENTS_API_KEY'),


    #Payeer
    'PAYEER_MERCHANT_ID'=>env('PAYEER_MERCHANT_ID'),
    'PAYEER_API_KEY'=>env('PAYEER_API_KEY'),
    'PAYEER_ADDITIONAL_API_KEY'=>env('PAYEER_ADDITIONAL_API_KEY'),


    #Perfectmoney
    'PERFECT_MONEY_ID'=>env('PERFECT_MONEY_ID','UXXXXXXX'),
    'PERFECT_MONEY_PASSPHRASE'=>env('PERFECT_MONEY_PASSPHRASE'),



    #TELR
    'TELR_MERCHANT_ID'=>env('TELR_MERCHANT_ID'),
    'TELR_API_KEY'=>env('TELR_API_KEY'),
    'TELR_MODE'=>env('TELR_MODE','test'),//test,live


    #CLICKPAY
    'CLICKPAY_SERVER_KEY'=>env('CLICKPAY_SERVER_KEY'),
    'CLICKPAY_PROFILE_ID'=>env('CLICKPAY_PROFILE_ID'),


    #MYFATOORAH
    'MYFATOORAH_API_KEY'=>env('MYFATOORAH_API_KEY'),
    'MYFATOORAH_MODE'=>env('MYFATOORAH_MODE','test'),//test or live
    'MYFATOORAH_COUNTRY'=>env('MYFATOORAH_COUNTRY',''),//eg,sa,ae,qa or empty for global
    'MYFATOORAH_CURRENCY'=>env('MYFATOORAH_CURRENCY','KWD'),

    #STRIPE
    'STRIPE_SECRET_KEY'=>env('STRIPE_SECRET_KEY'),
    'STRIPE_PUBLISHABLE_KEY'=>env('STRIPE_PUBLISHABLE_KEY'),
    'STRIPE_CURRENCY'=>env('STRIPE_CURRENCY','USD'),

    
    /*
    *please 
    *1- create POST route /payments/verify/{gateway} and put it before your verify route 
    *2- put it into app/Http/Middleware/VerifyCsrfToken.php middleware inside except array
    */
    'VERIFY_ROUTE_NAME' => "verify-payment",
    'APP_NAME'=>env('APP_NAME'),
];
