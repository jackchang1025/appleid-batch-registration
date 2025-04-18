<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\User;
use App\Services\AppleId\AppleIdBatchRegistration;
use Weijiajia\SaloonphpAppleClient\Exception\RegistrationException;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Saloon\Exceptions\Request\Statuses\ServiceUnavailableException;
use Filament\Notifications\Actions\Action;
use App\Filament\Resources\EmailResource\Pages\ViewEmail;
use App\Services\CountryLanguageService;
use Illuminate\Support\Facades\Log;
use App\Enums\EmailStatus;
use App\Services\Phone\PhoneDepositoryFacroty;
class RegisterAppleIdJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务超时时间（秒）
     */
    public int|float $timeout = 60 * 10;

    /**
     * 创建一个新的任务实例
     */
    public function __construct(
        protected Email $email,
        protected ?string $country = null,
        protected ?bool $isRandomUserAgent = false,
        protected ?string $phoneRepository = null
    ) {
        $this->onQueue('apple_id_registration');
    }

    /**
     * 任务的唯一ID
     */
    public function uniqueId(): string
    {
        return 'apple_id_registration_'.$this->email->email;
    }

    /**
     * 任务唯一锁的有效期（秒）
     */
    public function uniqueFor(): int
    {
        return 60 * 60; // 60分钟
    }

    /**
     * @return void
     * @throws RegistrationException
     * @throws ServiceUnavailableException
     * @throws \Throwable
     */
    public function handle():void
    {
        try {

            if ($this->email->status->value !== EmailStatus::AVAILABLE->value && $this->email->status->value !== EmailStatus::FAILED->value) {
                Log::warning("Job for {$this->email->email} skipped: Not available.");
                $this->delete(); // 删除重复的 Job
                return;
            }

            $appleIdBatchRegistration = app()->make(AppleIdBatchRegistration::class);
            $phoneDepositoryFacroty = app()->make(PhoneDepositoryFacroty::class);

            // 运行注册
            $appleIdBatchRegistration->run(
                email: $this->email, 
                country: CountryLanguageService::make($this->country), 
                isRandomUserAgent: $this->isRandomUserAgent, 
                phoneDepository: $phoneDepositoryFacroty->make($this->phoneRepository)
            );

            // 显示通知
            Notification::make()
                ->title("{$this->email->email} 注册成功")
                ->success()
                ->actions([
                    Action::make('view')
                        ->button()
                        ->url(ViewEmail::getUrl([
                            'record' => $this->email->id,
                        ]), shouldOpenInNewTab: true),
                ])
                ->sendToDatabase(User::first());

            Log::info("{$this->email->email} 注册成功");

            $this->delete(); // 显式删除成功完成的 Job
            return;

        }catch (Exception|\Throwable $e) {

            Log::error("{$this->email->email} 注册失败 {$e}");

            Notification::make()
            ->title("{$this->email->email} 注册失败")
            ->body($e->getMessage())
            ->danger()
            ->actions([
                Action::make('view')
                    ->button()
                    ->url(ViewEmail::getUrl([
                        'record' => $this->email->id,
                    ]), shouldOpenInNewTab: true),
            ])
            ->sendToDatabase(User::first());
        }

    }
}
