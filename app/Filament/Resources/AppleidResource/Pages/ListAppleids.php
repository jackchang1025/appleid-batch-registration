<?php

namespace App\Filament\Resources\AppleidResource\Pages;

use App\Filament\Resources\AppleidResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppleids extends ListRecords
{
    protected static string $resource = AppleidResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('批量注册 appleid'),
        ];
    }
}
