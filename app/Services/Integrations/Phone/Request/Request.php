<?php

namespace App\Services\Integrations\Phone\Request;

use Saloon\Http\Request as SaloonRequest;
use Saloon\Enums\Method;
use Saloon\Http\Response;

class Request extends SaloonRequest
{
    protected Method $method = Method::GET;

    public function __construct(
        public string $endpoint,
    ) {
        // 确保endpoint包含协议前缀
        if (!preg_match('/^https?:\/\//i', $endpoint)) {
            $this->endpoint = 'http://' . $endpoint;
        }
    }

    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }

    public function createDtoFromResponse(Response $response): ?string
    {
        return self::parse($response->body());
    }

    /**
     * 从字符串中解析出6位数字验证码
     * 
     * @param string $str 响应内容
     * @return string|null 找到的验证码或null
     */
    protected static function parse(string $str): ?string
    {
        preg_match_all('/\b\d{6}\b/', $str, $matches);

        if (!empty($matches[0])) {
            return end($matches[0]);
        }

        return null;
    }
}