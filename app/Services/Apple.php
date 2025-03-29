<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Saloon\Traits\RequestProperties\HasConfig;
use Weijiajia\HttpProxyManager\Contracts\ProxyInterface;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\SaloonphpAppleClient\Contracts\AppleIdInterface;
use Weijiajia\SaloonphpAppleClient\Integrations\Account\AccountConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleAuthenticationConnector\AppleAuthenticationConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\AppleIdConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AuthTvApple\AuthTvAppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\BuyTvAppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\Idmsa\IdmsaConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\TvApple\TvAppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\Icloud\IcloudConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\WebIcloud\WebIcloudConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\Icloud\SetupIcloudConnector;
use Weijiajia\SaloonphpHeaderSynchronizePlugin\Contracts\HeaderSynchronizeDriver;
use Weijiajia\SaloonphpHeaderSynchronizePlugin\Driver\FileHeaderSynchronize;
use Weijiajia\SaloonphpHeaderSynchronizePlugin\Driver\ArrayStoreHeaderSynchronize;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Weijiajia\SaloonphpAppleClient\Integrations\FeedbackwsIcloud\FeedbackwsIcloudConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleIdCdnApple\AppleIdCdnAppleConnector;
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
    protected ?IcloudConnector $icloudConnector = null;
    protected ?WebIcloudConnector $webIcloudConnector = null;
    protected ?FeedbackwsIcloudConnector $feedbackwsIcloudConnector = null;
    protected ?SetupIcloudConnector $setupIcloudConnector = null;
    protected ?AppleIdCdnAppleConnector $appleIdCdnAppleConnector = null;
    private ?CookieJar $cookieJar = null;

    public function __construct(
        protected AppleIdInterface $appleId,
        protected ?string $basePath = null,
        protected ?Dispatcher $dispatcher = null
    ) {

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

    public function feedbackwsIcloudConnector(): FeedbackwsIcloudConnector
    {
        if ($this->feedbackwsIcloudConnector === null) {
            $this->feedbackwsIcloudConnector = new FeedbackwsIcloudConnector();
            $this->feedbackwsIcloudConnector
                ->withLogger($this->getLogger())
                ->withCookies($this->getCookieJar())
                ->withProxyQueue($this->getProxySplQueue())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            if ($this->debug) {
                $this->feedbackwsIcloudConnector->debug();
            }

            $this->feedbackwsIcloudConnector->tries         = 3;
            $this->feedbackwsIcloudConnector->retryInterval = 1000;
        }

        return $this->feedbackwsIcloudConnector;
    }

    public function appleIdCdnAppleConnector(): AppleIdCdnAppleConnector
    {
        if ($this->appleIdCdnAppleConnector === null) {
            $this->appleIdCdnAppleConnector = new AppleIdCdnAppleConnector();
            $this->appleIdCdnAppleConnector
                ->withLogger($this->getLogger())
                ->withProxyQueue($this->getProxySplQueue());

            if ($this->debug) {
                $this->appleIdCdnAppleConnector->debug();
            }

            $this->appleIdCdnAppleConnector->tries         = 3;
            $this->appleIdCdnAppleConnector->retryInterval = 1000;
        }

        return $this->appleIdCdnAppleConnector;
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
        if ($this->idmsaConnector === null) {
            $this->idmsaConnector = new IdmsaConnector(
                $this->config()->get('serviceKey'),
                $this->config()->get('redirectUri')
            );
            $this->idmsaConnector
                ->withLogger($this->getLogger())
                ->withCookies($this->getCookieJar())
                ->withProxyQueue($this->getProxySplQueue())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            $this->idmsaConnector->tries         = 3;
            $this->idmsaConnector->retryInterval = 1000;
            if ($this->debug) {
                $this->idmsaConnector->debug();
            }
        }

        return $this->idmsaConnector;
    }

    public function webIcloudConnector(): WebIcloudConnector
    {
        if ($this->webIcloudConnector === null) {
            $this->webIcloudConnector = new WebIcloudConnector($this->config()->get('web_icloud_base_url'));
            $this->webIcloudConnector
                ->withLogger($this->getLogger())
                ->withCookies($this->getCookieJar())
                ->withProxyQueue($this->getProxySplQueue())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            if ($this->debug) {
                $this->webIcloudConnector->debug();
            }

            $this->webIcloudConnector->tries         = 3;
            $this->webIcloudConnector->retryInterval = 1000;
        }

        return $this->webIcloudConnector;
    }

    public function withHeaderSynchronizeDriver(HeaderSynchronizeDriver $headerSynchronizeDriver): static
    {
        $this->headerSynchronizeDriver = $headerSynchronizeDriver;

        return $this;
    }

    public function getCookieJar(): CookieJar
    {
        return $this->cookieJar ??= new CookieJar(
            $this->getCookieJarPath()
        );
    }

    public function getCookieJarPath(): string
    {
        return $this->getBasePath()."/{$this->appleId->getAppleId()}_cookies.json";
    }

    public function getBasePath(): string
    {
        if ($this->basePath === null) {
            $this->withBasePath(sys_get_temp_dir());
        }

        return $this->basePath;
    }

    public function withBasePath(string $basePath): static
    {
        if (!is_dir($basePath) && !mkdir($basePath, 0777, true) && !is_dir($basePath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $basePath));
        }

        $this->basePath = $basePath;

        return $this;
    }

    public function getAppleId(): AppleIdInterface
    {
        return $this->appleId;
    }

    public function getProxySplQueue(): ProxySplQueue
    {
        if ($this->proxySplQueue === null) {

            // return $this->proxySplQueue = (new ProxySplQueue(roundRobinEnabled: true, proxies: ['http://192.168.31.35:10811']));

            $proxyConnector = $this->getProxyManager()->driver();
            if ($this->debug) {
                $proxyConnector->debug();
            }
            $proxy = $proxyConnector->default();

            if ($proxy instanceof Collection) {

                $proxy->map(fn(ProxyInterface $item) => $item->getUrl());

                return $this->proxySplQueue = (new ProxySplQueue(roundRobinEnabled: true, proxies: $proxy->toArray()));
            }

            if ($proxy instanceof ProxyInterface) {
                return $this->proxySplQueue = (new ProxySplQueue(roundRobinEnabled: true, proxies: [$proxy->getUrl()]));
            }
            //192.168.31.35

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

    public function getHeaderSynchronizeDriverPath(): string
    {
        return $this->getBasePath()."/{$this->appleId->getAppleId()}_headers.json";
    }

    public function withCookieJar(CookieJar $cookieJar): static
    {
        $this->cookieJar = $cookieJar;

        return $this;
    }

    public function appleIdConnector(): AppleIdConnector
    {
        if ($this->appleIdConnector === null) {
            $this->appleIdConnector = new AppleIdConnector();
            $this->appleIdConnector
                ->withLogger($this->getLogger())
                ->withProxyQueue($this->getProxySplQueue())
                ->withCookies($this->getCookieJar())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            $this->appleIdConnector->tries         = 3;
            $this->appleIdConnector->retryInterval = 1000;
            if ($this->debug) {
                $this->appleIdConnector->debug();
            }
        }

        return $this->appleIdConnector;
    }

    public function accountConnector(): AccountConnector
    {
        if ($this->accountConnector === null) {
            $this->accountConnector = new AccountConnector();
            $this->accountConnector
                ->withLogger($this->getLogger())
                ->withProxyQueue($this->getProxySplQueue())
                ->withCookies($this->getCookieJar())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            $this->accountConnector->tries         = 3;
            $this->accountConnector->retryInterval = 1000;
            if ($this->debug) {
                $this->accountConnector->debug();
            }
        }

        return $this->accountConnector;
    }

    public function tvAppleConnector(): TvAppleConnector
    {
        if ($this->tvAppleConnector === null) {
            $this->tvAppleConnector = new TvAppleConnector();
            $this->tvAppleConnector
                ->withLogger($this->getLogger())
                ->withProxyQueue($this->getProxySplQueue())
                ->withCookies($this->getCookieJar())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            $this->tvAppleConnector->tries         = 3;
            $this->tvAppleConnector->retryInterval = 1000;
            if ($this->debug) {
                $this->tvAppleConnector->debug();
            }
        }

        return $this->tvAppleConnector;
    }

    public function authTvAppleConnector(): AuthTvAppleConnector
    {
        if ($this->authTvAppleConnector === null) {
            $this->authTvAppleConnector = new AuthTvAppleConnector();
            $this->authTvAppleConnector
                ->withLogger($this->getLogger())
                ->withProxyQueue($this->getProxySplQueue())
                ->withCookies($this->getCookieJar())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            $this->authTvAppleConnector->tries         = 3;
            $this->authTvAppleConnector->retryInterval = 1000;
            if ($this->debug) {
                $this->authTvAppleConnector->debug();
            }
        }

        return $this->authTvAppleConnector;
    }

    public function buyTvAppleConnector(): BuyTvAppleConnector
    {
        if ($this->buyTvAppleConnector === null) {
            $this->buyTvAppleConnector = new BuyTvAppleConnector();
            $this->buyTvAppleConnector
                ->withLogger($this->getLogger())
                ->withCookies($this->getCookieJar())
                ->withProxyQueue($this->getProxySplQueue())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            $this->buyTvAppleConnector->tries         = 3;
            $this->buyTvAppleConnector->retryInterval = 1000;
            if ($this->debug) {
                $this->buyTvAppleConnector->debug();
            }
        }

        return $this->buyTvAppleConnector;
    }

    public function icloudConnector(): IcloudConnector
    {
        if ($this->icloudConnector === null) {
            $this->icloudConnector = new IcloudConnector();
            $this->icloudConnector
                ->withLogger($this->getLogger())
                ->withCookies($this->getCookieJar())
                ->withProxyQueue($this->getProxySplQueue())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            $this->icloudConnector->tries         = 3;
            $this->icloudConnector->retryInterval = 1000;
            if ($this->debug) {
                $this->icloudConnector->debug();
            }
        }

        return $this->cloudCodeConnector;
    }

    public function setupIcloudConnector(): SetupIcloudConnector
    {
        if ($this->setupIcloudConnector === null) {
            $this->setupIcloudConnector = new SetupIcloudConnector();
            $this->setupIcloudConnector
                ->withLogger($this->getLogger())
                ->withCookies($this->getCookieJar())
                ->withProxyQueue($this->getProxySplQueue())
                ->withHeaderSynchronizeDriver($this->getHeaderSynchronizeDriver());

            $this->setupIcloudConnector->tries         = 3;
            $this->setupIcloudConnector->retryInterval = 1000;
            if ($this->debug) {
                $this->setupIcloudConnector->debug();
            }
        }

        return $this->setupIcloudConnector;
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
