<?php

namespace App\Services\AppleId;

use App\Enums\CountryEnum;
use App\Enums\EmailStatus;
use App\Enums\Request;
use App\Models\Appleid;
use App\Models\Email;
use App\Models\Phone;
use App\Services\Apple;
use App\Services\AppleBuilder;
use App\Services\Helper\Helper;
use App\Services\IpInfo\IpInfoService;
use App\Services\Trait\HasPhone;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use libphonenumber\PhoneNumberFormat;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use RuntimeException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Throwable;
use Weijiajia\DecryptVerificationCode\CloudCodeResponseInterface;
use Weijiajia\DecryptVerificationCode\Exception\DecryptCloudCodeException;
use Weijiajia\SaloonphpAppleClient\Exception\AccountAlreadyExistsException;
use Weijiajia\SaloonphpAppleClient\Exception\CaptchaException;
use Weijiajia\SaloonphpAppleClient\Exception\Email\MaxRetryVerificationEmailCodeException;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Weijiajia\SaloonphpAppleClient\Exception\Email\MaxRetryGetEmailCodeException;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\MaxRetryGetPhoneCodeException;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\MaxRetryVerificationPhoneCodeException;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\PhoneException;
use Weijiajia\SaloonphpAppleClient\Exception\RegistrationException;
use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeException;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\Account as VerificationAccount;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\Captcha;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\PhoneNumberVerification;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\SecurityCode;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\Validate;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\VerificationEmail;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Validate\VerificationInfo;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Account\Verification\SendVerificationEmail;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Response\Account\Verification\SendVerificationEmail as SendVerificationEmailResponse;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Response\Captcha\Captcha as CaptchaResponse;
use App\Services\CountryLanguageService;
use App\Services\CloudCode\CloudCodeService;
use Weijiajia\IpAddress\IpAddressManager;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
class AppleIdBatchRegistration
{
    use HasPhone;


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

    protected Apple $apple;

    protected ?Appleid $appleId = null;

    protected ?ProxySplQueue $proxySplQueue = null;

    protected ?string $appleSessionId = null;

    private ?string $code = null;

    protected ?array $direct = null;

    protected ?string $password = null;

    protected ?string $widgetKey = null;

    protected ?string $locale = null;

    protected ?string $timezone = null;

    protected ?CountryLanguageService $countryLanguageService = null;

    public function __construct(
        protected AppleBuilder $appleBuilder,
        protected LoggerInterface $logger,
        protected CloudCodeService $cloudCodeService,
        protected IpAddressManager $ipAddressManager,
        protected ProxyManager $proxyManager,
    ) {

    }


    /**
     * @return array
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException
     */
    public function direct(): array
    {
        if ($this->direct === null) {

            $this->direct = $this->parseBootArgs($this->widgetAccount())['direct'] ?? null;

            if (empty($this->direct)) {
                throw new RuntimeException('direct not found');
            }

            if (empty($this->direct['sessionId'])) {
                throw new RuntimeException('sessionId not found');
            }

            if (empty($this->direct['widgetKey'])) {
                throw new RuntimeException('widgetKey not found');
            }

            if (empty($this->direct['config']['localizedResources']['locale'])) {
                throw new RuntimeException('locale not found');
            }
        }

        return $this->direct;
    }

    /**
     * @return string
     * @throws RandomException
     */
    protected function password(): string
    {
        return $this->password ??= Helper::generatePassword();
    }

    protected function widgetKey(): string
    {
        return $this->widgetKey ??= 'd39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d';
    }

    /**
     * @return string
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException
     */
    protected function getLocale(): string
    {
        return $this->locale ??= $this->direct()['config']['localizedResources']['locale'];
    }

    /**
     * @return mixed
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException
     */
    protected function appleSessionId(): string
    {
        return $this->appleSessionId ??= $this->direct()['sessionId'];
    }

