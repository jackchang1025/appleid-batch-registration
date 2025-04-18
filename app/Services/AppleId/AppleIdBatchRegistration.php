<?php

namespace App\Services\AppleId;

use App\Enums\EmailStatus;
use App\Models\Appleid;
use App\Models\Email;
use App\Models\UserAgent;
use App\Services\CountryLanguageService;
use App\Services\Helper\Helper;
use App\Services\Phone\Phone;
use App\Services\Integrations\Email\EmailConnector;
use App\Services\Integrations\Email\Exception\EmailException;
use App\Services\Integrations\Email\Exception\GetEmailCodeException;
use App\Services\Integrations\Email\Exception\MaxRetryGetEmailCodeException;
use App\Services\Integrations\Phone\Exception\GetPhoneCodeException;
use App\Services\Integrations\Phone\PhoneConnector;
use App\Services\Trait\HasLog;
use Exception;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use JsonException;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use RuntimeException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;
use Throwable;
use Weijiajia\DecryptVerificationCode\CloudCode\CloudCodeConnector;
use Weijiajia\DecryptVerificationCode\CloudCodeResponseInterface;
use Weijiajia\DecryptVerificationCode\Exception\DecryptCloudCodeException;
use Weijiajia\HttpProxyManager\Exception\ProxyModelNotFoundException;
use Weijiajia\HttpProxyManager\ProxyConnector;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\IpAddress\IpAddressManager;
use Weijiajia\IpAddress\Request as IpAddressRequest;
use Weijiajia\SaloonphpAppleClient\Exception\AccountAlreadyExistsException;
use Weijiajia\SaloonphpAppleClient\Exception\CaptchaException;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\MaxRetryVerificationPhoneCodeException;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\PhoneException;
use Weijiajia\SaloonphpAppleClient\Exception\RegistrationException;
use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeException;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\AppleIdConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\Account as VerificationAccount;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\Captcha;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\PhoneNumberVerification;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\SecurityCode;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\Validate;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\VerificationEmail;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\VerificationInfo;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Verification\SendVerificationEmail;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Widget\Account as AccountDto;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Response\Account\Verification\SendVerificationEmail as SendVerificationEmailResponse;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Response\Captcha\Captcha as CaptchaResponse;
use Weijiajia\SaloonphpAppleClient\Integrations\Icloud\IcloudConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\SetupIcloud\Dto\Request\Setup\Ws\CreateLiteAccount;
use Weijiajia\SaloonphpAppleClient\Integrations\SetupIcloud\Dto\Request\Setup\Ws\GetTerms;
use Weijiajia\SaloonphpAppleClient\Integrations\SetupIcloud\SetupIcloudConnector;
use Weijiajia\SaloonphpHeaderSynchronizePlugin\Driver\ArrayStoreHeaderSynchronize;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Weijiajia\Saloonphp\FiveSim\FiveSimConnector;
use App\Services\Phone\PhoneDepository;
class AppleIdBatchRegistration
{
    use HasLog;

    //手机号码验证
    protected Captcha $captcha;

    //验证账号
    protected PhoneNumberVerification $phoneNumberVerification;

    //验证
    protected VerificationAccount $verificationAccount;

    //验证信息
    protected Validate $validate;

    protected VerificationInfo $verificationInfo;

    protected ?Email $email = null;

    protected ?CaptchaResponse $captchaResponse = null;


    protected ?Appleid $appleId = null;

    protected ?Phone $phone = null;

    protected ?ProxySplQueue $proxySplQueue = null;

