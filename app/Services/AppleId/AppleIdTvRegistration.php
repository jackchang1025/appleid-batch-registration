<?php

namespace App\Services\AppleId;

use App\Enums\EmailStatus;
use App\Models\Appleid;
use App\Models\Email;
use App\Services\Apple;
use App\Services\AppleBuilder;
use App\Services\Helper\Helper;
use App\Services\Integrations\Email\Exception\EmailException;
use App\Services\Integrations\Email\Exception\GetEmailCodeException;
use GuzzleHttp\Cookie\SetCookie;
use JsonException;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Throwable;
use Weijiajia\HttpProxyManager\Exception\ProxyModelNotFoundException;
use Weijiajia\SaloonphpAppleClient\Exception\AccountAlreadyExistsException;
use Weijiajia\SaloonphpAppleClient\Exception\AccountException;
use Weijiajia\SaloonphpAppleClient\Exception\CreateAccountException;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Weijiajia\SaloonphpAppleClient\Exception\RegistrationException;
use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeException;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\Data\CreateAccountSrvData;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\Data\ValidateAccountFieldsSrvData;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\Data\ValidateEmailConfirmationCodeSrvResponse;
use Psr\Log\LoggerInterface;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\IpAddress\IpAddressManager;
use GuzzleHttp\Cookie\CookieJar;
use Weijiajia\SaloonphpAppleClient\Integrations\TvApple\TvAppleConnector;
use App\Services\Trait\HasLog;
use Weijiajia\SaloonphpAppleClient\Integrations\AuthTvApple\AuthTvAppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\BuyTvAppleConnector;
use App\Services\Integrations\Email\EmailConnector;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;

class AppleIdTvRegistration
{
    use HasLog;

    private ?string $code = null;

    protected Email $email;

    protected ?TvAppleConnector $tvAppleConnector = null;

