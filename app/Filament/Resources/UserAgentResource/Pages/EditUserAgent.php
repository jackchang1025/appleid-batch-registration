<?php

namespace App\Filament\Resources\UserAgentResource\Pages;

use App\Filament\Resources\UserAgentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditUserAgent extends EditRecord
{
    protected static string $resource = UserAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
} 