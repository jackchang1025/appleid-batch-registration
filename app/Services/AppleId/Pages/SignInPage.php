<?php

namespace App\Services\AppleId\Pages;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Exception\NoSuchElementException;

class SignInPage extends Page
{
    protected static string $createAccountSelector = '#root > ui-main-pane > div > div.root-component > div > div > div > div > div > div.create > a';

    public function getTitle(): string
    {
        return 'Sign in with Apple Account';
    }

    public function isLoaded(): bool
    {
        try {

            $this->waitForElement(self::$createAccountSelector);

            return true;
        } catch (TimeoutException|NoSuchElementException $e) {
            return false;
        }
    }
    
    // 点击创建账号链接，导航到注册页面
    public function navigateToRegisterPage(): RegisterPage
    {
        $this->client->findElement(WebDriverBy::cssSelector(self::$createAccountSelector))->click();

        return new RegisterPage($this->client);
    }
    
}