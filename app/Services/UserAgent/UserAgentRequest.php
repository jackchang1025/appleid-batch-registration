<?php

namespace App\Services\UserAgent;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;
use Saloon\Http\SoloRequest;

class UserAgentRequest extends SoloRequest implements HasBody
{
    use HasJsonBody;

    /**
     * HTTP 方法
     *
     * @var Method
     */
    protected Method $method = Method::POST;


    /**
     * 构造函数
     *
     * @param UserAgent|UserAgentBuilder|array $userAgent
     */
    public function __construct(public array $data,public string $baseUrl = 'http://user-agents:3000/api/user-agent')
    {
    }

    public function resolveEndpoint(): string
    {
        return $this->baseUrl;
    }

    /**
     * 获取请求体
     *
     * @return array
     */
    protected function defaultBody(): array
    {
        return $this->data;
    }
}
