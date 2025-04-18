<?php

namespace App\Services\Integrations\Email;

use App\Services\Integrations\Email\Exception\EmailException;
use Saloon\Http\Connector;
use Weijiajia\SaloonphpLogsPlugin\Contracts\HasLoggerInterface;
use Weijiajia\SaloonphpLogsPlugin\HasLogger;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use App\Services\Integrations\Email\Exception\GetEmailCodeException;
use App\Services\Integrations\Email\Exception\MaxRetryGetEmailCodeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request;
use Illuminate\Support\Facades\Cache;
class EmailConnector extends Connector implements HasLoggerInterface
{
    use HasLogger;
    use AlwaysThrowOnErrors;

    public static array $emailHistory = [];

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
     * @throws GetEmailCodeException|EmailException
     */
    public function getEmailCode(string $emailUrl): ?string
    {
        $request = match (parse_url($emailUrl, PHP_URL_HOST)) {
            '213.130.144.243' => new Email8218126243Request($emailUrl),
            'api.acemail.co' => new ApiAcemailCoRequest($emailUrl),
            'authhk.bhdata.com' => new AuthhkBhdataComRequest($emailUrl),
            'api.online-disposablemail.com' => new ApiOnlineDisposablemailRequett($emailUrl),
            default => throw new \InvalidArgumentException('NO FIND EMAIL REQUEST'),
        };

        return $this->send($request)->dto();
    }

    /**
     * @param string $email
     * @param string $uri
     * @param int $attempts
     * @return string
     * @throws FatalRequestException
     * @throws GetEmailCodeException
     * @throws RequestException|EmailException
     */
    public function attemptGetEmailCode(string $email, string $uri, int $attempts = 5): string
    {
        for ($i = 1; $i <= $attempts; $i++) {

            sleep($i * 5);

            try {

                if (!$code = $this->getEmailCode($uri)) {
                    continue;
                }

                if (Cache::get('email_code_' . $email,null) === $code) {
                    continue;
                }

                Cache::put('email_code_' . $email, $code, 60 * 60 * 24);
                return $code;

            } catch (GetEmailCodeException) {
                continue;
            }
        }
        throw new GetEmailCodeException('Failed to get email code after ' . $attempts . ' attempts');
    }
}
