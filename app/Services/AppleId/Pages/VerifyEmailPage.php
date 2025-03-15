<?php

namespace App\Services\AppleId\Pages;

use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeException;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverBy;

class VerifyEmailPage extends RegisterPage
{
    protected array $selectors = [
        'title' => '#verifyEmail > div > div.inline-header-centered > h1',
        'codeInputs' => '#verifyEmail > div > div.code-wrapper-centered > div > div > input',
        'continueButton' => '#content > div > div > div > fieldset > div > div:nth-child(2) > button',
        'errorMessage' => '#form-security-code-error-1741949318144-0 > span.form-message',
        'resendButton' => '#verifyEmail > div > span > button > span'
    ];

    public function getTitle(): string
    {
        return 'Verify your email address';
    }
    
    public function isLoaded(): bool
    {
        try {
            return (bool) $this->waitForElement($this->selectors['title']);
        } catch (TimeoutException|NoSuchElementException $e) {
            return false;
        }
    }
    

    
    public function inputVerificationCode(string $code): self
    {
        $inputFields = $this->client->findElements(WebDriverBy::cssSelector($this->selectors['codeInputs']));
        
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

            $this->waitForElement($this->selectors['errorMessage'],5);
            $error = $this->client->findElement(WebDriverBy::cssSelector($this->selectors['errorMessage']));
            $error->getText() && throw new VerificationCodeException($error->getText());
        } catch (TimeoutException|NoSuchElementException $e) {
            return;
        }
    }
    
    public function clickContinue(int $maxAttempts = 5): VerifyPhonePage
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {

            try {

                $this->waitForElement($this->selectors['continueButton']);
                $this->client->findElement(WebDriverBy::cssSelector($this->selectors['continueButton']))->click();
                
                $this->verificationCodeException();

                $this->showException();

                // 没有找到错误信息，验证通过
                return new VerifyPhonePage($this->client);

            } catch (VerificationCodeException $e) {
                
                
                // 发送新验证码
                $this->client->findElement(WebDriverBy::cssSelector($this->selectors['resendButton']))->click();
                sleep(1);
            }
        }
        
        throw new MaxRetryAttemptsException("尝试 {$maxAttempts} 次后，邮箱验证码仍然错误");
    }
}