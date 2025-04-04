<?php

namespace App\Console\Commands;

use App\Services\AppleClientIdService;
use App\Services\Exception\RegistrationException;
use Illuminate\Console\Command;
use JsonException;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Random\RandomException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Weijiajia\SaloonphpAppleClient\Exception\AccountAlreadyExistsException;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use App\Models\Email;
use App\Services\AppleId\AppleIdBatchRegistration as AppleIdBatchRegistrationService;
use App\Enums\EmailStatus;
use App\Services\CountryLanguageService;

class AppleIdRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "app:apple-id-registration {email} {--C|country=CAN}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '注册苹果账号';


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
    public function handle(): void
    {
        $email = Email::where('email', $this->argument('email'))
         ->whereIn('status', [EmailStatus::AVAILABLE, EmailStatus::FAILED])
        ->firstOrFail();

        $appleIdBatchRegistration = app(AppleIdBatchRegistrationService::class);

        $appleIdBatchRegistration->run($email,CountryLanguageService::make($this->option('country')));

    }

}
