<?php

namespace App\Console\Commands;

use App\Enums\EmailStatus;
use App\Models\Email;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use App\Models\User;
use App\Filament\Resources\EmailResource\Pages\ViewEmail;

class AppleIdBatchRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:apple-id-batch-registration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {


        Email::whereIn('status', [EmailStatus::AVAILABLE, EmailStatus::FAILED])->get()->each(function (Email $email) {

            try {

                $this->call('app:apple-id-registration', ['email' => $email->email]);

            }catch (\Exception $e){

                $this->error($e->getMessage());
                
                    Notification::make()
                    ->title("{$email->email} 注册失败")
                    ->body($e->getMessage())
                    ->actions([
                            Action::make('view')
                                ->button()
                            ->url(ViewEmail::getUrl([
                                'record' => $email->id,
                            ]), shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase(User::first());

            }
        });
    }
}