    /**
     * 执行苹果ID批量注册流程
     *
     * @param Email $email 电子邮件对象
     * @param null|CountryEnum $country 国家代码
     * @return bool 注册是否成功
     * @throws AccountAlreadyExistsException
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws MaxRetryAttemptsException
     * @throws NumberFormatException
     * @throws RandomException
     * @throws RequestException
     * @throws Throwable
     */
    public function run(Email $email, CountryLanguageService $country): bool
    {
        $this->email   = $email;
        $this->country = $country;

        try {
            // 更新邮箱状态为处理中
            $email->update(['status' => EmailStatus::PROCESSING]);

             // 获取手机号码和设置会话
             $this->setupSession();

             $this->setupProxy();

             $this->setupDebugMiddleware();

            $this->setupHeaders();

            $this->setupPhone();

            // 准备注册所需的账户信息
            $this->prepareAccountInfo();

            // 执行验证流程
           $this->executeVerificationProcess();

            // 保存注册成功的账户信息
            $this->saveRegisteredAccount();

            // 更新状态为注册成功
            $this->updateSuccessStatus();

            return true;
        } catch (AccountAlreadyExistsException $e) {
            $this->handleAccountExistsException($e);
            throw $e;
        } catch (MaxRetryVerificationPhoneCodeException $e) {
            $this->handlePhoneVerificationException($e);
            throw $e;
        } catch (MaxRetryGetEmailCodeException $e) {
            $this->handleEmailVerificationException($e);
            throw $e;
        }catch (Throwable $e) {
            $this->handleGenericException($e);
            throw $e;
        }
    }



    /**
     * 准备注册账户所需的基本信息
     *
     * @return void
     * @throws FatalRequestException
     * @throws JsonException
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
                'preferredLanguage'    => $this->getLocale(),
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
                'number'          => $this->phone->getPhoneNumberService()->format(PhoneNumberFormat::NATIONAL),
                'countryCode'     => $this->phone->country_code,
                'countryDialCode' => $this->phone->country_dial_code,
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
     * 设置会话
     *
     * @return void
     * @throws RandomException
     */
    protected function setupSession(): void
    {
        $this->appleId = Appleid::make([
            'email'     => $this->email->email,
            'email_uri' => $this->email->email_uri,
            'password'  => $this->password(),
        ]);

        $this->apple = $this->appleBuilder->build($this->appleId);
        $this->apple->withDebug(true);
    }

    public function getAlpha2Code(): ?string
    {
        return $this->country ? $this->country->getAlpha2Code() : null;
    }

    public function getAlpha2Language(): ?string
    {
        return $this->country ? $this->country->getAlpha2Language() : null;
    }

    /**
     * 设置调试中间件
     *
     * @return void
     */
    protected function setupDebugMiddleware(): void
    {
        $this->apple->appleIdConnector()->middleware()->onRequest($this->debugRequest());
        $this->apple->appleIdConnector()->middleware()->onResponse($this->debugResponse());
        $this->cloudCodeService->cloudCodeConnector()->middleware()->onRequest($this->debugRequest());
        $this->cloudCodeService->cloudCodeConnector()->middleware()->onResponse($this->debugResponse());
    }

    /**
     * 设置代理和请求头
     *
     * @return void
     */
    protected function setupProxy(): void
    {

        $driver = $this->proxyManager->driver();
        $driver->debug();
        $driver->withCountry($this->country->getAlpha2Code());
        $driver->middleware()->onRequest($this->debugRequest());
        $driver->middleware()->onResponse($this->debugResponse());

        $proxy = $driver->defaultModelIp();

        $this->proxySplQueue = new ProxySplQueue(roundRobinEnabled: true);
        $this->proxySplQueue->enqueue($proxy->getUrl());

        $request = $this->ipAddressManager->driver();

        $request->debug();
        $request->middleware()->onRequest($this->debugRequest());
        $request->middleware()->onResponse($this->debugResponse());

        $request->withForceProxy(true)
            ->withProxyEnabled(true)
            ->withProxyQueue($this->proxySplQueue);

        $ipInfo = $request->request();

        $this->timezone = $ipInfo->getTimezone();
        $this->country = CountryLanguageService::make($ipInfo->getCountryCode());

        
        $this->apple->withProxySplQueue($this->proxySplQueue);
        $this->apple->appleIdConnector()->withForceProxy(true);
        $this->apple->appleIdConnector()->withProxyEnabled(true);

    }


    protected function setupPhone(): void
    {
        $this->phone = $this->getPhone();
    }


    protected function setupHeaders(): void
    {
        $this->apple->appleIdConnector()->headers()->add('X-Apple-I-Timezone', $this->timezone);
        $this->apple->appleIdConnector()->headers()->add('Accept-Language', $this->country->getAlpha2Language());
    }

