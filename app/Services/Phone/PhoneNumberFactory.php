<?php

namespace App\Services\Phone;

use App\Models\SecuritySetting;
use libphonenumber\PhoneNumberFormat;

class PhoneNumberFactory
{
    /**
     * @param string $phoneNumber
     * @param array|null $countryCode
     * @param int|null $phoneNumberFormat
     * @return PhoneService
     */
    public function create(
        string $phoneNumber,
        ?array $countryCode = [],
        ?int $phoneNumberFormat = PhoneNumberFormat::INTERNATIONAL
    ): PhoneService {
        return new PhoneService($phoneNumber, $countryCode, $phoneNumberFormat);
    }
}
