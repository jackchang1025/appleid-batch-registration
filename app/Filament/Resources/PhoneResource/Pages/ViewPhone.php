<?php

namespace App\Filament\Resources\PhoneResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\PhoneResource;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;

class ViewPhone extends ViewRecord
{
    protected static string $resource = PhoneResource::class;


  public function infolist(Infolist $infolist): Infolist{
        return $infolist
            ->schema([
                Section::make('手机信息')
                    ->schema([
                        TextEntry::make('phone'),
                        TextEntry::make('phone_address'),
                        TextEntry::make('country_code'),
                        TextEntry::make('country_code_alpha3'),
                        TextEntry::make('status')
                            ->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('created_at'),
                        TextEntry::make('updated_at')
                    ]),

            ]);
    }
}
