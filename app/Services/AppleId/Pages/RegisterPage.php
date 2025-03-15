<?php

namespace App\Services\AppleId\Pages;

use Weijiajia\DecryptVerificationCode\CloudCode\CloudCodeConnector;
use Weijiajia\SaloonphpAppleClient\Exception\CaptchaException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use RuntimeException;
use Weijiajia\DecryptVerificationCode\Exception\DecryptCloudCodeException;
use Weijiajia\SaloonphpAppleClient\Exception\AccountException;

class RegisterPage extends Page
{
    protected ?CloudCodeConnector $cloudCodeConnector = null;

    protected array $selectors = [
        'createIframe' => 'iframe#aid-create-widget-iFrame',
        'createForm' => 'form#create',
        'firstNameInput' => 'div.form-textbox input[name="firstName"]',
        'lastNameInput' => 'div.form-textbox input[name="lastName"]',
        'countrySelect' => 'form#create select[name="countrySelect"]',
        'monthSelect' => 'select[data-testid="select-month"]',
        'daySelect' => 'select[data-testid="select-day"]',
        'yearSelect' => 'select[data-testid="select-year"]',
        'emailInput' => 'div.form-textbox input[name="appleId"]',
        'passwordInput' => 'div.form-textbox input[name="password"]',
        'confirmPasswordInput' => 'div.form-textbox input[name="confirmPassword"]',
        'phoneNumberInput' => 'div.form-textbox input[name="phoneNumber"]',
        'announcementsCheckbox' => '#create > div > div:nth-child(9) > div:nth-child(1) > label > span.form-checkbox-indicator',
        'appsUpdateCheckbox' => '#create > div > div:nth-child(9) > div:nth-child(2) > label > span.form-checkbox-indicator',
        'captchaImage' => 'div.captcha div.captcha-container img.img',
        'captchaInput' => 'div.input input[name="captcha"]',
        'captchaRefreshButton' => '#create > div > div.captcha > div.input > div.button-wrapper > button:nth-child(1)',
        'captchaError' => 'form#create div.captcha label.form-textbox-label',
        'continueButton' => '#content > div > div > div > fieldset > div > div:nth-child(2) > button',
        'pageError' => '#create > div > div.generic-error > div',
        'formMessage' => 'div.form-textbox  .form-message-wrapper > span.form-message',
    ];

    protected static string $pageError = '#create > div > div.generic-error > div';


    protected function showFormMessage(): void
    {
        $formMessages = $this->client->findElements(WebDriverBy::cssSelector($this->selectors['formMessage']));

        $message = [];
        if(count($formMessages) > 0){
            foreach ($formMessages as $formMessage) {

                if($formMessage->getText() === 'Cannot Verify Email Address'){
                    throw new AccountException('Cannot Verify Email Address');
                }

                $message[] = $formMessage->getText();
            }

            throw new RuntimeException(message:json_encode($message,JSON_UNESCAPED_UNICODE));
        }

    }

    protected function showException(): void
    {
        try {

            $this->waitForElement(self::$pageError,5);

            $error = $this->client->findElement(WebDriverBy::cssSelector(self::$pageError));

            $error->getText() && throw new RuntimeException($error->getText());

        }catch(TimeoutException|NoSuchElementException|\Facebook\WebDriver\Exception\UnrecognizedExceptionException $e){
            return;
        }

    }
    
    public function getCloudCodeConnector(): CloudCodeConnector
    {
        return $this->cloudCodeConnector ??= new CloudCodeConnector();
    }

    public function getTitle(): string
    {
        return 'Create Your Apple Account';
    }
    
    public function isLoaded(): bool
    {
        try {

            $this->switchToIframe($this->selectors['createIframe']);
            $this->waitForElement($this->selectors['createForm']);

            return true;
        } catch (TimeoutException|NoSuchElementException $e) {
            return false;
        }
    }
    
    // 填充个人信息
    public function fillPersonalInfo(string $firstName, string $lastName, string $country): self
    {
        $this->fillInput($this->selectors['firstNameInput'], $firstName);
        $this->fillInput($this->selectors['lastNameInput'], $lastName);

        $this->selectOption($this->selectors['countrySelect'], $country);
        
        return $this;
    }
    
    // 填充生日信息
    public function fillBirthday(string $month, string $day, string $year): self
    {
        $this->selectOption($this->selectors['monthSelect'], $month);
        $this->selectOption($this->selectors['daySelect'], $day);
        $this->selectOption($this->selectors['yearSelect'], $year);
        
        return $this;
    }
    
    // 填充账号信息
    public function fillAccountInfo(string $email, string $password, string $confirmPassword, string $phoneNumber): self
    {
        $this->fillInput($this->selectors['emailInput'], $email);
        $this->fillInput($this->selectors['passwordInput'], $password);
        $this->fillInput($this->selectors['confirmPasswordInput'], $confirmPassword);
        $this->fillInput($this->selectors['phoneNumberInput'], $phoneNumber);
        
        return $this;
    }
    
    // 勾选服务条款
    public function acceptTerms(): self
    {
        $this->checkBox($this->selectors['announcementsCheckbox']);
        $this->checkBox($this->selectors['appsUpdateCheckbox']);
        
        return $this;
    }
    
    // 解析验证码
    public function solveCaptcha(): self
    {
        $this->client->findElement(WebDriverBy::cssSelector($this->selectors['captchaRefreshButton']))->click();
        $this->waitForElement($this->selectors['captchaImage']);
        $captchaImage = $this->client->findElement(WebDriverBy::cssSelector($this->selectors['captchaImage']));
        $imageSource = $captchaImage->getAttribute('src');
        
        $response = $this->getCloudCodeConnector()->decryptCloudCode(
            token: 'Hb1SOEObuMJyjEViLsaPI5M3SHCR1K-kToy5JKagxU0',
            type: '10110',
            image: $imageSource,
        );
        
        $this->fillInput($this->selectors['captchaInput'], $response->getCode());
        
        return $this;
    }

    public function shouwCaptchaError()
    {
        
        try {

            $captchaError = $this->client->findElement(WebDriverBy::cssSelector($this->selectors['captchaError']));
            $captchaError->getText() && throw new CaptchaException($captchaError->getText());

        } catch (TimeoutException|NoSuchElementException $e) {
            return false;
        }
    }
    
    // 提交表单，导航到邮箱验证页面
    public function submit(int $maxAttempts = 3): VerifyEmailPage
    {
        $this->showFormMessage();

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {


                // 解析验证码
                $this->solveCaptcha();
                

                //等待 判断 按钮是否包含  disabled 
                $this->client->waitForEnabled($this->selectors['continueButton']);
                // 点击继续按钮
                $this->client->findElement(WebDriverBy::cssSelector($this->selectors['continueButton']))->click();
                
                

                $this->showException();

                // 检查是否有验证码错误
                $this->shouwCaptchaError();
                // 成功提交表单
                
                return new VerifyEmailPage($this->client);
            } catch (CaptchaException|DecryptCloudCodeException $e) {
                // 如果验证码错误，则重新解析验证码
                sleep(1);
            }
        }
        
        throw new MaxRetryAttemptsException('验证码识别失败，已达到最大尝试次数');
    }
    
    private function switchToIframe(string $selector): void
    {
        $this->client->switchTo()->defaultContent();
        $this->waitForElement($selector);
        $iframe = $this->client->findElement(WebDriverBy::cssSelector($selector));
        $this->client->switchTo()->frame($iframe);
    }
}