<?php

namespace App\Services\UserAgent;

class UserAgentBuilder
{
    public const DEVICE_DESKTOP = 'desktop';
    public const DEVICE_MOBILE = 'mobile';
    public const DEVICE_TABLET = 'tablet';

    private array $filters = [];
    private bool $fullData = false;
    private int $count = 1;

    /**
     * 设置浏览器名称
     */
    public function withBrowserName(string $browserName): self
    {
        $this->filters['browserName'] = $browserName;
        return $this;
    }

    /**
     * 设置浏览器版本
     * 建议与 withBrowserName() 一同使用以获得最佳效果
     */
    public function withBrowserVersion(string $browserVersion): self
    {
        $this->filters['browserVersion'] = $browserVersion;
        return $this;
    }

    /**
     * 设置操作系统名称
     */
    public function withOsName(string $osName): self
    {
        $this->filters['osName'] = $osName;
        return $this;
    }

    /**
     * 设置操作系统版本
     * 建议与 withOsName() 一同使用以获得最佳效果
     */
    public function withOsVersion(string $osVersion): self
    {
        $this->filters['osVersion'] = $osVersion;
        return $this;
    }

    /**
     * 设置设备类型
     *
     * @param string $deviceCategory 使用本类的 DEVICE_* 常量
     * @return $this
     */
    public function withDeviceCategory(string $deviceCategory): self
    {
        if (!in_array($deviceCategory, [self::DEVICE_DESKTOP, self::DEVICE_MOBILE, self::DEVICE_TABLET])) {
            trigger_error("Invalid device category: {$deviceCategory}", E_USER_WARNING);
        }
        $this->filters['deviceCategory'] = $deviceCategory;
        return $this;
    }

    /**
     * 设置平台标识 (navigator.platform)
     * 例如: Win32, MacIntel, Linux x86_64, iPhone, iPad
     */
    public function withPlatform(string $platform): self
    {
        $this->filters['platform'] = $platform;
        return $this;
    }

    /**
     * 设置屏幕宽度 (screen.width)
     */
    public function withScreenWidth(int $width): self
    {
        $this->filters['screenWidth'] = $width;
        return $this;
    }

    /**
     * 设置屏幕高度 (screen.height)
     */
    public function withScreenHeight(int $height): self
    {
        $this->filters['screenHeight'] = $height;
        return $this;
    }

     /**
      * 设置视口宽度 (window.innerWidth)
      */
    public function withViewportWidth(int $width): self
    {
        $this->filters['viewportWidth'] = $width;
        return $this;
    }

     /**
      * 设置视口高度 (window.innerHeight)
      */
    public function withViewportHeight(int $height): self
    {
        $this->filters['viewportHeight'] = $height;
        return $this;
    }

     /**
      * 设置浏览器供应商 (navigator.vendor)
      * 例如: Google Inc., Apple Computer, Inc.
      */
    public function withVendor(string $vendor): self
    {
        $this->filters['vendor'] = $vendor;
        return $this;
    }

    /**
     * 设置连接类型 (navigator.connection.type)
     * 例如: wifi, 4g, 3g, cellular, ethernet, none, unknown
     * 注意: server.js 当前只做了简单匹配
     */
    public function withConnectionType(string $connectionType): self
    {
        // 注意：JS 服务器目前是扁平化处理这个 key
        $this->filters['connectionType'] = $connectionType;
        return $this;
    }

    /**
     * 设置是否返回完整数据
     */
    public function withFullData(bool $fullData = true): self
    {
        $this->fullData = $fullData;
        return $this;
    }

    /**
     * 设置返回的 User-Agent 数量
     * 注意: 服务器端会限制最大数量 (例如 100)
     */
    public function withCount(int $count): self
    {
        $this->count = max(1, $count); // 确保至少为 1
        return $this;
    }

    /**
     * 添加自定义选项 (如果服务器端支持)
     */
    public function withOption(string $key, $value): self
    {
        $this->filters[$key] = $value;
        return $this;
    }

    /**
     * 获取所有筛选条件
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * 获取是否返回完整数据
     */
    public function isFullData(): bool
    {
        return $this->fullData;
    }

    /**
     * 获取返回数量
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * 静态工厂方法
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 构建请求数据
     *
     * @return array{filters: array, fullData: bool, count: int}
     */
    public function build(): array
    {
        // 移除值为 null 的过滤器，避免发送不必要的空值
        $nonNullFilters = array_filter($this->filters, fn($value) => $value !== null);

        return [
            'filters' => $nonNullFilters,
            'fullData' => $this->fullData,
            'count' => $this->count,
        ];
    }
}