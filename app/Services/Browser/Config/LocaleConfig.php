<?php

namespace App\Services\Browser\Config;

class LocaleConfig
{
    /**
     * 国家代码到语言的映射
     */
    protected const COUNTRY_TO_LOCALE_MAP = [
        'US' => 'en-US',
        'CA' => 'en-CA',
        'GB' => 'en-GB',
        'CN' => 'zh-CN',
        'JP' => 'ja-JP',
        'FR' => 'fr-FR',
        'DE' => 'de-DE',
        'ES' => 'es-ES',
        'IT' => 'it-IT',
        'BR' => 'pt-BR',
        'RU' => 'ru-RU',
        'IN' => 'en-IN',
        'AU' => 'en-AU',
    ];

    public function __construct(
        public readonly string $locale,
        public readonly ?string $timezone = null,
        public readonly ?string $language = null
    ) {}
    
    /**
     * 根据国家代码创建本地化配置
     */
    public static function fromCountry(string $country, ?string $timezone = null): ?self
    {
        $locale = self::COUNTRY_TO_LOCALE_MAP[$country] ?? null;
        if (!$locale) {
            throw new \RuntimeException("no locale found for {$country}");
        }
        
        return new self(
            locale: $locale,
            timezone: $timezone,
            language: $locale
        );
    }
}