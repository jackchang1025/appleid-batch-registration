<?php

namespace App\Services;
use Symfony\Component\Intl\Countries;
class CountryLanguageService
{
    protected ?string $timezone = null;

    public function __construct(protected string $country)
    {

    }

    public static function make(string $country):static
    {
        return new static($country);
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getName(): string
    {
        return Countries::getName($this->country);
    }

    public static function labels(?string $displayLocale = null): array
    {

        $countries = Countries::getCountryCodes();
        $labels = [];
        foreach ($countries as $code) {
            $labels[$code] = Countries::getName($code, $displayLocale);
        }
        return $labels;
    }

    public  function getAlpha2Code(): ?string
    {
        if (strlen($this->country) === 2) {
            return strtoupper($this->country);
        }

        return Countries::getAlpha2Code($this->country);
    }

    public  function getAlpha3Code(): ?string
    {
        if (strlen($this->country) === 3) {
            return strtoupper($this->country);
        }

        return Countries::getAlpha3Code($this->country);
    }

    public function getAlpha2Language(): string
    {
        $language = config("country-language.{$this->getAlpha2Code()}");
        if(empty($language)){
            throw new \RuntimeException("Language not found for country: {$this->getAlpha2Code()}");
        }
        return $language;
    }
}
