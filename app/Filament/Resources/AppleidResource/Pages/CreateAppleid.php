<?php

namespace App\Filament\Resources\AppleidResource\Pages;

use App\Filament\Resources\AppleidResource;
use App\Jobs\RegisterAppleIdJob;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAppleid extends CreateRecord
{
    protected static string $resource = AppleidResource::class;
    
    /**
     * 重写整个创建方法，不创建数据库记录
     */
    public function create(bool $another = false): void
    {
        $this->authorizeAccess();

        try {
            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            // 在这里分发作业而不是创建记录
            if (isset($data['emails']) && is_array($data['emails'])) {
                $count = count($data['emails']);

                
                foreach ($data['emails'] as $email) {
                    RegisterAppleIdJob::dispatch($email)->onQueue('default');
                }
                
                // 显示通知
                Notification::make()
                    ->title("{$count} 个 Apple ID 注册任务已加入队列")
                    ->success()
                    ->send();
            }

            $this->redirect($this->getRedirectUrl());
        } catch (\Exception $exception) {
            // 处理异常
            Notification::make()
                ->title('提交失败')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
    
    /**
     * 创建记录的方法 (此方法必须实现，但我们不会使用它)
     */
    protected function handleRecordCreation(array $data): Model
    {
        // 此方法永远不会被调用，因为我们重写了 create 方法
        throw new \Exception('This method should not be called');
    }
    
    /**
     * 获取重定向 URL
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
