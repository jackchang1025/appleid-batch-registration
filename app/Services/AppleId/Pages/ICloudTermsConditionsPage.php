<?php

namespace App\Services\AppleId\Pages;

use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\Client;

class ICloudTermsConditionsPage extends Page
{
    public function __construct(Client $client)
    {
        $client->switchTo()->defaultContent();

        parent::__construct($client);
    }
    
    protected array $selectors = [
        'title' => '#root > ui-main-pane > div > div.root-component > div.flex-page-viewport.terms-and-conditions-route.fade-in > div > main > div > div > div.terms-header > div',
        'acceptTermsButton' => '#root > ui-main-pane > div > div.root-component > div.flex-page-viewport.terms-and-conditions-route.fade-in > div > main > div > div > div.terms-footer > div > ui-button.block.primary.icloud-mouse',
        'agreeButton' => '#root > ui-pane > ui-popup > ui-alert-container > ui-alert-footer > ui-alert-actions > ui-button.block.large.primary.icloud-mouse',
    ];

    public function getTitle(): string
    {
        return 'iCloud Terms and Conditions';
    }

    public function isLoaded(): bool
    {
        return (bool) $this->waitForElement($this->selectors['title']);
    }


    public function acceptTerms(): void
    {
        $this->waitForElement($this->selectors['acceptTermsButton']);
        $this->client->findElement(WebDriverBy::cssSelector($this->selectors['acceptTermsButton']))->click();

        $this->waitForElement($this->selectors['agreeButton']);
        $this->client->findElement(WebDriverBy::cssSelector($this->selectors['agreeButton']))->click();
    }
    
}