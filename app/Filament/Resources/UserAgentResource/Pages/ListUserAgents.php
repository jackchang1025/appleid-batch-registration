<?php

namespace App\Filament\Resources\UserAgentResource\Pages;

use App\Filament\Resources\UserAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Jobs\ImportUserAgentsJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Auth;
use App\Models\UserAgent;
use App\Enums\UserAgentStatus;
use Illuminate\Support\Facades\DB;

class ListUserAgents extends ListRecords
{
    protected static string $resource = UserAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('批量导入')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Textarea::make('user_agents')
                            ->label('User Agent列表')
                            ->helperText('请输入User Agent，每行一个')
                            ->rows(10),
                ])
                ->action(function (array $data) {

                    if (empty($data['user_agents'])) {
                        return;
                    }
                    
                    $userAgents = collect(explode("\n", trim($data['user_agents'])))
                    ->map(fn($line) => trim($line))
                    ->filter(fn($line) => !empty($line))
                    ->unique();
                    
                    if ($userAgents->isEmpty()) {
                        return;
                    }


                    if ($userAgents->count() <= 1000) {
                        $this->processUserAgents($userAgents);
                        $this->resetTable();
                        return;
                    }
                
                    $user = Auth::user();
                  
                   $batch = Bus::batch([])
                       ->name('导入UserAgent数据')
                       ->onQueue('imports')
                       ->then(function (\Illuminate\Bus\Batch $batch) use ($user) {
                           
                           // 构建消息
                           $message = "批量导入已完成";
                           if ($batch->failedJobs > 0) {
                               $message .= "，{$batch->failedJobs} 个任务失败";
                           }
                           
                           // 发送通知
                           Notification::make()
                               ->title('批量导入已完成')
                               ->body($message)
                               ->success()
                               ->sendToDatabase($user);
                       })
                       // 添加批处理失败的回调函数
                       ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($user) {
                           
                           Notification::make()
                               ->title('批量导入失败')
                               ->body('导入过程中发生错误: ' . $e->getMessage())
                               ->danger()
                               ->sendToDatabase($user);
                       })
                       ->dispatch();
                       
                   // 大量数据使用队列处理
                   $userAgents->chunk(1000)->map(fn(Collection $chunk) => $batch->add(new ImportUserAgentsJob($chunk->toArray())));

                   Notification::make()
                       ->title('批量导入已开始')
                       ->body("共 {$userAgents->count()} 条数据已加入队列处理中，处理完成后将通知您")
                       ->success()
                       ->send();
                    
                    // 刷新表格
                    $this->resetTable();
                }),

            CreateAction::make(),
        ];
    }
    
    protected function processUserAgents(Collection $userAgents)
    {
        // 获取所有已存在的user agent
        $existingUserAgents = UserAgent::whereIn('user_agent', $userAgents->toArray())
            ->pluck('user_agent')
            ->toArray();
            
        // 筛选出不存在的user agent
        $newUserAgents = array_diff($userAgents->toArray(), $existingUserAgents);
        
        $successCount = 0;
        $failedCount = $userAgents->count() - count($newUserAgents);
        
        if (!empty($newUserAgents)) {   
            // 准备批量插入数据
            $insertData = [];
            
            foreach ($newUserAgents as $userAgent) {
                $insertData[] = [
                    'user_agent' => $userAgent,
                    'status' => UserAgentStatus::ACTIVE->value,
                ];
            }
            
            // 使用事务进行批量插入
            DB::beginTransaction();
            try {
                // 每次插入500条记录
                foreach (array_chunk($insertData, 500) as $chunk) {
                    UserAgent::insert($chunk);
                }
                
                DB::commit();
                $successCount = count($newUserAgents);
            } catch (\Exception $e) {
                DB::rollBack();
                
                Notification::make()
                    ->title('导入失败')
                    ->body('导入过程中发生错误: ' . $e->getMessage())
                    ->danger()
                    ->send();
                    
                return;
            }
        }
        
        // 显示通知
        $message = "成功导入 {$successCount} 个User Agent";
        if ($failedCount > 0) {
            $message .= "，{$failedCount} 个因已存在而跳过";
        }
        
        Notification::make()
            ->title('批量导入完成')
            ->body($message)
            ->success()
            ->send();
    }
} 