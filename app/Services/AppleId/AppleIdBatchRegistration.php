<?php

namespace App\Services\AppleId;

use App\Models\Email;
use App\Models\Phone;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use RuntimeException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Response;
use Weijiajia\DecryptVerificationCode\CloudCode\CloudCodeConnector;
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

    protected CookieJarInterface $cookieJar;

    //使用过的手机号码
    protected array $usedPhones = [];

    protected Email $email;

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
    )
    {

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

            return $phone;
        });
    }

    protected function initProxy(): void
    {
        $proxyConnector = $this->proxyManager->driver();
        $proxyConnector->withLogger($this->logger);
        $proxyConnector->debug();

        $proxy = $proxyConnector->default();

        $this->queue = new ProxySplQueue();
        if ($proxy instanceof Collection){

            $proxy->each(fn (ProxyInterface $item) => $this->queue->enqueue($item->getUrl()));

        }else {
            $this->queue->enqueue($proxy->getUrl());
        }
    }

    /**
     * @param Email $email
     * @param bool $isUseProxy
     * @return void
     * @throws ClientException
     * @throws PhoneException
     * @throws \JsonException
     * @throws NumberFormatException
     * @throws RandomException
     */
    public function run(Email $email,bool $isUseProxy = false): void
    {
        $this->email = $email;

        $this->cookieJar = new FileCookieJar(storage_path("app/public/{$this->email->email}.json"), true);

        if ($isUseProxy){
            $this->initProxy();
            $this->connector->withSplQueue($this->queue);
        }

        $this->connector->withLogger($this->logger);
        $this->connector->withCookies($this->cookieJar);
        $this->connector->debug();

        $this->cloudCodeConnector->debug();
        $this->cloudCodeConnector->withLogger($this->logger);

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

        $this->resource = $this->connector->getAccountResource();

        $response = $this->resource->widgetAccount();

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

        $phone              = $this->getPhone();

        try {

            $this->usedPhones[] = $phone->id;

            $this->phoneNumberVerification = PhoneNumberVerification::from([
                'phoneNumber' => [
                    'id'              => 1,
                    'number'          => $phone->getPhoneNumberService()->format(PhoneNumberFormat::NATIONAL),
                    'countryCode'     => $phone->country_code,
                    'countryDialCode' => $phone->country_dial_code,
                    'nonFTEU'         => true,
                ],
                'mode'        => 'sms',
            ]);

            $this->captcha = Captcha::from([
                'id'     => 0,
                'token'  => '',
                'answer' => '',
            ]);

            $this->validate = new Validate($this->phoneNumberVerification, $this->verificationAccount, $this->captcha);

            $this->captcha();

            while (true) {

                try {

                    $this->verificationEmail();

                    //获取手机号码信息
                    $this->resource->sendVerificationPhone($this->validate);

                    $code = $this->attemptGetPhoneCode($phone);

                    $securityCode = SecurityCode::from([
                        'code' => $code,
                    ]);

                    $this->phoneNumberVerification->securityCode = $securityCode;

                    $this->resource->verificationPhone($this->validate);

                    $accountResponse = $this->resource->account($this->validate);

                    $phone->update(['status' => Phone::STATUS_BOUND]);
                    //注册成功
                    dd($accountResponse->json());

                } catch (PhoneException $e) {

                    $phone->update(['status' => Phone::STATUS_NORMAL]);

                    $phone                         = $this->getPhone();
                    $this->phoneNumberVerification = PhoneNumberVerification::from([
                        'phoneNumber' => [
                            'id'              => 1,
                            'number'          => $phone->getPhoneNumberService()->format(PhoneNumberFormat::NATIONAL),
                            'countryCode'     => $phone->country_code,
                            'countryDialCode' => $phone->country_dial_code,
                            'nonFTEU'         => true,
                        ],
                        'mode'        => 'sms',
                    ]);
                    $this->usedPhones[]            = $phone->id;

                    dump($e->getMessage());

                }
            }
        }catch (\Exception $e){

            $phone->update(['status' => Phone::STATUS_NORMAL]);
            throw $e;
        }
    }

    public function captcha(int $attempts = 5): Response
    {

        for ($i = 0; $i < $attempts; $i++) {

            sleep($i);

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

                var_dump($e->getMessage());
            }

        }

        throw new RuntimeException("captcha failed of attempt {$attempts} times");
    }

    /**
     * @return Response
     * @throws ClientException
     */
    public function verificationEmail(): Response
    {
        $response = $this->sendVerificationEmail();

        $this->verificationInfo->id = $response->verificationId;

        return $this->attemptVerificationEmailCode();

    }

    protected function sendVerificationEmail(): SendVerificationEmailResponse
    {
        return $this->resource
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
                    'countryCode' => 'USA',
                ])
            )
            ->dto();
    }

    /**
     * @param int $attempts
     * @return Response
     * @throws ClientException
     */
    public function attemptVerificationEmailCode(int $attempts = 3): Response
    {

        for ($i = 0; $i < $attempts; $i++) {

            try {

                $emailCode = $this->getEmailCode($this->email->email, $this->email->email_uri);

                $this->verificationInfo->answer = $emailCode;

                $verificationPutDto = VerificationEmail::from([
                    'name'             => $this->email->email,
                    'verificationInfo' => $this->verificationInfo,
                ]);

                //验证邮箱验证码
                return $this->resource->verificationEmail($verificationPutDto);
            } catch (VerificationCodeException $e) {

                var_dump($e->getMessage());
            }
        }

        throw new RuntimeException("verification email code failed of attempt {$attempts} times");
    }

    public function getEmailCode(string $email, string $uri, int $attempts = 10)
    {

        $isSuccess = false;
        for ($i = 0; $i < $attempts; $i++) {

            sleep($i * 3);

            $response = Http::get($uri);

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

    public function attemptGetPhoneCode(Phone $phone, int $attempts = 10): string
    {

        for ($i = 0; $i < $attempts; $i++) {

            $response = Http::get($phone->phone_address);

            $code = $this->parse($response->body());

            if ($code) {
                return $code;
            }

            sleep(3);
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

    /**
     * @return string
     * @throws \Random\RandomException
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

}































