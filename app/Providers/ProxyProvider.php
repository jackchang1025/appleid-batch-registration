<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ProxyConfiguration;
use Illuminate\Contracts\Config\Repository;
use Weijiajia\HttpProxyManager\ProxyManager;

class ProxyProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {

    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->singleton(ProxyManager::class, function ($app) {


            $config = $this->app->make(Repository::class);

            try {
                        
                $proxyConfiguration = ProxyConfiguration::first();

                $defaultDriver = $proxyConfiguration->configuration['default_driver'] ?? null;

                $defaultMode = $proxyConfiguration->configuration[$defaultDriver]['mode'] ?? null;

                if ($proxyConfiguration && $proxyConfiguration->status && !empty($defaultDriver)) {

                    $config->set('http-proxy-manager.default', $defaultDriver);

                    $config->set("http-proxy-manager.drivers.{$defaultDriver}.mode", $defaultMode);

                    $config->set("http-proxy-manager.drivers.{$defaultDriver}.{$defaultMode}", $proxyConfiguration->configuration[$defaultDriver]);

                }

            } catch (\Exception $e) {


            }

            return new ProxyManager($app);
        });
    }
}
