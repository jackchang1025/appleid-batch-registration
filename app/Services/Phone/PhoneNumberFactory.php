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
        ?array $countryCode = null,
        ?int $phoneNumberFormat = PhoneNumberFormat::INTERNATIONAL
    ): PhoneService {

        $phoneNumberFormat = $phoneNumberFormat ?: config('phone.format');

        $defaultCountryCode = $this->getDefaultCountryCode($countryCode);

        return new PhoneService($phoneNumber, $defaultCountryCode, $phoneNumberFormat);
    }

    private function getDefaultCountryCode(?array $countryCode): array
    {
        if ($countryCode) {
            return $countryCode;
        }

        $securitySetting = SecuritySetting::first();
        if ($securitySetting && isset($securitySetting->configuration['country_code'])) {
            return [$securitySetting->configuration['country_code']];
        }

        return config('phone.country_code');
    }
}
