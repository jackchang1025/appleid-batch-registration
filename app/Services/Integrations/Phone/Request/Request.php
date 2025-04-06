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
    }

    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }

    public function createDtoFromResponse(Response $response): ?string
    {
        return self::parse($response->body());
    }

    protected static function parse(string $str): ?string
    {
        preg_match_all('/\b\d{6}\b/', $str, $matches);

        if (isset($matches[0])) {
            return end($matches[0]);
        }

        if (isset($matches[1])) {
            return end($matches[1]);
        }

        return null;
    }
}