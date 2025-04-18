<?php

namespace App\Console\Commands;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\Phone;
use App\Services\AppleId\AppleIdBatchRegistration as AppleIdBatchRegistrationService;
use App\Services\CountryLanguageService;
use App\Services\Exception\RegistrationException;
use Illuminate\Console\Command;
use JsonException;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Random\RandomException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Throwable;
use Weijiajia\SaloonphpAppleClient\Exception\AccountAlreadyExistsException;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use InvalidArgumentException;
use App\Services\Phone\DatabasePhone;
use App\Services\Phone\FiveSimPhone;
use App\Services\Phone\PhoneDepositoryFacroty;
class AppleIdRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "apple-id:register-icloud
                            {email}
                            {--P|phone=}
                            {--PD|phone-depository=database}
                            {--C|country=CAN} 
                            {--R|random-user-agent=}
                            ";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '注册苹果账号';


    /**
     * @return void
     * @throws ClientException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws NumberFormatException
     * @throws RandomException
     * @throws RegistrationException
     * @throws RequestException
     * @throws Throwable
     * @throws AccountAlreadyExistsException
     * @throws MaxRetryAttemptsException
     */
    public function handle(PhoneDepositoryFacroty $phoneDepositoryFacroty): void
    {
        $email = Email::where('email', $this->argument('email'))
            // ->whereIn('status', [EmailStatus::AVAILABLE, EmailStatus::FAILED])
            ->firstOrFail();

        $appleIdBatchRegistration = app(AppleIdBatchRegistrationService::class);

        if ($phone = $this->option('phone')) {

            $phone = Phone::where('phone', 'like', '%'.$phone.'%')->firstOrFail();
        }

        $phoneDepository = $phoneDepositoryFacroty->make($this->option('phone-depository'));

        $appleIdBatchRegistration->run(
            email: $email,
            country: CountryLanguageService::make($this->option('country')),
            phoneDepository: $phoneDepository,
        );

    }

}
