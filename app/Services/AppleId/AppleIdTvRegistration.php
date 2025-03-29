<?php

namespace App\Services\AppleId;

use App\Enums\EmailStatus;
use App\Models\Appleid;
use App\Models\Email;
use App\Services\Apple;
use App\Services\AppleBuilder;
use App\Services\Helper\Helper;
use GuzzleHttp\Cookie\SetCookie;
use JsonException;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Throwable;
use Weijiajia\SaloonphpAppleClient\Exception\AccountAlreadyExistsException;
use Weijiajia\SaloonphpAppleClient\Exception\AccountException;
use Weijiajia\SaloonphpAppleClient\Exception\CreateAccountException;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Weijiajia\SaloonphpAppleClient\Exception\RegistrationException;
use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeException;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\Data\CreateAccountSrvData;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\Data\ValidateAccountFieldsSrvData;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\Data\ValidateEmailConfirmationCodeSrvResponse;

class AppleIdTvRegistration
{

    protected Apple $apple;
    private ?string $code = null;

    public function __construct(protected AppleBuilder $appleBuilder)
    {

    }

    /**
     * @param Email $email
     * @return Appleid
     * @throws AccountAlreadyExistsException
     * @throws MaxRetryAttemptsException
     * @throws RegistrationException
     * @throws VerificationCodeException
     * @throws JsonException
     * @throws Throwable
     * @throws AccountException
     * @throws CreateAccountException
     */
    public function run(Email $email): Appleid
    {

        // 生成随机个人信息
        $password   = Helper::generatePassword();
        $firstName  = fake()->firstName();
        $lastName   = fake()->lastName();
        $birthMonth = fake()->month();
        $birthDay   = fake()->dayOfMonth($birthMonth);
        $birthYear  = (int)date('Y', random_int(strtotime('1950-01-01'), strtotime('2000-12-31')));

        $appleId = Appleid::make([
            'email'     => $email->email,
            'email_uri' => $email->email_uri,
            'password'  => $password,
        ]);

        $this->apple = $this->appleBuilder->build($appleId);
        $this->apple->withDebug(true);

        try {

            $email->update([
                'status' => EmailStatus::PROCESSING,
            ]);


            $geo = new SetCookie([
                'Name'     => 'geo',
                'Value'    => 'US',
                'Domain'   => '.apple.com',
                'Path'     => '/',
                'Secure'   => false,
                'HttpOnly' => false,
            ]);

            $site = new SetCookie([
                'Name'     => 'site',
                'Value'    => 'USA',
                'Domain'   => '.apple.com',
                'Path'     => '/',
                'Secure'   => true,
                'HttpOnly' => true,
            ]);

            $dslang = new SetCookie([
                'Name'     => 'dslang',
                'Value'    => 'US-EN',
                'Domain'   => '.apple.com',
                'Path'     => '/',
                'Secure'   => true,
                'HttpOnly' => true,
            ]);

            $this->apple->getCookieJar()->setCookie($geo);
            $this->apple->getCookieJar()->setCookie($site);
            $this->apple->getCookieJar()->setCookie($dslang);

            $token = $this->getResourcesAndToken();
//            $token = 'eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6IldlYlBsYXlLaWQifQ.eyJpc3MiOiJBTVBXZWJQbGF5IiwiaWF0IjoxNzQxNzI0OTk2LCJleHAiOjE3NTcyNzY5OTYsInJvb3RfaHR0cHNfb3JpZ2luIjpbImFwcGxlLmNvbSJdfQ.i_WhpwafmgxICuONMXJ53rrBoNsK7jZGHsedk4ioocywCC7LZGYMT1DZnM-1wmwqUa9yWFdepV4ErgZcBYG5Hg';

            $email->createLog('获取资源和令牌', ['token' => $token]);

            $initializeSessionResponse = $this->apple->authTvAppleConnector()
                ->getResources()
                ->getInitializeSession();

            $email->createLog('获取初始化会话', $initializeSessionResponse->toArray());


            $response = $this->apple->authTvAppleConnector()
                ->getResources()
                ->getAccountNameValidate($email->email, $initializeSessionResponse->pageUUID);

            $email->createLog('账号验证', $response->toArray());

            if ($response->accountNameAvailable === false) {
                throw new AccountAlreadyExistsException($response->getResponse()->body());
            }

            $podResponse = $this->apple->buyTvAppleConnector()
                ->getResources()
                ->pod();

            $email->createLog('获取 pod', ['response' => $podResponse->json()]);

            $createOptionsResponse = $this->apple->buyTvAppleConnector()->getResources()->createOptions();

            $email->createLog('获取创建选项', $createOptionsResponse->toArray());

            $data = ValidateAccountFieldsSrvData::from([
                'email'             => $email->email,
                'acAccountName'     => $email->email,
                'firstName'         => $firstName,
                'lastName'          => $lastName,
                'birthMonth'        => $birthMonth,
                'birthDay'          => $birthDay,
                'birthYear'         => $birthYear,
                'acAccountPassword' => $password,
                'pageUUID'          => $createOptionsResponse->pageUUID,
            ]);

            $validateAccountFieldsSrvResponse = $this->apple->buyTvAppleConnector()
                ->getResources()
                ->validateAccountFieldsSrv($data);

            $email->createLog('验证账号字段', $validateAccountFieldsSrvResponse->toArray());

            $generateEmailConfirmationCodeSrvResponse = $this->apple->buyTvAppleConnector()
                ->getResources()
                ->generateEmailConfirmationCodeSrv($email->email);

            $email->createLog('生成邮箱验证码', $generateEmailConfirmationCodeSrvResponse->toArray());

            $validateEmailConfirmationCodeSrvResponse = $this->attemptsVerifyEmail(
                $email,
                $generateEmailConfirmationCodeSrvResponse->clientToken
            );

            $data = CreateAccountSrvData::from([
                'email'             => $email->email,
                'firstName'         => $firstName,
                'lastName'          => $lastName,
                'birthMonth'        => $birthMonth,
                'birthDay'          => $birthDay,
                'birthYear'         => $birthYear,
                'acAccountName'     => $email->email,
                'acAccountPassword' => $password,
                'pageUUID'          => $validateEmailConfirmationCodeSrvResponse->pageUUID,
                'secretCode'        => $this->code,
                'clientToken'       => $validateEmailConfirmationCodeSrvResponse->clientToken,
            ]);

            $createAccountSrvResponse = $this->apple->buyTvAppleConnector()
                ->getResources()
                ->createAccountSrv($token, $data);

            $email->createLog('创建账号数据', $createAccountSrvResponse->toArray());

            $email->update([
                'status' => EmailStatus::REGISTERED,
            ]);

            return Appleid::create([
                'email'      => $email->email,
                'email_uri'  => $email->email_uri,
                'password'   => $password,
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ]);

        } catch (AccountAlreadyExistsException $e) {
            $email->update([
                'status' => EmailStatus::REGISTERED,
            ]);

            $email->createLog("账号已注册", ['message' => $e->getMessage()]);
            throw $e;

        } catch (Throwable $e) {
            $email->update([
                'status' => EmailStatus::FAILED,
            ]);

            $email->createLog("注册失败", ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @return string|null
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException
     */
    private function getResourcesAndToken(): ?string
    {

        $response = $this->apple->tvAppleConnector()->getResources()->getTvApple();

        $meta    = $response->dom()->filter('meta[name="web-tv-app/config/environment"]');
        $content = $meta->attr('content');

        if (empty($content)) {
            throw new RuntimeException('content is empty');
        }

        $token = data_get($this->decodedContent($content), 'MEDIA_API.token');
        if (empty($token)) {
            throw new RuntimeException('token is empty');
        }

        return $token;
    }

    /**
     * @param string $encodedContent
     * @return array|null
     * @throws JsonException
     */
    private function decodedContent(string $encodedContent): ?array
    {
        return json_decode(urldecode($encodedContent), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param Email $email
     * @param string $clientToken
     * @param int $attempts
     * @return ValidateEmailConfirmationCodeSrvResponse
     * @throws MaxRetryAttemptsException
     * @throws RegistrationException
     * @throws FatalRequestException
     * @throws RequestException
     */
    protected function attemptsVerifyEmail(
        Email $email,
        string $clientToken,
        int $attempts = 5
    ): ValidateEmailConfirmationCodeSrvResponse {

        for ($i = 0; $i < $attempts; $i++) {

            try {

                $this->code = Helper::attemptEmailVerificationCode($email->email, $email->email_uri);

                $email->createLog('获取邮箱验证码', ['code' => $this->code]);

                $validateEmailConfirmationCodeSrvResponse = $this->apple->buyTvAppleConnector()
                    ->getResources()
                    ->validateEmailConfirmationCodeSrv(
                        email: $email->email,
                        clientToken: $clientToken,
                        secretCode: $this->code
                    );

                $email->createLog('验证邮箱验证码', $validateEmailConfirmationCodeSrvResponse->toArray());

                return $validateEmailConfirmationCodeSrvResponse;

            } catch (VerificationCodeException $e) {

                $email->createLog('验证邮箱验证码失败', ['message' => $e->getMessage()]);

            }
        }

        throw new MaxRetryAttemptsException(" {$attempts} 次验证邮箱验证码失败");
    }
}
