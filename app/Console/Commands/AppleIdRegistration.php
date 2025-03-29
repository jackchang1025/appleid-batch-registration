<?php

namespace App\Console\Commands;

use App\Models\ProxyConfiguration;
use App\Services\AppleClientIdService;
use App\Services\Exception\RegistrationException;
use App\Services\Helper\Helper;
use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use JsonException;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Random\RandomException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ServiceUnavailableException;
use Weijiajia\DecryptVerificationCode\Exception\DecryptCloudCodeException;
use Weijiajia\SaloonphpAppleClient\Exception\AccountAlreadyExistsException;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\PhoneException;
use Illuminate\Support\Facades\Cache;
use App\Models\Email;
use Illuminate\Contracts\Cache\Lock;
use App\Services\AppleId\AppleIdBatchRegistration as AppleIdBatchRegistrationService;
use App\Enums\EmailStatus;


class AppleIdRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:apple-id-registration {email} {--country=USA}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '注册苹果账号';


    //锁
    protected ?Lock $lock = null;


    public function __destruct()
    {
        $this->lock && $this->lock->release();
    }

    /**
     * @param AppleClientIdService $appleClientIdService
     * @return void
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws NumberFormatException
     * @throws RandomException
     * @throws RegistrationException
     * @throws RequestException
     * @throws \Throwable
     * @throws AccountAlreadyExistsException
     * @throws MaxRetryAttemptsException
     */
    public function handle(AppleClientIdService $appleClientIdService): void
    {

        $email = Email::where('email', $this->argument('email'))
        ->whereIn('status', [EmailStatus::AVAILABLE, EmailStatus::FAILED])
        ->firstOrFail();

        $appleIdBatchRegistration = app(AppleIdBatchRegistrationService::class);

        $appleIdBatchRegistration->run($email,$this->option('country'));

    }

}
