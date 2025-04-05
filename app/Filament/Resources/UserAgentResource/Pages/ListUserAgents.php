<?php

namespace App\Filament\Resources\UserAgentResource\Pages;

use App\Filament\Resources\UserAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use App\Imports\UserAgentsImport;
use App\Exports\UserAgentsExport;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Notifications\Notification;
use App\Enums\UserAgentStatus;
use App\Jobs\ImportUserAgentsJob;
use App\Models\UserAgent;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;

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
                    ->toArray();
                
                    
                    $totalCount = count($userAgents);

                    $user = Auth::user();

                    if ($totalCount == 0) {
                        return;
                    }


                    if ($totalCount <= 1000) {
                        $this->processUserAgents($userAgents);
                        $this->resetTable();
                        return;
                    }
                    
                   // 大量数据使用队列处理
                   $chunks = array_chunk($userAgents, 1000);
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
                       
                   foreach ($chunks as $chunk) {
                       $batch->add(new ImportUserAgentsJob($chunk, Auth::user()));
                   }
                   
                   Notification::make()
                       ->title('批量导入已开始')
                       ->body("共 {$totalCount} 条数据已加入队列处理中，处理完成后将通知您")
                       ->success()
                       ->send();
                    
                    // 刷新表格
                    $this->resetTable();
                }),
                
            CreateAction::make(),
        ];
    }

} 