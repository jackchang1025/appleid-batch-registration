<?php

namespace App\Jobs;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\ProxyConfiguration;
use App\Services\AppleId\AppleIdBatchRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegisterAppleIdJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 重试次数
     */
    public $tries = 1;

    /**
     * 任务超时时间（秒）
     */
    public $timeout = 600;

    /**
     * 创建一个新的任务实例
     */
    public function __construct(
        protected string $email
    ) {
        $this->onQueue('default');
    }

    /**
     * 任务的唯一ID
     */
    public function uniqueId(): string
    {
        return 'apple_id_registration_' . $this->email;
    }

    /**
     * 任务唯一锁的有效期（秒）
     */
    public function uniqueFor(): int
    {
        return 60 * 5; // 5分钟
    }

    /**
     * 执行任务
     */
    public function handle(AppleIdBatchRegistration $appleIdBatchRegistration): void
    {
        try {
            $email = Email::where('email', $this->email)
                ->where('status', EmailStatus::AVAILABLE)
                ->firstOrFail();
            
            
            // 获取代理配置
            $proxyInfo = ProxyConfiguration::first();

    
            // 运行注册
           $appleIdBatchRegistration->run($email, $proxyInfo && $proxyInfo->status);
            
            Log::info("AppleID registration successful for email: {$this->email}");
        } catch (\Exception $e) {

            // 处理错误情况
            Log::error("AppleID registration failed for email: {$this->email}: {$e}");
            
            throw $e;
        }
    }
} 