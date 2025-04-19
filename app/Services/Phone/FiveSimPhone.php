<?php

namespace App\Services\Phone;

use Weijiajia\Saloonphp\FiveSim\FiveSimConnector;
use Weijiajia\Saloonphp\FiveSim\Enums\Operator;
use Psr\Log\LoggerInterface;
use App\Services\CountryLanguageService;
use Weijiajia\Saloonphp\FiveSim\Enums\Country;
use Weijiajia\Saloonphp\FiveSim\Enums\Product;
use libphonenumber\PhoneNumberFormat;
use Weijiajia\Saloonphp\FiveSim\Enums\OrderStatus;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\PhoneException;

class FiveSimPhone implements PhoneDepository
{
    protected ?FiveSimConnector $fiveSimConnector = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected PhoneNumberFactory $phoneNumberFactory,
    ) 
    {
            $this->fiveSimConnector = new FiveSimConnector(config('phone-code-rece.five_sim.api_key'));
            $this->fiveSimConnector->withLogger($this->logger);
            $this->fiveSimConnector->debug();
    }

    public function connect(): FiveSimConnector
    {
        return $this->fiveSimConnector;
    }

    public function getPhone(CountryLanguageService $country): Phone
    {
        $countryCode = Country::fromIsoCode($country->getAlpha2Code());
        if ($countryCode === null) {
            throw new \RuntimeException('Country code not found');
        }

        $response = $this->connect()->resource()->buyNumber($countryCode, Operator::ANY_OPERATOR->value, Product::APPLE->value);

        $phoneService = $this->phoneNumberFactory->create($response->json('phone'), [$country->getAlpha2Code()]);

        return new Phone(
            id: $response->json('id'),
            phone: $phoneService->format(PhoneNumberFormat::NATIONAL),
            countryCode: $phoneService->getCountry(),
            countryDialCode: $phoneService->getCountryCode(),
        );
    }

    public function getPhoneCode(Phone $phone): string
    {
        for ($i = 1; $i <= 5; $i++) {

            sleep($i * 5);

            $reponse =  $this->connect()->resource()->checkOrder($phone->id());

            if (!in_array($reponse->status(), [OrderStatus::RECEIVED, OrderStatus::PENDING])) {
                throw new PhoneException('Phone status is not received or pending: ' . $reponse->status());
            }

            $code = $reponse->getLatestSms()?->code;
            if ($code) {
                return $code;
            }

        }

        throw new PhoneException('Phone code not found');
    }

    public function canPhone(Phone $phone)
    {
        $reponse =  $this->connect()->resource()->checkOrder($phone->id());

        if ($reponse->status() === OrderStatus::RECEIVED) {
            return $this->connect()->resource()->cancelOrder($phone->id());
        }

        if ($reponse->status() === OrderStatus::PENDING && $reponse->sms->toCollection()->isEmpty()) {
            return $this->connect()->resource()->cancelOrder($phone->id());
        }

        return false;
    }

    public function banPhone(Phone $phone)
    {
        return $this->canPhone($phone);
    }

    public function finishPhone(Phone $phone)
    {
        return $this->connect()->resource()->finishOrder($phone->id());
    }
    
}
