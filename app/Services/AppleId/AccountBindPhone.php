<?php

namespace App\Services\AppleId;

use App\Enums\PhoneStatus;
use App\Models\Phone;
use App\Services\Helper\Helper;
use App\Services\Integrations\Email\Exception\EmailException;
use App\Services\Integrations\Email\Exception\GetEmailCodeException;
use App\Services\Integrations\Phone\Exception\GetPhoneCodeException;
use App\Services\Trait\HasPhone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use JsonException;
use libphonenumber\PhoneNumberFormat;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Weijiajia\HttpProxyManager\Exception\ProxyModelNotFoundException;
use Weijiajia\SaloonphpAppleClient\Contracts\AppleIdInterface;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\PhoneException;
use Weijiajia\SaloonphpAppleClient\Exception\SignInException;
use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeException;
use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeSentTooManyTimesException;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Repair\Repair as RepairData;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Repair\Verify\Phone as PhoneData;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Request\Repair\Verify\Phone\SecurityCode;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Dto\Response\AccountManager\Repair\Repair as RepairResponse;
use Weijiajia\SaloonphpAppleClient\Integrations\Idmsa\Dto\Request\AppleAuth\VerifyEmailSecurityCode\VerifyEmailSecurityCode;
use Weijiajia\SaloonphpAppleClient\Integrations\Idmsa\Dto\Response\Auth\VerifyEmailSecurityCode\VerifyEmailSecurityCodeResponse;
use Weijiajia\SaloonphpHeaderSynchronizePlugin\Driver\ArrayStoreHeaderSynchronize;
use App\Services\Trait\HasLog;
use App\Models\Email;
use Psr\Log\LoggerInterface;
use Weijiajia\SaloonphpAppleClient\Integrations\Account\AccountConnector;
use App\Services\Trait\HasCookieJar;
use App\Services\Trait\HasSignIn;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleAuthenticationConnector\AppleAuthenticationConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\Idmsa\IdmsaConnector;
use App\Models\AppleId;
use App\Services\Integrations\Email\EmailConnector;
use App\Services\Integrations\Phone\PhoneConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\AppleIdConnector;
class AccountBindPhone
{
    use HasPhone;
    use HasLog;
    use HasCookieJar;
    use HasSignIn;

    protected ?ArrayStoreHeaderSynchronize $headers = null;

    protected ?Email $email = null;

    protected ?AccountConnector $accountAppleIdConnector = null;

    protected ?ProxySplQueue $proxySplQueue = null;

    protected ?AppleAuthenticationConnector $appleAuthenticationConnector = null;

    protected ?IdmsaConnector $idmsaConnector = null;

    protected ?EmailConnector $emailConnector = null;

    protected ?AppleIdConnector $appleIdConnector = null;

    protected ?PhoneConnector $phoneConnector = null;

    public function __construct(protected LoggerInterface $logger,protected ProxyManager $proxyManager,)
    {

    }
    public function email(): Email
    {
        return $this->email;
    }

    public function headerSynchronize(): ArrayStoreHeaderSynchronize
    {
        return $this->headers ??= new ArrayStoreHeaderSynchronize();
    }

