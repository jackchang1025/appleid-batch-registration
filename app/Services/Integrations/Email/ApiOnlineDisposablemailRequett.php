<?php

namespace App\Services\Integrations\Email;

use Saloon\Http\Request;
use Saloon\Enums\Method;
use App\Services\Integrations\Email\Exception\GetEmailCodeException;
use Saloon\Http\Response;
use App\Services\Integrations\Email\Exception\EmailException;
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
        if($response->json('code') === 42003){
            throw new EmailException($response->body());
        }

        if ($response->json('code') !== 200) {
            throw new GetEmailCodeException($response->body());
        }

        $code =  $response->json('data.code');

        if(str_contains($code, ',')){
            $code = explode(',', $code);
            return end($code);
        }

        return $code;
    }
}
