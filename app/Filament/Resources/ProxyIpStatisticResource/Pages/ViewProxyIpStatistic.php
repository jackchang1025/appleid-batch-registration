<?php

namespace App\Filament\Resources\ProxyIpStatisticResource\Pages;

use App\Filament\Resources\ProxyIpStatisticResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewProxyIpStatistic extends ViewRecord
{
    protected static string $resource = ProxyIpStatisticResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('代理与IP信息')
                    ->schema([
                        Components\TextEntry::make('ip_uri')->label('代理URI'),
                        Components\TextEntry::make('real_ip')->label('真实IP'),
                        Components\TextEntry::make('proxy_provider')->label('代理提供商'),
                        Components\TextEntry::make('country_code')->label('国家代码'),
                        Components\IconEntry::make('is_success')->label('是否成功')->boolean(),
                    ])->columns(2),
                Components\Section::make('关联信息')
                    ->schema([
                        Components\TextEntry::make('email.email')->label('关联邮箱'),
                    ]),
                Components\Section::make('IP 详细信息')
                    ->schema([
                        Components\KeyValueEntry::make('ip_info')
                            ->label('IP Info 数据')
                            ->columnSpanFull(),
                    ])->collapsible(),
                Components\Section::make('异常信息')
                    ->visible(fn ($record) => !empty($record->exception_message))
                    ->schema([
                        Components\TextEntry::make('exception_message')
                            ->label('异常信息')
                            ->columnSpanFull(),
                    ])->collapsible(),
                Components\Section::make('时间戳')
                    ->schema([
                        Components\TextEntry::make('created_at')->label('创建时间')->dateTime(),
                        Components\TextEntry::make('updated_at')->label('更新时间')->dateTime(),
                    ])->columns(2),
            ]);
    }
}
