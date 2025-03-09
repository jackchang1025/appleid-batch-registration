<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ProxyConfiguration;
use Illuminate\Contracts\Config\Repository;

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
        $config = $this->app->make(Repository::class);

        $proxyConfig = $config->get('http-proxy-manager');


        //default_driver
        try {
            
            $proxyConfiguration = ProxyConfiguration::first();

            $defaultDriver = $proxyConfiguration->configuration['default_driver'] ?? null;
    
            $defaultMode = $proxyConfiguration->configuration[$defaultDriver]['mode'] ?? null;
    
            if ($proxyConfiguration && $proxyConfiguration->status && !empty($defaultDriver)) {
    
                $config->set('http-proxy-manager.default', $defaultDriver);
    
                $config->set("http-proxy-manager.providers.{$defaultDriver}.default_mode", $defaultMode);
    
                $config->set("http-proxy-manager.providers.{$defaultDriver}.mode.{$defaultMode}.default_config", $proxyConfiguration->configuration[$defaultDriver]);
    
            }

        } catch (\Exception $e) {
        }
    }
}
