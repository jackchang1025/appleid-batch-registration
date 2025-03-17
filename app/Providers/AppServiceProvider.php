<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Panther\Client;
use App\Services\Browser\BrowserBuilder;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(Client::class, function () {
            
            $browserBuilder = new BrowserBuilder(env('SELENIUM_HOST'));

            if(env('MITM_PROXY_PASSWORD')){
                $browserBuilder->withCertificatePath(env('MITM_PROXY_PASSWORD'));
            }

            return $browserBuilder->createClientWithProxyIpInfo(env('SELENIUM_PROXY'));

        });

    }
}
