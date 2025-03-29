<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AppleId\AppleIdTvRegistration;
use App\Models\Email;
use App\Services\AppleId\AppleIdBatchRegistration;
class TvApple extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tv-apple {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(AppleIdTvRegistration $appleIdTvRegistration)
    {

        $email = Email::where('email', $this->argument('email'))->firstOrFail();

        $appleId = $appleIdTvRegistration->run($email);

        $this->info('注册成功: '.json_encode($appleId, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    }
}
