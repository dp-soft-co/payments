<?php

namespace Dpsoft\Payments;

use Illuminate\Support\ServiceProvider;
use Dpsoft\Payments\Classes\FawryPayment;
use Dpsoft\Payments\Classes\HyperPayPayment;
use Dpsoft\Payments\Classes\KashierPayment;
use Dpsoft\Payments\Classes\PaymobPayment;
use Dpsoft\Payments\Classes\PayPalPayment;
use Dpsoft\Payments\Classes\PaytabsPayment;
use Dpsoft\Payments\Classes\ThawaniPayment;
use Dpsoft\Payments\Classes\TapPayment;
use Dpsoft\Payments\Classes\OpayPayment;
use Dpsoft\Payments\Classes\PaymobWalletPayment;
use Dpsoft\Payments\Classes\MyFatoorahPayment;
use Dpsoft\Payments\Factories\PaymentFactory;

class DpsoftPaymentsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->configure();

        $langPath = 'vendor/payments';
        $langPath = (function_exists('lang_path'))
            ? lang_path($langPath)
            : resource_path('lang/' . $langPath);

        $this->registerPublishing($langPath);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dpsoft');




        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/dpsoft'),
            __DIR__ . '/../config/dpsoft-payments.php' => config_path('dpsoft-payments.php'),
            __DIR__ . '/../resources/lang' => $langPath,
        ], 'dpsoft-payments-all');

        $this->registerTranslations($langPath);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('dpsoft_payments', function () {
            return new PaymentFactory();
        });

        $this->app->bind(PaymobPayment::class, function () {
            return new PaymobPayment();
        });
        $this->app->bind(FawryPayment::class, function () {
            return new FawryPayment();
        });
        $this->app->bind(ThawaniPayment::class, function () {
            return new ThawaniPayment();
        });
        $this->app->bind(PaypalPayment::class, function () {
            return new PaypalPayment();
        });
        $this->app->bind(HyperPayPayment::class, function () {
            return new HyperPayPayment();
        });
        $this->app->bind(KashierPayment::class, function () {
            return new KashierPayment();
        });
        $this->app->bind(TapPayment::class, function () {
            return new TapPayment();
        });
        $this->app->bind(OpayPayment::class, function () {
            return new OpayPayment();
        });
        $this->app->bind(PaymobWalletPayment::class, function () {
            return new PaymobWalletPayment();
        });
        $this->app->bind(PaytabsPayment::class, function () {
            return new PaytabsPayment();
        });
        $this->app->bind(MyFatoorahPayment::class, function () {
            return new MyFatoorahPayment();
        });
    }

    /**
     * Setup the configuration for Dpsoft Payments.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dpsoft-payments.php',
            'dpsoft-payments'
        );
    }
    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations($langPath)
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'dpsoft');
        $this->loadTranslationsFrom($langPath, 'dpsoft');
    }
    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing($langPath)
    {
        $this->publishes([
            __DIR__ . '/../config/dpsoft-payments.php' => config_path('dpsoft-payments.php'),
        ], 'dpsoft-payments-config');

        $this->publishes([
            __DIR__ . '/../resources/lang' => $langPath,
        ], 'dpsoft-payments-lang');
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/payments'),
        ], 'dpsoft-payments-views');
    }
}
