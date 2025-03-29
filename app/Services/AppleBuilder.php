<?php

namespace App\Services;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Weijiajia\HttpProxyManager\Contracts\ProxyInterface;
use Illuminate\Support\Collection;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Weijiajia\SaloonphpAppleClient\Contracts\AppleIdInterface;

class AppleBuilder
{

    public function __construct(protected LoggerInterface $logger,protected Dispatcher $dispatcher,protected ProxyManager $proxyManager)
    {

    }

    public function build(AppleIdInterface $appleId):Apple
    {
        return (new Apple($appleId))
            ->withBasePath(storage_path("app/public/{$appleId->getAppleId()}"))
            ->withLogger($this->logger)
            ->withDispatcher($this->dispatcher)
            ->withProxyManager($this->proxyManager)
            ->withConfig('auth_base_url', env('APPLE_AUTH_BASE_URL'));
    }
}
