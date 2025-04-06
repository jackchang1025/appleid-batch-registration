<?php

namespace App\Services\Integrations\Email;

use Saloon\Http\Request;
use Saloon\Enums\Method;
use App\Services\Integrations\Email\Exception\GetEmailCodeException;
use Saloon\Http\Response;

class AuthhkBhdataComRequest extends Request
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
        if ($response->json('code') !== 0) {
            throw new GetEmailCodeException($response->body());
        }

        $string = $response->json('data.result');
        if ($string && is_string($string)) {
            preg_match('/您的验证码是：(\d+)/', $string, $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
        }

        return null;
    }
}
