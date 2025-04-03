<?php

namespace App\Filament\Resources\ProxyIpStatisticResource\Pages;

use App\Filament\Resources\ProxyIpStatisticResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProxyIpStatistics extends ListRecords
{
    protected static string $resource = ProxyIpStatisticResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
