<?php

namespace App\Services\Browser\Config;

use Facebook\WebDriver\WebDriverPlatform;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
class UserAgentConfig
{
    /**
     * 默认的用户代理字符串
     */
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.6943.54 Safari/537.36';

    public function __construct(
        public readonly string $userAgent = self::DEFAULT_USER_AGENT,
        public readonly ?string $platform = WebDriverPlatform::WINDOWS,
        public readonly ?string $browserName = WebDriverBrowserType::CHROME,
        public readonly ?string $browserVersion = '133.0.6943.54'
    ) {}
    
    /**
     * 根据操作系统和浏览器生成用户代理
     */
    public static function create(
        string $os = 'Windows', 
        string $browser = 'Chrome'
    ): self
    {
        $osVersion = '';
        $browserVersion = '';
        
        // 设置操作系统版本
        switch ($os) {
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
        switch ($browser) {
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
        $userAgent = "Mozilla/5.0 ($osVersion) AppleWebKit/537.36 (KHTML, like Gecko) $browserVersion";
        
        return new self(
            userAgent: $userAgent,
            platform: $os,
            browserName: $browser,
            browserVersion: preg_match('/Chrome\/([0-9\.]+)/', $browserVersion, $matches) ? $matches[1] : '133.0.6943.54'
        );
    }
}