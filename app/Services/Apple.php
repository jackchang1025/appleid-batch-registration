<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Saloon\Traits\RequestProperties\HasConfig;
use Weijiajia\HttpProxyManager\Contracts\ProxyInterface;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\SaloonphpAppleClient\Contracts\AppleIdInterface;
use Weijiajia\SaloonphpAppleClient\Integrations\Account\AccountConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleAuthenticationConnector\AppleAuthenticationConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\AppleIdConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AuthTvApple\AuthTvAppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\BuyTvAppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\Idmsa\IdmsaConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\TvApple\TvAppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\WebIcloud\WebIcloudConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\Icloud\SetupIcloudConnector;
use Weijiajia\SaloonphpHeaderSynchronizePlugin\Contracts\HeaderSynchronizeDriver;
use Weijiajia\SaloonphpHeaderSynchronizePlugin\Driver\ArrayStoreHeaderSynchronize;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Weijiajia\SaloonphpAppleClient\Integrations\FeedbackwsIcloud\FeedbackwsIcloudConnector;

class Apple
{
    use Macroable;
    use HasConfig;
    use HasSignIn;

    protected ?LoggerInterface $logger = null;
    protected ?ProxySplQueue $proxySplQueue = null;
    protected ?HeaderSynchronizeDriver $headerSynchronizeDriver = null;
    protected ?ProxyManager $proxyManager = null;
    protected bool $debug = false;
    protected ?AppleAuthenticationConnector $appleAuthenticationConnector = null;
    protected ?IdmsaConnector $idmsaConnector = null;
    protected ?AppleIdConnector $appleIdConnector = null;
    protected ?AccountConnector $accountConnector = null;
    protected ?TvAppleConnector $tvAppleConnector = null;
    protected ?AuthTvAppleConnector $authTvAppleConnector = null;
    protected ?BuyTvAppleConnector $buyTvAppleConnector = null;
    protected ?WebIcloudConnector $webIcloudConnector = null;
    protected ?FeedbackwsIcloudConnector $feedBackWsIcloudConnector = null;
    protected ?SetupIcloudConnector $setupIcloudConnector = null;
    private ?CookieJar $cookieJar = null;

    protected ?string $country = null;

    public function __construct(
        protected AppleIdInterface $appleId,
        protected ?Dispatcher $dispatcher = null
    ) {

    }

