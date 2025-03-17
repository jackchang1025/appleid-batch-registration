<?php

namespace App\Services\Browser;

use App\Services\Browser\Config\GeolocationConfig;
use App\Services\Browser\Config\LocaleConfig;
use App\Services\Browser\Config\UserAgentConfig;
use App\Services\Browser\Config\WebRTCConfig;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Symfony\Component\Panther\Client;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use App\Services\Browser\IpInfoService;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Facebook\WebDriver\WebDriverPlatform;

class BrowserBuilder
{
    protected ChromeOptions $chromeOptions;

    protected DesiredCapabilities $capabilities;
    
    // 浏览器配置
    protected ?GeolocationConfig $geolocationConfig = null;

    protected ?UserAgentConfig $userAgentConfig = null;

    protected ?LocaleConfig $localeConfig = null;

    protected ?WebRTCConfig $webRTCConfig = null;

    protected ?string $certificatePath = null;
    
    // JavaScript脚本
    protected array $pendingScripts = [];


    protected ?array $options = [
        'connection_timeout_in_ms' => 30,
    ];

    
    public function __construct(protected string $host)
    {
        $this->chromeOptions = new ChromeOptions();
        $this->capabilities = new DesiredCapabilities([
            WebDriverCapabilityType::BROWSER_NAME => WebDriverBrowserType::CHROME,
            WebDriverCapabilityType::PLATFORM => WebDriverPlatform::LINUX,
        ]);
        
        // 设置默认的用户代理
        $this->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.6943.54 Safari/537.36');

        $this->withCountry('US');
         
        // 设置默认WebRTC配置
        $this->withWebRTC(true);

        $this->chromeOptions->addArguments([
            '--no-sandbox',          // 禁用沙箱(Docker环境常用)
            '--disable-gpu',         // 禁用GPU加速
            '--disable-dev-shm-usage', // 禁用/dev/shm使用
            '--remote-debugging-port=0', // 启用CDP
            '--start-maximized',//启动时最大化窗口
        ]);
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getChromeOptions(): ChromeOptions
    {
        return $this->chromeOptions;
    }

    public function getCapabilities(): DesiredCapabilities
    {
        return $this->capabilities;
    }

    public function getGeolocationConfig(): ?GeolocationConfig
    {
        return $this->geolocationConfig;
    }

    public function getLocaleConfig(): ?LocaleConfig
    {
        return $this->localeConfig;
    }

    public function getWebRTCConfig(): ?WebRTCConfig
    {
        return $this->webRTCConfig;
    }

    public function getPendingScripts(): array
    {
        return $this->pendingScripts;
    }

    public function getCertificatePath(): ?string
    {
        return $this->certificatePath;
    }

    public function getUserAgentConfig(): UserAgentConfig
    {
        return $this->userAgentConfig;
    }

    /**
     * 设置Capability
     */
    public function setCapability(string $name, $value): self
    {
        $this->capabilities->setCapability($name, $value);
        return $this;
    }

    public function withHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }
    
    /**
     * 设置地理位置
     */
    public function withGeolocation(float $latitude, float $longitude, float $accuracy = 1): self
    {
        $this->geolocationConfig = new GeolocationConfig($latitude, $longitude, $accuracy);
        return $this;
    }
    
    /**
     * 根据国家设置地理位置
     */
    public function withCountry(string $country, ?string $timezone = null): self
    {
        // 设置地理位置
        $geolocation = GeolocationConfig::fromCountry($country);
        if ($geolocation) {
            $this->geolocationConfig = $geolocation;
        }
        
        // 设置本地化
        $locale = LocaleConfig::fromCountry($country, $timezone);
        if ($locale) {
            $this->localeConfig = $locale;
        }
        
        return $this;
    }
    
    /**
     * 设置代理
     */
    public function withProxy(string $proxy): self
    {
        $this->chromeOptions->addArguments([
            "--proxy-server={$proxy}",
        ]);
        return $this;
    }
    
    /**
     * 设置用户代理
     */
    public function withUserAgent(string $userAgent): self
    {
        $this->userAgentConfig = new UserAgentConfig($userAgent);
        return $this;
    }
    
