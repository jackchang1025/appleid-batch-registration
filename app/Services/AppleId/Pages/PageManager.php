<?php

namespace App\Services\AppleId\Pages;

use App\Services\ClientFactory;
use Symfony\Component\Panther\Client;

class PageManager
{
    private Client $client;
    private ?Page $currentPage = null;
    
    public function __construct(ClientFactory $clientFactory)
    {
        $this->client = $clientFactory
        ->withProxy(env('SELENIUM_PROXY'))
        ->createClientWithIpInfo(env('SELENIUM_HOST'));

        $this->client->manage()->timeouts()->implicitlyWait(30)->pageLoadTimeout(30);

    }

    public function getClient(): Client
    {
        return $this->client;
    }
    
    // 获取当前页面
    public function getCurrentPage(): ?Page
    {
        return $this->currentPage;
    }
    
    // 设置当前页面
    public function setCurrentPage(Page $page): self
    {
        $this->currentPage = $page;
        return $this;
    }
    
    // 导航到首页
    public function navigateToHomePage(): ICloudPage
    {
        $this->client->get('https://icloud.com');
        $homePage = new ICloudPage($this->client);
        $this->setCurrentPage($homePage);
        return $homePage;
    }
    
    // 获取特定类型的页面
    public function getPage(string $pageClass): Page
    {
        $page = new $pageClass($this->client);
        if (!$page->isLoaded()) {
            throw new \RuntimeException("页面 {$pageClass} 未成功加载");
        }
        $this->setCurrentPage($page);
        return $page;
    }
    
    // 关闭浏览器
    public function quit(): void
    {
        if (isset($this->client)) {
            $this->client->quit();
        }
    }

    public function __destruct()
    {
        $this->quit();
    }
}