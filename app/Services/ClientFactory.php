<?php

namespace App\Services;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Weijiajia\HttpProxyManager\ProxyManager;
use Symfony\Component\Panther\Client;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ClientFactory
{
    /**
     * 默认的 Chrome 参数
     * 
     * @var array
     */
    protected const DEFAULT_CHROME_ARGUMENTS = [
        '--no-sandbox',          // 禁用沙箱(Docker环境常用)
        '--disable-gpu',         // 禁用GPU加速
        '--disable-dev-shm-usage', // 禁用/dev/shm使用
        '--start-maximized',     // 启动时最大化窗口
        '--remote-debugging-port=0', // 启用CDP
    ];

    protected ?Response $response = null;

    /**
     * 国家代码到语言的映射
     * 
     * @var array
     */
    protected const COUNTRY_TO_LOCALE_MAP = [
        'US' => 'en-US',
        'CA' => 'en-CA',
        'GB' => 'en-GB',
        'CN' => 'zh-CN',
        'JP' => 'ja-JP',
        'FR' => 'fr-FR',
        'DE' => 'de-DE',
        'ES' => 'es-ES',
        'IT' => 'it-IT',
        'BR' => 'pt-BR',
        'RU' => 'ru-RU',
        'IN' => 'en-IN',
        'AU' => 'en-AU',
    ];

    /**
     * 国家代码到默认坐标的映射
     * 
     * @var array
     */
    protected const COUNTRY_TO_COORDS_MAP = [
        'US' => ['latitude' => 37.7749, 'longitude' => -122.4194], // 旧金山
        'CA' => ['latitude' => 43.6532, 'longitude' => -79.3832], // 多伦多 
        'GB' => ['latitude' => 51.5074, 'longitude' => -0.1278],  // 伦敦
        'CN' => ['latitude' => 39.9042, 'longitude' => 116.4074], // 北京
        'JP' => ['latitude' => 35.6762, 'longitude' => 139.6503], // 东京
        'FR' => ['latitude' => 48.8566, 'longitude' => 2.3522],   // 巴黎
        'DE' => ['latitude' => 52.5200, 'longitude' => 13.4050],  // 柏林
        'ES' => ['latitude' => 40.4168, 'longitude' => -3.7038],  // 马德里
        'IT' => ['latitude' => 41.9028, 'longitude' => 12.4964],  // 罗马
        'BR' => ['latitude' => -23.5505, 'longitude' => -46.6333], // 圣保罗
        'RU' => ['latitude' => 55.7558, 'longitude' => 37.6173],  // 莫斯科
        'IN' => ['latitude' => 28.6139, 'longitude' => 77.2090],  // 新德里
        'AU' => ['latitude' => -33.8688, 'longitude' => 151.2093], // 悉尼
    ];

    /**
     * 默认的用户代理字符串
     * 
     * @var string
     */
    protected const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.6943.54 Safari/537.36';

    //禁用WebRTC
    protected ?bool $webRTC = false;

    //WebRTC 元数据
    protected ?string $webRTCDatas = 'Google Inc. (NVIDIA) ANGLE (NVIDIA, NVIDIA GeForce GT 1030 Direct3D11 vs_5_0 ps_5_0, D3D11-26.21.14.3086)';

    //WebRTC 版本
    protected ?string $webRTCVersion = '26.21.14.3086';

    //WebRTC 平台
    protected ?string $webRTCPlatform = 'NVIDIA GeForce GT 1030 Direct3D11 vs_5_0 ps_5_0';

    //CPU
    protected ?string $cpu = 'Intel(R) Core(TM) i5-8250U CPU @ 1.60GHz';

    //RAM
    protected ?string $ram = '16384';

    //显卡
    protected ?string $gpu = 'NVIDIA GeForce GT 1030';

    //硬盘
    protected ?string $disk = '1000000';

    //操作系统
    protected ?string $os = 'Windows';

    //浏览器
    protected ?string $browser = 'Chrome';

    //设备名称
    protected ?string $deviceName = 'DESKTOP-YFTMADI';

    //MAC地址
    protected ?string $macAddress = 'CC-F4-11-5D-30-C8';

    /**
     * 待执行的JavaScript脚本数组
     * 
     * @var array
     */
    protected array $pendingScripts = [];

    protected ?string $certificatePath = null;

    /**
     * 构造函数
     *
     * @param ProxyManager $proxyManager 代理管理器
     * @param DesiredCapabilities|null $capabilities 浏览器功能配置
     * @param array $options 其他选项
     * @param bool $isW3cCompliant 是否遵循W3C标准
     * @param string|null $userAgent 用户代理字符串
     * @param string|null $country 国家代码
     * @param string|null $timezone 时区
     * @param string|null $locale 本地化设置
     * @param string|null $language 语言
     * @param float|null $latitude 纬度
     * @param float|null $longitude 经度
     * @param float $accuracy 准确度
     * @param string|null $proxy 代理
     * @param array $chromeArguments Chrome启动参数
     */
    public function __construct(
        protected ?DesiredCapabilities $capabilities = null,
        protected ?array $options = [],
        protected bool $isW3cCompliant = true,
        protected ?string $userAgent = null,
        protected ?string $country = null,
        protected ?string $timezone = null,
        protected ?string $locale = null,
        protected ?string $language = null,
        protected ?float $latitude = null,
        protected ?float $longitude = null,
        protected float $accuracy = 1,
        protected ?string $proxy = null,
        protected array $chromeArguments = self::DEFAULT_CHROME_ARGUMENTS,
    ) {
        $this->capabilities ??= DesiredCapabilities::chrome();
        $this->userAgent ??= self::DEFAULT_USER_AGENT;

        $this->certificatePath = env('MITM_CERTIFICATE_PATH');
    }

    /**
     * 设置用户代理
     *
     * @param string $userAgent 用户代理字符串
     * @return static
     */
    public function withUserAgent(string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }
    
    /**
     * 设置语言
     *
     * @param string $language 语言代码，如 'en-US'
     * @return static
     */
    public function withLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    /**
     * 设置地理位置坐标
     *
     * @param float $latitude 纬度
     * @param float $longitude 经度
     * @param float $accuracy 精度，默认为1
     * @return static
     */
    public function withLocation(float $latitude, float $longitude, float $accuracy = 1): static
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->accuracy = $accuracy;
        return $this;
    }

    /**
     * 设置纬度
     *
     * @param float $latitude 纬度
     * @return static
     */
    public function withLatitude(float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    /**
     * 设置经度
     *
     * @param float $longitude 经度
     * @return static
     */
    public function withLongitude(float $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    /**
     * 设置区域设置
     *
     * @param string $locale 区域设置如 'en-US'
     * @return static
     */
    public function withLocale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * 设置WebRTC
     *
     * @param bool $webRTC 是否启用WebRTC
     * @return static
     */
    public function withWebRTC(bool $webRTC): static
    {
        $this->webRTC = $webRTC;
        return $this;
    }
    
    /**
     * 设置WebRTC元数据     
     *
     * @param string $webRTCDatas WebRTC元数据
     * @return static
     */
    public function withWebRTCDatas(string $webRTCDatas): static
    {
        $this->webRTCDatas = $webRTCDatas;
        return $this;
    }

    /**
     * 设置WebRTC版本
     *
     * @param string $webRTCVersion WebRTC版本
     * @return static
     */
    public function withWebRTCVersion(string $webRTCVersion): static
    {
        $this->webRTCVersion = $webRTCVersion;
        return $this;
    }

    /**
     * 设置WebRTC平台
     *
     * @param string $webRTCPlatform WebRTC平台
     * @return static
     */
    public function withWebRTCPlatform(string $webRTCPlatform): static
    {
        $this->webRTCPlatform = $webRTCPlatform;
        return $this;
    }

    /**
     * 设置CPU
     *
     * @param string $cpu CPU
     * @return static
     */
    public function withCPU(string $cpu): static
    {
        $this->cpu = $cpu;
        return $this;
    }
    
    /**
     * 设置RAM
     *
     * @param string $ram RAM
     * @return static
     */
    public function withRAM(string $ram): static
    {
        $this->ram = $ram;
        return $this;
    }
    
    /**
     * 设置显卡
     *
     * @param string $gpu 显卡
     * @return static
     */
    public function withGPU(string $gpu): static
    {
        $this->gpu = $gpu;
        return $this;
    }

    /**
     * 设置硬盘
     *
     * @param string $disk 硬盘
     * @return static
     */
    public function withDisk(string $disk): static
    {
        $this->disk = $disk;
        return $this;   
    }   


    /**
     * 设置操作系统
     *
     * @param string $os 操作系统
     * @return static
     */
    public function withOS(string $os): static
    {
        $this->os = $os;
        return $this;
    }

    /** 
     * 设置浏览器
     *
     * @param string $browser 浏览器
     * @return static
     */
    public function withBrowser(string $browser): static
    {
        $this->browser = $browser;
        return $this;
    }

    /** 
     * 设置设备名称
     *
     * @param string $deviceName 设备名称
     * @return static
     */
    public function withDeviceName(string $deviceName): static
    {
        $this->deviceName = $deviceName;
        return $this;
    }

    /**
     * 设置MAC地址
     *
     * @param string $macAddress MAC地址
     * @return static
     */
    public function withMacAddress(string $macAddress): static
    {
        $this->macAddress = $macAddress;
        return $this;
    }
    
    
    /**
     * 设置国家
     *
     * @param string $country 国家代码（两字母，如 'US'）
     * @return static
     */
    public function withCountry(string $country): static
    {
        $this->country = $country;
        
        // 根据国家自动设置对应的区域和地理坐标（除非已明确设置）
        if (!isset($this->locale) && isset(self::COUNTRY_TO_LOCALE_MAP[$country])) {
            $this->locale = self::COUNTRY_TO_LOCALE_MAP[$country];
        }
        
        if (!isset($this->latitude) && !isset($this->longitude) && isset(self::COUNTRY_TO_COORDS_MAP[$country])) {
            $coords = self::COUNTRY_TO_COORDS_MAP[$country];
            $this->latitude = $coords['latitude'];
            $this->longitude = $coords['longitude'];
        }
        
        return $this;
    }

    /**
     * 设置时区
     *
     * @param string $timezone 时区ID，如 'America/New_York'
     * @return static
     */
    public function withTimezone(string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * 设置精度
     *
     * @param float $accuracy 精度值
     * @return static
     */
    public function withAccuracy(float $accuracy): static
    {
        $this->accuracy = $accuracy;
        return $this;
    }

    /**
     * 设置代理
     *
     * @param string $proxy 代理地址，格式如 '192.168.1.1:8080'
     * @return static
     */
    public function withProxy(string $proxy): static
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * 设置额外选项
     *
     * @param array $options 选项数组
     * @return static
     */
    public function withOptions(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * 设置Chrome启动参数
     *
     * @param array $arguments Chrome启动参数数组
     * @return static
     */
    public function withChromeArguments(array $arguments): static
    {
        $this->chromeArguments = $arguments;
        return $this;
    }

    /**
     * 添加Chrome启动参数
     *
     * @param string|array $arguments 要添加的参数
     * @return static
     */
    public function addChromeArguments(string|array $arguments): static
    {
        if (is_string($arguments)) {
            $this->chromeArguments[] = $arguments;
        } else {
            $this->chromeArguments = array_merge($this->chromeArguments, $arguments);
        }
        return $this;
    }

    /**
     * 设置证书路径
     *
     * @param string $certificatePath 证书文件路径
     * @return static
     */
    public function withCertificatePath(string $certificatePath): static
    {
        $this->certificatePath = $certificatePath;
        return $this;
    }

    /**
     * 使用IP信息创建客户端
     * 
     * 此方法会自动获取IP信息并用它来配置浏览器，覆盖现有设置
     * 
     * @param string|null $host Selenium服务器地址
     * @return Client 配置好的浏览器客户端
     */
    public function createClientWithIpInfo(?string $host = 'http://127.0.0.1:4444/wd/hub'): Client
    {
        // 获取IP信息
        $this->response = $this->getIpinfo($this->proxy);


        // 检查IP信息是否有效且包含必要字段
        if (!empty($this->response->json('country'))) {
            $this->withCountry($this->response->json('country'));
        }

        if (!empty($this->response->json('timezone'))) {
            $this->withTimezone($this->response->json('timezone'));
        }
        
        if (!empty($this->response->json('latitude'))) {   
            $this->withLatitude(is_float($this->response->json('latitude')) 
                ? $this->response->json('latitude') 
                : (float)$this->response->json('latitude'));
        }

        if (!empty($this->response->json('longitude'))) {
            $this->withLongitude(is_float($this->response->json('longitude')) 
                ? $this->response->json('longitude') 
                : (float)$this->response->json('longitude'));
        }
        // 创建客户端
        return $this->createClient($host);
    }
    
    /**
     * 创建浏览器客户端
     *
     * @param string|null $host Selenium服务器地址
     * @return Client 配置好的浏览器客户端
     */
    public function createClient(?string $host = 'http://127.0.0.1:4444/wd/hub'): Client
    {
        $chromeOptions = new ChromeOptions();

        // 添加Chrome启动参数
        $chromeOptions->addArguments($this->chromeArguments);


        // 如果指定了证书路径，添加到 Chrome 选项中
        $this->configureSslCertificates($chromeOptions);
        
        // 设置接受不安全证书
        $this->capabilities->setCapability('acceptInsecureCerts', true);
        
        // 设置代理（如果提供并且格式有效）
        if ($this->proxy && $this->isValidProxyFormat($this->proxy)) {
            // $this->capabilities->setCapability(WebDriverCapabilityType::PROXY, [
            //     'proxyType' => 'manual',
            //     'httpProxy' => $this->proxy,
            //     'sslProxy' => $this->proxy,
            // ]);

            // 配置代理
            $chromeOptions->addArguments([
                "--proxy-server={$this->proxy}",
            ]);
        }

        // 设置Chrome选项到Capabilities
        $this->capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        // 创建客户端
        $client = Client::createSeleniumClient(
            host: $host,
            capabilities: $this->capabilities,
            options: $this->options
        );

        // 使用CDP设置浏览器特性
        $this->configureBrowserWithCDP($client);
        
        // 执行之前准备的JavaScript脚本
        $this->executeAllPendingScripts($client);
        
        return $client;
    }

        /**
     * 配置 Chrome 以处理 SSL 证书
     */
    protected function configureSslCertificates(ChromeOptions $chromeOptions): void
    {
        // 1. 设置证书策略
        if ($this->certificatePath && file_exists($this->certificatePath)) {
            // 创建临时目录存储证书策略
            $tmpDir = sys_get_temp_dir() . '/chrome-cert-' . uniqid();
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
            
            // 复制证书到目标位置
            $certFile = $tmpDir . '/mitmproxy-cert.pem';
            copy($this->certificatePath, $certFile);
            
            // 添加证书相关参数
            $chromeOptions->addArguments([
                "--ignore-certificate-errors", 
                "--allow-insecure-localhost",
            ]);
            
            // 添加证书路径
            $chromeOptions->addExtensions([$certFile]);
        } else {
            // 如果没有证书文件，使用最基本的忽略错误参数
            $chromeOptions->addArguments([
                "--ignore-certificate-errors",
            ]);
        }
    }

    /**
     * 执行所有待执行的JavaScript脚本
     *
     * @param Client $client Panther客户端
     * @return void
     */
    protected function executeAllPendingScripts(Client $client): void
    {
        foreach ($this->pendingScripts as $script) {
            try {
                $client->executeScript($script);
            } catch (\Exception $e) {
                // 忽略执行错误
            }
        }
        
        // 清空待执行脚本
        $this->pendingScripts = [];
    }

    /**
     * 检查代理格式是否有效
     * 
     * @param string $proxy 代理地址
     * @return bool 是否有效
     */
    protected function isValidProxyFormat(string $proxy): bool
    {
        // 简单检查格式：IP:端口 或 主机名:端口
        return (bool)preg_match('/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9]):\d{1,5}$/', $proxy) 
            || (bool)preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{1,5}$/', $proxy);
    }

    /**
     * 使用Chrome DevTools Protocol配置浏览器
     *
     * @param Client $client Panther客户端
     * @return void
     */
    protected function configureBrowserWithCDP(Client $client): void
    {
        // 获取原始的WebDriver实例
        $driver = $client->getWebDriver();
                    
        // 创建ChromeDevToolsDriver实例
        $devTools = new ChromeDevToolsDriver($driver);
        
        // 设置地理位置 - 这是有效的CDP命令
        $this->configureGeolocation($devTools);
        
        // 设置时区 - 这是有效的CDP命令
        $this->configureTimezone($devTools);
        
        // 设置区域设置 - 这是有效的CDP命令
        $this->configureLocale($devTools);
        
        // 设置用户代理、平台和接受语言 - 使用标准的Network.setUserAgentOverride命令
        // 注意：platform参数可用于模拟操作系统
        $this->configureUserAgentWithPlatform($devTools);
        
        // 注入JavaScript以禁用WebRTC（如果需要）
        if ($this->webRTC === false) {
            $this->injectWebRTCDisabler($client);
        }
    }
    
    /**
     * 配置用户代理和平台设置
     *
     * @param ChromeDevToolsDriver $devTools CDP驱动
     * @return void
     */
    protected function configureUserAgentWithPlatform(ChromeDevToolsDriver $devTools): void
    {
        // 如果未指定用户代理，则基于操作系统和浏览器构建一个
        if (!$this->userAgent) {
            $this->buildUserAgent();
        }
        
       // 提取用户代理中的Chrome版本
        $chromeVersion = '133';
        if (preg_match('/Chrome\/([0-9\.]+)/', $this->userAgent, $matches)) {
            $chromeVersion = $matches[1];
        }
        
        $devTools->execute('Emulation.setUserAgentOverride', [
            'userAgent' => $this->userAgent,
            'acceptLanguage' => $this->locale ?? 'en-US',
            'platform' => $this->os ?? 'Windows',
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
                'platform' => $this->os ?? 'Windows',
                'platformVersion' => '10.0.0',
                'architecture' => 'x86',
                'model' => '',
                'mobile' => false,
            ]
        ]);
            
    }
    
    /**
     * 注入JavaScript修改用户代理
     *
     * @param string $userAgent 用户代理字符串
     * @param string $platform 平台
     * @return void
     */
    protected function injectUserAgentScript(string $userAgent, string $platform): void
    {
        $script = <<<JS
        Object.defineProperty(navigator, 'userAgent', {
            get: function () { return '$userAgent'; }
        });
        Object.defineProperty(navigator, 'platform', {
            get: function () { return '$platform'; }
        });
        JS;
        
        // 这需要在浏览器加载页面时执行，所以我们会在createClient完成后调用
        // 暂存脚本，等待浏览器准备好时执行
        $this->pendingScripts[] = $script;
    }
    
    /**
     * 根据当前设置构建用户代理字符串
     *
     * @return void
     */
    protected function buildUserAgent(): void
    {
        $osVersion = '';
        $browserVersion = '';
        
        // 设置操作系统版本
        switch ($this->os) {
            case 'Windows':
                $osVersion = 'Windows NT 10.0; Win64; x64';
                break;
            case 'Mac':
            case 'macOS':
                $osVersion = 'Macintosh; Intel Mac OS X 10_15_7';
                break;
            case 'Linux':
                $osVersion = 'X11; Linux x86_64';
                break;
            case 'Android':
                $osVersion = 'Linux; Android 13; SM-A715F';
                break;
            case 'iOS':
                $osVersion = 'iPhone; CPU iPhone OS 17_1 like Mac OS X';
                break;
            default:
                $osVersion = 'Windows NT 10.0; Win64; x64';
        }
        
        // 设置浏览器版本
        switch ($this->browser) {
            case 'Chrome':
                $browserVersion = 'Chrome/133.0.6943.54 Safari/537.36';
                break;
            case 'Edge':
                $browserVersion = 'Edg/133.0.2623.71 Chrome/133.0.6943.54 Safari/537.36';
                break;
            case 'Firefox':
                $browserVersion = 'Firefox/125.0';
                break;
            case 'Safari':
                $browserVersion = 'Version/17.4 Safari/605.1.15';
                break;
            default:
                $browserVersion = 'Chrome/133.0.6943.54 Safari/537.36';
        }
        
        // 构建最终的用户代理字符串
        $this->userAgent = "Mozilla/5.0 ($osVersion) AppleWebKit/537.36 (KHTML, like Gecko) $browserVersion";
    }
    
    /**
     * 通过JavaScript注入禁用WebRTC
     *
     * @param Client $client Panther客户端
     * @return void
     */
    protected function injectWebRTCDisabler(Client $client): void
    {
        // 注入JavaScript来禁用WebRTC
        $script = <<<'JS'
        // 替换原生的WebRTC方法
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia = function() {
                return new Promise(function(resolve, reject) {
                    reject(new DOMException('Permission denied', 'NotAllowedError'));
                });
            };
        }
        
        // 禁用RTCPeerConnection
        if (window.RTCPeerConnection) {
            window.RTCPeerConnection = function() {
                throw new Error('WebRTC is disabled');
            };
        }
        
        // 禁用旧版API
        if (navigator.getUserMedia) {
            navigator.getUserMedia = function() {
                throw new Error('WebRTC is disabled');
            };
        }
        JS;
        
        // 执行JavaScript
        $client->executeScript($script);
    }
    
    /**
     * 配置用户代理（此方法已被configureUserAgentWithPlatform替代，保留以兼容现有代码）
     *
     * @param ChromeDevToolsDriver $devTools CDP驱动
     * @return void
     */
    protected function configureUserAgent(ChromeDevToolsDriver $devTools): void
    {
        // 调用新方法
        $this->configureUserAgentWithPlatform($devTools);
    }
    
    /**
     * 配置时区
     *
     * @param ChromeDevToolsDriver $devTools CDP驱动
     * @return void
     */
    protected function configureTimezone(ChromeDevToolsDriver $devTools): void
    {
        $devTools->execute('Emulation.setTimezoneOverride', [
            'timezoneId' => $this->timezone
        ]);
    }
    
    /**
     * 配置区域设置
     *
     * @param ChromeDevToolsDriver $devTools CDP驱动
     * @return void
     */
    protected function configureLocale(ChromeDevToolsDriver $devTools): void
    {
        // 确定使用的locale
        if (!$this->locale) {
            $this->locale = self::COUNTRY_TO_LOCALE_MAP[$this->country] ?? 'en-US';
        }

        $devTools->execute('Emulation.setLocaleOverride', [
            'locale' => $this->locale
        ]);
    }
    
    /**
     * 配置地理位置
     *
     * @param ChromeDevToolsDriver $devTools CDP驱动
     * @return void
     */
    protected function configureGeolocation(ChromeDevToolsDriver $devTools): void
    {
        $devTools->execute('Emulation.setGeolocationOverride', [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy
        ]);
    }

    /**
     * 获取IP信息
     *
     * @param string|null $proxy 要使用的代理
     * @param int $retries 重试次数
     * @return Response IP信息
     */
    public function getIpinfo(?string $proxy = null): Response
    {
        return Http::retry(3, 100)->withHeaders([
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'Accept-Language' => 'en,zh-CN;q=0.9,zh;q=0.8',
            'Connection' => 'keep-alive',
            'Sec-Ch-Ua' => '"Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-site',
            'User-Agent' => $this->userAgent ?? self::DEFAULT_USER_AGENT,
        ])
        ->timeout(10) // 设置超时时间
        ->withOptions(array_filter([
            'proxy' => $proxy,
            'verify' => false, // 禁用SSL验证，在某些环境可能需要
        ]))
        ->get('http://api.ip.cc/');
    }

    /**
     * 获取证书的 SPKI 指纹
     * 
     * @return string|null 证书指纹
     */
    protected function getCertificateFingerprint(): ?string
    {
        if (!$this->certificatePath || !file_exists($this->certificatePath)) {
            return null;
        }
        
        // 尝试从证书中提取 SPKI 指纹
        try {
            $certData = file_get_contents($this->certificatePath);
            $cert = openssl_x509_read($certData);
            if ($cert) {
                $pubkey = openssl_get_publickey($cert);
                if ($pubkey) {
                    $keyData = openssl_pkey_get_details($pubkey);
                    if (isset($keyData['key'])) {
                        // 计算 SPKI SHA-256 指纹
                        return base64_encode(hash('sha256', $keyData['key'], true));
                    }
                }
            }
        } catch (\Exception $e) {
        }
        
        return null;
    }

    public function toArray(): array
    {
        return [
            'country' => $this->country,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'proxy' => $this->proxy,
            'userAgent' => $this->userAgent,
            'chromeArguments' => $this->chromeArguments,
            'options' => $this->options,
            'isW3cCompliant' => $this->isW3cCompliant,
            'capabilities' => $this->capabilities->toArray(),
            'response' => $this->response->json(),
            'webRTC' => $this->webRTC,
            'webRTCPlatform' => $this->webRTCPlatform,
            'cpu' => $this->cpu,
            'ram' => $this->ram,
            'gpu' => $this->gpu,
            'disk' => $this->disk,
            'os' => $this->os,
            'browser' => $this->browser,
        ];
    }
}
