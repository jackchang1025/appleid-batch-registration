<?php

namespace App\Services\AppleId;

use App\Models\Phone;
use App\Services\Apple;
use App\Services\AppleBuilder;
use App\Services\Helper\Helper;
use App\Services\Trait\HasPhone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use JsonException;
use libphonenumber\PhoneNumberFormat;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
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

class AccountBindPhone
{
    use HasPhone;

    protected Apple $apple;

    public function __construct(protected AppleBuilder $appleBuilder)
    {

    }

    /**
     * @param AppleIdInterface $appleId
     * @return mixed
     * @throws ClientException
     * @throws MaxRetryAttemptsException
     * @throws RequestException
     * @throws JsonException
     * @throws NumberFormatException
     * @throws FatalRequestException
     * @throws SignInException
     */
    public function run(AppleIdInterface $appleId): RepairResponse
    {
        if ($appleId->getEmailUri() === null) {
            throw new InvalidArgumentException('email uri is null');
        }

        $this->apple = $this->appleBuilder->build($appleId);
        $this->apple->withDebug(true);

        /** @var ArrayStoreHeaderSynchronize $headers */
        $headers = $this->apple->getHeaderSynchronizeDriver();

        file_exists($this->apple->getCookieJarPath()) && unlink($this->apple->getCookieJarPath());
        file_exists($this->apple->getHeaderSynchronizeDriverPath()) && unlink(
            $this->apple->getHeaderSynchronizeDriverPath()
        );

        $this->apple->accountConnector()->getResources()->signIn();

        $this->apple->signIn($appleId->getAppleId(), $appleId->getPassword());

        $id = $this->apple->getAppleAuth()->direct->twoSV->emailVerification['id'] ?? null;
        if ($id === null) {
            throw new InvalidArgumentException('get email verification id failed');
        }

        $this->attemptsVerifyEmail($appleId, $id);

        $headers->add('X-Apple-Session-Token', $headers->get('X-Apple-Repair-Session-Token'));

        $serviceKey = $this->apple->config()->get('serviceKey');
        $this->apple->appleIdConnector()->getRepairResource()->widgetRepair($serviceKey);

        $sessionId = $this->apple->getCookieJar()->getCookieByName('aidsp')?->getValue();
        if ($sessionId === null) {
            throw new InvalidArgumentException('get session id failed');
        }

        $this->apple->appleIdConnector()->getRepairResource()->options($serviceKey, $sessionId);

        $response = $this->attemptsVerifyPhone($serviceKey, $sessionId);

        return $this->apple->appleIdConnector()->getRepairResource()->repair(
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
            $serviceKey,
            $sessionId
        );
    }

    /**
     * @param AppleIdInterface $appleId
     * @param string $id
     * @param int $attempts
     * @return VerifyEmailSecurityCodeResponse
     * @throws ClientException
     * @throws FatalRequestException
     * @throws MaxRetryAttemptsException
     * @throws RequestException
     */
    protected function attemptsVerifyEmail(
        AppleIdInterface $appleId,
        string $id,
        int $attempts = 5
    ): VerifyEmailSecurityCodeResponse {

        for ($i = 0; $i < $attempts; $i++) {

            try {
                $code = Helper::attemptEmailVerificationCode($appleId->getAppleId(), $appleId->getEmailUri());

                return $this->apple->idmsaConnector()->getAuthenticateResources()->verifyEmailSecurityCode(
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
     * @param string $widgetKey
     * @param string $sessionId
     * @param int $attempts
     * @return RepairResponse
     * @throws FatalRequestException
     * @throws RequestException
     * @throws ModelNotFoundException
     * @throws NumberFormatException|MaxRetryAttemptsException
     */
    protected function attemptsVerifyPhone(string $widgetKey, string $sessionId, int $attempts = 5): RepairResponse
    {
        for ($i = 0; $i < $attempts; $i++) {

            try {

                $this->phone = $this->getPhone();

                $response = $this->apple->appleIdConnector()->getRepairResource()->verifyPhone(
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
                    $widgetKey,
                    $sessionId
                );

                if (!$id = ($response->phoneNumberVerification['phoneNumber']['id'] ?? null)) {
                    throw new InvalidArgumentException('phone number id is null');
                }

                $code = Helper::attemptPhoneVerificationCode($this->phone->phone_address);

                $response = $this->apple->appleIdConnector()->getRepairResource()->verifySecurityCode(
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
                    $widgetKey,
                    $sessionId
                );

                $this->phone->update(['status' => Phone::STATUS_BOUND]);

                return $response;
            } catch (VerificationCodeException|VerificationCodeSentTooManyTimesException|PhoneException $e) {

                $this->addActiveBlacklistIds($this->phone->id);
                $this->phone && $this->phone->update(['status' => Phone::STATUS_NORMAL]);
            }
        }

        throw new MaxRetryAttemptsException(" {$attempts} 次验证手机验证码失败");
    }
}
