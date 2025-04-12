<?php

namespace App\Services\AppleId;

use App\Models\Email;
use App\Models\Phone;
use App\Services\AppleId\Pages\PageManager;
use App\Services\AppleId\Pages\VerifyPhonePage;
use App\Services\Helper\Helper;
use App\Services\Trait\HasPhone;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeException;
use libphonenumber\PhoneNumberFormat;
use App\Services\AppleId\Pages\ICloudTermsConditionsPage;
use App\Models\Appleid;
use App\Enums\EmailStatus;
use Weijiajia\SaloonphpAppleClient\Exception\AccountException;
class AppleIdRegistrationForBrowser
{
    use HasPhone;

    public function __construct(
        protected PageManager $pageManager,
        protected string $country,
        protected Email $email,
        protected ?string $firstName = null,
        protected ?string $lastName = null,
        protected ?string $birthMonth = null,
        protected ?string $birthDay = null,
        protected ?string $birthYear = null,
        protected ?string $password = null,

    ) {

        $this->firstName ??= fake()->firstName();
        $this->lastName ??= fake()->lastName();
        $this->birthMonth ??= fake()->month();
        $this->birthDay ??= fake()->dayOfMonth();
        $this->birthYear ??= date('Y', random_int(strtotime('1950-01-01'), strtotime('2000-12-31')));
        $this->password ??= Helper::generatePassword();
    }


    public function register(): void
    {
        try{

            $this->phone = $this->getPhone();

            // 1. 导航到iCloud首页
            $homePage = $this->pageManager->navigateToHomePage();

            // 2. 点击登录，进入认证页面
            $authPage = $homePage->navigateToSignInPage();

            // 3. 点击创建账号，进入注册页面
            $registerPage = $authPage->navigateToRegisterPage();

            // 4. 填写注册表单并提交
            $verifyEmailPage = $registerPage
                ->fillPersonalInfo($this->firstName, $this->lastName, $this->country)
                ->fillBirthday($this->birthMonth, $this->birthDay, $this->birthYear)
                ->fillAccountInfo($this->email->email, $this->password, $this->password, $this->phone->getPhoneNumberService()->format(PhoneNumberFormat::NATIONAL))
                ->acceptTerms()
                ->submit();

            // 5. 验证邮箱
            $emailCode = Helper::attemptEmailVerificationCode(
                $this->email->email,
                $this->email->email_uri
            );

            $verifyPhonePage = $verifyEmailPage
                ->inputVerificationCode($emailCode)
                ->clickContinue();

            // 6. 验证手机号
            $iCloudTermsConditionsPage = $this->verifyPhone($verifyPhonePage);

            $iCloudTermsConditionsPage->acceptTerms();

            $appleid = Appleid::create([
                'email'                   => $this->email->email,
                'email_uri'               => $this->email->email_uri,
                'phone'                   => $this->phone->phone,
                'phone_uri'               => $this->phone->phone_address,
                'password'                => $this->password,
                'first_name'              => $this->firstName,
                'last_name'               => $this->lastName,
                'country'                 => $this->country,
                'phone_country_code'      => $this->phone->country_code,
                'phone_country_dial_code' => $this->phone->country_dial_code,
            ]);

            $this->email->update(['status' => EmailStatus::REGISTERED]);
            $this->phone->update(['status' => Phone::STATUS_BOUND]);

            var_dump($appleid->toArray());
        }catch(AccountException|\Throwable $e){

            $this->email && $this->email->update(['status' => EmailStatus::FAILED]);
            $this->phone && $this->phone->update(['status' => Phone::STATUS_NORMAL]);

            $filename = storage_path("logs/screenshots/{$this->email->email}_appleid_registration_failed_{time()}.png");
            @mkdir(dirname($filename), 0755, true);

            // 如果注册失败，则生成截图
            $this->pageManager->getClient()->takeScreenshot($filename);

            $this->email->createLog($e->getMessage(),[
                'email' => $this->email->email,
                'phone' => $this->phone->phone,
                'password' => $this->password,
                'country' => $this->country,
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'birth_month' => $this->birthMonth,
                'birth_day' => $this->birthDay,
                'birth_year' => $this->birthYear,
            ]);

            throw $e;
        }
        // 注册成功
    }


    /**
     * 处理手机验证过程，包括可能的多次尝试和手机号变更
     */
    private function verifyPhone(VerifyPhonePage $verifyPhonePage, int $maxAttempts = 5): ICloudTermsConditionsPage
    {
        for($i = 0; $i < $maxAttempts; $i++){

            try {

                /**
                 * @var Phone $phone
                 */
                $phoneCode = Helper::attemptPhoneVerificationCode($this->phone->phone_address);

                return $verifyPhonePage
                    ->inputVerificationCode($phoneCode)
                    ->clickContinue();

            } catch (VerificationCodeException $e) {

                $this->addActiveBlacklistIds($this->phone->id);
                $this->usedPhones[] = $this->phone->id;
                $this->phone->update(['status' => Phone::STATUS_NORMAL]);

                $this->phone = $this->getPhone();

                // 否则尝试切换到新手机号页面，然后重新验证
                $newPhonePage = $verifyPhonePage->navigateToNewPhonePage();
                $verifyPhonePage = $newPhonePage
                    ->enterPhoneNumber($phone->getPhoneNumberService()->format(PhoneNumberFormat::NATIONAL))
                    ->clickContinue();
            }
        }

        throw new MaxRetryAttemptsException("尝试 {$maxAttempts} 次后，手机验证码仍然错误");
    }
}
