<?php

namespace App\Services\AppleId\Pages;

use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverBy;

class NewPhonePage extends RegisterPage
{
    protected array $selectors = [
        'title' => '#updatePhone > div > div.inline-header-centered > h1',
        'phoneNumberInput' => '#updatePhone input[name=phoneNumber]',
        'continueButton' => '#content > div > div > div > fieldset > div > div > button:nth-child(2)',
        'actionButtons' => 'div.phone-verify-description div.action-icon-button-container div.text-typography-body-reduced-tight'
    ];

    public function getTitle(): string
    {
        return 'New phone number';
    }
    
    public function isLoaded(): bool
    {
        try {
            return (bool) $this->waitForElement($this->selectors['title']);
        } catch (TimeoutException|NoSuchElementException $e) {
            return false;
        }
    }
    
    public function enterPhoneNumber(string $phoneNumber): self
    {
        $this->waitForElement($this->selectors['phoneNumberInput']);
        $this->client->findElement(WebDriverBy::cssSelector($this->selectors['phoneNumberInput']))
            ->click()->clear()->sendKeys($phoneNumber);
        return $this;
    }
    
    public function clickContinue(): VerifyPhonePage
    {
        $this->waitForElement($this->selectors['continueButton']);
        $this->client->findElement(WebDriverBy::cssSelector($this->selectors['continueButton']))->click();
        return new VerifyPhonePage($this->client);
    }
}