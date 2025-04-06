<?php

namespace App\Services\Integrations\Email;

use Saloon\Http\Request;
use Saloon\Enums\Method;
use Saloon\Http\Response;
use App\Services\Integrations\Email\Exception\GetEmailCodeException;

class Email8218126243Request extends Request
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
        if ($response->json('status') !== 1) {
            throw new GetEmailCodeException($response->body());
        }

        return $response->json('email_code');
    }
}
