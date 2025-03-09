<?php

namespace App\Filament\Resources\PhoneResource\Pages;

use App\Filament\Imports\PhoneImporter;
use App\Filament\Resources\PhoneResource;
use App\Jobs\ImportCsvJob;
use Filament\Actions;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPhones extends ListRecords
{
    protected static string $resource = PhoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
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
