<?php

namespace App\Services\Phone;

use Saloon\Http\Connector;
use App\Services\CountryLanguageService;

interface PhoneDepository
{
    public function getPhone(CountryLanguageService $country): Phone;

    public function getPhoneCode(Phone $phone);

    public function canPhone(Phone $phone);

    public function banPhone(Phone $phone);

    public function finishPhone(Phone $phone);

    public function connect():Connector;
}