    /**
     * 设置浏览器和操作系统
     */
    public function withBrowserAndOS(string $browser = 'Chrome', string $os = 'Windows'): self
    {
        $this->userAgentConfig = UserAgentConfig::create($os, $browser);
        return $this;
    }
    
    /**
     * 设置区域设置
     */
    public function withLocale(string $locale, ?string $timezone = null): self
    {
        $this->localeConfig = new LocaleConfig($locale, $timezone);
        return $this;
    }
    
    /**
     * 设置时区
     */
    public function withTimezone(string $timezone): self
    {
        if ($this->localeConfig) {
            $this->localeConfig = new LocaleConfig(
                $this->localeConfig->locale,
                $timezone,
                $this->localeConfig->language
            );
        } else {
            $this->localeConfig = new LocaleConfig('en-US', $timezone);
        }
        return $this;
    }
    
    /**
     * 设置窗口大小
     */
    public function withWindowSize(int $width, int $height, bool $fullscreen = false): self
    {
        $this->chromeOptions->addArguments([
            "--window-size={$width},{$height}",
        ]);

        return $this;
    }
    
    /**
     * 设置全屏模式
     */
    public function withFullscreen(bool $fullscreen = true): self
    {
        if ($fullscreen) {
            $this->chromeOptions->addArguments([
                "--start-maximized",
                "--kiosk",
            ]);
        }
        return $this;
    }
    
    /**
     * 设置WebRTC选项
     */
    public function withWebRTC(bool $enabled = false): self
    {
        $this->webRTCConfig = new WebRTCConfig($enabled);

        $script = $this->webRTCConfig->getDisablerScript();
        if ($script) {
            $this->addScript($script);
        }
        return $this;
    }
    
    /**
     * 设置SSL证书路径
     */
    public function withCertificatePath(string $path): self
    {
        $this->certificatePath = $path;
        $this->configureSslCertificates();
        return $this;
    }
    
    /**
     * 添加待执行的JavaScript脚本
     */
    public function addScript(string $script): self
    {
        $this->pendingScripts[] = $script;
        return $this;
    }
    
    /**
     * 配置SSL证书
     */
    protected function configureSslCertificates(): void
    {
        if ($this->certificatePath && file_exists($this->certificatePath)) {
            // 创建临时目录存储证书政策
            $tmpDir = sys_get_temp_dir() . '/chrome-cert-' . uniqid();
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
            
            // 复制证书到目标位置
            $certFile = $tmpDir . '/mitmproxy-cert.pem';
            copy($this->certificatePath, $certFile);
            
            // 添加证书相关参数
            $this->chromeOptions->addArguments([
                "--ignore-certificate-errors", 
                "--allow-insecure-localhost",
            ]);
            
            // 添加证书路径
            $this->chromeOptions->addExtensions([$certFile]);
        } else {
            // 如果没有证书文件，使用最基本的忽略错误参数
            $this->chromeOptions->addArguments([
                "--ignore-certificate-errors",
            ]);
        }

        // 设置接受不安全证书
        $this->capabilities->setCapability('acceptInsecureCerts', true);
    }

       /**
     * 创建浏览器客户端
     */
    public function createClient(): Client
    {

        // 将ChromeOptions设置到Capabilities
        $this->capabilities->setCapability(ChromeOptions::CAPABILITY, $this->getChromeOptions());


        
        // 创建客户端
        $client = Client::createSeleniumClient(
            host: $this->getHost(),
            capabilities: $this->getCapabilities(),
            options: $this->options
        );


        // 使用CDP配置高级特性
        $this->configureBrowserWithCDP($client);

        // 执行待执行的JavaScript脚本
        $this->executeAllPendingScripts($client);

        return $client;
    }
    
