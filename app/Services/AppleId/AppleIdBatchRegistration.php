<?php

namespace App\Services\AppleId;

use App\Models\Appleid;
use App\Models\Email;
use App\Models\Phone;
use App\Models\EmailLog;
use Exception;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
use Saloon\Http\Response;
use Weijiajia\DecryptVerificationCode\CloudCode\CloudCodeConnector;
use Weijiajia\DecryptVerificationCode\Exception\DecryptCloudCodeException;
use Weijiajia\HttpProxyManager\Contracts\ProxyInterface;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\SaloonphpAppleClient\Exception\CaptchaException;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\PhoneException;
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
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Response\Account\Verification\SendVerificationEmail as SendVerificationEmailResponse;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Resources\AccountResource;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use App\Enums\EmailStatus;
use Throwable;
use Saloon\Http\PendingRequest;
use GuzzleHttp\RequestOptions;
use App\Enums\Request;

class AppleIdBatchRegistration
{
    //验证码
    protected Captcha $captcha;

    //手机号码验证
    protected PhoneNumberVerification $phoneNumberVerification;

    //验证账号
    protected VerificationAccount $verificationAccount;

    //验证
    protected Validate $validate;

    //验证信息
    protected VerificationInfo $verificationInfo;

    protected ?AccountResource $resource = null;

    protected ?ProxySplQueue $queue = null;

    /** @var CookieJar $cookieJar */
    protected CookieJar $cookieJar;

    protected ?Phone $phone = null;

    //使用过的手机号码
    protected array $usedPhones = [];

    protected ?Email $email = null;

    /**
     * @param ProxyManager $proxyManager
     * @param LoggerInterface $logger
     * @param AppleIdConnector $connector
     * @param CloudCodeConnector $cloudCodeConnector
     */
    public function __construct(
        protected ProxyManager $proxyManager,
        protected LoggerInterface $logger,
        protected AppleIdConnector $connector = new AppleIdConnector(),
        protected CloudCodeConnector $cloudCodeConnector = new CloudCodeConnector(),
    ) {

    }

