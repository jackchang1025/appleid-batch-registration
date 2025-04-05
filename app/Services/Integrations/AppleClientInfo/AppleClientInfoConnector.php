<?php

namespace App\Services\Integrations\AppleClientInfo;

use Saloon\Http\Connector;
use Weijiajia\SaloonphpLogsPlugin\Contracts\HasLoggerInterface;
use Weijiajia\SaloonphpLogsPlugin\HasLogger;

class AppleClientInfoConnector extends Connector implements  HasLoggerInterface
{
    use HasLogger;

    public function __construct(public string $baseUrl)
    {
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
