<?php

namespace App\Services\AppleId\Pages;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\WebDriverSelect;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\DomCrawler\Crawler;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\UnrecognizedExceptionException;
use Facebook\WebDriver\WebDriverElement;
abstract class Page
{

    protected array $selectors = [];
    
    public function __construct(protected Client $client)
    {
        $this->client = $client;

        if (!$this->isLoaded()) {
            throw new \RuntimeException("page {$this->getTitle()} not loaded");
        }
    }

    public function isAssertValue(string $selector, string $value): bool
    {
        $element = $this->client->findElement(WebDriverBy::cssSelector($selector));
        return $element->getText() === $value;
    }

    public function isAssertIsEmpty(string $selector): bool
    {
        $element = $this->client->findElement(WebDriverBy::cssSelector($selector));
        return $element->getText() === '';
    }

    public function isAssertIsNotEmpty(string $selector): bool
    {
        $element = $this->client->findElement(WebDriverBy::cssSelector($selector));
        return $element->getText() !== '';
    }


    public function getElementWaitForElement(string $selector, int $timeout = 5): WebDriverElement
    {
        try {

            $this->client->waitForVisibility($selector, $timeout);

            return $this->client->findElement(WebDriverBy::cssSelector($selector));

        } catch (TimeoutException|NoSuchElementException $e) {
            throw $e;
        }
        
    }


    public function isAssertValueWaitForElement(string $selector, string $value, int $timeout = 5): bool
    {
        try {

            $this->waitForElement($selector, $timeout);
            
            return $this->isAssertValue($selector, $value);

        } catch (TimeoutException | NoSuchElementException|UnrecognizedExceptionException $e) {
            return false;
        }
    }
    
    // 常用方法，如等待元素、填充输入框、选择选项等
    /**
     * @throws NoSuchElementException
     * @throws TimeoutException
     */
    protected function waitForElement(string $selector, int $timeoutInSeconds = 30): Crawler
    {
        try {

            return $this->client->waitForVisibility($selector, $timeoutInSeconds);

        } catch (TimeoutException|NoSuchElementException $e) {
            throw $e;
        }
    }
    
    protected function fillInput(string $selector, string $value): void
    {
        $element = $this->client->findElement(WebDriverBy::cssSelector($selector));
        $element->click()->clear()->sendKeys($value);
        sleep(1);
    }
    
    protected function selectOption(string $selector, string $visibleText): void
    {
        $element = $this->client->findElement(WebDriverBy::cssSelector($selector));
        $element->click();
        $select = new WebDriverSelect($element);
        $select->selectByValue($visibleText);
        sleep(1);
    }
    
    protected function checkBox(string $selector): void
    {
        $element = $this->client->findElement(WebDriverBy::cssSelector($selector));
        if (!$element->isSelected()) {
            $element->click();
        }
        sleep(1);
    }
    
    protected function takeScreenshot(string $name): string
    {
        $filename = storage_path("logs/screenshots/{$name}_" . date('YmdHis') . '.png');
        @mkdir(dirname($filename), 0755, true);
        $this->client->takeScreenshot($filename);
        return $filename;
    }
    
    // 检查当前页面是否正确加载
    abstract public function isLoaded(): bool;

    abstract public function getTitle(): string;
}