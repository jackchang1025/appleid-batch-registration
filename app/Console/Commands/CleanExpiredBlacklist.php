<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AppleId\AppleIdBatchRegistration;
use Illuminate\Support\Facades\Log;

class CleanExpiredBlacklist extends Command
{
    /**
     * 命令名称与签名
     *
     * @var string
     */
    protected $signature = 'phone:clean-blacklist';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '清理已过期的手机黑名单状态';

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->info('开始清理过期的手机黑名单...');
        
        $expiredPhoneIds = AppleIdBatchRegistration::cleanExpiredBlacklist();

        $this->info('清理的手机号ID：' . $expiredPhoneIds->implode(','));
    }
} 