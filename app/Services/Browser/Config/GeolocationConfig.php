<?php

namespace App\Services\Browser\Config;

class GeolocationConfig
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $accuracy = 1
    ) {}
    
    /**
     * 根据国家代码创建地理位置配置
     */
    public static function fromCountry(string $country): ?self
    {
        $coords = self::getCountryCoordinates($country);
        if (!$coords) {
            throw new \RuntimeException("no country coordinates found for {$country}");
        }
        
        return new self(
            latitude: $coords['latitude'],
            longitude: $coords['longitude']
        );
    }
    
    /**
     * 获取国家的默认坐标
     */
    protected static function getCountryCoordinates(string $country): ?array
    {
        $map = [
            'US' => ['latitude' => 37.7749, 'longitude' => -122.4194], // 旧金山
            'CA' => ['latitude' => 43.6532, 'longitude' => -79.3832], // 多伦多 
            'GB' => ['latitude' => 51.5074, 'longitude' => -0.1278],  // 伦敦
            'CN' => ['latitude' => 39.9042, 'longitude' => 116.4074], // 北京
            'JP' => ['latitude' => 35.6762, 'longitude' => 139.6503], // 东京
            'FR' => ['latitude' => 48.8566, 'longitude' => 2.3522],   // 巴黎
            'DE' => ['latitude' => 52.5200, 'longitude' => 13.4050],  // 柏林
            'ES' => ['latitude' => 40.4168, 'longitude' => -3.7038],  // 马德里
            'IT' => ['latitude' => 41.9028, 'longitude' => 12.4964],  // 罗马
            'BR' => ['latitude' => -23.5505, 'longitude' => -46.6333], // 圣保罗
            'RU' => ['latitude' => 55.7558, 'longitude' => 37.6173],  // 莫斯科
            'IN' => ['latitude' => 28.6139, 'longitude' => 77.2090],  // 新德里
            'AU' => ['latitude' => -33.8688, 'longitude' => 151.2093], // 悉尼
        ];


        return $map[$country] ?? null;
    }
}