    /**
     * @throws ProxyModelNotFoundException
     */
    public function accountConnector(): AccountConnector
    {
        if ($this->accountAppleIdConnector === null) {
            $this->accountAppleIdConnector = new AccountConnector();
            $this->accountAppleIdConnector->withLogger($this->logger);
            $this->accountAppleIdConnector->debug();
            $this->accountAppleIdConnector->withHeaderSynchronizeDriver($this->headerSynchronize());
            $this->accountAppleIdConnector->withCookies($this->cookieJar());
            $this->accountAppleIdConnector->withProxyQueue($this->proxySplQueue());
            $this->accountAppleIdConnector->middleware()->onRequest($this->debugRequest());
            $this->accountAppleIdConnector->middleware()->onResponse($this->debugResponse());
        }
        return $this->accountAppleIdConnector;
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
     * @throws ProxyModelNotFoundException
     */
    public function appleAuthenticationConnector(): AppleAuthenticationConnector{
        if ($this->appleAuthenticationConnector === null) {
            $this->appleAuthenticationConnector = new AppleAuthenticationConnector(config('services.apple_auth.base_url'));
            $this->appleAuthenticationConnector->withLogger($this->logger);
            $this->appleAuthenticationConnector->debug();
            $this->appleAuthenticationConnector->withProxyQueue($this->proxySplQueue());
            $this->appleAuthenticationConnector->withCookies($this->cookieJar());
            $this->appleAuthenticationConnector->withHeaderSynchronizeDriver($this->headerSynchronize());
            $this->appleAuthenticationConnector->middleware()->onRequest($this->debugRequest());
            $this->appleAuthenticationConnector->middleware()->onResponse($this->debugResponse());
        }
        return $this->appleAuthenticationConnector;
    }

    public function serviceKey(): string{
        return 'af1139274f266b22b68c2a3e7ad932cb3c0bbe854e13a79af78dcc73136882c3';
    }

    /**
     * @throws ProxyModelNotFoundException
     */
    public function idmsaConnector(): IdmsaConnector{
        if ($this->idmsaConnector === null) {
            $this->idmsaConnector = new IdmsaConnector($this->serviceKey(),'https://account.apple.com');
            $this->idmsaConnector->withLogger($this->logger);
            $this->idmsaConnector->debug();
            $this->idmsaConnector->withProxyQueue($this->proxySplQueue());
            $this->idmsaConnector->withCookies($this->cookieJar());
            $this->idmsaConnector->withHeaderSynchronizeDriver($this->headerSynchronize());
            $this->idmsaConnector->middleware()->onRequest($this->debugRequest());
            $this->idmsaConnector->middleware()->onResponse($this->debugResponse());
        }
        return $this->idmsaConnector;
    }

    public function emailConnector(): EmailConnector{
        if ($this->emailConnector === null) {
            $this->emailConnector = new EmailConnector();
            $this->emailConnector->withLogger($this->logger);
            $this->emailConnector->debug();
            $this->emailConnector->middleware()->onRequest($this->debugRequest());
            $this->emailConnector->middleware()->onResponse($this->debugResponse());
        }
        return $this->emailConnector;
    }

    public function phoneConnector(): PhoneConnector{
        if ($this->phoneConnector === null) {
            $this->phoneConnector = new PhoneConnector();
            $this->phoneConnector->withLogger($this->logger);
            $this->phoneConnector->debug();
            $this->phoneConnector->middleware()->onRequest($this->debugRequest());
            $this->phoneConnector->middleware()->onResponse($this->debugResponse());
        }
        return $this->phoneConnector;
    }
    protected function appleIdConnector(): AppleIdConnector
    {

        if ($this->appleIdConnector === null) {
            $this->appleIdConnector = new AppleIdConnector();
            $this->appleIdConnector->debug();
            $this->appleIdConnector->withLogger($this->logger);
            $this->appleIdConnector->withCookies($this->cookieJar());
            $this->appleIdConnector->withProxyQueue($this->proxySplQueue());
            $this->appleIdConnector->withHeaderSynchronizeDriver($this->headerSynchronize());
            $this->appleIdConnector->middleware()->onRequest($this->debugRequest());
            $this->appleIdConnector->middleware()->onResponse($this->debugResponse());
            $this->appleIdConnector->withForceProxy(true);
            $this->appleIdConnector->withProxyEnabled(true);
        }
        return $this->appleIdConnector;
    }

    /**
     * @param AppleId $appleId
     * @return mixed
     * @throws ClientException
     * @throws EmailException
     * @throws FatalRequestException
     * @throws GetEmailCodeException
     * @throws GetPhoneCodeException
     * @throws JsonException
     * @throws MaxRetryAttemptsException
     * @throws NumberFormatException
     * @throws ProxyModelNotFoundException
     * @throws RequestException
     * @throws SignInException
     */
    public function run(AppleId $appleId): RepairResponse
    {
        $this->email = $appleId->hasOneEmail;
        $this->accountConnector()->getResources()->signIn();

        $this->signIn($appleId->getAppleId(), $appleId->getPassword());

        $id = $this->getAppleAuth()->direct->twoSV->emailVerification['id'] ?? null;
        if ($id === null) {
            throw new InvalidArgumentException('get email verification id failed');
        }

        $this->attemptsVerifyEmail($appleId, $id);

        $this->headerSynchronize()->add('X-Apple-Session-Token',$this->headerSynchronize()->get('X-Apple-Repair-Session-Token'));

        $this->appleIdConnector()->getRepairResource()->widgetRepair($this->serviceKey());

        $sessionId = $this->cookieJar()->getCookieByName('aidsp')?->getValue();
        if ($sessionId === null) {
            throw new InvalidArgumentException('get session id failed');
        }

        $this->appleIdConnector()->getRepairResource()->options($this->serviceKey(), $sessionId);

        $response = $this->attemptsVerifyPhone($sessionId);

        return $this->appleIdConnector()->getRepairResource()->repair(
            RepairData::from([
                'phoneNumberVerification' => [
                    'phoneNumber' => [
                        'id'          => $response->phoneNumberVerification['phoneNumber']['id'],
                        'number'      => $response->phoneNumberVerification['phoneNumber']['number'],
                        'type'        => 'Approver',
                        'countryCode' => $response->phoneNumberVerification['phoneNumber']['countryCode'],
                    ],
                ],
            ]),
            $this->serviceKey(),
            $sessionId
        );
    }

    /**
     * @param AppleId $appleId
     * @param string $id
     * @param int $attempts
     * @return VerifyEmailSecurityCodeResponse
     * @throws ClientException
     * @throws FatalRequestException
     * @throws MaxRetryAttemptsException
     * @throws ProxyModelNotFoundException
     * @throws RequestException
     * @throws EmailException
     * @throws GetEmailCodeException
     */
    protected function attemptsVerifyEmail(
        AppleId $appleId,
        string $id,
        int $attempts = 5
    ): VerifyEmailSecurityCodeResponse {

        for ($i = 0; $i < $attempts; $i++) {

            try {
                $code = $this->emailConnector()->attemptGetEmailCode($appleId->getAppleId(), $appleId->getEmailUri());

                return $this->idmsaConnector()->getAuthenticateResources()->verifyEmailSecurityCode(
                    VerifyEmailSecurityCode::from([
                        'id'           => $id,
                        'securityCode' => ['code' => $code],
                        'emailAddress' => ['id' => 1],
                    ])
                );

            } catch (VerificationCodeException $e) {

            }
        }

        throw new MaxRetryAttemptsException(" {$attempts} 次验证邮箱验证码失败");
    }

    /**
     * @param string $sessionId
     * @param int $attempts
     * @return RepairResponse
     * @throws FatalRequestException
     * @throws GetPhoneCodeException
     * @throws MaxRetryAttemptsException
     * @throws NumberFormatException
     * @throws RequestException
     */
    protected function attemptsVerifyPhone(string $sessionId, int $attempts = 5): RepairResponse
    {
        for ($i = 0; $i < $attempts; $i++) {

            try {

                $this->phone = $this->getPhone();

                $response = $this->appleIdConnector()->getRepairResource()->verifyPhone(
                    PhoneData::from([
                        'phoneNumberVerification' => [
                            'phoneNumber' => [
                                'number'      => $this->phone->getPhoneNumberService()->format(
                                    PhoneNumberFormat::NATIONAL
                                ),
                                'type'        => 'Approver',
                                'countryCode' => $this->phone->country_code,
                            ],
                            'mode'        => 'sms',
                        ],
                    ]),
                    $this->serviceKey(),
                    $sessionId
                );

                if (!$id = ($response->phoneNumberVerification['phoneNumber']['id'] ?? null)) {
                    throw new InvalidArgumentException('phone number id is null');
                }

                $code = $this->phoneConnector()->attemptGetPhoneCode($this->phone->phone, $this->phone->phone_address);

                $response = $this->appleIdConnector()->getRepairResource()->verifySecurityCode(
                    SecurityCode::from([
                        'phoneNumberVerification' => [
                            'phoneNumber'  => [
                                'number'      => $this->phone->phone,
                                'type'        => 'Approver',
                                'countryCode' => $this->phone->country_code,
                                'id'          => $id,
                            ],
                            'mode'         => 'sms',
                            'securityCode' => ['code' => $code],
                        ],
                    ]),
                    $this->serviceKey(),
                    $sessionId
                );

                $this->phone->update(['status' => PhoneStatus::BOUND]);

                return $response;
            } catch (VerificationCodeException|VerificationCodeSentTooManyTimesException|PhoneException $e) {

                self::addActiveBlacklistIds($this->phone->id);
                $this->phone && $this->phone->update(['status' => PhoneStatus::NORMAL]);
            }
        }

        throw new MaxRetryAttemptsException(" {$attempts} 次验证手机验证码失败");
    }
}
