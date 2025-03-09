<?php

namespace App\Filament\Resources\AppleidResource\Pages;

use App\Filament\Resources\AppleidResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppleid extends EditRecord
{
    protected static string $resource = AppleidResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
