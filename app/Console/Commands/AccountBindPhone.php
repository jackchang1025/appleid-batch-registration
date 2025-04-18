<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appleid as AppleidModel;
use App\Services\AppleId\AccountBindPhone as AccountBindPhoneService;

class AccountBindPhone extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apple-id:bind-phone
                            {appleId}'
    ;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(AccountBindPhoneService $accountBindPhone)
    {
        $appleId = AppleidModel::where('email',$this->argument('appleId'))->firstOrFail();

        $accountBindPhone->run($appleId);
    }
}
