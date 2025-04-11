<?php

namespace App\Services\Integrations\Email;

use Saloon\Http\Request;
use Saloon\Enums\Method;
use App\Services\Integrations\Email\Exception\GetEmailCodeException;
use Saloon\Http\Response;

class ApiOnlineDisposablemailRequett extends Request
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
        if ($response->json('code') !== 200) {
            throw new GetEmailCodeException($response->body());
        }

        return $response->json('data.code');
    }
}
