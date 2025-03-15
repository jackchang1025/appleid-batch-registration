<?php

namespace App\Services\AppleId\Pages;


use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use \Facebook\WebDriver\Exception\UnrecognizedExceptionException;
use App\Services\Exception\RegistrationException;

class VerifyPhonePage extends RegisterPage
{
    protected array $elements = [
        'title' => '#verifyPhone > div > div.inline-header-centered > h1',
        'codeInputs' => '#verifyPhone > div > div.code-wrapper-centered > div > div > input',
        'continueButton' => '#content > div > div > div > fieldset > div > div:nth-child(2) > button',
        'errorMessage' => '#verifyPhone div.form-message-wrapper span.form-message',
        'pageError' => '#verifyPhone > div > div.generic-error > div.text',
        'changePhoneButton' => '#description > button > span'
    ];

    /**
     * 显示异常
     * @throws RegistrationException
     */
    public function showException(): void
    {
        try {

            $this->waitForElement($this->elements['pageError'], 5);
            $error = $this->client->findElement(WebDriverBy::cssSelector($this->elements['pageError']));
            throw new RegistrationException($error->getText());

        } catch (TimeoutException | NoSuchElementException|UnrecognizedExceptionException $e) {
            return;
        }
    }

    public function getTitle(): string
    {
        return 'Verify phone number';
    }
    
    public function isLoaded(): bool
    {
        try {
            return (bool) $this->waitForElement($this->elements['title']);
        } catch (TimeoutException|NoSuchElementException $e) {
            return false;
        }
    }
    

    
    public function inputVerificationCode(string $code): self
    {
        $inputFields = $this->client->findElements(WebDriverBy::cssSelector($this->elements['codeInputs']));
        
        if (count($inputFields) !== 6) {
            throw new \RuntimeException("预期有 6 个输入框，但找到了 " . count($inputFields));
        }
        
        foreach (str_split($code) as $index => $digit) {
            $inputField = $inputFields[$index];
            $inputField->click();
            $inputField->clear();
            $inputField->sendKeys($digit);
            usleep(200000);
        }
        
        return $this;
    }

    protected function verificationCodeException(): void
    {
        try {

            $this->waitForElement($this->elements['errorMessage'], 5);
            $error = $this->client->findElement(WebDriverBy::cssSelector($this->elements['errorMessage']));
            throw new VerificationCodeException($error->getText());

        } catch (TimeoutException | NoSuchElementException|UnrecognizedExceptionException $e) {
            return;
        }
    }
    
    public function clickContinue(): ICloudTermsConditionsPage
    {
        $this->waitForElement($this->elements['continueButton']);
        $this->client->findElement(WebDriverBy::cssSelector($this->elements['continueButton']))->click();
        
        $this->verificationCodeException();

        $this->showException();

        // 没有找到错误信息，验证通过
        return $this->navigateToICloudTermsConditionsPage();
    }

  
    protected function navigateToICloudTermsConditionsPage(): ICloudTermsConditionsPage{

        return new ICloudTermsConditionsPage($this->client);
    }
    
    public function navigateToNewPhonePage(): NewPhonePage
    {
        $this->waitForElement($this->elements['changePhoneButton']);
        $this->client->findElement(WebDriverBy::cssSelector($this->elements['changePhoneButton']))->click();
        
        return new NewPhonePage($this->client);
    }

}