    /**
     * @return Response
     * @throws FatalRequestException
     * @throws RequestException
     */
    protected function widgetAccount(): Response
    {
        return $this->apple->appleIdConnector()->getAccountResource()->widgetAccount(
            widgetKey: $this->widgetKey(),
            referer: 'https://www.icloud.com/',
            appContext: 'icloud',
            lv: '0.3.16'
        );
    }

    /**
     * @throws JsonException
     */
    public function parseBootArgs(Response $response): array
    {
        $crawler = $response->dom();
        $script = $crawler->filter('script#boot_args');
        if ($script->count() === 0) {
            throw new RuntimeException('boot_args not found');
        }

        $json = json_decode($script->text(), true, 512, JSON_THROW_ON_ERROR);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON format');
        }

        return $json;
    }



    /**
     * 执行验证流程
     *
     * @return Response
     * @throws AccountAlreadyExistsException
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws MaxRetryGetEmailCodeException
     * @throws MaxRetryVerificationEmailCodeException
     * @throws MaxRetryVerificationPhoneCodeException
     * @throws NumberFormatException
     * @throws RequestException|ConnectionException|RegistrationException|MaxRetryAttemptsException
     */
    protected function executeVerificationProcess(): Response
    {
        // 获取验证码图片
        $this->captcha($this->appleSessionId(), $this->widgetKey());

        // 验证邮箱和密码
        $this->validateEmailAndPassword();

        // 验证验证码
        $this->attemptsCaptcha(
            appleIdSessionId: $this->appleSessionId(),
            widgetKey: $this->widgetKey()
        );

        // 验证邮箱验证码
        $this->attemptVerificationEmailCode($this->appleSessionId(), $this->widgetKey());

        // 验证手机验证码
        $this->attemptVerificationPhoneCode(
            appleIdSessionId: $this->appleSessionId(),
            widgetKey: $this->widgetKey()
        );

        // 提交账户信息
        return $this->apple->appleIdConnector()->getAccountResource()->account(
            validateDto: $this->validate,
            appleIdSessionId: $this->appleSessionId(),
            appleWidgetKey: $this->widgetKey()
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
        $this->apple->appleIdConnector()->getAccountResource()->appleid(
            $this->email->email,
            $this->appleSessionId(),
            $this->widgetKey()
        );

        $this->apple->appleIdConnector()->getAccountResource()->password(
            $this->email->email,
            $this->verificationAccount->password,
            $this->appleSessionId(),
            $this->widgetKey()
        );
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
            'phone'                   => $this->phone->phone,
            'phone_uri'               => $this->phone->phone_address,
            'password'                => $this->verificationAccount->password,
            'first_name'              => $this->verificationAccount->person->name->firstName,
            'last_name'               => $this->verificationAccount->person->name->lastName,
            'country'                 => $this->verificationAccount->person->primaryAddress->country,
            'phone_country_code'      => $this->phone->country_code,
            'phone_country_dial_code' => $this->phone->country_dial_code,
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
        $this->phone->update(['status' => Phone::STATUS_BOUND]);
    }

    /**
     * 处理账户已存在异常
     *
     * @param AccountAlreadyExistsException $e 异常
     * @return void
     */
    protected function handleAccountExistsException(AccountAlreadyExistsException $e): void
    {
        $this->phone && $this->phone->update(['status' => Phone::STATUS_NORMAL]);
        $this->email && $this->email->update(['status' => EmailStatus::REGISTERED]);
        $this->log('注册失败', ['message' => $e->getMessage()]);
    }

    /**
     * 处理手机验证异常
     *
     * @param MaxRetryVerificationPhoneCodeException $e 异常
     * @return void
     */
    protected function handlePhoneVerificationException(MaxRetryVerificationPhoneCodeException $e): void
    {
        $this->email && $this->email->update(['status' => EmailStatus::FAILED]);
        $this->phone && $this->phone->update(['status' => Phone::STATUS_INVALID]);
        $this->log('注册失败', ['message' => $e->getMessage()]);
    }

    /**
     * 处理邮箱验证异常
     *
     * @param MaxRetryGetEmailCodeException $e 异常
     * @return void
     */
    protected function handleEmailVerificationException(MaxRetryGetEmailCodeException $e): void
    {
        $this->email && $this->email->update(['status' => EmailStatus::INVALID]);
        $this->phone && $this->phone->update(['status' => Phone::STATUS_NORMAL]);
        $this->log('注册失败', ['message' => $e->getMessage()]);
    }

    /**
     * 处理通用异常
     *
     * @param Throwable $e 异常
     * @return void
     */
    protected function handleGenericException(Throwable $e): void
    {
        $this->phone && $this->phone->update(['status' => Phone::STATUS_NORMAL]);
        $this->email && $this->email->update(['status' => EmailStatus::FAILED]);
        $this->log('注册失败', ['message' => $e->getMessage()]);
    }


    protected function debugRequest(): callable
    {
        return function (PendingRequest $pendingRequest) {
            $psrRequest = $pendingRequest->createPsrRequest();

            $headers = array_map(function ($value) {
                return implode(';', $value);
            }, $psrRequest->getHeaders());

            $connectorClass = $pendingRequest->getConnector()::class;
            $requestClass   = $pendingRequest->getRequest()::class;
            $enum           = Request::fromClass($connectorClass) ?? Request::fromClass($requestClass);

            $label = $enum?->label() ?? '未知请求';

            $body = (string)$psrRequest->getBody() ?: '{}';

            try {

                $jsonBody = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $jsonBody = [];
            }

            $this->log("{$label} 请求", [
                'connector' => $connectorClass,
                'request'   => $requestClass,
                'method'    => $psrRequest->getMethod(),
                'uri'       => (string)$psrRequest->getUri(),
                'headers'   => $headers,
                'proxy'     => $pendingRequest->config()->get(RequestOptions::PROXY),
                'body'      => $jsonBody,
            ]);
        };
    }

    /**
     * 记录日志
     */
    protected function log(string $message, array $data = []): void
    {
        $this->email->createLog($message, $data);
    }

    protected function debugResponse(): callable
    {
        return function (Response $response) {
            $psrResponse = $response->getPsrResponse();

            $headers = array_map(function ($value) {
                return implode(';', $value);
            }, $psrResponse->getHeaders());

            $connectorClass = $response->getConnector()::class;
            $requestClass   = $response->getRequest()::class;

            $enum  = Request::fromClass($connectorClass) ?? Request::fromClass($requestClass);
            $label = $enum?->label() ?? '未知响应';

            try {
                $jsonBody = json_decode($response->body() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $jsonBody = $response->body();

                //避免字符串过长
                if (strlen($jsonBody) > 1000) {
                    $jsonBody = substr($jsonBody, 0, 1000).'...';
                }
            }

            $this->log("{$label} 响应", [
                'status'  => $response->status(),
                'headers' => $headers,
                'body'    => $jsonBody,
            ]);
        };
    }

    /**
     * @param string $XAppleIdSessionId
     * @param string $XAppleWidgetKey
     * @return CaptchaResponse
     * @throws FatalRequestException
     * @throws RequestException
     */
    protected function captcha(string $XAppleIdSessionId, string $XAppleWidgetKey): CaptchaResponse
    {
        return $this->captchaResponse = $this->apple->appleIdConnector()->getAccountResource()->captcha(
            $XAppleIdSessionId,
            $XAppleWidgetKey
        );
    }

    /**
     * @param string $appleIdSessionId
     * @param string $widgetKey
     * @param int $attempts
     * @return Response
     * @throws AccountAlreadyExistsException
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException|RegistrationException|MaxRetryAttemptsException
     */
    protected function attemptsCaptcha(
        string $appleIdSessionId,
        string $widgetKey,
        int $attempts = 5
    ): Response {

        for ($i = 0; $i < $attempts; $i++) {
            try {

                $response = $this->cloudCodeService->resolveCaptcha($this->captchaResponse->payload->content);

                $this->updateCaptchaValidation($response);

                return $this->validateCaptcha($appleIdSessionId, $widgetKey);
            } catch (CaptchaException|DecryptCloudCodeException $e) {

                $this->captcha($appleIdSessionId, $widgetKey);
            }
        }

        throw new MaxRetryAttemptsException("验证码验证失败，已尝试 {$attempts} 次");
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
     * @param string $appleIdSessionId 会话ID
     * @param string $widgetKey widget密钥
     * @return Response 验证响应
     * @throws AccountAlreadyExistsException
     * @throws CaptchaException
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException|RegistrationException
     */
    protected function validateCaptcha(string $appleIdSessionId, string $widgetKey): Response
    {
        return $this->apple->appleIdConnector()->getAccountResource()->validate(
            validateDto: $this->validate,
            appleIdSessionId: $appleIdSessionId,
            appleWidgetKey: $widgetKey
        );
    }

    /**
     * @param string $XAppleSessionId
     * @param string $widgetKey
     * @param int $attempts
     * @return Response
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws MaxRetryVerificationEmailCodeException
     * @throws RequestException|MaxRetryGetEmailCodeException|ConnectionException
     */
    protected function attemptVerificationEmailCode(
        string $XAppleSessionId,
        string $widgetKey,
        int $attempts = 5
    ): Response {
        for ($i = 0; $i < $attempts; $i++) {
            try {

                $response = $this->sendVerificationEmail($XAppleSessionId, $widgetKey);

                $this->validate->account->verificationInfo->id = $response->verificationId;

                $emailCode = $this->attemptGetEmailCode($this->email->email, $this->email->email_uri);

                $this->validate->account->verificationInfo->answer = $emailCode;

                return $this->verifyEmailCode($XAppleSessionId, $widgetKey);
            } catch (VerificationCodeException $e) {

            }
        }

        throw new MaxRetryVerificationEmailCodeException("邮箱验证码验证失败，已尝试 {$attempts} 次");
    }

    /**
     * 验证邮箱验证码
     *
     * @param string $XAppleSessionId 会话ID
     * @param string $widgetKey Widget Key
     * @return Response 验证响应
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException|VerificationCodeException
     */
    protected function verifyEmailCode(string $XAppleSessionId, string $widgetKey): Response
    {
        $verificationPut = VerificationEmail::from([
            'name'             => $this->email->email,
            'verificationInfo' => $this->validate->account->verificationInfo,
        ]);

        return $this->apple->appleIdConnector()->getAccountResource()->verificationEmail(
            $verificationPut,
            $XAppleSessionId,
            $widgetKey
        );
    }

    /**
     * @param string $XAppleSessionId
     * @param string $widgetKey
     * @return SendVerificationEmailResponse
     * @throws FatalRequestException
     * @throws RequestException
     */
    protected function sendVerificationEmail(string $XAppleSessionId, string $widgetKey): SendVerificationEmailResponse
    {
        $data = SendVerificationEmail::from([
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
        ]);

        return $this->apple->appleIdConnector()
            ->getAccountResource()
            ->sendVerificationEmail($data, $XAppleSessionId, $widgetKey)
            ->dto();
    }

    /**
     * @param string $email
     * @param string $uri
     * @param int $attempts
     * @return string
     * @throws MaxRetryGetEmailCodeException|ConnectionException
     */
    protected function attemptGetEmailCode(string $email, string $uri, int $attempts = 5): string
    {
        $isSuccess = false;

        for ($i = 0; $i < $attempts; $i++) {
            // 添加延迟时间，避免请求过于频繁
            sleep($i * 5);

            // 获取邮箱验证码
            $response = Http::retry(5, 100)->get($uri);
            $this->log('获取邮箱验证码', ['response' => $response->json()]);

            // 验证响应状态
            if (!$this->isValidEmailResponse($response)) {
                continue;
            }

            // 提取验证码
            $code = $this->extractEmailCode($response);
            if (empty($code)) {
                continue;
            }

            // 验证缓存中的验证码
            $cacheCode = Cache::get($email);
            if ($cacheCode === $code) {
                continue;
            }

            // 首次成功获取验证码的标记
            if (empty($cacheCode) && $isSuccess === false) {
                $isSuccess = true;
                continue;
            }

            // 缓存验证码
            Cache::put($email, $code, 60 * 30);

            return $code;
        }

        throw new MaxRetryGetEmailCodeException("尝试获取邮箱验证码失败，已尝试 {$attempts} 次");
    }

    /**
     * 验证邮箱响应是否有效
     *
     * @param \Illuminate\Http\Client\Response $response 响应对象
     * @return bool 是否有效
     */
    protected function isValidEmailResponse(\Illuminate\Http\Client\Response $response): bool
    {
        return $response->json('status') === 1 || $response->json('statusCode') === 200 || $response->json('code') === 0;
    }

    /**
     * 提取邮箱验证码
     *
     * @param \Illuminate\Http\Client\Response $response 响应对象
     * @return string|null 验证码
     */
    protected function extractEmailCode(\Illuminate\Http\Client\Response $response): ?string
    {

        if ($response->json('message.email_code')) {
            return $response->json('message.email_code');
        }

        if ($response->json('data.code')) {
            return $response->json('data.code');
        }

        if ($response->json('email_code')) {
            return $response->json('email_code');
        }

        $string = $response->json('data.result');
        if ($string && is_string($string)) {
            preg_match('/您的验证码是：(\d+)/', $string, $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @param string $appleIdSessionId
     * @param string $widgetKey
     * @param int $attempts
     * @return Response
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws MaxRetryVerificationPhoneCodeException
     * @throws NumberFormatException
     * @throws RequestException|ConnectionException
     */
    protected function attemptVerificationPhoneCode(
        string $appleIdSessionId,
        string $widgetKey,
        int $attempts = 5
    ): Response {
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $this->sendPhoneVerificationCode($appleIdSessionId, $widgetKey);

                $code = $this->attemptGetPhoneCode($this->phone);

                $this->setPhoneVerificationCode($code);

                return $this->verifyPhoneCode($appleIdSessionId, $widgetKey);
            } catch (PhoneException|MaxRetryGetPhoneCodeException $e) {

                $this->handlePhoneVerificationFailure();
            } catch (VerificationCodeException $e) {

            }
        }

        throw new MaxRetryVerificationPhoneCodeException("手机验证码验证失败，已尝试 {$attempts} 次");
    }

    /**
     * 发送手机验证码
     *
     * @param string $appleIdSessionId 会话ID
     * @param string $widgetKey Widget Key
     * @return Response 发送响应
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws PhoneException
     * @throws RequestException
     */
    protected function sendPhoneVerificationCode(string $appleIdSessionId, string $widgetKey): Response
    {
        return $this->apple->appleIdConnector()->getAccountResource()->sendVerificationPhone(
            $this->validate,
            $appleIdSessionId,
            $widgetKey
        );
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
     * @param string $appleIdSessionId 会话ID
     * @param string $widgetKey Widget Key
     * @return Response 验证响应
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws RequestException|VerificationCodeException
     */
    protected function verifyPhoneCode(string $appleIdSessionId, string $widgetKey): Response
    {
        return $this->apple->appleIdConnector()->getAccountResource()->verificationPhone(
            $this->validate,
            $appleIdSessionId,
            $widgetKey
        );
    }

    /**
     * 处理手机验证失败
     *
     * @return void
     * @throws NumberFormatException
     */
    protected function handlePhoneVerificationFailure(): void
    {
        // 添加黑名单
        $this->addActiveBlacklistIds($this->phone->id);

        $this->phone->update(['status' => Phone::STATUS_INVALID]);
        $this->phone = $this->getPhone();

        $this->validate->phoneNumberVerification = PhoneNumberVerification::from([
            'phoneNumber' => [
                'id'              => 1,
                'number'          => $this->phone->getPhoneNumberService()->format(PhoneNumberFormat::NATIONAL),
                'countryCode'     => $this->phone->country_code,
                'countryDialCode' => $this->phone->country_dial_code,
                'nonFTEU'         => true,
            ],
            'mode'        => 'sms',
        ]);
    }

    /**
     * @param Phone $phone
     * @param int $attempts
     * @return string
     * @throws MaxRetryGetPhoneCodeException|ConnectionException
     */
    protected function attemptGetPhoneCode(Phone $phone, int $attempts = 5): string
    {
        for ($i = 0; $i < $attempts; $i++) {
            // 添加延迟时间，避免请求过于频繁
            sleep($i * 5);

            // 获取手机验证码
            $response = Http::retry(5, 100)->get($phone->phone_address);

            $this->log('手机验证码', ['response' => $response->body()]);

            // 提取验证码
            $code = self::parse($response->body());
            if ($code) {
                return $code;
            }
        }

        throw new MaxRetryGetPhoneCodeException("尝试获取手机验证码失败，已尝试 {$attempts} 次");
    }

    protected static function parse(string $str): ?string
    {
        if (preg_match('/\b\d{6}\b/', $str, $matches)) {
            return $matches[0];
        }

        return null;
    }
}































