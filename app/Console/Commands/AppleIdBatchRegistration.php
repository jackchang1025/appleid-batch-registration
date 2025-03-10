<?php

namespace App\Console\Commands;

use App\Models\ProxyConfiguration;
use Illuminate\Console\Command;
use JsonException;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Random\RandomException;
use Saloon\Exceptions\Request\ClientException;
use Weijiajia\SaloonphpAppleClient\Exception\Phone\PhoneException;
use Illuminate\Support\Facades\Cache;
use App\Models\Email;
use Illuminate\Contracts\Cache\Lock;
use App\Services\AppleId\AppleIdBatchRegistration as AppleIdBatchRegistrationService;
use App\Enums\EmailStatus;
class AppleIdBatchRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:apple-id-batch-registration {email}';

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
     * @param AppleIdBatchRegistrationService $appleIdBatchRegistration
     * @return void
     * @throws ClientException
     * @throws JsonException
     * @throws NumberFormatException
     * @throws PhoneException
     * @throws RandomException
     */
    public function handle(AppleIdBatchRegistrationService $appleIdBatchRegistration): void
    {
        // dd(Email::all()->toArray());

        $email = Email::where('email', $this->argument('email'))
        ->whereIn('status', [EmailStatus::AVAILABLE, EmailStatus::FAILED])
        ->firstOrFail();

        //判断邮箱是否真正注册
        $this->lock = Cache::lock('domain_check_lock', 60 * 10);

        if (!$this->lock->get()) {
            //邮箱正在注册中
            $this->error('email is  registered');

            return;
        }

        $proxyInfo = ProxyConfiguration::first();

        $appleIdBatchRegistration->run($email,$proxyInfo && $proxyInfo->status);
    }

}