    protected ?AccountDto $widgetAccount = null;
    protected ?string $password = null;
    protected ?string $widgetKey = null;
    protected ?string $timezone = null;
    protected ?AppleIdConnector $appleIdConnector = null;
    protected ?ProxyConnector $proxyConnector = null;
    protected ?IpAddressRequest $request = null;
    protected ?EmailConnector $emailConnector = null;
    protected ?PhoneConnector $phoneConnector = null;
    protected ?CloudCodeConnector $cloudCodeConnector = null;
    protected ?IcloudConnector $icloudConnector = null;
    protected ?SetupIcloudConnector $setupIcloudConnector = null;
    protected ?FiveSimConnector $fiveSimConnector = null;
    protected ?PhoneDepository $phoneDepository = null;
    protected ?ArrayStoreHeaderSynchronize $headerSynchronize = null;
    protected ?CountryLanguageService $country = null;
    protected bool $isRandomUserAgent = false;
    protected ?string $clientBuildNumber = null;
    protected ?string $clientMasteringNumber = null;
    private ?string $code = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected IpAddressManager $ipAddressManager,
        protected ProxyManager $proxyManager,
    ) {

    }

    public function email(): Email
    {
        return $this->email;
    }

    /**
     * 执行苹果ID批量注册流程
     *
     * @param Email $email 电子邮件对象
     * @param CountryLanguageService $country 国家代码
     * @param Phone|null $phone
     * @param bool $isRandomUserAgent
     * @return bool 注册是否成功
     * @throws AccountAlreadyExistsException
     * @throws ClientException
     * @throws ConnectionException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws MaxRetryAttemptsException
     * @throws MaxRetryGetEmailCodeException
     * @throws MaxRetryVerificationPhoneCodeException
     * @throws NumberFormatException
     * @throws ProxyModelNotFoundException
     * @throws RandomException
     * @throws RegistrationException
     * @throws RequestException
     * @throws Throwable
     */
    public function run(
        Email $email,
        CountryLanguageService $country,
        PhoneDepository $phoneDepository,
        bool $isRandomUserAgent = false,
        
    ): bool {

        $this->email             = $email;
        $this->country           = $country;

        $this->isRandomUserAgent = $isRandomUserAgent;
        $this->phoneDepository   = $phoneDepository;

        try {
            // 更新邮箱状态为处理中
            $email->update(['status' => EmailStatus::PROCESSING]);

            $this->setupProxyConnector();
            $this->setupProxySplQueue();
            $this->setupProxy();


            // 获取手机号码和设置会话
            $this->setupAppleIdConnector();

            // $icloudResponse = $this->icloudConnector()->getAuthenticateResources()->icloud();

            // $this->clientBuildNumber = $icloudResponse->clientBuildNumber();
            // if ($this->clientBuildNumber === null) {
            //     throw new RuntimeException('get client build number failed');
            // }
            // $this->clientMasteringNumber = $icloudResponse->clientMasteringNumber();
            // if ($this->clientMasteringNumber === null) {
            //     throw new RuntimeException('get client mastering number failed');
            // }


            $this->setupHeaders();
            $this->phoneDepository->connect()->middleware()->onRequest($this->debugRequest());
            $this->phoneDepository->connect()->middleware()->onResponse($this->debugResponse());

            $this->phone = $this->phoneDepository->getPhone($this->country);

            // 准备注册所需的账户信息
            $this->prepareAccountInfo();

            // 执行验证流程
            $response = $this->executeVerificationProcess();

            // 保存注册成功的账户信息并更新状态
            $this->saveRegisteredAccount();

            $this->updateSuccessStatus();

            return true;

        } catch (VerificationCodeException $e) {
            $this->handleException($e, EmailStatus::FAILED);
            if ($this->phone) {
                $this->phoneDepository->canPhone($this->phone);
            }

            throw $e;
        } catch (GetEmailCodeException|EmailException $e) {
            $this->handleException($e, EmailStatus::INVALID);
            if ($this->phone) {
                $this->phoneDepository->canPhone($this->phone);
            }
            throw $e;
        } catch (RegistrationException|PhoneException $e) {
            if ($this->phone) {
                $this->phoneDepository->banPhone($this->phone);
            }
            $this->handleException($e, EmailStatus::FAILED);
            throw $e;
        } catch (Throwable $e) {
            $this->handleException($e, EmailStatus::FAILED);
            if ($this->phone) {
                $this->phoneDepository->canPhone($this->phone);
            }
            throw $e;
        }

        // return $this->saveRegisteredAccountAfterVerification($response);

    }


    protected function saveRegisteredAccountAfterVerification(Response $response): bool
    {
        try {

            $xAppleSessionToken = $response->headers()->get('X-Apple-Session-Token');
            if ($xAppleSessionToken === null) {
                throw new RuntimeException('X-Apple-Session-Token is null');
            }

            $clientId = Str::uuid()->toString();

            $this->setupIcloudConnector()->headers()->add('Origin', 'https://www.icloud.com');
            $this->setupIcloudConnector()->headers()->add('Referer', 'https://www.icloud.com');
            $this->setupIcloudConnector()->headers()->add('Accept-Language', $this->country->getAlpha2Language());

            $termsResponse = $this->setupIcloudConnector()->setupWsResources()->getTerms(
                clientBuildNumber: $this->clientBuildNumber,
                clientMasteringNumber: $this->clientMasteringNumber,
                clientId: $clientId,
                data: GetTerms::from([
                    'locale'        => $this->widgetAccount()->locale(),
                    'createPayload' => [
                        'accountWasCreated' => true,
                        'session'           => $xAppleSessionToken,
                        'account'           => [
                            'name'   => $this->email->email,
                            'person' => [
                                'name' => [
                                    'middleNameRequired' => false,
                                    'firstName'          => $this->verificationAccount->person->name->firstName,
                                    'lastName'           => $this->verificationAccount->person->name->lastName,
                                ],
                            ],
                        ],
                    ],
                ])
            );

            $this->setupIcloudConnector()->setupWsResources()->createLiteAccount(
                clientBuildNumber: $this->clientBuildNumber,
                clientMasteringNumber: $this->clientMasteringNumber,
                clientId: $clientId,
                data: CreateLiteAccount::from([
                    'createPayload'       => [
                        'accountWasCreated' => true,
                        'session'           => $xAppleSessionToken,
                        'account'           => [
                            'name'   => $this->email->email,
                            'person' => [
                                'name' => [
                                    'middleNameRequired' => false,
                                    'firstName'          => $this->verificationAccount->person->name->firstName,
                                    'lastName'           => $this->verificationAccount->person->name->lastName,
                                ],
                            ],
                        ],
                    ],
                    'acceptedICloudTerms' => $termsResponse->json('iCloudTerms.version'),
                ])
            );

            $this->setupIcloudConnector()->setupWsResources()->validate(
                clientBuildNumber: $this->clientBuildNumber,
                clientMasteringNumber: $this->clientMasteringNumber,
                clientId: $clientId
            );

            return true;

        } catch (Throwable $e) {
            $this->log('注册失败', [
                'message'   => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    protected function setupProxyConnector(): void
    {
        $this->proxyConnector = $this->proxyManager
        ->forgetDrivers()
        ->driver()
        ->withLogger($this->logger)
        ->withCountry($this->country->getAlpha2Code())
        ->debug();

        $this->proxyConnector->middleware()->onRequest($this->debugRequest());
        $this->proxyConnector->middleware()->onResponse($this->debugResponse());
        
    }

    /**
     * @return void
     * @throws ProxyModelNotFoundException
     */
    protected function setupProxySplQueue(): void
    {
        $proxy               = $this->proxyConnector->defaultModelIp();
        $this->proxySplQueue = new ProxySplQueue(roundRobinEnabled: true);
        $this->proxySplQueue->enqueue($proxy->getUrl());
    }

    /**
     * 设置代理和请求头
     *
     * @return void
     * @throws JsonException
     * @throws Exception
     */
    protected function setupProxy(): void
    {
        $this->request = $this->ipAddressManager->driver();
        $this->request->debug();
        $this->request->withLogger($this->logger);
        $this->request->middleware()->onRequest($this->debugRequest());
        $this->request->middleware()->onResponse($this->debugResponse());
        $this->request->withForceProxy(true)
            ->withProxyEnabled(true)
            ->withProxyQueue($this->proxySplQueue);

        $ipInfo = $this->request->request();

        $this->timezone = $ipInfo->getTimezone();
        $this->country  = CountryLanguageService::make($ipInfo->getCountryCode());
    }

    /**
     * 设置调试中间件
     *
     * @return void
     */
    protected function setupAppleIdConnector(): void
    {

        $this->appleIdConnector = new AppleIdConnector();
        $this->appleIdConnector->debug();
        $this->appleIdConnector->withLogger($this->logger);
        // $this->appleIdConnector->withCookies(new CookieJar());
        $this->appleIdConnector->withProxyQueue($this->proxySplQueue);
        $this->appleIdConnector->withHeaderSynchronizeDriver($this->headerSynchronize());
        $this->appleIdConnector->middleware()->onRequest($this->debugRequest());
        $this->appleIdConnector->middleware()->onResponse($this->debugResponse());
        $this->appleIdConnector->withForceProxy(true);
        $this->appleIdConnector->withProxyEnabled(true);
    }

    protected function headerSynchronize(): ArrayStoreHeaderSynchronize
    {
        return $this->headerSynchronize ??= new ArrayStoreHeaderSynchronize();
    }

    public function icloudConnector(): IcloudConnector
    {
        if ($this->icloudConnector === null) {
            $this->icloudConnector = new IcloudConnector('https://www.icloud.com');
            $this->icloudConnector->debug();
            $this->icloudConnector->withLogger($this->logger);
            $this->icloudConnector->withCookies(new CookieJar());
            $this->icloudConnector->withProxyQueue($this->proxySplQueue);
            $this->icloudConnector->withHeaderSynchronizeDriver($this->headerSynchronize());
            $this->icloudConnector->middleware()->onRequest($this->debugRequest());
            $this->icloudConnector->middleware()->onResponse($this->debugResponse());
            $this->icloudConnector->withForceProxy(true);
            $this->icloudConnector->withProxyEnabled(true);
        }

        return $this->icloudConnector;
    }

    protected function fiveSimConnector(): FiveSimConnector
    {
        if ($this->fiveSimConnector === null) {
            $this->fiveSimConnector = new FiveSimConnector(config('phone-code-rece.five_sim.api_key'));
            $this->fiveSimConnector->debug();
            $this->fiveSimConnector->withLogger($this->logger);
            $this->fiveSimConnector->middleware()->onRequest($this->debugRequest());
            $this->fiveSimConnector->middleware()->onResponse($this->debugResponse());
        }

        return $this->fiveSimConnector;
    }

    /**
     * @return void
     */
    protected function setupHeaders(): void
    {
        $this->appleIdConnector->headers()->add('X-Apple-I-Timezone', $this->timezone);
        $this->appleIdConnector->headers()->add('Accept-Language', $this->country->getAlpha2Language());

        if ($this->isRandomUserAgent) {
            $userAgent = UserAgent::getRandomActive(
            )?->user_agent ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36';

            $this->appleIdConnector->headers()->add('User-Agent', $userAgent);
        }
    }

    /**
     * 准备注册账户所需的基本信息
     *
     * @return void
     * @throws FatalRequestException
     * @throws NumberFormatException
     * @throws RandomException
     * @throws RequestException
     */
    protected function prepareAccountInfo(): void
    {
        $this->verificationInfo = VerificationInfo::from([
            'id'     => '',
            'answer' => '',
        ]);

        $firstName = fake()->firstName();
        $lastName  = fake()->lastName();
        $birthday  = date('Y-m-d', random_int(strtotime('1950-01-01'), strtotime('2000-12-31')));


        $this->verificationAccount = VerificationAccount::from([
            'name'             => $this->email->email,
            'password'         => $this->password(),
            'person'           => [
                'name'           => [
                    'firstName' => $firstName,
                    'lastName'  => $lastName,
                ],
                'birthday'       => $birthday,
                'primaryAddress' => [
                    'country' => $this->country->getAlpha3Code(),
                ],
            ],
            'preferences'      => [
                'preferredLanguage'    => $this->widgetAccount()->locale(),
                'marketingPreferences' => [
                    'appleNews'     => false,
                    'appleUpdates'  => true,
                    'iTunesUpdates' => true,
                ],
            ],
            'verificationInfo' => $this->verificationInfo,
        ]);

        $this->phoneNumberVerification = PhoneNumberVerification::from([
            'phoneNumber' => [
                'id'              => 1,
                'number'          => $this->phone->phone(),
                'countryCode'     => $this->phone->countryCode(),
                'countryDialCode' => $this->phone->countryDialCode(),
                'nonFTEU'         => true,
            ],
            'mode'        => 'sms',
        ]);

        $this->captcha = Captcha::from([
            'id'     => 0,
            'token'  => '',
            'answer' => '',
        ]);

        $this->validate = new Validate(
            $this->phoneNumberVerification,
            $this->verificationAccount,
            $this->captcha,
            false
        );
    }

    /**
     * @return string
     * @throws RandomException
     */
    protected function password(): string
    {
        return $this->password ??= Helper::generatePassword();
    }

    /**
     * @return AccountDto
     * @throws FatalRequestException
     * @throws RequestException
     */
    protected function widgetAccount(): AccountDto
    {
        return $this->widgetAccount ??= $this->appleIdConnector->getAccountResource()->widgetAccount(
            widgetKey: $this->widgetKey(),
            referer: 'https://www.icloud.com/',
            appContext: 'icloud',
            lv: '0.3.16'
        );
    }

    protected function widgetKey(): string
    {
        return $this->widgetKey ??= 'd39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d';
    }

    /**
     * 执行验证流程
     *
     * @return Response
     * @throws AccountAlreadyExistsException
     * @throws CaptchaException
     * @throws ClientException
     * @throws FatalRequestException
     * @throws GetEmailCodeException
     * @throws JsonException
     * @throws NumberFormatException
     * @throws PhoneException
     * @throws RegistrationException
     * @throws RequestException
     * @throws VerificationCodeException|EmailException
     */
    protected function executeVerificationProcess(): Response
    {
        // 获取验证码图片
        $this->captcha();

        // 验证邮箱和密码
        $this->validateEmailAndPassword();

        // 验证验证码
        $this->attemptsCaptcha();

        // 验证邮箱验证码
        $this->attemptVerificationEmailCode();

        // 验证手机验证码
        $this->attemptVerificationPhoneCode();

        // 提交账户信息
        return $this->appleIdConnector->getAccountResource()->account(
            validateDto: $this->validate,
            appleIdSessionId: $this->widgetAccount()->appleSessionId(),
            appleWidgetKey: $this->widgetKey()
        );

    }

    /**
     * @return CaptchaResponse
     * @throws FatalRequestException
     * @throws RequestException
     */
    protected function captcha(): CaptchaResponse
    {
        return $this->captchaResponse = $this->appleIdConnector->getAccountResource()->captcha(
            $this->widgetAccount()->appleSessionId(),
            $this->widgetKey()
        );
    }

    /**
     * 验证邮箱和密码
     *
     * @return void
     * @throws AccountAlreadyExistsException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException
     */
    protected function validateEmailAndPassword(): void
    {
        $this->appleIdConnector->getAccountResource()->appleid(
            $this->email->email,
            $this->widgetAccount()->appleSessionId(),
            $this->widgetKey()
        );

        $this->appleIdConnector->getAccountResource()->password(
            $this->email->email,
            $this->verificationAccount->password,
            $this->widgetAccount()->appleSessionId(),
            $this->widgetKey()
        );
    }

    /**
     * @param int $attempts
     * @return Response
     * @throws AccountAlreadyExistsException
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException|RegistrationException|CaptchaException
     */
    protected function attemptsCaptcha(int $attempts = 5): Response
    {
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response = $this->cloudCodeConnector()->decryptCloudCode(
                    token: config('cloudcode.token'),
                    type: config('cloudcode.type'),
                    image: $this->captchaResponse->payload->content
                );

                $this->updateCaptchaValidation($response);

                return $this->validateCaptcha();
            } catch (CaptchaException|DecryptCloudCodeException $e) {
                // 验证码识别失败，重新获取验证码图片
                $this->captcha();
                continue;
            }
        }

        throw new CaptchaException("验证码验证失败，已尝试 {$attempts} 次");
    }

    public function cloudCodeConnector(): CloudCodeConnector
    {
        if ($this->cloudCodeConnector === null) {
            $this->cloudCodeConnector = new CloudCodeConnector();
            $this->cloudCodeConnector->withLogger($this->logger);
            $this->cloudCodeConnector->debug();
            $this->cloudCodeConnector->middleware()->onRequest($this->debugRequest());
            $this->cloudCodeConnector->middleware()->onResponse($this->debugResponse());
        }

        return $this->cloudCodeConnector;
    }

    /**
     * 更新验证码验证信息
     *
     * @param CloudCodeResponseInterface $response 验证码解析响应
     * @return void
     */
    protected function updateCaptchaValidation(CloudCodeResponseInterface $response): void
    {
        $this->validate->captcha->id     = $this->captchaResponse->id;
        $this->validate->captcha->token  = $this->captchaResponse->token;
        $this->validate->captcha->answer = $response->getCode();
    }

    /**
     * 验证验证码
     *
     * @return Response 验证响应
     * @throws AccountAlreadyExistsException
     * @throws CaptchaException
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException|RegistrationException
     */
    protected function validateCaptcha(): Response
    {
        return $this->appleIdConnector->getAccountResource()->validate(
            validateDto: $this->validate,
            appleIdSessionId: $this->widgetAccount()->appleSessionId(),
            appleWidgetKey: $this->widgetKey()
        );
    }

    /**
     * @param int $attempts
     * @return Response
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws VerificationCodeException
     * @throws RequestException|GetEmailCodeException|EmailException
     */
    protected function attemptVerificationEmailCode(int $attempts = 5): Response
    {
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response                                      = $this->sendVerificationEmail();
                $this->validate->account->verificationInfo->id = $response->verificationId;

                $emailCode                                         = $this->emailConnector()->attemptGetEmailCode(
                    $this->email->email,
                    $this->email->email_uri
                );
                $this->validate->account->verificationInfo->answer = $emailCode;

                return $this->verifyEmailCode();
            } catch (VerificationCodeException $e) {
                // 验证码错误，继续尝试
                continue;
            }
        }

        throw new VerificationCodeException("邮箱验证码验证失败，已尝试 {$attempts} 次");
    }

    /**
     * @return SendVerificationEmailResponse
     * @throws FatalRequestException
     * @throws RequestException
     */
    protected function sendVerificationEmail(): SendVerificationEmailResponse
    {
        return $this->appleIdConnector
            ->getAccountResource()
            ->sendVerificationEmail(
                SendVerificationEmail::from([
                    'account'     => [
                        'name'   => $this->email->email,
                        'person' => [
                            'name' => [
                                'firstName' => $this->verificationAccount->person->name->firstName,
                                'lastName'  => $this->verificationAccount->person->name->lastName,
                            ],
                        ],
                    ],
                    'countryCode' => $this->country->getAlpha3Code(),
                ]),
                $this->widgetAccount()->appleSessionId(),
                $this->widgetKey()
            )
            ->dto();
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
     * 验证邮箱验证码
     *
     * @return Response 验证响应
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException|VerificationCodeException
     */
    protected function verifyEmailCode(): Response
    {
        $verificationPut = VerificationEmail::from([
            'name'             => $this->email->email,
            'verificationInfo' => $this->validate->account->verificationInfo,
        ]);

        return $this->appleIdConnector->getAccountResource()->verificationEmail(
            $verificationPut,
            $this->widgetAccount()->appleSessionId(),
            $this->widgetKey()
        );
    }

    /**
     * @param int $attempts
     * @return Response
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws VerificationCodeException
     * @throws NumberFormatException
     * @throws RequestException|PhoneException
     */
    protected function attemptVerificationPhoneCode(int $attempts = 5): Response
    {
        for ($i = 0; $i < $attempts; $i++) {
            try {

                $this->sendPhoneVerificationCode();

                $this->setPhoneVerificationCode(
                    $this->phoneDepository->getPhoneCode(
                        $this->phone
                        )
                );

                return $this->verifyPhoneCode();

            } catch (GetPhoneCodeException $e) {
                // 标记当前手机为无效并获取新手机
                $this->phoneDepository->banPhone($this->phone);
                $this->phone = $this->phoneDepository->getPhone($this->country);
                $this->updatePhoneNumberVerification();
                continue;
            } catch (VerificationCodeException $e) {
                // 验证码错误，继续尝试
                continue;
            }
        }

        throw new VerificationCodeException("手机验证码验证失败，已尝试 {$attempts} 次");
    }

    /**
     * 发送手机验证码
     *
     * @param int $attempts 尝试次数
     * @return Response 发送响应
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws PhoneException
     * @throws RequestException
     */
    protected function sendPhoneVerificationCode(int $attempts = 5): Response
    {
        for ($i = 0; $i < $attempts; $i++) {
            try {
                return $this->appleIdConnector->getAccountResource()->sendVerificationPhone(
                    $this->validate,
                    $this->widgetAccount()->appleSessionId(),
                    $this->widgetKey()
                );
            } catch (PhoneException $e) {

            }
        }

        throw new PhoneException("发送手机验证码失败，已尝试 {$attempts} 次");
    }

    public function phoneConnector(): PhoneConnector
    {
        if ($this->phoneConnector === null) {
            $this->phoneConnector = new PhoneConnector();
            $this->phoneConnector->withLogger($this->logger);
            $this->phoneConnector->debug();
            $this->phoneConnector->middleware()->onRequest($this->debugRequest());
            $this->phoneConnector->middleware()->onResponse($this->debugResponse());
        }

        return $this->phoneConnector;
    }

    /**
     * 设置手机验证码
     *
     * @param string $code 验证码
     * @return void
     */
    protected function setPhoneVerificationCode(string $code): void
    {
        $this->validate->phoneNumberVerification->securityCode = SecurityCode::from([
            'code' => $code,
        ]);
    }

    /**
     * 验证手机验证码
     *
     * @return Response 验证响应
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException|VerificationCodeException
     */
    protected function verifyPhoneCode(): Response
    {
        return $this->appleIdConnector->getAccountResource()->verificationPhone(
            $this->validate,
            $this->widgetAccount()->appleSessionId(),
            $this->widgetKey()
        );
    }

    /**
     * 更新手机验证信息
     *
     * @return void
     * @throws NumberFormatException
     */
    protected function updatePhoneNumberVerification(): void
    {
        $this->validate->phoneNumberVerification = PhoneNumberVerification::from([
            'phoneNumber' => [
                'id'              => 1,
                'number'          => $this->phone->phone(),
                'countryCode'     => $this->phone->countryCode(),
                'countryDialCode' => $this->phone->countryDialCode(),
                'nonFTEU'         => true,
            ],
            'mode'        => 'sms',
        ]);
    }

    /**
     * 保存注册成功的账户信息
     *
     * @return void
     */
    protected function saveRegisteredAccount(): void
    {
        Appleid::create([
            'email'                   => $this->email->email,
            'email_uri'               => $this->email->email_uri,
            'phone'                   => $this->phone->phone(),
            'phone_uri'               => $this->phone->phoneAddress(),
            'password'                => $this->verificationAccount->password,
            'first_name'              => $this->verificationAccount->person->name->firstName,
            'last_name'               => $this->verificationAccount->person->name->lastName,
            'country'                 => $this->verificationAccount->person->primaryAddress->country,
            'phone_country_code'      => $this->phone->countryCode(),
            'phone_country_dial_code' => $this->phone->countryDialCode(),
        ]);
    }

    /**
     * 更新注册成功状态
     *
     * @return void
     */
    protected function updateSuccessStatus(): void
    {
        $this->email->update(['status' => EmailStatus::REGISTERED]);
        $this->phoneDepository->finishPhone($this->phone);
    }

    /**
     * 处理异常的通用方法
     *
     * @param Throwable $e 异常
     * @param EmailStatus $emailStatus 邮箱状态
     * @return void
     */
    protected function handleException(Throwable $e, EmailStatus $emailStatus): void
    {
        $this->email->update(['status' => $emailStatus]);

        $this->log('注册失败', [
            'message'   => $e->getMessage(),
            'exception' => get_class($e),
        ]);
    }

    public function setupIcloudConnector(): SetupIcloudConnector
    {
        if ($this->setupIcloudConnector === null) {
            $this->setupIcloudConnector = new SetupIcloudConnector();
            $this->setupIcloudConnector->debug();
            $this->setupIcloudConnector->withLogger($this->logger);
            $this->setupIcloudConnector->withCookies(new CookieJar());
            $this->setupIcloudConnector->withProxyQueue($this->proxySplQueue);
            $this->setupIcloudConnector->withHeaderSynchronizeDriver($this->headerSynchronize());
            $this->setupIcloudConnector->middleware()->onRequest($this->debugRequest());
            $this->setupIcloudConnector->middleware()->onResponse($this->debugResponse());
            $this->setupIcloudConnector->withForceProxy(true);
            $this->setupIcloudConnector->withProxyEnabled(true);
        }

        return $this->setupIcloudConnector;
    }
}































