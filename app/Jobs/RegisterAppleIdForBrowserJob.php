<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\User;
use App\Services\AppleId\AppleIdBatchRegistration;
use App\Services\Exception\RegistrationException;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Saloon\Exceptions\Request\Statuses\ServiceUnavailableException;
use Filament\Notifications\Actions\Action;
use App\Filament\Resources\EmailResource\Pages\ViewEmail;
use App\Services\AppleId\Pages\PageManager;
use App\Services\AppleId\AppleIdRegistrationForBrowser;

class RegisterAppleIdForBrowserJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 重试次数
     */
    public int $tries = 5;

    /**
     * 任务超时时间（秒）
     */
    public int|float $timeout = 60 * 10;

    /**
     * 创建一个新的任务实例
     */
    public function __construct(
        protected Email $email,
        protected string $country = 'USA'
    ) {
        $this->onQueue('default');
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
        return 60 * 10; // 5分钟
    }

    /**
     * 计算重试任务之前要等待的秒数
     *
     * @return int
     */
    public function backoff(): int
    {
        return 5;
    }

    /**
     * @param AppleIdBatchRegistration $appleIdBatchRegistration
     * @return void
     * @throws RegistrationException
     * @throws ServiceUnavailableException
     * @throws \Throwable
     */
    public function handle(PageManager $pageManager): void
    {
        try {

            $appleIdRegistration = new AppleIdRegistrationForBrowser(
                pageManager:$pageManager,
                country:$this->country,
                email:$this->email,
            );

            $appleIdRegistration->register();

            Log::info("AppleID registration successful for email: {$this->email->email}");

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

        } catch (RegistrationException|ServiceUnavailableException $e) {

            //抛出异常
            throw $e;

        } catch (Exception $e) {

            // 处理错误情况
            Log::error("AppleID registration failed for email: {$this->email}: {$e}");

            // 显示通知
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

    public function failed(Exception $exception): void
    {
        Log::error("AppleID registration failed for email: {$this->email}: {$exception}");

        Notification::make()
            ->title("{$this->email->email} 注册失败")
            ->body($exception->getMessage())
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
