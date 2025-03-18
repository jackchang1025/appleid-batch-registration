<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AppleId\AppleIdTvRegistration;
use App\Models\Email;

class TvApple extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tv-apple';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    public function decodedContent(string $encodedContent):?array
    {
        return json_decode(urldecode($encodedContent), true);
    }


    /**
     * Execute the console command.
     */
    public function handle(AppleIdTvRegistration $appleIdTvRegistration)
    {
        $email = Email::where('email', 'LindaCartery273@gmail.com')->firstOrFail();

        for($i = 0; $i < 5; $i++){
            try{

                dd($appleIdTvRegistration->run($email)); 

            }catch(\Saloon\Exceptions\Request\Statuses\ServiceUnavailableException $e){

            }

            sleep(5);
        }
    }
}
