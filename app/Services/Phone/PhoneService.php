<?php

namespace App\Services\Phone;

use libphonenumber\PhoneNumberFormat;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Propaganistas\LaravelPhone\PhoneNumber;
use Symfony\Component\Intl\Countries;
/**
 * @mixin PhoneNumber
 */
class PhoneService
{
    protected PhoneNumber $phone;

    public function __construct(
        protected string $number,
        protected array $country = [],
        protected int $format = PhoneNumberFormat::INTERNATIONAL
    ) {
        $this->phone = new PhoneNumber($number, $country);
    }

    public function getDefaultNumber(): string
    {
        return $this->number;
    }

    public function getDefaultCountry(): array
    {
        return $this->country;
    }

    public function getDefaultFormat(): int
    {
        return $this->format;
    }

    /**
     * @param string|int|null $format
     * @return string
     * @throws NumberFormatException
     */
    public function format(null|string|int $format = null): string
    {
        $format = $format ?? $this->format;

        return $this->phone->format($format);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->phone->$name(...$arguments);
    }

    /**
     * @return int|null
     * @throws \Propaganistas\LaravelPhone\Exceptions\NumberParseException
     */
    public function getCountryCode(): ?int
    {
        return $this->phone->toLibPhoneObject()?->getCountryCode();
    }

    /**
     * @return int|null
     * @throws \Propaganistas\LaravelPhone\Exceptions\NumberParseException
     */
    public function getNationalNumber(): ?int
    {
        return $this->phone->toLibPhoneObject()?->getNationalNumber();
    }

    /**
     * 获取国家代码的 alpha3 码
     *
     * @return string|null
     */
    public function getCountryCodeAlpha3(): ?string
    {
        $alpha2Code = $this->getCountry();

        $alpha3Code = Countries::getAlpha3Code($alpha2Code);

        return $alpha3Code;
    }
}
