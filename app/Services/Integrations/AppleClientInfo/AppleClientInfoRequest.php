<?php

namespace App\Services\Integrations\AppleClientInfo;

use Saloon\Http\Request;
use Saloon\Enums\Method;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
class AppleClientInfoRequest extends Request implements HasBody
{
    use HasJsonBody;
    protected Method $method = Method::POST;

    public function __construct(
        public string $userAgent,
        public string $language,
        public string $timeZone,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/api/client-info';
    }

    public function defaultBody(): array
    {
        return [
            'userAgent' => $this->userAgent,
            'language' => $this->language,
            'timeZone' => $this->timeZone,
        ];
    }
}
