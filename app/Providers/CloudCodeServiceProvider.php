<?php

namespace App\Providers;

use App\Services\CloudCode\CloudCodeService;
use Illuminate\Support\ServiceProvider;
use Weijiajia\DecryptVerificationCode\CloudCode\CloudCodeConnector;

class CloudCodeServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {

    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        $this->app->singleton(CloudCodeService::class, function ($app) {
            return new CloudCodeService(
                token: config('cloudcode.token'),
                connector:$app->make(CloudCodeConnector::class),
                logger: $app->make('log'),
                type: config('cloudcode.type'),
                debug: config('app.debug'),
            );
        });
    }
}
