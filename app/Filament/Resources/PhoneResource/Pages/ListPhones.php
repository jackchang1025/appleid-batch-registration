<?php

namespace App\Filament\Resources\PhoneResource\Pages;

use App\Filament\Imports\PhoneImporter;
use App\Filament\Resources\PhoneResource;
use App\Jobs\ImportCsvJob;
use App\Models\Phone;
use Filament\Actions;
use Filament\Actions\ImportAction;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPhones extends ListRecords
{
    protected static string $resource = PhoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Action::make('batch_import')
                ->label('批量导入')
                ->icon('heroicon-o-document-plus')
                ->form([
                    Forms\Components\Textarea::make('phones_data')
                        ->label('手机号码列表')
                        ->required()
                        ->helperText('请输入手机号码和URI，每行一个，使用 ---- 分隔。例如：
+16514779187----https://api.sms-999.com/api/sms/record?key=e0c5a4b5cb15510330d2f97c35d61b3d')
                        ->rows(10),
                ])
                ->action(function (array $data) {
                    $lines = explode("\n", trim($data['phones_data']));
                    $successCount = 0;
                    $failedEntries = [];
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) {
                            continue;
                        }
                        
                        $parts = explode('----', $line);
                        if (count($parts) !== 2) {
                            $failedEntries[] = $line . ' (格式错误)';
                            continue;
                        }
                        
                        $phone = trim($parts[0]);
                        $phoneAddress = trim($parts[1]);
                        
                        // Handle URL prefix for phone_address
                        // Remove 'https://' prefix if it exists since the form field has a prefix
                        if (str_starts_with($phoneAddress, 'https://')) {
                            $phoneAddress = substr($phoneAddress, 8);
                        }
                        
                        try {
                            // Check if phone already exists
                            if (Phone::where('phone', $phone)->exists()) {
                                $failedEntries[] = $phone . ' (已存在)';
                                continue;
                            }
                            
                            // Create new phone record
                            $phoneObj = Phone::create([
                                'phone' => $phone,
                                'phone_address' => $phoneAddress,
                                'status' => Phone::STATUS_NORMAL,
                                'country_code' => '', // Will be set by the model's mutator
                                'country_code_alpha3' => '', // Will be set by the model's mutator
                                'country_dial_code' => '', // Will be set by the model's mutator
                            ]);
                            
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedEntries[] = $phone . ' (' . $e->getMessage() . ')';
                        }
                    }
                    
                    // Show notification
                    $message = "成功导入 {$successCount} 个手机号码";
                    if (!empty($failedEntries)) {
                        $message .= "，" . count($failedEntries) . " 个导入失败：" . implode(', ', array_slice($failedEntries, 0, 5));
                        if (count($failedEntries) > 5) {
                            $message .= "...等";
                        }
                    }
                    
                    Notification::make()
                        ->title('批量导入完成')
                        ->body($message)
                        ->success()
                        ->send();
                    
                    // Refresh the page to show new records
                    $this->resetTable();
                }),
            // Actions\ImportAction::make()
            //     ->importer(PhoneImporter::class)
            //     ->job(ImportCsvJob::class)
            //     ->beforeFormFilled(function (ImportAction  $action) {
            //         Notification::make()
            //             ->title('导入说明')
            //             ->body('请确保电话号码包含国际区号，并以 "+" 开头。如果使用 Excel 打开 CSV 文件，请将电话号码列格式设置为文本以保留 "+" 符号。')
            //             ->info()
            //             ->send();
            //     }),
        ];
    }
}
