<?php

namespace App\Console\Commands;

use App\Models\ProxyConfiguration;
use App\Services\Exception\RegistrationException;
use Illuminate\Console\Command;
use JsonException;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Random\RandomException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ServiceUnavailableException;
use Weijiajia\DecryptVerificationCode\Exception\DecryptCloudCodeException;
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
     * @return void
     * @throws ClientException
     * @throws DecryptCloudCodeException
     * @throws FatalRequestException
     * @throws JsonException
     * @throws NumberFormatException
     * @throws RandomException
     * @throws RequestException
     * @throws \Throwable
     */
    public function handle(): void
    {

      

        $email = Email::where('email', $this->argument('email'))
        ->whereIn('status', [EmailStatus::AVAILABLE, EmailStatus::FAILED])
        ->firstOrFail();

        //判断邮箱是否真正注册
        $this->lock = Cache::lock("domain_check_lock_{$email->email}", 60 * 10);

        if (!$this->lock->get()) {
            //邮箱正在注册中
            $this->error('email is  registered');

            return;
        }

        $proxyInfo = ProxyConfiguration::first();

        for ($i = 0; $i < 5; $i++){

            try {

                $appleIdBatchRegistration = app(AppleIdBatchRegistrationService::class);

                $appleIdBatchRegistration->run($email,$proxyInfo && $proxyInfo->status, $this->option('country'));

            }catch (RegistrationException | ServiceUnavailableException $e){}

            sleep(5);
        }
    }

}
