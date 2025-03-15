<?php

namespace App\Services\AppleId\Pages;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\WebDriverBy;

class ICloudPage extends Page
{
    protected string $url = 'https://www.icloud.com/';

    public function getTitle(): string
    {
        return 'iCloud';
    }

    protected static string $signInSelector = '#root > ui-main-pane > div > div.root-component > div > div > main > div > div.landing-page-content > ui-button';
    
    public function isLoaded(): bool
    {
        try {
            return (bool) $this->waitForElement(self::$signInSelector);
        } catch (TimeoutException|NoSuchElementException $e) {
            return false;
        }
    }
    
    // 点击登录按钮，导航到身份验证页面
    public function navigateToSignInPage(): SignInPage
    {
        $this->client->findElement(WebDriverBy::cssSelector(self::$signInSelector))->click();
        return new SignInPage($this->client);
    }
}