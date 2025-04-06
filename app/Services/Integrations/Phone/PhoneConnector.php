<?php

namespace App\Services\Integrations\Phone;

use Saloon\Http\Connector;
use Weijiajia\SaloonphpLogsPlugin\Contracts\HasLoggerInterface;
use Weijiajia\SaloonphpLogsPlugin\HasLogger;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use App\Services\Integrations\Phone\Exception\GetPhoneCodeException;
use App\Services\Integrations\Phone\Exception\MaxRetryGetPhoneCodeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request;
use App\Services\Integrations\Phone\Request\Request as PhoneRequest;

class PhoneConnector extends Connector implements HasLoggerInterface
{
    use HasLogger;
    use AlwaysThrowOnErrors;

    public static array $phoneHistory = [];

    public ?int $tries = 5;


    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        return $exception instanceof FatalRequestException;
    }


    public function resolveBaseUrl(): string
    {
        return '';
    }

    /**
     * @param string $emailUrl
     * @return string|null
     * @throws FatalRequestException
     * @throws RequestException
     * @throws GetPhoneCodeException
     */
    public function getPhoneCode(string $url): ?string
    {
        $request = match (parse_url($url, PHP_URL_HOST)) {
            default => new PhoneRequest($url),
        };

        return $this->send($request)->dto();
    }

    /**
     * @param string $phone
     * @param string $uri
     * @param int $attempts
     * @return string
     * @throws FatalRequestException
     * @throws MaxRetryGetPhoneCodeException
     * @throws RequestException
     */
    public function attemptGetPhoneCode(string $phone, string $url, int $attempts = 5): string
    {
        for ($i = 1; $i <= $attempts; $i++) {

            sleep($i * 5);

            try {

                if (!$code = $this->getPhoneCode($url)) {
                    continue;
                }

                if ((self::$phoneHistory[$phone] ?? null) === $code) {
                    continue;
                }

                self::$phoneHistory[$phone] = $code;
                return $code;

            } catch (GetPhoneCodeException) {
                continue;
            }
        }
        throw new MaxRetryGetPhoneCodeException('Failed to get phone code');
    }
}
