<?php

namespace App\Services\AppleId;

use App\Models\Appleid;
use App\Models\Email;
use App\Models\Phone;
use App\Services\Exception\RegistrationException;
use Exception;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use JsonException;
use libphonenumber\PhoneNumberFormat;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use RuntimeException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ServiceUnavailableException;
use Saloon\Http\Response;
use Weijiajia\DecryptVerificationCode\CloudCode\CloudCodeConnector;
use Weijiajia\DecryptVerificationCode\Exception\DecryptCloudCodeException;
use Weijiajia\HttpProxyManager\Contracts\ProxyInterface;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\SaloonphpAppleClient\Exception\CaptchaException;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
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
use App\Services\AppleClientIdService;

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

    protected ?string $country = null;

    protected ?int $hcBits = null;

    protected ?string $hcChallenge = null;

    // 添加类常量
    public const string PHONE_BLACKLIST_KEY = 'phone_code_blacklist';
    public const int BLACKLIST_EXPIRE_SECONDS = 3600; // 1小时过期

    public static array $preferredLanguage = [
        'USA' => 'en_US',
        'CAN' => 'en_GB',
    ];

    // 国家到标准时区标识符的映射
    public static array $countryTimeZoneIdentifiers = [
        'USA' => 'America/New_York', // 美国东部时间，可根据需要更改
        'CAN' => 'America/Edmonton',  // 加拿大东部时间
        'GBR' => 'Europe/London',    // 英国时间
        'CHN' => 'Asia/Shanghai',    // 中国时间
        'AUS' => 'Australia/Sydney', // 澳大利亚东部时间
        'JPN' => 'Asia/Tokyo',       // 日本时间
        'FRA' => 'Europe/Paris',     // 法国时间
        'DEU' => 'Europe/Berlin',    // 德国时间
        'IND' => 'Asia/Kolkata',     // 印度时间
        'BRA' => 'America/Sao_Paulo',// 巴西圣保罗时间
        // 可以添加更多国家...
    ];

    public static function countryTimeZoneIdentifiers(string $country): string
    {
        return self::$countryTimeZoneIdentifiers[$country] ?? 'America/New_York';
    }

        /**
     * 根据国家代码动态获取当前的GMT时区字符串
     *
     * @param string $country 三字母国家代码
     * @return string 格式化为 'GMT±HH:MM' 的时区字符串
     */
    public static function getCountryTimezone(string $country): string
    {
        // 获取国家对应的时区标识符，如果不存在则使用纽约时间作为默认值
        $timezoneIdentifier = self::$countryTimeZoneIdentifiers[$country] ?? 'America/New_York';

        // 创建时区对象
        $timezone = new \DateTimeZone($timezoneIdentifier);

        // 获取当前时间在该时区的DateTime对象
        $date = new \DateTime('now', $timezone);

        // 获取与UTC的偏移秒数
        $offsetSeconds = $timezone->getOffset($date);

        // 将秒数转换为小时和分钟
        $offsetHours = floor(abs($offsetSeconds) / 3600);
        $offsetMinutes = floor((abs($offsetSeconds) % 3600) / 60);

        // 格式化为 'GMT±HH:MM' 格式
        $sign = $offsetSeconds >= 0 ? '+' : '-';
        return sprintf('GMT%s%02d:%02d', $sign, $offsetHours, $offsetMinutes);
    }


    /**
     * @param ProxyManager $proxyManager
     * @param LoggerInterface $logger
     * @param AppleIdConnector $connector
     * @param CloudCodeConnector $cloudCodeConnector
     */
    public function __construct(
        protected ProxyManager $proxyManager,
        protected LoggerInterface $logger,
        protected AppleClientIdService $appleClientIdService,
        protected AppleIdConnector $connector = new AppleIdConnector(),
        protected CloudCodeConnector $cloudCodeConnector = new CloudCodeConnector(),

    ) {

    }


    public static function preferredLanguage(string $country): string
    {
        return self::$preferredLanguage[$country] ?? 'en_US';
    }

    /**
     * 计算满足指定位数前导零的哈希挑战
     *
     * @param int $version 版本号
     * @param int $bits 需要满足的前导零位数
     * @param int $date 日期字符串
     * @param string $challenge 挑战字符串
     * @return string|null 满足条件的哈希挑战字符串
     */
    public static function calculate_hc(int $version, int $bits, int $date, string $challenge): ?string
    {
        $counter = 0;

        while (true) {
            // 构建挑战字符串
            $hc = implode(":", [$version, $bits, $date, $challenge, ":" . $counter]);

            // 计算 SHA-1 哈希值 (使用 raw_output=true 获取二进制形式)
            $hashed_hc = sha1($hc, true);

            // 将二进制转换为位字符串
            $binary_hc = '';
            for ($i = 0, $iMax = strlen($hashed_hc); $i < $iMax; $i++) {
                // 将每个字节转换为8位二进制表示，并确保前导零被保留
                $binary_hc .= str_pad(decbin(ord($hashed_hc[$i])), 8, '0', STR_PAD_LEFT);
            }

            // 检查前 $bits 位是否全为零
            if (substr($binary_hc, 0, $bits) === str_repeat('0', $bits)) {
                return $hc;
            }

            $counter++;
        }
    }


    /**
     * @param Email $email
     * @param bool $isUseProxy
     * @param string $country
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
    public function run(Email $email, bool $isUseProxy = false,string $country = 'USA'): bool
    {
        $this->email = $email;
        $this->country = $country;

        try {

            // 更新邮箱状态为处理中
            $email->update(['status' => EmailStatus::PROCESSING]);

            $cookiePath = storage_path("app/public/{$this->email->email}.json");

            //清除cookie
            if (file_exists($cookiePath)) {
                unlink($cookiePath);
            }

            $this->cookieJar = new FileCookieJar($cookiePath, true);

            if ($isUseProxy) {
                $this->initProxy();
                $this->connector->withSplQueue($this->queue);
            }
            $this->connector->withLogger($this->logger);
            $this->connector->withCookies($this->cookieJar);
            $this->connector->debug();

            $this->connector->middleware()->onRequest($this->setHcBitsAndChallenge());
            $this->connector->middleware()->onRequest($this->debugRequest());

            $this->connector->middleware()->onResponse($this->getHcBitsAndChallenge());
            $this->connector->middleware()->onResponse($this->debugResponse());

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
                        'country' => $country,
                    ],
                ],
                'preferences'      => [
                    'preferredLanguage'    => self::preferredLanguage($country),
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

            $response        = $this->resource->widgetAccount(
                appContext: 'icloud',
                widgetKey: 'd39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d',
                lv: '0.3.16',
                referer: 'https://www.icloud.com/'
            );

            $XAppleSessionId = $this->cookieJar->getCookieByName('aidsp')?->getValue();

            if (!$XAppleSessionId) {
                throw new RuntimeException('X-Apple-Session-Id not found');
            }

            $this->connector->headers()->add(
                'X-Apple-Widget-Key',
                'd39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d'
            );

            $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

            $language = self::countryTimeZoneIdentifiers($country);
            $xAppleITimeZone = self::getCountryTimezone($country);
            $clientInfo = $this->appleClientIdService->getClientId([
                'userAgent' => $userAgent,
                'language' => $language,
                'timeZone' => $xAppleITimeZone,
                'plugins' => [],
            ]);

            if (empty($clientInfo['fullData'])){
                throw new RuntimeException('clientInfo not found');
            }

            $this->connector->headers()->add('X-Apple-Request-Context', 'create');
            $this->connector->headers()->add('X-Apple-Id-Session-Id', $XAppleSessionId);
            $this->connector->headers()->add('Accept-Language', "{$language},en;q=0.9");
            $this->connector->headers()->add('X-Apple-I-Timezone', $xAppleITimeZone);
            $this->connector->headers()->add('Sec-Ch-Ua', '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"');
            $this->connector->headers()->add('Sec-Ch-Mobile', '?0');
            $this->connector->headers()->add('Sec-Ch-Mobile', '?0');
            $this->connector->headers()->add('Sec-Ch-Platform', 'Windows');
            $this->connector->headers()->add('User-Agent', $userAgent);
            $this->connector->headers()->add('X-Apple-I-Fd-Client-Info', $clientInfo['fullData']);

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
        }catch (ClientException | MaxRetryAttemptsException $e){

            $this->phone && $this->phone->update(['status' => Phone::STATUS_NORMAL]);

            if ($e instanceof MaxRetryAttemptsException){
                $this->email && $this->email->update(['status' => EmailStatus::INVALID]);
            }else{
                $this->email && $this->email->update(['status' => EmailStatus::FAILED]);
            }

            $this->log('注册失败', ['message' => $e->getMessage()]);

            $validationErrors = $e->getResponse()->json('service_errors');
            if ($validationErrors[0]['code'] ?? '' === '-34607001') {
                throw new RegistrationException(message: json_encode($validationErrors, JSON_THROW_ON_ERROR));
            }

            $validationErrors = $e->getResponse()->json('validationErrors');
            if ($validationErrors[0]['code'] ?? '' === 'captchaAnswer.Invalid') {
                throw new RegistrationException(message: json_encode($validationErrors, JSON_THROW_ON_ERROR));
            }

            throw $e;

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

    protected function getHcBitsAndChallenge(): callable
    {
        return function (Response $response) {

            $xAppleHcBits = $response->headers()->get('X-Apple-HC-Bits');
            $xAppleHcChallenge = $response->headers()->get('X-Apple-HC-Challenge');

            if (!empty($xAppleHcBits) && !empty($xAppleHcChallenge)){
                $this->hcBits = $xAppleHcBits;
                $this->hcChallenge = $xAppleHcChallenge;
            }
        };
     }

     protected function setHcBitsAndChallenge(): callable
     {
        return function (PendingRequest $pendingRequest) {

            if (!empty($this->hcBits) && !empty($this->hcChallenge)){
                $hc = self::calculate_hc(1, $this->hcBits, date('ymdhis'), $this->hcChallenge);
                $pendingRequest->headers()->add('X-Apple-Hc', $hc);
            }

            return $pendingRequest;
        };
     }


    /**
     * Generate a secure random password with specific requirements
     *
     * @param int $minLength Minimum password length (must be at least 4)
     * @param int $maxLength Maximum password length
     * @return string The generated password
     * @throws \InvalidArgumentException If invalid length parameters are provided
     * @throws \Random\RandomException If random_int fails
     */
    public static function generatePassword(int $minLength = 8, int $maxLength = 20): string
    {
        // Validate input parameters
        if ($minLength < 8) {
            throw new \InvalidArgumentException('Minimum length must be at least 8 to accommodate required character types');
        }

        if ($minLength > $maxLength) {
            throw new \InvalidArgumentException('Minimum length cannot be greater than maximum length');
        }

        // Define character sets
        $charSets = [
            'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'lowercase' => 'abcdefghijklmnopqrstuvwxyz',
            'numbers'   => '0123456789',
            'special'   => '!@#$%^&*()_+-=[]{}|;:,.<>?'
        ];

        // Determine password length
        $length = random_int($minLength, $maxLength);

        // Start with empty password
        $password = '';

        // Add one character from each required set
        foreach ($charSets as $type => $chars) {
            $randomIndex = random_int(0, strlen($chars) - 1);
            $password .= $chars[$randomIndex];
        }

        // Create a combined character set for remaining characters
        $allChars = implode('', $charSets);
        $allCharsLength = strlen($allChars) - 1;

        // Fill the remaining length with random characters
        $remainingLength = $length - count($charSets);
        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $allChars[random_int(0, $allCharsLength)];
        }

        // Shuffle the password to avoid predictable patterns
        return str_shuffle($password);
    }

    public function getPhone(): Phone
    {
        return DB::transaction(function () {

            // 获取有效黑名单ID
            $blacklistIds = $this->getActiveBlacklistIds();

            $phone = Phone::query()
                ->where('status', Phone::STATUS_NORMAL)
                ->whereNotNull(['phone_address', 'phone'])
                ->whereNotIn('id', $this->usedPhones)
//                ->whereNotIn('id', $blacklistIds)
                ->where('country_code_alpha3', $this->country)
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

            } catch (CaptchaException|DecryptCloudCodeException $e) {

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
            'countryCode' => $this->country,
        ]);

        return $this->resource
            ->sendVerificationEmail($data)
            ->dto();
    }

    /**
     * 获取当前有效的黑名单手机号ID
     *
     * @return array
     */
    protected function getActiveBlacklistIds(): array
    {

        // 获取所有黑名单记录
        $blacklist = Redis::hgetall(self::PHONE_BLACKLIST_KEY);

        // 过滤出未过期的黑名单手机号ID
        return array_keys(array_filter($blacklist, function ($timestamp) {
            return (now()->timestamp - $timestamp) < self::BLACKLIST_EXPIRE_SECONDS;
        }));
    }

    protected function addActiveBlacklistIds(int $id): void
    {
        Redis::hset(self::PHONE_BLACKLIST_KEY, $id, now()->timestamp);
        Redis::expire(self::PHONE_BLACKLIST_KEY, self::BLACKLIST_EXPIRE_SECONDS);
    }

    /**
     * @param int $attempts
     * @return Response
     * @throws ClientException
     * @throws JsonException
     * @throws FatalRequestException
     * @throws RequestException|MaxRetryAttemptsException
     */
    public function attemptVerificationEmailCode(int $attempts = 5): Response
    {

        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 3);

            try {
                $emailCode = $this->attemptGetEmailCode($this->email->email, $this->email->email_uri);

                $this->validate->account->verificationInfo->answer = $emailCode;

                $verificationPut = VerificationEmail::from([
                    'name'             => $this->email->email,
                    'verificationInfo' => $this->validate->account->verificationInfo,
                ]);

                //验证邮箱验证码
                return $this->resource->verificationEmail($verificationPut);

            } catch (VerificationCodeException $e) {

                //重新发送邮件
                $response = $this->sendVerificationEmail();

                $this->validate->account->verificationInfo->id = $response->verificationId;
            }
        }

        throw new RuntimeException("verification email code failed of attempt {$attempts} times");
    }

    /**
     * @param string $email
     * @param string $uri
     * @param int $attempts
     * @return string
     * @throws MaxRetryAttemptsException
     */
    public function attemptGetEmailCode(string $email, string $uri, int $attempts = 5): string
    {

        $isSuccess = false;
        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 5);

            $response = Http::get($uri);

            $this->log('获取邮箱验证码', ['request' => $uri, 'response' => $response->json()]);

            if ($response->json('status') !== 1 && $response->json('statusCode') !== 200) {
                continue;
            }

            $code = $response->json('message.email_code') ?: $response->json('data.code');

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

        throw new MaxRetryAttemptsException('get email code failed of attempt '.$attempts.' times');
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
    public function attemptsSendVerificationPhoneCode(int $attempts = 10): Response
    {
        for ($i = 0; $i < $attempts; $i++) {

            sleep(5);

            try {

                return $this->resource->sendVerificationPhone($this->validate);

            } catch (PhoneException $e) {

                // 添加黑名单
                $this->addActiveBlacklistIds($this->phone->id);

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
     * @throws JsonException|MaxRetryAttemptsException
     */
    public function attemptVerificationPhoneCode(int $attempts = 5): Response
    {
        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 3);

            try {

                $code = $this->attemptGetPhoneCode($this->phone);

                $this->validate->phoneNumberVerification->securityCode = SecurityCode::from([
                    'code' => $code,
                ]);

                return $this->resource->verificationPhone($this->validate);

            } catch (VerificationCodeException $e) {

            }
        }

        throw new RuntimeException("verification phone code failed of attempt {$attempts} times");
    }

    /**
     * @param Phone $phone
     * @param int $attempts
     * @return string
     * @throws MaxRetryAttemptsException
     */
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
        throw new MaxRetryAttemptsException("Attempt {$attempts} times failed to get phone code");
    }

    public function parse(string $str): ?string
    {
        if (preg_match('/\b\d{6}\b/', $str, $matches)) {
            return $matches[0];
        }

        return null;
    }
}