    protected ?AuthTvAppleConnector $authTvAppleConnector = null;
    protected ?BuyTvAppleConnector $buyTvAppleConnector = null;
    protected ?EmailConnector $emailConnector = null;
    protected ?ProxySplQueue $proxySplQueue = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected IpAddressManager $ipAddressManager,
        protected ProxyManager $proxyManager,
    )
    {

    }

    public function cookieJar(): CookieJar
    {
        return new CookieJar();
    }

    public function email(): Email
    {
        return $this->email;
    }

    /**
     * @param Email $email
     * @return Appleid
     * @throws AccountAlreadyExistsException
     * @throws MaxRetryAttemptsException
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
        $this->email = $email;
        // 生成随机个人信息
        $password   = Helper::generatePassword();
        $firstName  = fake()->firstName();
        $lastName   = fake()->lastName();
        $birthMonth = fake()->month();
        $birthDay   = fake()->dayOfMonth($birthMonth);
        $birthYear  = (int)date('Y', random_int(strtotime('1950-01-01'), strtotime('2000-12-31')));


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

            $this->cookieJar()->setCookie($geo);
            $this->cookieJar()->setCookie($site);
            $this->cookieJar()->setCookie($dslang);

            $token = $this->getResourcesAndToken();
//            $token = 'eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6IldlYlBsYXlLaWQifQ.eyJpc3MiOiJBTVBXZWJQbGF5IiwiaWF0IjoxNzQxNzI0OTk2LCJleHAiOjE3NTcyNzY5OTYsInJvb3RfaHR0cHNfb3JpZ2luIjpbImFwcGxlLmNvbSJdfQ.i_WhpwafmgxICuONMXJ53rrBoNsK7jZGHsedk4ioocywCC7LZGYMT1DZnM-1wmwqUa9yWFdepV4ErgZcBYG5Hg';

            $initializeSessionResponse = $this->authTvAppleConnector()
                ->getResources()
                ->getInitializeSession();

            $response = $this->authTvAppleConnector()
                ->getResources()
                ->getAccountNameValidate($email->email, $initializeSessionResponse->pageUUID);

            if ($response->accountNameAvailable === false) {
                throw new AccountAlreadyExistsException($response->getResponse()->body());
            }

            $this->buyTvAppleConnector()
                ->getResources()
                ->pod();

            $createOptionsResponse = $this->buyTvAppleConnector()->getResources()->createOptions();

            $data = ValidateAccountFieldsSrvData::from([
                'email'             => $this->email->email,
                'acAccountName'     => $this->email->email,
                'firstName'         => $firstName,
                'lastName'          => $lastName,
                'birthMonth'        => $birthMonth,
                'birthDay'          => $birthDay,
                'birthYear'         => $birthYear,
                'acAccountPassword' => $password,
                'pageUUID'          => $createOptionsResponse->pageUUID,
            ]);

            $this->buyTvAppleConnector()
                ->getResources()
                ->validateAccountFieldsSrv($data);


            $generateEmailConfirmationCodeSrvResponse = $this->buyTvAppleConnector()
                ->getResources()
                ->generateEmailConfirmationCodeSrv($email->email);

            $validateEmailConfirmationCodeSrvResponse = $this->verifyEmail(
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

            $this->buyTvAppleConnector()
                ->getResources()
                ->createAccountSrv($token, $data);

            $this->email->update([
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
            $this->email->update([
                'status' => EmailStatus::REGISTERED,
            ]);

            $this->log("账号已注册", ['message' => $e->getMessage()]);
            throw $e;

        } catch (EmailException $e) {
            $this->email->update([
                'status' => EmailStatus::INVALID,
            ]);

            $this->log("账号失效", ['message' => $e->getMessage()]);
            throw $e;

        }catch (Throwable $e) {
            $this->email->update([
                'status' => EmailStatus::FAILED,
            ]);

            $this->log("注册失败", ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @throws ProxyModelNotFoundException
     */
    public function tvAppleConnector(): TvAppleConnector
    {
        if ($this->tvAppleConnector === null) {
            $this->tvAppleConnector = new TvAppleConnector();
            $this->tvAppleConnector->withLogger($this->logger);
            $this->tvAppleConnector->debug();
            $this->tvAppleConnector->withProxyQueue($this->proxySplQueue());
            $this->tvAppleConnector->withCookies($this->cookieJar());
            $this->tvAppleConnector->headers()->add('accept-language','en-ca');
            $this->tvAppleConnector->middleware()->onRequest($this->debugRequest());
            $this->tvAppleConnector->middleware()->onResponse($this->debugResponse());
        }

        return $this->tvAppleConnector;
    }

    /**
     * @throws ProxyModelNotFoundException
     */
    public function authTvAppleConnector(): AuthTvAppleConnector{
        if ($this->authTvAppleConnector === null) {
            $this->authTvAppleConnector = new AuthTvAppleConnector();
            $this->authTvAppleConnector->withLogger($this->logger);
            $this->authTvAppleConnector->debug();
            $this->authTvAppleConnector->headers()->add('accept-language','en-ca');
            $this->authTvAppleConnector->withProxyQueue($this->proxySplQueue());
            $this->authTvAppleConnector->withCookies($this->cookieJar());
            $this->authTvAppleConnector->middleware()->onRequest($this->debugRequest());
            $this->authTvAppleConnector->middleware()->onResponse($this->debugResponse());
        }
        return $this->authTvAppleConnector;
    }

    /**
     * @throws ProxyModelNotFoundException
     */
    public function buyTvAppleConnector(): BuyTvAppleConnector{
        if ($this->buyTvAppleConnector === null) {
            $this->buyTvAppleConnector = new BuyTvAppleConnector();
            $this->buyTvAppleConnector->withLogger($this->logger);
            $this->buyTvAppleConnector->debug();
            $this->buyTvAppleConnector->headers()->add('accept-language','en-ca');
            $this->buyTvAppleConnector->withProxyQueue($this->proxySplQueue());
            $this->buyTvAppleConnector->withCookies($this->cookieJar());
            $this->buyTvAppleConnector->middleware()->onRequest($this->debugRequest());
            $this->buyTvAppleConnector->middleware()->onResponse($this->debugResponse());
        }
        return $this->buyTvAppleConnector;
    }

    public function emailConnector(): EmailConnector
    {
        if ($this->emailConnector === null) {
            $this->emailConnector = new EmailConnector();
            $this->emailConnector->withLogger($this->logger);
            $this->emailConnector->debug();
            $this->emailConnector->middleware()->onRequest($this->debugRequest());
            $this->emailConnector->middleware()->onResponse($this->debugResponse());
        }
        return $this->emailConnector;
    }

    /**
     * @return ProxySplQueue
     * @throws ProxyModelNotFoundException
     */
    public function proxySplQueue(): ProxySplQueue
    {
        if ($this->proxySplQueue === null) {
            $proxyConnector = $this->proxyManager->forgetDrivers()->driver();
            $proxyConnector->withLogger($this->logger);
            $proxyConnector->debug();
            $proxyConnector->middleware()->onRequest($this->debugRequest());
            $proxyConnector->middleware()->onResponse($this->debugResponse());
            $proxy               = $proxyConnector->defaultModelIp();
            $this->proxySplQueue = new ProxySplQueue(roundRobinEnabled: true);
            $this->proxySplQueue->enqueue($proxy->getUrl());
        }
        return $this->proxySplQueue;
    }

    /**
     * @return string|null
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException|ProxyModelNotFoundException
     */
    private function getResourcesAndToken(): ?string
    {

        $response = $this->tvAppleConnector()->getResources()->getTvApple();

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
     * @param string $clientToken
     * @return ValidateEmailConfirmationCodeSrvResponse
     * @throws FatalRequestException
     * @throws RegistrationException
     * @throws RequestException
     * @throws VerificationCodeException
     * @throws EmailException
     * @throws GetEmailCodeException|ProxyModelNotFoundException
     */
    protected function verifyEmail(string $clientToken): ValidateEmailConfirmationCodeSrvResponse {

        $this->code = $this->emailConnector()->attemptGetEmailCode($this->email->email, $this->email->email_uri);

        return $this->buyTvAppleConnector()
            ->getResources()
            ->validateEmailConfirmationCodeSrv(
                email: $this->email->email,
                clientToken: $clientToken,
                secretCode: $this->code
            );
    }
}