    public function withCountry(string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function appleAuthenticationConnector(): AppleAuthenticationConnector
    {
        if ($this->appleAuthenticationConnector === null) {

            if ($this->config()->get('auth_base_url') === null) {
                throw new InvalidArgumentException('auth_base_url is required');
            }

            $this->appleAuthenticationConnector = new AppleAuthenticationConnector(
                $this->config()->get('auth_base_url')
            );
            $this->appleAuthenticationConnector
                ->withLogger($this->getLogger());

            if ($this->debug) {
                $this->appleAuthenticationConnector->debug();
            }
        }

        return $this->appleAuthenticationConnector;
    }

    public function feedBackWsIcloudConnector(): FeedbackwsIcloudConnector
    {
        return $this->feedBackWsIcloudConnector ??= $this->configureConnector(new FeedbackwsIcloudConnector());
    }

    public function withLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;
        return $this;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function idmsaConnector(): IdmsaConnector
    {
        return $this->idmsaConnector ??= $this->configureConnector(new IdmsaConnector(
            $this->config()->get('serviceKey'),
            $this->config()->get('redirectUri')
        ));
    }

    public function webIcloudConnector(): WebIcloudConnector
    {
        return $this->webIcloudConnector ??= $this->configureConnector(new WebIcloudConnector($this->config()->get('web_icloud_base_url')));
    }

    public function withHeaderSynchronizeDriver(HeaderSynchronizeDriver $headerSynchronizeDriver): static
    {
        $this->headerSynchronizeDriver = $headerSynchronizeDriver;
        return $this;
    }

    public function getCookieJar(): CookieJar
    {
        return $this->cookieJar ??= new CookieJar();
    }

    public function getAppleId(): AppleIdInterface
    {
        return $this->appleId;
    }

    public function getProxySplQueue(): ProxySplQueue
    {
        if ($this->proxySplQueue === null) {

            $proxyConnector = $this->getProxyManager()->driver();
            if ($this->debug) {
                $proxyConnector->debug();
            }

            $proxy = $proxyConnector->withCountry($this->getCountry())->defaultModelIp();

            if ($proxy instanceof Collection) {

                $proxy->map(fn(ProxyInterface $item) => $item->getUrl());

                return $this->proxySplQueue = (new ProxySplQueue(roundRobinEnabled: true, proxies: $proxy->toArray()));
            }

            if ($proxy instanceof ProxyInterface) {
                return $this->proxySplQueue = (new ProxySplQueue(roundRobinEnabled: true, proxies: [$proxy->getUrl()]));
            }

            throw new InvalidArgumentException('proxy is not a valid proxy');
        }

        return $this->proxySplQueue;
    }

    public function getProxyManager(): ProxyManager
    {
        return $this->proxyManager;
    }

    public function getHeaderSynchronizeDriver(): HeaderSynchronizeDriver
    {
        return $this->headerSynchronizeDriver ??= new ArrayStoreHeaderSynchronize();
    }

    public function withCookieJar(CookieJar $cookieJar): static
    {
        $this->cookieJar = $cookieJar;
        return $this;
    }

    public function appleIdConnector(): AppleIdConnector
    {
        return $this->appleIdConnector ??= $this->configureConnector(new AppleIdConnector());
    }

    public function accountConnector(): AccountConnector
    {
        return $this->accountConnector ??= $this->configureConnector(new AccountConnector());
    }

    public function tvAppleConnector(): TvAppleConnector
    {
        return $this->tvAppleConnector ??= $this->configureConnector(new TvAppleConnector());
    }

    public function authTvAppleConnector(): AuthTvAppleConnector
    {
        return $this->authTvAppleConnector ??= $this->configureConnector(new AuthTvAppleConnector());
    }

    public function buyTvAppleConnector(): BuyTvAppleConnector
    {
        return $this->buyTvAppleConnector ??= $this->configureConnector(new BuyTvAppleConnector());
    }

    public function setupIcloudConnector(): SetupIcloudConnector
    {
        return $this->setupIcloudConnector ??= $this->configureConnector(new SetupIcloudConnector());
    }

     /**
     * 配置通用连接器属性
     * 
     * @param mixed $connector 要配置的连接器
     */
    protected function configureConnector(AppleConnector $connector): AppleConnector
    {
        $connector->withLogger($this->getLogger())
            ->withCookies($this->getCookieJar())
            ->withProxyQueue($this->getProxySplQueue())
            ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

        $connector->tries = 3;
        $connector->retryInterval = 1000;
        
        if ($this->debug) {
            $connector->debug();
        }

        return $connector;
    }

    public function withDebug(bool $debug): static
    {
        $this->debug = $debug;
        return $this;
    }

    public function withProxySplQueue(ProxySplQueue $proxySplQueue): static
    {
        $this->proxySplQueue = $proxySplQueue;
        return $this;
    }

    public function withConfig($key, $value): static
    {
        $this->config()->add($key, $value);
        return $this;
    }

    public function withProxyManager(ProxyManager $proxyManager): static
    {
        $this->proxyManager = $proxyManager;
        return $this;
    }

    public function withDispatcher(Dispatcher $dispatcher): static
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    /**
     * Default Config
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'serviceKey'  => 'af1139274f266b22b68c2a3e7ad932cb3c0bbe854e13a79af78dcc73136882c3',
            'redirectUri' => 'https://account.apple.com',
            'web_icloud_base_url' => 'https://www.icloud.com/',
        ];
    }

}