    /**
     * @param Email $email
     * @param bool $isUseProxy
     * @return bool
     * @throws ClientException
     * @throws DecryptCloudCodeException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws NumberFormatException
     * @throws RandomException
     * @throws RequestException
     * @throws Throwable
     */
    public function run(Email $email, bool $isUseProxy = false): bool
    {
        $this->email = $email;

        try {

            // 更新邮箱状态为处理中
            $email->update(['status' => EmailStatus::PROCESSING]);

            $cookiePath = storage_path("app/public/{$this->email->email}.json");

            $this->cookieJar = new FileCookieJar($cookiePath, true);

            if ($isUseProxy) {
                $this->initProxy();
                $this->connector->withSplQueue($this->queue);
            }

            $this->connector->withLogger($this->logger);
            $this->connector->withCookies($this->cookieJar);
            $this->connector->debug();
            $this->connector->debugRequest($this->debugRequest());
            $this->connector->debugResponse($this->debugResponse());

            $this->cloudCodeConnector->debug();
            $this->cloudCodeConnector->withLogger($this->logger);
            $this->cloudCodeConnector->debugRequest($this->debugRequest());
            $this->cloudCodeConnector->debugResponse($this->debugResponse());

            $this->log('开始注册 Apple ID', [
                'cookie_path' => $cookiePath,
                'proxy'       => [
                    'isUseProxy' => $isUseProxy,
                    'proxy'      => $this->queue?->getAllProxies(),
                ],
            ]);

            $this->verificationInfo = VerificationInfo::from([
                'id'     => '',
                'answer' => '',
            ]);

            //生成长度为8-20的密码的大小写混合加数字的密码
            $password  = self::generatePassword();
            $firstName = fake()->firstName();
            $lastName  = fake()->lastName();

            $this->verificationAccount = VerificationAccount::from([
                'name'             => $this->email->email,
                'password'         => $password,
                'person'           => [
                    'name'           => [
                        'firstName' => $firstName,
                        'lastName'  => $lastName,
                    ],
                    'birthday'       => '1996-06-12',
                    'primaryAddress' => [
                        'country' => 'USA',
                    ],
                ],
                'preferences'      => [
                    'preferredLanguage'    => 'en_US',
                    'marketingPreferences' => [
                        'appleNews'     => false,
                        'appleUpdates'  => true,
                        'iTunesUpdates' => true,
                    ],
                ],
                'verificationInfo' => $this->verificationInfo,
            ]);

            $this->phone = $this->getPhone();


            $this->resource = $this->connector->getAccountResource();

            $response        = $this->resource->widgetAccount();
            $XAppleSessionId = $this->cookieJar->getCookieByName('aidsp')?->getValue();

            if (!$XAppleSessionId) {
                throw new RuntimeException('X-Apple-Session-Id not found');
            }

            $this->connector->headers()->add(
                'X-Apple-Widget-Key',
                'd39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d'
            );
            $this->connector->headers()->add('x-apple-request-context', 'create');
            $this->connector->headers()->add('x-apple-id-session-id', $XAppleSessionId);

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
                true
            );

            $this->attemptsCaptcha();


            $response = $this->sendVerificationEmail();

            $this->verificationInfo->id = $response->verificationId;

            $this->attemptVerificationEmailCode();


            $this->attemptsSendVerificationPhoneCode();

            $this->attemptVerificationPhoneCode();

            $accountResponse = $this->resource->account($this->validate);

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

            // 注册成功，更新状态
            $email->update(['status' => EmailStatus::REGISTERED]);
            $this->phone->update(['status' => Phone::STATUS_BOUND]);
            $this->log('注册成功', ['account_details' => $accountResponse->json()]);

            // 注册成功，返回 true
            return true;
        } catch (Exception|Throwable $e) {
            $this->phone && $this->phone->update(['status' => Phone::STATUS_NORMAL]);
            $this->email && $this->email->update(['status' => EmailStatus::FAILED]);
            $this->log('注册失败', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function initProxy(): void
    {
        $proxyConnector = $this->proxyManager->driver();
        $proxyConnector->withLogger($this->logger);
        $proxyConnector->debug();
        $proxyConnector->debugRequest($this->debugRequest());
        $proxyConnector->debugResponse($this->debugResponse());

        $proxy = $proxyConnector->default();

        $this->queue = new ProxySplQueue();
        if ($proxy instanceof Collection) {

            $proxy->each(fn(ProxyInterface $item) => $this->queue->enqueue($item->getUrl()));

        } else {
            $this->queue->enqueue($proxy->getUrl());
        }
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

        $this->logger->info($message, $data);
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
            }

            $this->log("{$label} 响应", [
                'status'  => $response->status(),
                'headers' => $headers,
                'body'    => $jsonBody,
            ]);
        };

    }

    /**
     * @return string
     * @throws RandomException
     */
    public static function generatePassword(): string
    {
        $length     = random_int(8, 20);
        $uppercase  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase  = 'abcdefghijklmnopqrstuvwxyz';
        $numbers    = '0123456789';
        $characters = $uppercase.$lowercase.$numbers;

        $password = '';
        // 确保至少包含一个大写字母
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        // 确保至少包含一个小写字母
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        // 确保至少包含一个数字
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];

        // 填充剩余长度
        for ($i = 3; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // 打乱密码字符顺序
        return str_shuffle($password);
    }

    public function getPhone(): Phone
    {
        return DB::transaction(function () {

            $phone = Phone::query()
                ->where('status', Phone::STATUS_NORMAL)
                ->whereNotNull(['phone_address', 'phone'])
                ->whereNotIn('id', $this->usedPhones)
                ->lockForUpdate()
                ->firstOrFail();

            $phone->update(['status' => Phone::STATUS_BINDING]);

            $this->usedPhones[] = $phone->id;

            return $phone;
        });
    }

    /**
     * @param int $attempts
     * @return Response
     * @throws ClientException
     * @throws JsonException
     * @throws FatalRequestException
     * @throws RequestException
     * @throws DecryptCloudCodeException
     */
    public function attemptsCaptcha(int $attempts = 5): Response
    {

        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 3);

