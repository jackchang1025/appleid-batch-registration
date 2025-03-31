<?php

namespace App\Services;
use Symfony\Component\Intl\Countries;

class CountryLanguageService
{
    public static function getAlpha2Code(string $country): string
    {
        if (strlen($country) === 2 && Countries::exists(strtoupper($country))) {
            return strtoupper($country);
        }
        
        return Countries::getAlpha2Code($country);
    }

    public static function getAlpha3Code(string $country): string
    {
        if (strlen($country) === 3 && Countries::exists(strtoupper($country))) {
            return strtoupper($country);
        }
        
        return Countries::getAlpha3Code($country);
    }

    public static function getLanguageForCountry(string $countryCode): string
    {
        $countryCode = self::getAlpha2Code($countryCode);
        
        // 默认映射关系
        $defaultMap = [
            'US' => 'en_US',
            'GB' => 'en_GB',
            'CA' => 'en_CA',
            'AU' => 'en_AU',
            'FR' => 'fr_FR',
            'DE' => 'de_DE',
            'CN' => 'zh_CN',
            // 可以添加更多默认映射...
        ];

        if (isset($defaultMap[$countryCode])) {
            return $defaultMap[$countryCode];
        }

        throw new \Exception('不支持的国家代码');
    }

    public static function getAcceptLanguage(string $countryCode): string
    {
        $countryCode = self::getAlpha2Code($countryCode);

        return match($countryCode) {
            'US' => 'en-US,en-GB;q=0.9,en;q=0.8',
            'GB' => 'en-GB,en-US;q=0.9,en;q=0.8',
            'CA' => 'en-CA,en-GB;q=0.9,en;q=0.8',
            'FR' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'DE' => 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
            'ES' => 'es-ES,es;q=0.9,en-US;q=0.8,en;q=0.7',
            'IT' => 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7',
            'JP' => 'ja-JP,ja;q=0.9,en-US;q=0.8,en;q=0.7',
            'CN' => 'zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7',
            default => 'en-US,en-GB;q=0.9,en;q=0.8', // 默认使用美式英语
        };
    }
}