    /**
     * 根据IP信息创建浏览器客户端
     */
    public function createClientWithProxyIpInfo(string $proxy): Client
    {
        // 创建一个基础的BrowserBuilder
        $this->withProxy($proxy);
        
        // 获取IP信息
        $ipInfoService = new IpInfoService();
        $ipInfo = $ipInfoService->getIpInfo($proxy);
        
        // 设置基于IP信息的配置
        if (!empty($ipInfo->json('country_code'))) {
            $this->withCountry($ipInfo->json('country_code'));
        }
        
        if (!empty($ipInfo->json('timezone'))) {
            $this->withTimezone($ipInfo->json('timezone'));
        }
        
        if (!empty($ipInfo->json('latitude')) && !empty($ipInfo->json('longitude'))) {
            $this->withGeolocation(
                latitude: (float)$ipInfo->json('latitude'),
                longitude: (float)$ipInfo->json('longitude')
            );
        }

        // 构建配置并创建客户端
        return $this->createClient();
    }
    

    /**
     * 使用CDP配置浏览器高级特性
     */
    protected function configureBrowserWithCDP(Client $client): void
    {
        // 获取原始的WebDriver实例
        $driver = $client->getWebDriver();
        
        // 创建ChromeDevToolsDriver实例
        $devTools = new ChromeDevToolsDriver($driver);
        
        // 配置地理位置
        if ($this->getGeolocationConfig()) {
            $this->configureGeolocation($devTools);
        }
        
        // 配置本地化设置
        if ($this->getLocaleConfig()) {
            $this->configureLocale($devTools);
        }
        
        // 配置用户代理
        if ($this->getUserAgentConfig()) {
            $this->configureUserAgent($devTools);
        }
    }
    
    /**
     * 配置地理位置
     */
    protected function configureGeolocation(ChromeDevToolsDriver $devTools): void
    {
        $devTools->execute('Emulation.setGeolocationOverride', [
            'latitude' => $this->getGeolocationConfig()->latitude,
            'longitude' => $this->getGeolocationConfig()->longitude,
            'accuracy' => $this->getGeolocationConfig()->accuracy
        ]);
    }
    
    /**
     * 配置本地化设置
     */
    protected function configureLocale(ChromeDevToolsDriver $devTools): void
    {
        // 设置区域
        $devTools->execute('Emulation.setLocaleOverride', [
            'locale' => $this->getLocaleConfig()->locale
        ]);
        
        // 设置时区（如果提供）
        if ($this->getLocaleConfig()->timezone) {
            $devTools->execute('Emulation.setTimezoneOverride', [
                'timezoneId' => $this->getLocaleConfig()->timezone
            ]);
        }
    }

    /**
     * 配置用户代理
     */
    protected function configureUserAgent(ChromeDevToolsDriver $devTools): void
    {
        // 提取Chrome版本
        $chromeVersion = '133';
        if (preg_match('/Chrome\/([0-9\.]+)/', $this->getUserAgentConfig()->userAgent, $matches)) {
            $chromeVersion = $matches[1];
        }
        
        $devTools->execute('Emulation.setUserAgentOverride', [
            'userAgent' => $this->getUserAgentConfig()->userAgent,
            'acceptLanguage' => 'en-US',
            'platform' => $this->getUserAgentConfig()->platform ?? 'Windows',
            'userAgentMetadata' => [
                'brands' => [
                    ['brand' => 'Chromium', 'version' => $chromeVersion],
                    ['brand' => 'Google Chrome', 'version' => $chromeVersion],
                    ['brand' => 'Not=A?Brand', 'version' => '99'],
                ],
                'fullVersionList' => [
                    ['brand' => 'Chromium', 'version' => $chromeVersion],
                    ['brand' => 'Google Chrome', 'version' => $chromeVersion],
                    ['brand' => 'Not=A?Brand', 'version' => '99'],
                ],
                'platform' => $this->getUserAgentConfig()->platform ?? 'Windows',
                'platformVersion' => '10.0.0',
                'architecture' => 'x86',
                'model' => '',
                'mobile' => false,
            ]
        ]);
    }
    
    /**
     * 执行所有待执行的JavaScript脚本
     */
    protected function executeAllPendingScripts(Client $client): void
    {
        foreach ($this->getPendingScripts() as $script) {
            $client->executeScript($script);
        }
    }
}