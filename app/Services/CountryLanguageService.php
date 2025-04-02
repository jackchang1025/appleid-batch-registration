<?php

namespace App\Services;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Symfony\Component\Intl\Countries;

class CountryLanguageService
{

    protected ?ProxySplQueue $proxySplQueue = null;

    protected ?string $timezone = null;

    public function __construct(protected string $country)
    {
        
    }

    public static function make(string $country):static
    {
        return new static($country);
    }

    public static function labels(): array
    {
      
        $countries = Countries::getCountryCodes();
        $labels = [];
        foreach ($countries as $code) {
            $labels[$code] = Countries::getName($code);
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
        $countryCode = $this->getAlpha2Code();
        
        // 默认映射关系
        $defaultMap = [
            'US' => 'en-US,en-GB;q=0.9,en;q=0.8',
            'GB' => 'en-GB,en-US;q=0.9,en;q=0.8',
            'CA' => 'en-CA,en-GB;q=0.9,en;q=0.8',
            'FR' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'DE' => 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
            'ES' => 'es-ES,es;q=0.9,en-US;q=0.8,en;q=0.7',
            'IT' => 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7',
            'JP' => 'ja-JP,ja;q=0.9,en-US;q=0.8,en;q=0.7',
            'CN' => 'zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7',
            'AU' => 'en-AU,en;q=0.9,en-US;q=0.8,en;q=0.7',
            'NZ' => 'en-NZ,en;q=0.9,en-US;q=0.8,en;q=0.7',
            'PT' => 'pt-PT,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'BR' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'NL' => 'nl-NL,nl;q=0.9,en-US;q=0.8,en;q=0.7',
            'BE' => 'nl-BE,nl;q=0.9,en-US;q=0.8,en;q=0.7',
            'SE' => 'sv-SE,sv;q=0.9,en-US;q=0.8,en;q=0.7',
            'NO' => 'nb-NO,nb;q=0.9,en-US;q=0.8,en;q=0.7',
            'DK' => 'da-DK,da;q=0.9,en-US;q=0.8,en;q=0.7',
            'FI' => 'fi-FI,fi;q=0.9,en-US;q=0.8,en;q=0.7',
            'RU' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'PL' => 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
            'CZ' => 'cs-CZ,cs;q=0.9,en-US;q=0.8,en;q=0.7',
            'SK' => 'sk-SK,sk;q=0.9,en-US;q=0.8,en;q=0.7',
            'HU' => 'hu-HU,hu;q=0.9,en-US;q=0.8,en;q=0.7',
            'RO' => 'ro-RO,ro;q=0.9,en-US;q=0.8,en;q=0.7',
            'BG' => 'bg-BG,bg;q=0.9,en-US;q=0.8,en;q=0.7',
            'GR' => 'el-GR,el;q=0.9,en-US;q=0.8,en;q=0.7',
            'TR' => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            'IL' => 'he-IL,he;q=0.9,en-US;q=0.8,en;q=0.7',
            'SA' => 'ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'AE' => 'ar-AE,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'KR' => 'ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
            'TW' => 'zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7',
            'HK' => 'zh-HK,zh;q=0.9,en-US;q=0.8,en;q=0.7',
            'SG' => 'en-SG,en;q=0.9,en-US;q=0.8,en;q=0.7',
            'MY' => 'ms-MY,ms;q=0.9,en-US;q=0.8,en;q=0.7',
            'TH' => 'th-TH,th;q=0.9,en-US;q=0.8,en;q=0.7',
            'VN' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
            'ID' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
        ];

        // 如果在映射表中找到对应的语言代码，直接返回
        if (isset($defaultMap[$countryCode])) {
            return $defaultMap[$countryCode];
        }

        
        // 如果不在映射表中，返回英语作为默认语言
        return 'en-US,en-GB;q=0.9,en;q=0.8';
    }

  
}