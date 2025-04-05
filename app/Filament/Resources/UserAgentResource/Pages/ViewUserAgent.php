<?php

namespace App\Filament\Resources\UserAgentResource\Pages;

use App\Filament\Resources\UserAgentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserAgent extends ViewRecord
{
    protected static string $resource = UserAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
} 