<?php

namespace App\Filament\Resources\EmailResource\Pages;

use App\Enums\EmailStatus;
use App\Filament\Resources\EmailResource;
use App\Models\Email;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEmails extends ListRecords
{
    protected static string $resource = EmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('batch_import')
                ->label('批量导入')
                ->icon('heroicon-o-document-plus')
                ->form([
                    Forms\Components\Textarea::make('emails_data')
                        ->label('邮箱列表')
                        ->required()
                        ->helperText('请输入邮箱和URI，每行一个，使用 ---- 分隔。例如：
https://api.acemail.co/lastemail/v2?project_id=1&extend_b64=RBH0zjjlTlF17zqgJUC9Q0----HarrisMarkUFlpI@gmail.com')
                        ->rows(10),
                ])
                ->action(function (array $data) {
                    $lines = explode("\n", trim($data['emails_data']));
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

                        //判断是否 http uri 格式
                        if (filter_var($parts[0], FILTER_VALIDATE_URL)) {
                            $emailUri = trim($parts[0]);
                            $email = trim($parts[1]);
                        } else {
                            $emailUri = trim($parts[1]);
                            $email = trim($parts[0]);
                        }

                        // URL解码，确保特殊字符不被编码
                        $emailUri = rawurldecode($emailUri);

                        try {
                            // Check if email already exists
                            if (Email::where('email', $email)->exists()) {
                                $failedEntries[] = $email . ' (已存在)';
                                continue;
                            }

                            // Create new email record
                            Email::create([
                                'email' => $email,
                                'email_uri' => $emailUri,
                                'status' => EmailStatus::AVAILABLE->value,
                            ]);

                            $successCount++;
                        } catch (\Exception $e) {
                            $failedEntries[] = $email . ' (' . $e->getMessage() . ')';
                        }
                    }

                    // Show notification
                    $message = "成功导入 {$successCount} 个邮箱";
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

                    // Refresh the table
                    $this->resetTable();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
