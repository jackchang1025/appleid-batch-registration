<?php

namespace App\Services\Integrations\Email;

use Saloon\Http\Request;
use Saloon\Enums\Method;
use App\Services\Integrations\Email\Exception\GetEmailCodeException;
use Saloon\Http\Response;

class ApiAcemailCoRequest extends Request
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

        return $response->json('message.email_code');
    }
}
