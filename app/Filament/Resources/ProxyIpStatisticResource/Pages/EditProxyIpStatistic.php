<?php

namespace App\Filament\Resources\ProxyIpStatisticResource\Pages;

use App\Filament\Resources\ProxyIpStatisticResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProxyIpStatistic extends EditRecord
{
    protected static string $resource = ProxyIpStatisticResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
