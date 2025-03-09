<?php

namespace App\Filament\Resources\ProxyConfigurationResource\Pages;

use App\Filament\Resources\ProxyConfigurationResource;
use Filament\Resources\Pages\EditRecord;
use App\Models\ProxyConfiguration;

class EditProxyConfiguration extends EditRecord
{
    protected static string $resource = ProxyConfigurationResource::class;

    public function mount(int | string|null $record = null): void
    {
        $proxyConfiguration = ProxyConfiguration::first();
        if(!$proxyConfiguration){
            $proxyConfiguration = ProxyConfiguration::create(['configuration' => []]);
        }

        $this->record  = $proxyConfiguration;

        $this->authorizeAccess();

        $this->fillForm();

        $this->previousUrl = url()->previous();
    }

    protected function getActions(): array
    {
        return []; // 移除所有操作按钮
    }
}
