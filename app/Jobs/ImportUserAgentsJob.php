<?php

namespace App\Jobs;

use App\Enums\UserAgentStatus;
use App\Models\UserAgent;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use App\Models\User;

class ImportUserAgentsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务尝试次数
     */
    public $tries = 3;

    /**
     * 任务超时时间（秒）
     */
    public $timeout = 120;

    /**
     * 创建新的任务实例
     */
    public function __construct(protected array $userAgents)
    {

    }

    /**
     * 执行任务
     */
    public function handle(): void
    {
        try {
            // 获取所有已存在的user agent
            $existingUserAgents = UserAgent::whereIn('user_agent', $this->userAgents)
                ->pluck('user_agent')
                ->toArray();
                
            // 筛选出不存在的user agent
            $newUserAgents = array_diff($this->userAgents, $existingUserAgents);
            
            if (empty($newUserAgents)) {
                return;
            }
            
            // 准备批量插入数据
            $insertData = [];
            $now = now();
            
            foreach ($newUserAgents as $userAgent) {
                $insertData[] = [
                    'user_agent' => $userAgent,
                    'status' => UserAgentStatus::ACTIVE->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            
            // 使用事务进行批量插入
            DB::beginTransaction();
            
            // 每次插入500条记录
            foreach (array_chunk($insertData, 500) as $chunk) {
                UserAgent::insert($chunk);
            }
            
            DB::commit();
            
         
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UserAgent导入失败: ' . $e->getMessage(), [
                'exception' => $e,
                'user_agents_count' => count($this->userAgents),
            ]);
            
            $this->release(10); // 10秒后重试
        }
    }
    
} 