            try {

                $captcha = $this->resource->captcha()->dto();

                // 获取验证码图片
                $captchaImage = $captcha->payload->content;

                // 保存验证码图片
                $imagePath = storage_path("app/public/captcha.jpeg");

                file_put_contents($imagePath, base64_decode($captchaImage));

                $cloudCodeResponse = $this->cloudCodeConnector->decryptCloudCode(
                    token: 'Hb1SOEObuMJyjEViLsaPI5M3SHCR1K-kToy5JKagxU0',
                    type: '10110',
                    image: $captcha->payload->content,
                );

                $this->validate->captcha->id     = $captcha->id;
                $this->validate->captcha->token  = $captcha->token;
                $this->validate->captcha->answer = $cloudCodeResponse->getCode();


                return $this->resource->validate($this->validate);

            } catch (CaptchaException $e) {

                $this->log("第{$i}次验证验证码失败", ['message' => $e->getMessage()]);
            }

        }

        throw new RuntimeException("captcha failed of attempt {$attempts} times");
    }

    /**
     * @return SendVerificationEmailResponse
     * @throws FatalRequestException
     * @throws RequestException
     */
    protected function sendVerificationEmail(): SendVerificationEmailResponse
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
            'countryCode' => 'USA',
        ]);

        return $this->resource
            ->sendVerificationEmail($data)
            ->dto();
    }

    /**
     * @param int $attempts
     * @return Response
     * @throws ClientException
     * @throws JsonException
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function attemptVerificationEmailCode(int $attempts = 5): Response
    {

        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 3);

            try {
                $emailCode = $this->attemptGetEmailCode($this->email->email, $this->email->email_uri);

                $this->verificationInfo->answer = $emailCode;

                $verificationPutDto = VerificationEmail::from([
                    'name'             => $this->email->email,
                    'verificationInfo' => $this->verificationInfo,
                ]);

                //验证邮箱验证码
                return $this->resource->verificationEmail($verificationPutDto);
            } catch (VerificationCodeException $e) {

                $this->log("第 {$i} 次验证邮箱验证码失败", ['message' => $e->getMessage()]);
            }
        }

        throw new RuntimeException("verification email code failed of attempt {$attempts} times");
    }

    public function attemptGetEmailCode(string $email, string $uri, int $attempts = 5)
    {

        $isSuccess = false;
        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 3);

            $response = Http::get($uri);

            $this->log('获取邮箱验证码', ['request' => $uri, 'response' => $response->json()]);

            if ($response->json('status') !== 1) {
                continue;
            }

            $code = $response->json('message.email_code');

            if (empty($code)) {
                continue;
            }

            $cacheCode = Cache::get($email);

            if ($cacheCode === $code) {
                continue;
            }

            if (empty($cacheCode) && $isSuccess === false) {
                $isSuccess = true;
                continue;
            }

            Cache::put($email, $code, 60 * 666);

            return $code;
        }

        throw new RuntimeException('get email code failed of attempt '.$attempts.' times');
    }

    /**
     * @param int $attempts
     * @return Response
     * @throws ClientException
     * @throws FatalRequestException
     * @throws NumberFormatException
     * @throws RequestException
     * @throws JsonException
     */
    public function attemptsSendVerificationPhoneCode(int $attempts = 5): Response
    {
        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 3);

            try {

                return $this->resource->sendVerificationPhone($this->validate);

            } catch (PhoneException $e) {

                $this->log("第 {$i} 次发送手机验证码失败", ['message' => $e->getMessage()]);

                $this->phone->update(['status' => Phone::STATUS_NORMAL]);
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
        }

        throw new RuntimeException('Failed to send verification phone code after '.$attempts.' times');
    }

    /**
     * @param int $attempts
     * @return Response
     * @throws ClientException
     * @throws FatalRequestException
     * @throws RequestException
     * @throws JsonException
     */
    public function attemptVerificationPhoneCode(int $attempts = 5): Response
    {
        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 3);

            try {

                $code = $this->attemptGetPhoneCode($this->phone);

                $securityCode = SecurityCode::from([
                    'code' => $code,
                ]);

                $this->phoneNumberVerification->securityCode = $securityCode;

                return $this->resource->verificationPhone($this->validate);

            } catch (VerificationCodeException $e) {

                $this->log("第{$i}次验证手机验证码失败", ['message' => $e->getMessage()]);
            }
        }

        throw new RuntimeException("verification phone code failed of attempt {$attempts} times");
    }

    public function attemptGetPhoneCode(Phone $phone, int $attempts = 10): string
    {

        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 3);

            $response = Http::get($phone->phone_address);

            $this->log('获取手机验证码', ['request' => $phone->phone_address, 'response' => $response->json()]);

            $code = $this->parse($response->body());

            if ($code) {
                return $code;
            }
        }

        throw new RuntimeException("Attempt {$attempts} times failed to get phone code");
    }

    public function parse(string $str): ?string
    {
        if (preg_match('/\b\d{6}\b/', $str, $matches)) {
            return $matches[0];
        }

        return null;
    }
}































